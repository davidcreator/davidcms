#!/usr/bin/env bash
# Fase 9 / Tarefa 01 — proposta de contrato, sem runtime/SQL/HTTP.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
NODE_BIN="${NODE_BIN:-node}"
command -v "$NODE_BIN" >/dev/null 2>&1 || { printf '%s\n' 'ERRO: Node20+ não encontrado.' >&2; exit 2; }
"$NODE_BIN" -e 'if(Number(process.versions.node.split(".")[0])<20)process.exit(2);'
RUNNER="$ROOT/tools/e2e/e2e_f901.cjs"
[[ -f "$RUNNER" ]] || { printf '%s\n' 'ERRO: runner F9-01 ausente.' >&2; exit 2; }
"$NODE_BIN" --check "$RUNNER"
"$NODE_BIN" "$RUNNER" "$@"
