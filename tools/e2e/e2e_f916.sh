#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---plan}"
if [[ "$MODE" != "--plan" ]]; then
  echo "Este utilitário só documenta o plano. Execução de E2E/browser/app é bloqueada nesta entrega." >&2
  echo "Uso: $0 --plan" >&2
  exit 2
fi
cat "$ROOT/tools/e2e/README.md"
