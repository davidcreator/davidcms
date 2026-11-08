# Restaura dev/install/sql/schema.sql pelos BYTES EXATOS do blob Git bcd0a351...
# POWERSHELL 5.1+ | sem Bash/Python/rede | NAO decide D09, NAO altera matriz/hash.
#   powershell -NoProfile -ExecutionPolicy Bypass -File docs\CORRIGIR-SCHEMA.ps1
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$BLOB = 'bcd0a351be1d49d709028480939b50c17c5c3254'
$ESPERADO = 'c9dd1ec27af95d7506b8aff6af06133f75b288c2db31e5537fb7fbac2e1f67a5'
$TAM = 22495
$REL = 'dev\install\sql\schema.sql'

Write-Host ""
Write-Host "== CORRIGIR-SCHEMA.ps1 ==" -ForegroundColor Cyan
$top = & git rev-parse --show-toplevel 2>$null
if (-not $top) { Write-Host "ERRO: rode dentro do repositorio git." -ForegroundColor Red; exit 2 }
$destAbs = Join-Path $top $REL
Write-Host ("   repo: " + $top)
if (Test-Path -LiteralPath $destAbs -PathType Leaf) {
    $h0 = (Get-FileHash -LiteralPath $destAbs -Algorithm SHA256).Hash.ToLowerInvariant()
    $n0 = (Get-Item -LiteralPath $destAbs).Length
    Write-Host ("   ANTES : " + $n0 + " bytes / " + $h0)
    if ($n0 -eq 22960) { Write-Host "   (22960 = 22495 + 465 linhas -> CRLF confirmado)" }
} else { Write-Host "   ANTES : arquivo ausente" }

& git cat-file -e $BLOB 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "   ERRO: blob ausente neste clone. Rode: git fetch --all" -ForegroundColor Red; exit 2
}
$tmp = [System.IO.Path]::GetTempFileName()
try {
    & cmd /c "git cat-file blob $BLOB > `"$tmp`""
    if ($LASTEXITCODE -ne 0) { throw "git cat-file falhou" }
    [System.IO.File]::Copy($tmp, $destAbs, $true)
} finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }

$h1 = (Get-FileHash -LiteralPath $destAbs -Algorithm SHA256).Hash.ToLowerInvariant()
$n1 = (Get-Item -LiteralPath $destAbs).Length
Write-Host ("   DEPOIS: " + $n1 + " bytes / " + $h1)
if ($h1 -eq $ESPERADO -and $n1 -eq $TAM) {
    Write-Host "   OK  schema.sql restaurado EXATAMENTE." -ForegroundColor Green
} else {
    Write-Host "   FAIL  ainda divergente." -ForegroundColor Red; exit 1
}
Write-Host ""
Write-Host "   NAO decide D09, NAO altera decision-matrix.json, NAO reemite hash."
Write-Host "   Para evitar reconversao: git config core.autocrlf input"
Write-Host "   Confira: powershell -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1"
exit 0
