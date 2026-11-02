#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
# release-readiness-report.sh — F11-06
# PAINEL READ-ONLY: agrega os 3 artefatos de estado de release ja existentes.
#
#   bash tools/release-readiness-report.sh
#
# NAO e um gate, NAO aprova release, NAO reimplementa os checkers de
# tools/e2e/ (em particular e2e_f927_static.cjs). Apenas le os JSON/MD
# publicados e imprime o estado declarado por eles.
# Nao acessa rede, banco, staging, PHP, browser nem escreve no repo.
# ════════════════════════════════════════════════════════════════════════
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1

# Python real (inclui Windows com alias da Microsoft Store / launcher py -3).
PYTHON_CMD="${PYTHON_CMD:-}"
[ -f tools/lib/python-runtime.sh ] && . tools/lib/python-runtime.sh

if [ -z "$PYTHON_CMD" ]; then
  echo '== SKIP: nenhum interpretador Python real — painel NAO EXECUTADO =='
  echo '   SKIP nao e aprovacao. Instale Python 3 ou use o launcher py -3.'
  exit 3
fi

pyrun - <<'PY'
import json, os, sys

def load(p):
    if not os.path.isfile(p):
        return None
    try:
        return json.load(open(p, encoding='utf-8'))
    except Exception as e:
        print('  AVISO  %s: JSON invalido (%s)' % (p, e))
        return None

st  = load('docs/F9-27-Status.json')
pre = load('docs/F9-27-Preflight-Status.json')
risk= load('docs/F9-18-Risk-Register.json')
hand = os.path.isfile('docs/PROJECT-HANDOFF.md') and open('docs/PROJECT-HANDOFF.md', encoding='utf-8').read() or ''

print('== ReamurCMS — painel de prontidao (F11-06) ==')
print('   Fonte: docs/F9-27-Status.json, F9-27-Preflight-Status.json,')
print('          F9-18-Risk-Register.json, PROJECT-HANDOFF.md')
print()

if st:
    m = st.get('matrix', {}) or {}
    not_run = sum(1 for v in m.values() if v == 'NOT_RUN')
    blocked = sum(1 for v in m.values() if str(v).startswith('BLOCKED'))
    print('F9-27  status             :', st.get('status'))
    print('       release_approved   :', st.get('release_approved'))
    print('       f9_12_status       :', st.get('f9_12_status'))
    print('       runtime_executado  :', st.get('runtime_tests_executed'))
    print('       matriz             : %d NOT_RUN / %d BLOCKED / %d itens' %
          (not_run, blocked, len(m)))
    print('       pre-requisitos     :', len(st.get('required_before_execution', [])), 'itens pendentes')
if pre:
    print('       autorizacao runtime:', pre.get('explicit_runtime_authorization'))
    print('       staging            :', pre.get('staging_hostname'))
if risk:
    f = risk.get('findings', []) or []
    p1 = sum(1 for x in f if x.get('severity') == 'P1' and x.get('status') == 'open')
    p2 = sum(1 for x in f if x.get('severity') == 'P2' and x.get('status') == 'open')
    print('F9-18  release_ready      :', risk.get('release_ready'))
    print('       achados abertos    : %d P1 / %d P2' % (p1, p2))
print('BDREditor                 :', 'PAUSADO em F9-103 (handoff)' if 'paused/closed at F9-103' in hand else 'ver docs/PROJECT-HANDOFF.md')

print()
print('== Veredito INFORMATIVO: release NAO aprovada; gate BLOQUEADO ==')
print('   Este painel nao substitui autorizacao, staging descartavel,')
print('   confirmacao de F9-12 nem os checkers E2E. Nao marca nada como ready.')
PY
