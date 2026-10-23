#!/usr/bin/env bash
# Fase 8 / Tarefa 12 — consolidação QA/documentação; nenhum fluxo mutável.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
NODE_BIN="${NODE_BIN:-node}"
command -v "$NODE_BIN" >/dev/null 2>&1 || { printf '%s\n' 'ERRO: Node20+ não encontrado.' >&2; exit 2; }
"$NODE_BIN" -e 'if(Number(process.versions.node.split(".")[0])<20)process.exit(2);'
RUNNER="$ROOT/tools/e2e/e2e_f812.cjs"
[[ -f "$RUNNER" ]] || { printf '%s\n' 'ERRO: runner F8-12 ausente.' >&2; exit 2; }
"$NODE_BIN" --check "$RUNNER"
"$NODE_BIN" "$RUNNER" "$@"
