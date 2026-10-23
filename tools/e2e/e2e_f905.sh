#!/usr/bin/env bash
# Fase 9 / Tarefa 05 — validação estática de Model/ACL; não executa app/PHP/SQL.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
case "${1:---check}" in
  --check|--help) ;;
  *) printf '%s\n' 'Uso: tools/e2e/e2e_f905.sh --check' >&2; exit 2 ;;
esac
if [[ "${1:-}" == "--help" ]]; then
  printf '%s\n' 'Uso: tools/e2e/e2e_f905.sh --check'
  exit 0
fi
if ! command -v node >/dev/null 2>&1; then
  printf '%s\n' 'Node.js ausente; verificação F9-05 não executada.' >&2
  exit 2
fi
node "$ROOT/tools/e2e/e2e_f905.cjs" --check
