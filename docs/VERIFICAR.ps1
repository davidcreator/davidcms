# Verificacao completa do ReamurCMS - POWERSHELL PURO (5.1+), sem Bash/Python/lychee.
# Substitui LOCALMENTE: verify-checksums.sh, verify-contract-freeze.py, lychee --offline.
# Os guards OFICIAIS do CI continuam sendo os .sh / .py. Este e apenas conferencia local.
#
# Uso (PowerShell, na raiz do repo):
#   powershell -NoProfile -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1
#   powershell -NoProfile -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1 -Aplicar
#
# -Aplicar executa o git rm da limpeza da raiz. Sem ele, DRY-RUN (nada alterado).
# NUNCA faz commit e NUNCA faz push.

param(
    [switch]$Aplicar,
    [string]$Listas = ''
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$ROOT = (Get-Location).Path
$script:P = 0
$script:F = 0

function Ok($nome, $cond, $extra) {
    if ($cond) { $script:P++; Write-Host ("OK   " + $nome) -ForegroundColor Green }
    else { $script:F++; Write-Host ("FAIL " + $nome) -ForegroundColor Red }
    if ($extra) { Write-Host ("     " + $extra) }
}
function Hash($path) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { return $null }
    return (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
}

Write-Host ""
Write-Host ("== VERIFICAR.ps1 | repo=" + $ROOT + " | modo=" + $(if ($Aplicar) {'APLICAR'} else {'DRY-RUN'}) + " ==")
Write-Host ""

# ---------------------------------------------------------------- 1. docs/README.md
Write-Host "-- 1. docs/README.md (o FAIL do CHECKSUMS-F11-10.txt) --" -ForegroundColor Cyan
$readme = Join-Path $ROOT 'docs\README.md'
$h = Hash $readme
$tam = if (Test-Path -LiteralPath $readme) { (Get-Item -LiteralPath $readme).Length } else { 0 }
Ok 'docs/README.md existe' (Test-Path -LiteralPath $readme -PathType Leaf)
Ok 'tamanho = 3213 bytes' ($tam -eq 3213) ("$tam bytes")
Ok 'SHA = 6f0783e2...' ($h -eq '6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec') ($h)
Write-Host ""

# ---------------------------------------------------------------- 2. Manifestos
Write-Host "-- 2. Manifestos CHECKSUMS*.txt (verify-checksums.sh) --" -ForegroundColor Cyan
$mans = @(Get-ChildItem -LiteralPath $ROOT -Filter 'CHECKSUMS*.txt' -File | Sort-Object Name)
$tot = 0; $div = 0; $aus = 0
foreach ($m in $mans) {
    $okM = 0; $badM = 0
    foreach ($line in (Get-Content -LiteralPath $m.FullName -Encoding UTF8)) {
        if ($line -match '^\s*(#|$)') { continue }
        $c = $line -split '\s+'
        if ($c.Count -lt 4) { continue }
        $rel = $c[3]; $esp = $c[1].ToLowerInvariant(); $tamEsp = $c[2]
        $alvo = Join-Path $ROOT ($rel -replace '/', '\')
        $tot++
        if (-not (Test-Path -LiteralPath $alvo -PathType Leaf)) { $aus++; $badM++; continue }
        $real = Hash $alvo
        $realTam = (Get-Item -LiteralPath $alvo).Length
        if ($real -eq $esp -and ([string]$realTam) -eq $tamEsp) { $okM++ }
        else { $div++; $badM++; Write-Host ("     DIVERGENTE " + $m.Name + " -> " + $rel) -ForegroundColor Red }
    }
    Write-Host ("     " + $m.Name.PadRight(28) + " $okM OK / $badM divergente(s)")
}
Ok ("14 manifestos presentes") ($mans.Count -eq 14) ("$($mans.Count) encontrados")
Ok '0 arquivos ausentes' ($aus -eq 0) ("$aus ausentes")
Ok '0 hashes divergentes' ($div -eq 0) ("$div divergentes / $tot entradas conferidas")
Write-Host ""

# ---------------------------------------------------------------- 3. Congelados
Write-Host "-- 3. Contratos congelados (verify-contract-freeze.py, sem Python) --" -ForegroundColor Cyan
$matriz = Join-Path $ROOT 'tools\decision-matrix.json'
$pend = 0; $frozOk = 0; $frozN = 0
if (Test-Path -LiteralPath $matriz -PathType Leaf) {
    $j = Get-Content -LiteralPath $matriz -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($prop in $j.frozen_artifacts_sha256.PSObject.Properties) {
        $frozN++
        $alvo = Join-Path $ROOT ($prop.Name -replace '/', '\')
        if ((Hash $alvo) -eq $prop.Value.ToLowerInvariant()) { $frozOk++ }
        else { Write-Host ("     DIVERGENTE " + $prop.Name) -ForegroundColor Red }
    }
    foreach ($d in $j.decisions) { if ($d.status -eq 'PENDING_DECISION') { $pend++ } }
}
Ok 'matriz presente' (Test-Path -LiteralPath $matriz -PathType Leaf)
Ok '11/11 hashes coerentes' ($frozN -eq 11 -and $frozOk -eq 11) ("$frozOk/$frozN")
Ok '10 decisoes ainda PENDENTES (nao decidir D01-D10)' ($pend -eq 10) ("$pend")
Write-Host ""

# ---------------------------------------------------------------- 4. Links
Write-Host "-- 4. Links Markdown relativos (lychee --offline, triagem) --" -ForegroundColor Cyan
$mds = @(Get-ChildItem -LiteralPath $ROOT -Recurse -Filter '*.md' -File |
         Where-Object { $_.FullName -notmatch '\\\.git\\' })
$linkTot = 0; $linkBad = 0; $linkDir = 0
$rx = [regex]'\[[^\]]*\]\(([^)]+)\)'
foreach ($f in $mds) {
    $txt = Get-Content -LiteralPath $f.FullName -Raw -Encoding UTF8
    foreach ($mt in $rx.Matches($txt)) {
        $t = ($mt.Groups[1].Value -split '#')[0].Trim().Trim('<>')
        if ($t -match '^(https?://|mailto:|data:)') { continue }
        if ($t -eq '') { continue }
        $linkTot++
        try {
            $alvo = [System.IO.Path]::GetFullPath((Join-Path $f.DirectoryName ($t -replace '/', '\')))
        } catch { $linkBad++; continue }
        if (Test-Path -LiteralPath $alvo -PathType Container) { $linkDir++; continue }
        if (-not (Test-Path -LiteralPath $alvo -PathType Leaf)) {
            $linkBad++
            if ($linkBad -le 12) { Write-Host ("     QUEBRADO " + $f.FullName.Substring($ROOT.Length+1) + " -> " + $t) }
        }
    }
}
Write-Host ("     $($mds.Count) .md | $linkTot links internos | $linkDir para diretorios | $linkBad sem alvo")
Write-Host "     Nota: links para DIRETORIOS e 'mailto:' sem esquema sao aceitos pelo lychee."
Write-Host "     Falsos positivos ocorrem se o checkout estiver incompleto. CI e a verdade."
Write-Host ""

# ---------------------------------------------------------------- 5. Limpeza da raiz
Write-Host "-- 5. Limpeza da raiz (listas/) --" -ForegroundColor Cyan
$cands = @('listas', '.', 'docs')
$dirListas = $null
if ($Listas) { if (Test-Path -LiteralPath $Listas -PathType Container) { $dirListas = $Listas } }
if (-not $dirListas) {
    foreach ($c in $cands) {
        $p = Join-Path $ROOT $c
        if ((Test-Path -LiteralPath (Join-Path $p 'lista-raiz-unificar.txt')) -and
            (Test-Path -LiteralPath (Join-Path $p 'lista-raiz-remover.txt'))) { $dirListas = $p; break }
    }
}
$ARRS = @()
if ($dirListas) {
    foreach ($fn in @('lista-raiz-unificar.txt','lista-raiz-remover.txt')) {
        foreach ($l in (Get-Content -LiteralPath (Join-Path $dirListas $fn) -Encoding UTF8)) {
            $l = $l.Trim()
            if ($l -and -not $l.StartsWith('#')) { $ARRS += $l }
        }
    }
    $ARRS = @($ARRS | Sort-Object -Unique)
    Write-Host ("     listas em: " + $dirListas + " | " + $ARRS.Count + " paths")
} else {
    Write-Host "     listas NAO encontradas (extraia o ZIP dentro do repo ou use -Listas <pasta>)"
}

$proib = '^(CHECKSUMS.*\.txt|F11-.*-INDICE\.md|README\.md|CONTRIBUTING\.md|SECURITY\.md|LICENSE|\.gitignore|\.editorconfig|Pauta-Decisoes-D01-D10\.md)$'
$bloqueio = 0
if ($ARRS.Count -gt 0) {
    foreach ($p in $ARRS) {
        if ($p -match $proib) { Write-Host ("     RECUSADO (intocavel): " + $p); $bloqueio++ }
        $noMan = $false
        foreach ($m in $mans) {
            foreach ($line in (Get-Content -LiteralPath $m.FullName -Encoding UTF8)) {
                $c = $line -split '\s+'
                if ($c.Count -ge 4 -and $c[3] -eq $p) { $noMan = $true; break }
            }
            if ($noMan) { break }
        }
        if ($noMan) { Write-Host ("     RECUSADO (em manifesto): " + $p); $bloqueio++ }
        if (-not (Test-Path -LiteralPath (Join-Path $ROOT ($p -replace '/','\')))) {
            Write-Host ("     AUSENTE no checkout: " + $p); $bloqueio++
        }
    }
    Ok 'seguranca: 0 intocaveis / 0 em manifestos / 0 ausentes' ($bloqueio -eq 0) ("$bloqueio bloqueios")
    if ($Aplicar -and $bloqueio -eq 0 -and $script:F -eq 0) {
        Write-Host ""
        Write-Host "  Executando git rm ..." -ForegroundColor Yellow
        & git rm -- $ARRS
        Write-Host "  git rm concluido. Commit LOCAL e seu. Este script NAO faz push."
    } elseif ($Aplicar) {
        Write-Host "  ABORTADO: existem FAILs ou bloqueios acima. Corrija antes." -ForegroundColor Red
    } else {
        Write-Host "  DRY-RUN: nada alterado. Para aplicar: -Aplicar"
    }
}
Write-Host ""

Write-Host ("== RESULTADO: " + $script:P + " OK / " + $script:F + " FALHAS ==")
Write-Host "   release_approved:false | F9-27 BLOCKED_NOT_RUN | D01-D10 intactas"
Write-Host "   Guard verde NAO significa release aprovada. Nenhuma escrita no GitHub."
Write-Host ""
if ($script:F -gt 0) { exit 1 }
exit 0
