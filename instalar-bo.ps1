# Frente BO-r4 - instalador local do runtime (Windows / PowerShell)
# Equivalente ao instalar-bo.py, sem precisar de Python.
# Voce aplica. Nao escreve no GitHub, nao roda migration/deploy/CI,
# nao altera SQL, contratos congelados, CHECKSUMS.txt nem o catalogo publico.
#
# Uso (PowerShell, dentro da pasta extraida do pacote):
#   powershell -NoProfile -ExecutionPolicy Bypass -File instalar-bo.ps1 -Repo "C:\xampp\htdocs\davidcms" -Check
#   powershell -NoProfile -ExecutionPolicy Bypass -File instalar-bo.ps1 -Repo "C:\xampp\htdocs\davidcms" -Apply
#
# Ou simplesmente de dois cliques em INSTALAR.bat (ele pergunta a pasta).
#
# O -Apply copia os 10 arquivos de patch\arquivos\ para os MESMOS caminhos
# no repositorio e FALHA alto e claro se scenes\david-universe.js nao
# existir ao final. E a causa mais comum de "3D nao aparece": main.js
# atualizado, modulo novo ausente -> import 404 -> o modulo ES inteiro aborta.

param(
    [Parameter(Mandatory = $true)][string]$Repo,
    [switch]$Apply,
    [switch]$Check
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ARQUIVOS = @(
    'dev/system/library/three/runtime/three/main.js',
    'dev/system/library/three/asset_manifest.php',
    'dev/system/library/theme/theme_package.php',
    'dev/system/library/theme/condition_engine.php',
    'dev/system/library/theme/theme_snapshot.php',
    'dev/system/library/three/scene_preset_registry.php',
    'dev/catalog/controller/startup/theme.php',
    'dev/catalog/controller/common/home.php',
    'dev/admin/controller/design/theme.php',
    'dev/system/library/three/runtime/three/scenes/david-universe.js'
)
$NOVO = 'dev/system/library/three/runtime/three/scenes/david-universe.js'

function Full($root, $rel) {
    $p = $root
    foreach ($parte in ($rel -split '/')) { $p = Join-Path $p $parte }
    return $p
}

$script:P = 0
$script:F = 0
function Ok($nome, $cond, $extra) {
    if ($cond) {
        $script:P++
        Write-Host ("OK   " + $nome) -ForegroundColor Green
    } else {
        $script:F++
        Write-Host ("FAIL " + $nome) -ForegroundColor Red
    }
    if ($extra) { Write-Host ("     " + $extra) }
}

$repo = [System.IO.Path]::GetFullPath($Repo)
$cands = @(
    (Join-Path $PSScriptRoot 'patch\arquivos'),
    (Join-Path (Split-Path $PSScriptRoot -Parent) 'patch\arquivos'),
    (Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'patch\arquivos')
)
$src = $null
foreach ($c in $cands) { if (Test-Path -LiteralPath $c -PathType Container) { $src = $c; break } }
if (-not $src) { $src = $cands[0] }

if (-not (Test-Path -LiteralPath $repo -PathType Container)) {
    Write-Host ("ERRO: pasta do repositorio nao existe: " + $repo) -ForegroundColor Red
    exit 2
}
if (-not (Test-Path -LiteralPath $src -PathType Container)) {
    Write-Host ("ERRO: patch\arquivos\ nao encontrado ao lado deste script: " + $src) -ForegroundColor Red
    exit 2
}

$modo = 'CHECK'
if ($Apply) { $modo = 'APPLY' }
Write-Host ""
Write-Host ("== BO-r4 instalador Windows | repo=" + $repo + " | modo=" + $modo + " ==")
Write-Host ""

# 1) todos os arquivos de origem existem no pacote
foreach ($rel in $ARQUIVOS) {
    $f = Full $src $rel
    Ok ("pacote contem " + ($rel -split '/')[-1]) (Test-Path -LiteralPath $f -PathType Leaf)
}

# 2) o modulo novo e o ponto critico
$novoSrc = Full $src $NOVO
$existeNovo = Test-Path -LiteralPath $novoSrc -PathType Leaf
$tamNovo = 0
if ($existeNovo) { $tamNovo = (Get-Item -LiteralPath $novoSrc).Length }
Ok 'modulo novo tem conteudo real' ($existeNovo -and ($tamNovo -gt 10000)) ("$tamNovo bytes")

# 3) copia
if ($Apply) {
    foreach ($rel in $ARQUIVOS) {
        $destino = Full $repo $rel
        $pasta = Split-Path $destino -Parent
        if (-not (Test-Path -LiteralPath $pasta)) {
            New-Item -ItemType Directory -Path $pasta -Force | Out-Null
        }
        Copy-Item -LiteralPath (Full $src $rel) -Destination $destino -Force
    }
    Write-Host ""
    Write-Host "10 arquivos copiados (incluindo o NOVO scenes\david-universe.js)."
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "Modo CHECK: nada foi copiado. Use -Apply para instalar."
    Write-Host ""
}

# 4) verificacao POS-install
if ($Apply) {
    foreach ($rel in $ARQUIVOS) {
        $d = Full $repo $rel
        $existe = Test-Path -LiteralPath $d -PathType Leaf
        $tam = 0
        if ($existe) { $tam = (Get-Item -LiteralPath $d).Length }
        Ok ("instalado " + ($rel -split '/')[-1]) ($existe -and ($tam -gt 0))
    }

    $m = Full $repo 'dev/system/library/three/runtime/three/main.js'
    $txtM = ''
    if (Test-Path -LiteralPath $m) { $txtM = Get-Content -LiteralPath $m -Raw -Encoding UTF8 }
    Ok 'main.js importa david-universe' ($txtM.Contains('./scenes/david-universe.js'))

    $am = Full $repo 'dev/system/library/three/asset_manifest.php'
    $txtAM = ''
    if (Test-Path -LiteralPath $am) { $txtAM = Get-Content -LiteralPath $am -Raw -Encoding UTF8 }
    Ok 'asset_manifest publica david-universe' ($txtAM.Contains('three/scenes/david-universe.js'))

    $h = Full $repo 'dev/catalog/controller/common/home.php'
    $txtH = ''
    if (Test-Path -LiteralPath $h) { $txtH = Get-Content -LiteralPath $h -Raw -Encoding UTF8 }
    $needle = "'david-universe' => 'three_hero_enabled'"
    Ok 'home.php mapeia three_hero_enabled' ($txtH.Contains($needle))

    $dn = Full $repo $NOVO
    $existeDn = Test-Path -LiteralPath $dn -PathType Leaf
    $tamDn = 0
    if ($existeDn) { $tamDn = (Get-Item -LiteralPath $dn).Length }
    Ok 'ARQUIVO NOVO LEGIVEL NO SERVIDOR' ($existeDn -and ($tamDn -gt 10000)) $dn

    Write-Host ""
    Write-Host "== proximos passos ==" -ForegroundColor Cyan
    Write-Host "1. Limpe o cache de opcode/Twig do CMS e recarregue com Ctrl+F5."
    Write-Host "2. Importe tema\david_universe_3d_bo.reamurtheme.json no Admin e ATIVE o tema."
    Write-Host "3. Copie verificar-bo.html para a RAIZ do site e abra no navegador."
    Write-Host "4. Lembre-se: three-runtime/three/scenes/david-universe.js -> 200."
    Write-Host "   Um 404 aqui derruba o main.js inteiro."
}

Write-Host ""
Write-Host ("BO instalador Windows: " + $script:P + " OK / " + $script:F + " FALHAS")
Write-Host ""
if ($script:F -gt 0) { exit 1 }
exit 0
