#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---check}"
if [[ "$MODE" != "--check" ]]; then echo "Uso: $0 --check" >&2; exit 2; fi
node --check "$ROOT/tools/e2e/e2e_f915.cjs"
node "$ROOT/tools/e2e/e2e_f915.cjs"
