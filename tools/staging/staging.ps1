#Requires -Version 5.1
<#
============================================================================
 tools/staging/staging.ps1 - provisiona e opera o ambiente de TESTES descartavel
============================================================================
 staging/ e um clone derivado de dev/ + um banco clone de davidcms. Ele NUNCA
 compartilha banco, storage, sessao ou rota com dev/ ou prod/.

 Este arquivo e ASCII puro de proposito: o PowerShell 5.1 le .ps1 sem BOM como
 ANSI, e caracteres de desenho/licenca quebrariam o parser.

 Acoes:
   sync     copia dev/ -> staging/ e regenera os config.php do staging
   config   apenas regenera staging/{catalog,admin}/config.php
   db-reset dropa e recria `davidcms_staging` como clone de `davidcms`
   run      roda as baterias (tools/staging/run-batteries.cjs)
   doctor   imprime os pre-requisitos e o estado atual
   all      sync + db-reset + run  (provisiona do zero)

 Exemplos:
   powershell -ExecutionPolicy Bypass -File tools/staging/staging.ps1 all
   powershell -ExecutionPolicy Bypass -File tools/staging/staging.ps1 sync
   powershell -ExecutionPolicy Bypass -File tools/staging/staging.ps1 run -Only static,php
   powershell -ExecutionPolicy Bypass -File tools/staging/staging.ps1 db-reset -KeepDump
============================================================================
#>
[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('all', 'sync', 'config', 'db-reset', 'run', 'doctor')]
    [string]$Action = 'all',

    [string]$Only = '',
    [switch]$UpdateBaseline,
    [switch]$KeepDump
)

$ErrorActionPreference = 'Stop'

# --- Caminhos e binarios ------------------------------------------------
$StagingTools = $PSScriptRoot
$Root = Split-Path -Parent (Split-Path -Parent $StagingTools)   # raiz do repositorio
$Dev = Join-Path $Root 'dev'
$Stg = Join-Path $Root 'staging'
$Reports = Join-Path $Stg 'reports'

$Bin = @{
    Php       = 'e:\wamp64\bin\php\php8.4.15\php.exe'
    Mysql     = 'e:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe'
    Mysqldump = 'e:\wamp64\bin\mysql\mysql8.4.7\bin\mysqldump.exe'
}
$Db = @{
    Host   = 'localhost'
    Port   = 3306
    User   = 'root'
    Source = 'davidcms'
    Target = 'davidcms_staging'
}
$BaseUrl = 'http://localhost/davidcms/staging'

# Conteudo volatil do storage: nunca copiado; recriado vazio com o guard.
$VolatileDirs = @('cache', 'logs', 'logs/error', 'session', 'temp', 'cache/template')

function Write-Step($text) { Write-Host "`n== $text ==" -ForegroundColor Cyan }
function Write-Ok($text)   { Write-Host "  ok  $text" -ForegroundColor Green }
function Write-Attn($text) { Write-Host "  !!  $text" -ForegroundColor Yellow }
function Write-Err($text)  { Write-Host "  xx  $text" -ForegroundColor Red }

function Assert-Tool([string]$Label, [string]$Path) {
    if (-not (Test-Path -LiteralPath $Path)) { throw "$Label nao encontrado: $Path" }
}

function Invoke-Robocopy([string[]]$Arguments) {
    & robocopy @Arguments | Out-Null
    # robocopy: 0-7 = sucesso (1 = copiou, 2 = extras, 4 = divergencias)
    if ($LASTEXITCODE -ge 8) { throw "robocopy falhou com codigo $LASTEXITCODE" }
}

function Invoke-Sync {
    Assert-Tool 'dev/' $Dev
    if (-not (Test-Path -LiteralPath $Stg)) { New-Item -ItemType Directory -Path $Stg -Force | Out-Null }

    Write-Step 'sync dev/ -> staging/ (clone completo, menos config e volateis)'
    $excludeDirs = @((Join-Path $Stg 'reports')) +
        ($VolatileDirs | ForEach-Object { Join-Path $Dev ("system\storage\" + ($_ -replace '/', '\')) })

    Invoke-Robocopy @(
        $Dev, $Stg, '/MIR', '/COPY:DAT', '/R:1', '/W:1',
        '/XD', $excludeDirs,
        '/XF', 'config.php',
        '/NFL', '/NDL', '/NJH', '/NJS', '/NP'
    )
    Write-Ok 'clone atualizado (config.php nunca e copiado; e gerado em seguida)'

    foreach ($rel in $VolatileDirs) {
        $dstDir = Join-Path $Stg ("system\storage\" + ($rel -replace '/', '\'))
        New-Item -ItemType Directory -Path $dstDir -Force | Out-Null
        Get-ChildItem -LiteralPath $dstDir -Force |
            Where-Object { $_.Name -ne 'index.html' } |
            Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
        $guard = Join-Path $Dev ("system\storage\" + ($rel -replace '/', '\') + "\index.html")
        if (Test-Path -LiteralPath $guard) {
            Copy-Item -LiteralPath $guard -Destination (Join-Path $dstDir 'index.html') -Force
        }
    }
    Write-Ok 'storage volatil recriado vazio com guards em staging/system/storage/'
}

function Write-ConfigFile([string]$App) {
    $dir = Join-Path $Stg $App
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    $target = Join-Path $dir 'config.php'
    $reamur = $Root + '/'

    $text = @"
<?php
/**
 * config.php do AMBIENTE DE TESTES (staging) - gerado por tools/staging/staging.ps1.
 * NUNCA versionar. Nada aqui pode apontar para dev/ ou prod/.
 * Constantes permitidas: docs/Contrato-Bootstrap.md 1.1.
 */

// HTTP
define('HTTP_SERVER', '$BaseUrl/$App/');
define('HTTPS_SERVER', '$BaseUrl/$App/');

// Ambiente de testes: erros visiveis na tela (em producao isto e false).
define('REAMUR_ERROR_DISPLAY', true);

// DIR (raizes calculadas a partir de DIR_REAMUR)
define('DIR_REAMUR', '$reamur');
define('DIR_APPLICATION', DIR_REAMUR . 'staging/$App/');
define('DIR_SYSTEM', DIR_REAMUR . 'staging/system/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
define('DIR_EXTENSION', DIR_REAMUR . 'staging/extension/');

// DB - banco DESCARTaVEL, clone de '$($Db.Source)'
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', '$($Db.Host)');
define('DB_USERNAME', '$($Db.User)');
define('DB_PASSWORD', '');
define('DB_DATABASE', '$($Db.Target)');
define('DB_PORT', '$($Db.Port)');
define('DB_PREFIX', 'rms_');
"@

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($target, $text, $utf8NoBom)
    Write-Ok "staging/$App/config.php -> $BaseUrl/$App/ - banco $($Db.Target)"
}

function Write-Configs {
    Write-Step 'config.php do staging'
    Write-ConfigFile 'catalog'
    Write-ConfigFile 'admin'
}

function Invoke-DbReset {
    Assert-Tool 'mysql.exe' $Bin.Mysql
    Assert-Tool 'mysqldump.exe' $Bin.Mysqldump
    New-Item -ItemType Directory -Path $Reports -Force | Out-Null
    $dump = Join-Path $Reports 'staging-dump.sql'

    Write-Step "dump de '$($Db.Source)'"
    & $Bin.Mysqldump "--host=$($Db.Host)" "--port=$($Db.Port)" "--user=$($Db.User)" `
        --routines --triggers --events --single-transaction --default-character-set=utf8mb4 `
        --skip-comments --no-tablespaces "--result-file=$dump" $Db.Source
    if ($LASTEXITCODE -ne 0) { throw "mysqldump falhou (codigo $LASTEXITCODE)" }
    $sizeMb = [math]::Round((Get-Item -LiteralPath $dump).Length / 1MB, 2)
    Write-Ok "dump gerado ($sizeMb MB)"

    Write-Step "recriando '$($Db.Target)'"
    & $Bin.Mysql "--host=$($Db.Host)" "--port=$($Db.Port)" "--user=$($Db.User)" `
        "--execute=DROP DATABASE IF EXISTS $($Db.Target); CREATE DATABASE $($Db.Target) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    if ($LASTEXITCODE -ne 0) { throw "nao foi possivel recriar $($Db.Target) (codigo $LASTEXITCODE)" }
    Write-Ok "$($Db.Target) recriado (utf8mb4 / utf8mb4_unicode_ci)"

    Write-Step 'carregando o clone'
    $source = $dump -replace '\\', '/'
    & $Bin.Mysql "--host=$($Db.Host)" "--port=$($Db.Port)" "--user=$($Db.User)" `
        --default-character-set=utf8mb4 "--execute=SOURCE $source" $Db.Target
    if ($LASTEXITCODE -ne 0) { throw "falha ao carregar o dump (codigo $LASTEXITCODE)" }

    $tables = & $Bin.Mysql "--host=$($Db.Host)" "--port=$($Db.Port)" "--user=$($Db.User)" `
        --skip-column-names --batch "--execute=SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$($Db.Target)'"
    if ($LASTEXITCODE -ne 0) { throw 'nao foi possivel verificar o clone' }
    if ([int]$tables -le 0) { throw "clone vazio: nenhuma tabela em $($Db.Target)" }
    Write-Ok "$tables tabela(s) clonada(s) de '$($Db.Source)'"

    if (-not $KeepDump) {
        Remove-Item -LiteralPath $dump -Force -ErrorAction SilentlyContinue
        Write-Ok 'dump descartado (use -KeepDump para preservar)'
    } else {
        Write-Ok "dump preservado em $dump"
    }
}

function Invoke-Batteries {
    $runner = Join-Path $StagingTools 'run-batteries.cjs'
    if (-not (Test-Path -LiteralPath $runner)) { throw "orquestrador ausente: $runner" }

    $env:STAGING_DB_TARGET = $Db.Target
    $env:STAGING_DB_SOURCE = $Db.Source
    $env:STAGING_BASE_URL = $BaseUrl

    $nodeArgs = @($runner)
    if ($Only) { $nodeArgs += "--only=$Only" }
    if ($UpdateBaseline) { $nodeArgs += '--update-baseline' }

    Write-Step "baterias$(if ($Only) { " ($Only)" })"
    & node @nodeArgs
    $code = $LASTEXITCODE
    if ($code -eq 0) { Write-Ok 'nenhuma bateria FAIL' }
    elseif ($code -eq 1) { Write-Err 'houve bateria FAIL - veja o relatorio' }
    else { Write-Attn "o orquestrador retornou $code (pre-requisitos pendentes?)" }
}

function Show-Doctor {
    Write-Step 'diagnostico do ambiente de testes'
    foreach ($k in $Bin.Keys) {
        $state = if (Test-Path -LiteralPath $Bin[$k]) { 'presente' } else { 'AUSENTE ' }
        Write-Host ("  {0,-10} {1} {2}" -f $k, $state, $Bin[$k])
    }
    $node = Get-Command node -ErrorAction SilentlyContinue
    $nodeVersion = if ($node) { (& node -v) } else { '' }
    Write-Host ("  {0,-10} {1} {2}" -f 'node', $(if ($node) { 'presente' } else { 'AUSENTE ' }), $nodeVersion)

    Write-Host ''
    Write-Host ("  staging/ existe....... " + (Test-Path -LiteralPath $Stg))
    Write-Host ("  catalog/config.php.... " + (Test-Path -LiteralPath (Join-Path $Stg 'catalog\config.php')))
    Write-Host ("  admin/config.php...... " + (Test-Path -LiteralPath (Join-Path $Stg 'admin\config.php')))
    Write-Host ("  relatorios............ " + $Reports)

    if (Test-Path -LiteralPath $Bin.Mysql) {
        $dbs = & $Bin.Mysql "--host=$($Db.Host)" "--port=$($Db.Port)" "--user=$($Db.User)" `
            --skip-column-names --batch "--execute=SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME"
        if ($LASTEXITCODE -eq 0) {
            Write-Host '  MySQL acessivel....... sim'
            Write-Host ("  $($Db.Source) (espelho)... " + ($dbs -contains $Db.Source))
            Write-Host ("  $($Db.Target) (descartavel)... " + ($dbs -contains $Db.Target))
        } else {
            Write-Attn 'MySQL inacessivel - o WAMP esta ligado?'
        }
    }

    Write-Host ''
    Write-Host '  WAMP: confirme Apache + MySQL verdes na bandeja antes das baterias.' -ForegroundColor DarkGray
    Write-Host "  URLs: $BaseUrl/catalog/  -  $BaseUrl/admin/" -ForegroundColor DarkGray
}

switch ($Action) {
    'doctor'   { Show-Doctor }
    'sync'     { Invoke-Sync; Write-Configs }
    'config'   { Write-Configs }
    'db-reset' { Invoke-DbReset }
    'run'      { Invoke-Batteries }
    'all'      { Invoke-Sync; Write-Configs; Invoke-DbReset; Invoke-Batteries }
}

Write-Host ''
