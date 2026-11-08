#!/usr/bin/env bash
# Restaura dev/install/sql/schema.sql pelos BYTES EXATOS do blob Git bcd0a351...
# (HEAD 703b0127). NAO altera a matriz, NAO registra decisao D09, NAO reemite hash.
# Causa tipica: core.autocrlf converteu LF -> CRLF no working tree local.
#   bash docs/CORRIGIR-SCHEMA.sh
set -uo pipefail
BLOB=bcd0a351be1d49d709028480939b50c17c5c3254
ESPERADO=c9dd1ec27af95d7506b8aff6af06133f75b288c2db31e5537fb7fbac2e1f67a5
TAM=22495
REL=dev/install/sql/schema.sql
ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "ERRO: rode dentro do repo git."; exit 2; }
cd "$ROOT" || exit 2
echo "== CORRIGIR-SCHEMA.sh | repo=$ROOT =="
echo -n "   ANTES : "; if [ -f "$REL" ]; then
  wc -c < "$REL" | tr -d '\n'; echo -n " bytes / "; sha256sum "$REL" | cut -d' ' -f1
else echo "ausente"; fi
if [ -f "$REL" ]; then
  N=$(wc -c < "$REL" | tr -d ' ')
  [ "$N" = "22960" ] && echo "   (22960 bytes = 22495 + 465 linhas -> CRLF confirmado)"
fi
if ! git cat-file -e "$BLOB" 2>/dev/null; then
  echo "   ERRO: blob $BLOB ausente neste clone. Rode: git fetch --all"; exit 2
fi
git cat-file blob "$BLOB" > "$REL"
H=$(sha256sum "$REL" | cut -d' ' -f1); N=$(wc -c < "$REL" | tr -d ' ')
echo "   DEPOIS: $N bytes / $H"
if [ "$H" = "$ESPERADO" ] && [ "$N" = "$TAM" ]; then
  echo "   OK  schema.sql restaurado EXATAMENTE."
else
  echo "   FAIL  ainda divergente."; exit 1
fi
echo
echo "   IMPORTANTE: isto NAO decide D09, NAO altera decision-matrix.json e NAO"
echo "   reemite frozen_artifacts_sha256. O guard deve voltar a 11/11 sozinho."
echo "   Para evitar reconversao: git config core.autocrlf input"
echo
echo "   Confira: bash docs/verificar-congelados.sh   (11/11, 10 pendentes)"
echo "            bash tools/repo-health.sh           (6 PASS / 0 FAIL)"
exit 0
