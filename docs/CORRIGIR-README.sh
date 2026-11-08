#!/usr/bin/env bash
# Restaura docs/README.md pelos BYTES EXATOS do blob Git 24b7957d... (commit 687fba34).
# Git Bash / WSL / Linux. Sem Python, sem ZIP extraido, sem rede, imune a core.autocrlf.
#   bash docs/CORRIGIR-README.sh
set -uo pipefail
BLOB=24b7957d1d668591d9792659a41d2a6e26bcc470
ESPERADO=6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec
ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "ERRO: rode dentro do repo git."; exit 2; }
cd "$ROOT" || exit 2
echo "== CORRIGIR-README.sh | repo=$ROOT =="
echo -n "   ANTES : "; if [ -f docs/README.md ]; then
  wc -c < docs/README.md | tr -d '\n'; echo -n " bytes / "; sha256sum docs/README.md | cut -d' ' -f1
else echo "ausente"; fi
if ! git cat-file -e "$BLOB" 2>/dev/null; then
  echo "   ERRO: blob $BLOB ausente neste clone. Rode: git fetch --all"; exit 2
fi
git cat-file blob "$BLOB" > docs/README.md
H=$(sha256sum docs/README.md | cut -d' ' -f1); N=$(wc -c < docs/README.md | tr -d ' ')
echo "   DEPOIS: $N bytes / $H"
if [ "$H" = "$ESPERADO" ] && [ "$N" = "3213" ]; then
  echo "   OK  restaurado EXATAMENTE (3213 bytes / $ESPERADO)"
else
  echo "   FAIL  ainda divergente. Confira: git config core.autocrlf"; exit 1
fi
echo
echo "   -- recalculando apenas CHECKSUMS-F11-10.txt --"
DIV=0; TOT=0
while read -r crc sha size path; do
  case "$crc" in ''|\#*) continue ;; esac
  [ -z "${path:-}" ] && continue
  TOT=$((TOT+1))
  if [ ! -f "$path" ]; then DIV=$((DIV+1)); continue; fi
  RH=$(sha256sum "$path" | cut -d' ' -f1); RN=$(wc -c < "$path" | tr -d ' ')
  if [ "$RH" != "$sha" ] || [ "$RN" != "$size" ]; then
    echo "     DIVERGENTE $path"; DIV=$((DIV+1))
  fi
done < CHECKSUMS-F11-10.txt
echo "     $TOT entradas / $DIV divergente(s)"
echo
echo "   Proximos passos (VOCE aplica):"
echo "     git add docs/README.md"
echo '     git commit -m "fix(docs): restore frozen docs/README.md"'
echo "     bash tools/verify-checksums.sh"
echo "   NAO reemita CHECKSUMS-F11-10.txt. Sem push, sem escrita minha no GitHub."
[ "$DIV" -eq 0 ]
