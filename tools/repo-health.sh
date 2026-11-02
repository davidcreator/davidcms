#!/usr/bin/env bash
# repo-health.sh — F11-13 (fechamento da Fase 11)
#
# ORQUESTRADOR READ-ONLY. NAO reimplementa NENHUMA verificacao: apenas invoca
# os guardas que ja existem no repositorio e agrega o veredito. Nao duplica
# ferramentas, nao escreve arquivos, nao acessa rede, nao executa PHP/Composer/
# PHPStan/browser/banco/staging/deploy.
#
#   bash tools/repo-health.sh
#
# exit 0 = nenhum guarda reprovou | exit 1 = pelo menos um FAIL.
# Guardas ainda nao aplicados (F11-11, F11-06) sao SKIP, nao PASS.
set -u
cd "$(dirname "$0")/.." || exit 1

# Python real (inclui Windows com alias da Microsoft Store / launcher py -3).
PYTHON_CMD="${PYTHON_CMD:-}"
[ -f tools/lib/python-runtime.sh ] && . tools/lib/python-runtime.sh

# runner| caminho  (a ordem e a mesma do repo-guards no CI)
# runner | caminho | requisito ('py' = exige interpretador Python real)
GUARDS=(
  'bash|tools/verify-deps-layout.sh|'
  'bash|tools/verify-checksums.sh|'
  'bash|tools/verify-ci-workflow.sh|'
  'bash|tools/verify-f11-01.sh|'
  'python|tools/verify-contract-freeze.py|py'
  'bash|tools/release-readiness-report.sh|py'
)

pass=0; fail=0; skip=0
printf '%-46s %s\n' 'GUARDA' 'RESULTADO'
printf '%-46s %s\n' '----------------------------------------------' '---------'
for g in "${GUARDS[@]}"; do
  IFS='|' read -r runner path need <<< "$g"
  if [ ! -f "$path" ]; then
    printf '%-46s %s\n' "$path" 'SKIP (pacote ainda nao aplicado)'
    skip=$((skip + 1)); continue
  fi
  if [ "$need" = 'py' ] && [ -z "$PYTHON_CMD" ]; then
    printf '%-46s %s\n' "$path" 'SKIP (Python real ausente — NAO EXECUTADO)'
    skip=$((skip + 1)); continue
  fi
  [ "$runner" = 'python' ] && runner="$PYTHON_CMD"
  out="$($runner "$path" 2>&1)"; rc=$?
  if [ "$rc" -eq 3 ]; then
    printf '%-46s %s\n' "$path" 'SKIP (Python real ausente — NAO EXECUTADO)'
    skip=$((skip + 1)); continue
  fi
  if [ "$rc" -eq 0 ]; then
    resumo="$(printf '%s\n' "$out" | grep 'resumo:' | tail -1 | xargs)"
    printf '%-46s PASS %s\n' "$path" "$resumo"
    pass=$((pass + 1))
  else
    printf '%-46s FAIL (exit %d)\n' "$path" "$rc"
    printf '%s\n' "$out" | tail -5 | sed 's/^/    /'
    fail=$((fail + 1))
  fi
done

printf '\n== repo-health: %d PASS / %d FAIL / %d SKIP ==\n' "$pass" "$fail" "$skip"
if [ "$fail" -ne 0 ]; then
  echo '   VEREDITO: REPROVADO — abra a saida do guarda indicado antes de qualquer release.'
  exit 1
fi
if [ "$skip" -ne 0 ]; then
  echo "   ATENCAO: $skip guarda(s) NAO EXECUTADO(S) — ver linhas SKIP acima."
fi
echo '   VEREDITO: COERENTE (guardas estaticos). SKIP nao e aprovacao.'
echo '   Fora deste health check, por exigirem runtime/rede: CI (7 jobs), 61 checkers e2e, F9-27, staging.'
exit 0
