#!/usr/bin/env bash
# Fase 9 / Tarefa 07 — gate estático de integração de valores no CMS/page.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
case "${1:---check}" in --check|--help) ;; *) echo 'Use somente --check.' >&2; exit 2;; esac
if [[ "${1:-}" == "--help" ]]; then echo 'Uso: tools/e2e/e2e_f907.sh --check'; exit 0; fi
command -v node >/dev/null 2>&1 || { echo 'Node.js ausente.' >&2; exit 2; }
node "$ROOT/tools/e2e/e2e_f907.cjs" --check
