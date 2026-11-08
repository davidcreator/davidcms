# Restaura docs/README.md pelos BYTES EXATOS do blob Git 24b7957d... (commit 687fba34).
# POWERSHELL 5.1+ | sem Bash, sem Python, sem ZIP extraido, sem git checkout, sem rede.
# Imune a core.autocrlf: grava o blob cru via redirecionamento do cmd (byte a byte).
#
#   powershell -NoProfile -ExecutionPolicy Bypass -File docs\CORRIGIR-README.ps1
#
# Nao faz commit, nao faz push, nao altera manifesto, nao altera contrato congelado.

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$BLOB = '24b7957d1d668591d9792659a41d2a6e26bcc470'
$ESPERADO = '6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec'
$TAM = 3213
$DEST = 'docs\README.md'

Write-Host ""
Write-Host "== CORRIGIR-README.ps1 ==" -ForegroundColor Cyan
Write-Host ""

# 1) repo
$top = & git rev-parse --show-toplevel 2>$null
if (-not $top) { Write-Host "ERRO: rode dentro do repositorio git." -ForegroundColor Red; exit 2 }
Write-Host ("   repo: " + $top)
$destAbs = Join-Path $top $DEST

# 2) estado ANTES
if (Test-Path -LiteralPath $destAbs -PathType Leaf) {
    $h0 = (Get-FileHash -LiteralPath $destAbs -Algorithm SHA256).Hash.ToLowerInvariant()
    $n0 = (Get-Item -LiteralPath $destAbs).Length
    Write-Host ("   ANTES : " + $n0 + " bytes / " + $h0)
} else {
    Write-Host "   ANTES : arquivo ausente"
}

# 3) o blob existe no object database local?
& git cat-file -e $BLOB 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "   ERRO: blob $BLOB nao existe neste clone." -ForegroundColor Red
    Write-Host "   Rode: git fetch --all   (ou git fetch origin 687fba34f90b0eefcef5e32907d74be8ce42432b)"
    exit 2
}

# 4) grava BYTES CRUS (cmd /c > nao converte encoding nem EOL)
$tmp = [System.IO.Path]::GetTempFileName()
try {
    & cmd /c "git cat-file blob $BLOB > `"$tmp`""
    if ($LASTEXITCODE -ne 0) { throw "git cat-file falhou" }
    [System.IO.File]::Copy($tmp, $destAbs, $true)
} finally {
    Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
}

# 5) estado DEPOIS
$h1 = (Get-FileHash -LiteralPath $destAbs -Algorithm SHA256).Hash.ToLowerInvariant()
$n1 = (Get-Item -LiteralPath $destAbs).Length
Write-Host ("   DEPOIS: " + $n1 + " bytes / " + $h1)
Write-Host ""
if ($h1 -eq $ESPERADO -and $n1 -eq $TAM) {
    Write-Host "   OK  docs/README.md restaurado EXATAMENTE." -ForegroundColor Green
    Write-Host ("   Esperado: " + $TAM + " bytes / " + $ESPERADO)
} else {
    Write-Host "   FAIL  hash/tamanho ainda divergem." -ForegroundColor Red
    Write-Host "   Possivel core.autocrlf=true: confira 'git config core.autocrlf'."
    exit 1
}

# 6) recalcula o manifesto F11-10 localmente (sem Bash)
Write-Host ""
Write-Host "   -- CHECKSUMS-F11-10.txt recalculado --"
$man = Join-Path $top 'CHECKSUMS-F11-10.txt'
$div = 0; $tot = 0
if (Test-Path -LiteralPath $man) {
    foreach ($line in (Get-Content -LiteralPath $man -Encoding UTF8)) {
        $c = $line -split '\s+'
        if ($c.Count -lt 4) { continue }
        $rel = $c[3]; $esp = $c[1].ToLowerInvariant(); $sz = $c[2]
        $p = Join-Path $top ($rel -replace '/', '\')
        $tot++
        if (-not (Test-Path -LiteralPath $p -PathType Leaf)) { $div++; continue }
        $hh = (Get-FileHash -LiteralPath $p -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($hh -ne $esp -or ([string](Get-Item -LiteralPath $p).Length) -ne $sz) {
            $div++; Write-Host ("     DIVERGENTE " + $rel) -ForegroundColor Red
        }
    }
}
Write-Host ("     " + $tot + " entradas / " + $div + " divergente(s)")
Write-Host ""
Write-Host "   Proximos passos (VOCE aplica):" -ForegroundColor Cyan
Write-Host "     git add docs/README.md"
Write-Host '     git commit -m "fix(docs): restore frozen docs/README.md (CHECKSUMS-F11-10)"'
Write-Host "     bash tools/verify-checksums.sh     (Git Bash/WSL)  OU"
Write-Host "     powershell -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1"
Write-Host ""
Write-Host "   NAO reemita CHECKSUMS-F11-10.txt. Nenhuma escrita no GitHub foi feita por mim."
if ($div -gt 0) { exit 1 }
exit 0
