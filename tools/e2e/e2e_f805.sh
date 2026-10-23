#!/usr/bin/env bash
# Fase 8 / Tarefa 05 — entrada única de validação, sem novo nível de ajuste.
# Padrão: somente verificações locais. Navegador/gravações são opt-in e assistidos.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---check}"
NODE_BIN="${NODE_BIN:-node}"
RUNNER="$ROOT/tools/e2e/e2e_f805.cjs"
BROWSER="$ROOT/tools/e2e/e2e_f805_browser.js"

help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f805.sh [--check|--self-test|--prepare-browser|--clean-browser|--help]

--check            Padrão. Sintaxe, 32 casos de guardas/modelos, contratos Twig
                   e os 28 testes DOM simulados já existentes (18 RTE + 10 mídia).
                   Não abre navegador, acessa HTTP/banco ou instala dependências.
--self-test        Só sintaxe e os 32 casos das guardas/modelos deste Tool.
--prepare-browser  Executa --check e confere laboratório local descartável.
                   Cria SOMENTE dois QA temporários no FUI e imprime dois
                   comandos para o Console do navegador JÁ autenticado.
                   O CLI NÃO executa o fluxo de navegador nem altera banco.
--clean-browser    Confere novamente o ambiente e remove só os dois arquivos QA
                   do F805_NONCE. Não apaga posts, imagens, configs ou banco.
--help             Esta ajuda. --backend/--browser NÃO são modos deste wrapper.

Requer Node.js 20+ e Bash; sem npm/Composer/Playwright. Testes históricos mantêm
seus caminhos por compatibilidade; não é necessário reaplicar os ZIPs anteriores.

Preparar/limpar EXIGE variáveis explícitas:
  ALLOW_F805_BROWSER=YES_WRITE_REAMUR_TEST
  B=http://127.0.0.1:8090/davidcms/dev  (raiz, HTTP(S) loopback)
  FUI=/caminho/absoluto/da/copia/servida/dev
  MIRROR=/caminho/absoluto/do/repositorio/dev
  FUI/.f805-disposable contendo: reamur_test
  configs admin/catalog: DB_DATABASE literal reamur_test, DB_HOSTNAME loopback,
  HTTP_SERVER correspondente a B; assets/Twig idênticos entre MIRROR e FUI.
  Ao limpar, também F805_NONCE=<nonce de 32 hex impresso na preparação>.

No navegador: o trecho faz upload, cria/edita/salva/reabre UM rascunho próprio,
confere CSRF/referência e exclui seus itens se passar. Em falha, preserva-os
para inspeção. A permissão dura 15 minutos; não usar em dados reais/produção.
Só o relatório produzido no navegador é evidência dessa execução real.

Veja docs/F8-05-Validacao-Integrada-Blog.md.
HELP
}
fail() { printf 'ERRO: %s\n' "$*" >&2; exit 2; }
if [[ "$#" -gt 1 ]]; then help >&2; exit 2; fi
case "$MODE" in
  --help|-h) help; exit 0 ;;
  --check|--self-test|--prepare-browser|--clean-browser) ;;
  *) help >&2; exit 2 ;;
esac
command -v "$NODE_BIN" >/dev/null 2>&1 || fail 'Node.js não encontrado. Requer Node.js 20+.'
"$NODE_BIN" -e 'if (Number(process.versions.node.split(".")[0]) < 20) process.exit(2);'
[[ -f "$RUNNER" && -f "$BROWSER" ]] || fail 'Arquivos do Tool F8-05 ausentes.'
if [[ "$MODE" == '--prepare-browser' || "$MODE" == '--clean-browser' ]]; then
  [[ "${ALLOW_F805_BROWSER:-}" == 'YES_WRITE_REAMUR_TEST' ]] || fail 'Preparação/limpeza bloqueada: confirmação explícita ausente.'
  [[ -n "${B:-}" && -n "${FUI:-}" && -n "${MIRROR:-}" ]] || fail 'Defina B, FUI e MIRROR explicitamente.'
fi
"$NODE_BIN" --check "$RUNNER"
"$NODE_BIN" --check "$BROWSER"
if [[ "$MODE" == '--clean-browser' ]]; then
  "$NODE_BIN" "$RUNNER" --clean-browser
  exit 0
fi
"$NODE_BIN" "$RUNNER" --self-test
if [[ "$MODE" == '--self-test' ]]; then exit 0; fi
CHECK_MIRROR="${MIRROR:-$ROOT/dev}"
MIRROR="$CHECK_MIRROR" "$NODE_BIN" "$RUNNER" --inspect
[[ -f "$ROOT/tools/e2e/e2e_f805_a02_ckeditor.sh" ]] || fail 'Tool histórico aplicado não encontrado na base do projeto.'
NODE_BIN="$NODE_BIN" MIRROR="$CHECK_MIRROR" \
  ADMIN_JS_SOURCE="$CHECK_MIRROR/admin/view/js/admin.js" \
  BLOG_MEDIA_SOURCE="$CHECK_MIRROR/admin/view/js/blog-media.js" \
  bash "$ROOT/tools/e2e/e2e_f805_a02_ckeditor.sh" --regression
if [[ "$MODE" == '--prepare-browser' ]]; then
  # Não exportar o MIRROR padrão acima: preparação exige escolha EXPLÍCITA.
  "$NODE_BIN" "$RUNNER" --prepare-browser
else
  printf '%s\n' 'PASS local não é homologação de navegador/servidor. Execute o fluxo assistido apenas no laboratório autorizado.'
fi
