#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
# verify-checksums.sh — F11-03, reforcado em F11-05
# Verifica READ-ONLY: (0) existencia dos auditores exigidos pelo job
# repo-guards e (1) todos os manifestos CHECKSUMS*.txt da raiz do repo.
#
#   bash tools/verify-checksums.sh
#
# F11-05: o CI chamava tools/verify-checksums.sh sem que o arquivo existisse
# no repositório (exit code 127), e verify-ci-workflow.sh aprovava o workflow
# porque so conferia a REFERENCIA no YAML. Esta checagem de existencia fecha
# essa brecha sem exigir alteracao do workflow.
#
# Nao instala, nao altera, nao comita, nao acessa rede/banco.
# Formato aceito (linhas com '#' sao ignoradas):
#   CRC32  SHA256  tamanho(bytes)  caminho
# ════════════════════════════════════════════════════════════════════════
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1

PASS=0; FAIL=0; TOTAL=0; MANIFESTS=0
ck(){ if [ "$1" -eq 0 ]; then printf '  PASS  %s\n' "$2"; PASS=$((PASS+1)); else printf '  FAIL  %s\n' "$2"; FAIL=$((FAIL+1)); fi; }

echo "== F11-05 — integridade e presenca dos guardas ($ROOT) =="

echo "-- 0. Auditores exigidos pelo job repo-guards --"
for g in tools/verify-deps-layout.sh tools/verify-checksums.sh tools/verify-ci-workflow.sh; do
  [ -f "$g" ]; ck $? "$g presente (evita exit 127 no CI)"
done

echo "-- 1. Manifestos CHECKSUMS*.txt --"
for m in CHECKSUMS*.txt; do
  [ -f "$m" ] || continue
  MANIFESTS=$((MANIFESTS+1)); ok=0; bad=0
  while read -r crc sha size path; do
    case "$crc" in ''|\#*) continue ;; esac
    if [ ! -f "$path" ]; then
      printf '  FAIL  %s: arquivo ausente %s\n' "$m" "$path"; bad=$((bad+1)); continue
    fi
    actual=$(sha256sum "$path" | cut -d' ' -f1)
    real_size=$(wc -c < "$path" | tr -d ' ')
    if [ "$actual" = "$sha" ] && [ "$real_size" = "$size" ]; then
      ok=$((ok+1))
    else
      printf '  FAIL  %s: hash/tamanho divergente %s\n' "$m" "$path"; bad=$((bad+1))
    fi
  done < "$m"
  printf '  %-28s %2d OK / %d divergente(s)\n' "$m" "$ok" "$bad"
  TOTAL=$((TOTAL+ok)); FAIL=$((FAIL+bad))
done

echo
if [ "$MANIFESTS" -eq 0 ]; then
  echo "  FAIL  nenhum CHECKSUMS*.txt encontrado"; exit 1
fi
echo "== resumo: $PASS PASS / $FAIL FAIL | $MANIFESTS manifesto(s), $TOTAL arquivo(s) conferidos =="
echo "   Os CRC-32 de cada entrada tambem podem ser confrontados com 'unzip -t' do ZIP original."
[ "$FAIL" -eq 0 ] && [ "$TOTAL" -gt 0 ]
