#!/usr/bin/env bash
# Fase 9 / Tarefa 03 — recebe SQL e faz auditoria estática; não executa banco.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
NODE_BIN="${NODE_BIN:-node}"
 case "${1:-}" in
  --check|'') ;;
  --help) printf '%s\n' 'Uso: tools/e2e/e2e_f903.sh --check [--sql dev/install/sql/bd-davidalmeida.sql]' ; exit 0 ;;
  *) printf '%s\n' 'Opção não permitida. Use --check com --sql opcional.' >&2; exit 2 ;;
 esac
if ! command -v "$NODE_BIN" >/dev/null 2>&1; then
  printf '%s\n' 'Node.js ausente; auditoria F9-03 não executada.' >&2
  exit 2
fi
"$NODE_BIN" "$ROOT/tools/e2e/e2e_f903.cjs" --check "$@"
