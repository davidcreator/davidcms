#!/usr/bin/env bash
# Fase 9 / Tarefa 02 — migração SQL revisável; nenhum banco/aplicação executado.
set -euo pipefail
case "${1:---check}" in --check) ;; *) printf "Uso: %s --check\n" "${0##*/}" >&2; exit 2;; esac
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TOOL="$ROOT/tools/e2e/e2e_f902.sh"
PIPES=(xdpyinfo xmllint jq python3 php node)
for p in "${PIPES[@]}"; do command -v "$p" >/dev/null 2>&1 || true; done
if ! bash -n "$TOOL"; then printf '%s\n' 'Tool inválida.' >&2; exit 2; fi
if [[ ! -f "$ROOT/dev/install/sql/upgrade_f9_02_custom_fields_schema.sql" || ! -f "$ROOT/dev/install/sql/rollback_f9_02_custom_fields_schema.sql" ]]; then
 printf '%s\n' 'Arquivos SQL F9-02 ausentes.' >&2; exit 2;
fi
python3 -B - <<'PY'
from pathlib import Path
import re,json
up=Path('dev/install/sql/upgrade_f9_02_custom_fields_schema.sql')
rb=Path('dev/install/sql/rollback_f9_02_custom_fields_schema.sql')
u=up.read_text();r=rb.read_text()
assert 'CREATE TABLE IF NOT EXISTS' in u and 'DROP' not in u and 'DELETE' not in u and 'rms_user_group' not in u
assert 'DROP TABLE IF EXISTS' in r and 'CREATE TABLE' not in r and 'rms_custom_field_value' in r
assert 'custom_fields' not in u
assert 'visual identity' not in u.lower()
tbl=re.findall(r'CREATE TABLE IF NOT EXISTS `([^`]+)`',u)
assert tbl==['rms_custom_field_group','rms_custom_field_group_description','rms_custom_field','rms_custom_field_description','rms_custom_field_value'],tbl
print('SQL estático aprovado; nenhum DB executado.')
PY
