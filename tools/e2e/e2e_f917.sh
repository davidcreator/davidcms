#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODE="${1:---plan}"
if [[ "$MODE" == "--plan" ]]; then
  cat "$SCRIPT_DIR/../../docs/F9-17-Checklist-E2E-Blog-Blocos.md"
elif [[ "$MODE" == "--static" && $# -eq 2 ]]; then
  node "$SCRIPT_DIR/e2e_f917.cjs" "$2"
else
  echo "Uso: $0 --plan | --static <checkout-DavidCMS>" >&2
  echo "--static lê fontes como texto; não inicia PHP, SQL, aplicação ou navegador." >&2
  exit 2
fi
