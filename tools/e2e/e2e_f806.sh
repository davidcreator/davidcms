#!/usr/bin/env bash
# Fase 8 / Tarefa 06 — Páginas CMS; padrão seguro, sem servidor/banco.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---check}"
PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
CHECK_MIRROR="${MIRROR:-$ROOT/dev}"
PHP_TEST="$ROOT/tools/e2e/e2e_f806_contracts.php"
DOM_TEST="$ROOT/tools/e2e/e2e_f806_dom.cjs"
HTTP_TEST="$ROOT/tools/e2e/e2e_f806_http.cjs"
help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f806.sh [--check|--http|--help]

--check  Padrão. PHP lint + 30 contratos PHP/Twig reais com DB/serviços/IO de
         sessão em fixtures; 6 casos DOM/Bootstrap simulados e 16 casos dos
         helpers HTTP com fetch simulado. Sem HTTP/banco/navegador reais.
--http   Executa --check e depois fluxo HTTP real opt-in no laboratório:
         login único, i18n, CSRF, criar/editar/reabrir, filtros e delete da
         página própria. Não reinstala, não executa SQL direto nem recria banco.
         Não é teste de navegador/validação física/contraste.
--help   Esta ajuda. Não existe --backend e nenhuma suíte destrutiva é chamada.

Requisitos: PHP 8.1+ com mbstring e Twig 3 do Composer do projeto; Node 20+ com fetch/Headers.getSetCookie;
Bash. Sem instalação automática. O Tool F8-05 da base deve estar presente.
O Twig é carregado de dev/system/storage/vendor/autoload.php; alternativamente
TWIG_AUTOLOAD pode apontar explicitamente a um autoload local de teste.

Para --http, definir explicitamente:
 ALLOW_F806_HTTP=YES_WRITE_REAMUR_TEST
 B=http://127.0.0.1:8090/davidcms/dev
 FUI=/caminho/absoluto/copia/servida/dev
 MIRROR=/caminho/absoluto/repositorio/dev
 F806_USERNAME e F806_PASSWORD de conta FICTÍCIA com modify cms/page
 FUI/.f805-disposable com reamur_test (guarda de laboratório reutilizada)
 Os configs admin/catalog devem ser literais, apontando a reamur_test em
 loopback e HTTP_SERVER correspondente; fontes/arquivos CMS devem coincidir.
 Opcional: F806_REPORT=/tmp/novo-resultado-f806.json (novo, absoluto).

O HTTP cria um probe temporário de identidade no FUI e uma página própria;
remove-os se passar. Em falha, preserva página para inspeção, sem retry de
POST/ID presumido/limpeza ampla. Nunca usar produção/proxy/túnel para produção.

Veja docs/F8-06-Paginas-CMS.md.
HELP
}
fail() { printf 'ERRO: %s\n' "$*" >&2; exit 2; }
[[ "$#" -le 1 ]] || { help >&2; exit 2; }
case "$MODE" in --help|-h) help; exit 0;; --check|--http) ;; *) help >&2; exit 2;; esac
command -v "$PHP_BIN" >/dev/null 2>&1 || fail 'PHP CLI não encontrado.'
command -v "$NODE_BIN" >/dev/null 2>&1 || fail 'Node não encontrado.'
"$PHP_BIN" -r 'if (PHP_VERSION_ID < 80100 || !extension_loaded("mbstring")) { fwrite(STDERR,"Requer PHP 8.1+ e mbstring.\n"); exit(2); }'
"$NODE_BIN" -e 'if (Number(process.versions.node.split(".")[0]) < 20) process.exit(2);'
if [[ "$MODE" == '--http' ]]; then
  [[ "${ALLOW_F806_HTTP:-}" == 'YES_WRITE_REAMUR_TEST' ]] || fail 'HTTP bloqueado: confirmação explícita ausente.'
  [[ -n "${B:-}" && -n "${FUI:-}" && -n "${MIRROR:-}" && -n "${F806_USERNAME:-}" && -n "${F806_PASSWORD:-}" ]] || fail 'HTTP exige B, FUI, MIRROR e credenciais fictícias explícitas.'
fi
for p in "$PHP_TEST" "$DOM_TEST" "$HTTP_TEST" "$ROOT/tools/e2e/e2e_f805.cjs" "$ROOT/tools/e2e/e2e_f805_browser.js"; do [[ -f "$p" ]] || fail "Tool/dependência da base ausente: $p"; done
for p in admin/controller/cms/page.php admin/model/cms/page.php admin/language/en-us/cms/page.php admin/language/pt-br/cms/page.php admin/language/es-eu/cms/page.php; do "$PHP_BIN" -l "$CHECK_MIRROR/$p"; done
"$PHP_BIN" -l "$PHP_TEST"
for p in "$DOM_TEST" "$HTTP_TEST" "$CHECK_MIRROR/admin/view/js/cms-page.js"; do "$NODE_BIN" --check "$p"; done
for p in "$ROOT/dev/tests/e2e/e2e_regressao.sh" "$ROOT/tools/e2e/e2e_f701.sh"; do [[ ! -f "$p" ]] || bash -n "$p"; done
"$PHP_BIN" "$PHP_TEST" "$CHECK_MIRROR"
"$NODE_BIN" "$DOM_TEST" "$CHECK_MIRROR/admin/view/js/cms-page.js"
"$NODE_BIN" "$HTTP_TEST" --self-test
if [[ "$MODE" == '--http' ]]; then
  "$NODE_BIN" "$HTTP_TEST" --http
else
  printf '%s\n' 'Verificações locais concluídas. HTTP/DB, navegador e aceite visual reais NÃO executados por --check.'
fi
