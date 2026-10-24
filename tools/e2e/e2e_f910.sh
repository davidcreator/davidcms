#!/usr/bin/env bash
# Fase 9 / Tarefa 10 — gate integrado estático das tarefas F9 custom-fields.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --check) shift ;;
    --root) [[ $# -ge 2 ]] || { echo '--root requer um caminho.' >&2; exit 2; }; ROOT="$(cd "$2" && pwd)"; shift 2 ;;
    --help) echo 'Uso: tools/e2e/e2e_f910.sh --check [--root /caminho/davidcms]'; exit 0 ;;
    *) echo "Opção não permitida: $1" >&2; exit 2 ;;
  esac
done
command -v node >/dev/null 2>&1 || { echo 'Node.js ausente.' >&2; exit 2; }
command -v python3 >/dev/null 2>&1 || { echo 'Python 3 ausente.' >&2; exit 2; }
for rel in dev/install/sql/upgrade_f9_02_custom_fields_schema.sql docs/Dicionario-de-Dados.md tools/e2e/e2e_f902.sh tools/e2e/e2e_f905.sh tools/e2e/e2e_f906.sh tools/e2e/e2e_f907.sh tools/e2e/e2e_f908.sh tools/e2e/e2e_f909.sh; do
  [[ -f "$ROOT/$rel" ]] || { echo "Arquivo necessário ausente: $rel" >&2; exit 2; }
done
node "$HERE/e2e_f910.cjs" --check --root "$ROOT"
cd "$ROOT"
for task in f902 f905 f906 f907 f908 f909; do
  printf '\n== F9-%s static gate ==\n' "${task#f9}"
  bash "tools/e2e/e2e_${task}.sh" --check
done
printf '\nPASS F9-10: todos os gates estáticos encadeados passaram. Nenhum PHP, DB, navegador ou rede foi executado.\n'
