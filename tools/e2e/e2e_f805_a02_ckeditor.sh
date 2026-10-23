#!/usr/bin/env bash
# Fase 8 / tarefa 05 / ajuste 02 — configuração isolada do CKEditor do Blog.
# Apenas contratos JS/DOM/CKEDITOR simulados; não inicia backend nem acessa banco.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---dom}"
NODE_BIN="${NODE_BIN:-node}"
MIRROR="${MIRROR:-$ROOT/dev}"
SOURCE="${ADMIN_JS_SOURCE:-$MIRROR/admin/view/js/admin.js}"
RUNNER="$ROOT/tools/e2e/e2e_f805_a02_ckeditor_dom.cjs"
A01="$ROOT/tools/e2e/e2e_f805_a01_blog_media.sh"

help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f805_a02_ckeditor.sh [--dom|--regression|--help]

--dom         Padrão. Sintaxe + 18 casos sobre o admin.js real, com DOM e
              CKEDITOR simulados. Não executa o core/iframe do editor.
--regression  Executa --dom e a regressão A01 --dom (10 casos), já presente
              no projeto. Não executa backend/DB nem instala dependências.
--help        Esta ajuda.

Requer Node.js 20+ e Bash. Não usa PHP, navegador, rede, npm ou Composer.
Variáveis opcionais: NODE_BIN, MIRROR (pasta dev), ADMIN_JS_SOURCE.
A01 também aceita BLOG_MEDIA_SOURCE conforme sua própria documentação.
O modo --backend NÃO existe neste wrapper: é recusado com código 2.

O runner CJS aceita caminho/admin.js, '-' (JS por stdin) ou --stdin-json
com {"admin":"...","legacyConfig":"..."} para controles negativos locais.
Sem o vendor no ZIP, usa um recorte da falha legada com $; no projeto,
lê o config.js existente para a mesma simulação de ordem de carregamento.

Veja docs/F8-05-A02-Configuracao-Isolada-CKEditor.md para limites,
aplicação/rollback e homologação em navegador na Validação 03.
HELP
}
fail() { printf 'ERRO: %s\n' "$*" >&2; exit 2; }

if [[ "$#" -gt 1 ]]; then help >&2; exit 2; fi
case "$MODE" in
  --help|-h) help; exit 0 ;;
  --dom|--regression) ;;
  *) help >&2; exit 2 ;;
esac

command -v "$NODE_BIN" >/dev/null 2>&1 || fail 'Node.js não encontrado. Requer Node.js 20+.'
"$NODE_BIN" -e 'if (Number(process.versions.node.split(".")[0]) < 20) { console.error("Requer Node.js 20+."); process.exit(2); }'
[[ -f "$SOURCE" ]] || fail "JavaScript não encontrado: $SOURCE"
[[ -f "$RUNNER" ]] || fail "Runner não encontrado: $RUNNER"
if [[ "$MODE" == '--regression' ]]; then
  [[ -f "$A01" ]] || fail 'Tool A01 ausente: aplique/atualize a base c2df689 antes desta entrega.'
fi

printf '%s\n' '══ F8-05 A02 — sintaxe e regressão de contrato simulada ══'
"$NODE_BIN" --check "$SOURCE"
"$NODE_BIN" --check "$RUNNER"
"$NODE_BIN" "$RUNNER" "$SOURCE"

if [[ "$MODE" == '--regression' ]]; then
  printf '%s\n' '══ F8-05 A01 — regressão DOM simulada da biblioteca de capas ══'
  NODE_BIN="$NODE_BIN" MIRROR="$MIRROR" bash "$A01" --dom
fi
printf '%s\n' 'Sem navegador/core CKEditor real, HTTP ou banco. Validação integrada 03 permanece pendente.'
