#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
# verify-ci-workflow.sh — F11-04 / ADR 0082 (reforcado em F11-05)
# Auditor READ-ONLY do contrato do proprio CI (.github/workflows/php.yml).
#
#   bash tools/verify-ci-workflow.sh [caminho-do-workflow]
#
# O argumento opcional permite auditar um workflow candidato sem substituir
# o arquivo do repositorio. Nao instala, nao escreve, nao acessa a rede.
# ════════════════════════════════════════════════════════════════════════
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1
WF="${1:-.github/workflows/php.yml}"

PASS=0; FAIL=0
ck(){ if [ "$1" -eq 0 ]; then printf '  PASS  %s\n' "$2"; PASS=$((PASS+1)); else printf '  FAIL  %s\n' "$2"; FAIL=$((FAIL+1)); fi; }

echo "== F11-04 — contrato do CI ($WF) =="
[ -f "$WF" ]; ck $? "workflow existe"
[ -f "$WF" ] || { echo "== resumo: $PASS PASS / $FAIL FAIL =="; exit 1; }

echo "-- 1. Higiene e determinismo --"
grep -q '^permissions:' "$WF" && grep -q 'contents: read' "$WF"; ck $? "permissions: contents: read (least privilege)"
grep -q '^concurrency:' "$WF"; ck $? "concurrency declarado (cancela runs duplicados)"
! grep -q '^defaults:' "$WF"; ck $? "sem defaults.run global (F11-01)"
[ "$(grep -c 'working-directory:' "$WF")" -eq 2 ]; ck $? "working-directory apenas nos 2 jobs do Composer/PHPStan"

# Um 'runs-on:' por job — evita contar chaves de topo (on.push, etc.).
JOBS=$(grep -c 'runs-on:' "$WF")
TIMEOUTS=$(grep -c 'timeout-minutes:' "$WF")
[ "$JOBS" -gt 0 ] && [ "$JOBS" -eq "$TIMEOUTS" ]
ck $? "timeout-minutes em todos os $JOBS jobs ($TIMEOUTS declarados)"

echo "-- 2. Runner pinado (janela de migracao ubuntu-latest 19/10-19/11/2026) --"
! grep -q 'runs-on: ubuntu-latest' "$WF"; ck $? "nenhum ubuntu-latest"
RUNS=$(grep -c 'runs-on:' "$WF"); PINNED=$(grep -c 'runs-on: ubuntu-24.04' "$WF")
[ "$RUNS" -gt 0 ] && [ "$RUNS" -eq "$PINNED" ]
ck $? "$RUNS/$PINNED jobs pinados em ubuntu-24.04"

echo "-- 3. Actions em runtime Node 24 (releases oficiais verificadas) --"
for a in 'actions/checkout@v7' 'actions/setup-node@v7' 'actions/cache@v6' \
         'shivammathur/setup-php@v2' 'lycheeverse/lychee-action@v2'; do
  grep -qF "$a" "$WF"; ck $? "$a"
done
# v6 do cache JA e a major node24 — nao pode entrar na lista de proibidas.
! grep -qE 'actions/checkout@v[1-6]|actions/setup-node@v[1-6]|actions/cache@v[1-5]|lycheeverse/lychee-action@v1' "$WF"
ck $? "nenhuma major antiga de action Node 20"

echo "-- 4. Guardas de governanca e matrix --"
for g in tools/verify-deps-layout.sh tools/verify-checksums.sh tools/verify-ci-workflow.sh; do
  grep -qF "$g" "$WF"; ck $? "job repo-guards executa $g"
done
grep -q 'fail-fast: false' "$WF" && grep -q "node-version: \['20', '22'\]" "$WF"
ck $? "matrix Node 20 + 22 com fail-fast: false"

echo "-- 5. Guardas referenciados existem no disco (F11-05) --"
# F11-05: o workflow citava tools/verify-checksums.sh sem que o arquivo
# existisse no repo (exit 127). Conferir so o YAML nao bastava.
for g in tools/verify-deps-layout.sh tools/verify-checksums.sh tools/verify-ci-workflow.sh; do
  [ -f "$g" ]; ck $? "$g existe"
done

echo "-- 6. Sintaxe YAML (quando PyYAML disponivel) --"
if command -v python3 >/dev/null 2>&1 && python3 -c 'import yaml' >/dev/null 2>&1; then
  python3 - "$WF" <<'PY'
import sys,yaml
d=yaml.safe_load(open(sys.argv[1],encoding='utf-8'))
assert 'jobs' in d and 'defaults' not in d
assert 'concurrency' in d
PY
  ck $? "yaml.safe_load valido, sem defaults global e com concurrency"
else
  printf '  SKIP  PyYAML indisponivel — parse estrutural nao executado\n'
fi

echo
echo "== resumo: $PASS PASS / $FAIL FAIL =="
echo "   Este script NAO executa PHP, Composer, PHPStan, npm, browser ou deploy."
[ "$FAIL" -eq 0 ]
