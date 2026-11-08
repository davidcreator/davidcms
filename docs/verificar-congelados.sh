#!/usr/bin/env bash
# F11-11 — equivalente SOMENTE-LEITURA de tools/verify-contract-freeze.py, SEM Python.
# NAO substitui o guard oficial: no CI continua valendo o .py.
# Uso (Git Bash, dentro do repo):  bash docs/verificar-congelados.sh
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1
M=tools/decision-matrix.json
if [ ! -f "$M" ]; then echo "FAIL  matriz ausente: $M"; exit 1; fi

BLOCO=$(sed -n '/"frozen_artifacts_sha256"[[:space:]]*:/,/^[[:space:]]*}/p' "$M")
N=0; OK=0; AUS=0; DIV=0
while IFS= read -r linha; do
  rel=$(printf '%s\n' "$linha" | sed -E 's/^[[:space:]]*"([^"]+)".*/\1/')
  esp=$(printf '%s\n' "$linha" | grep -oE '[0-9a-f]{64}' | head -1)
  [ -z "$rel" ] && continue
  [ -z "$esp" ] && continue
  N=$((N+1))
  if [ ! -f "$rel" ]; then
    echo "   AUSENTE    $rel (fail-closed)"; AUS=$((AUS+1)); continue
  fi
  real=$(sha256sum "$rel" | cut -d' ' -f1)
  if [ "$real" = "$esp" ]; then OK=$((OK+1));
  else echo "   DIVERGENTE $rel"; DIV=$((DIV+1)); fi
done < <(printf '%s\n' "$BLOCO" | grep -E '"[^"]+"[[:space:]]*:[[:space:]]*"[0-9a-f]{64}"')

PEND=$(grep -c '"status"[[:space:]]*:[[:space:]]*"PENDING_DECISION"' "$M" 2>/dev/null || true)
echo "== F11-11 (bash, sem Python) — congelamento de contratos =="
echo "   $OK/$N hashes coerentes | $AUS ausentes | $DIV divergentes | $PEND decisoes pendentes"
echo "   (decisoes pendentes NAO sao aprovacao; F9-27 segue BLOCKED_NOT_RUN)"
if [ "$N" -eq 0 ]; then echo "FAIL  matriz invalida / bloco nao parseado"; exit 1; fi
if [ "$AUS" -gt 0 ] || [ "$DIV" -gt 0 ]; then
  echo "== RESULTADO: BLOQUEADO — registre a decisao antes de alterar contratos =="; exit 1
fi
echo "== RESULTADO: COERENTE — nenhum contrato alterado sem decisao =="
exit 0
