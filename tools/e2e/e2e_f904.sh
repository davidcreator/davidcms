#!/usr/bin/env bash
# Fase 9 / Tarefa 04 — gate estático de dry-run; não executa SQL nem conecta.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
case "${1:---check}" in
  --check|--help) ;;
  *) printf '%s\n' 'Opção não permitida. Use --check; --sql privado é opcional.' >&2; exit 2 ;;
esac
if [[ "${1:-}" == "--help" ]]; then
  printf '%s\n' 'Uso: tools/e2e/e2e_f904.sh --check [--inventory <json>] [--map <json>] [--sql /caminho/privado/bd-davidalmeida.sql]'
  exit 0
fi
if ! command -v node >/dev/null 2>&1; then
  printf '%s\n' 'Node.js ausente; Tool F9-04 não executado.' >&2
  exit 2
fi
node "$ROOT/tools/e2e/e2e_f904.cjs" --check "$@"
