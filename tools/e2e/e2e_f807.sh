#!/usr/bin/env bash
# Fase 8 / Tarefa 07 — Categorias do portfólio; padrão seguro, sem servidor/banco.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---check}"
PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
CHECK_MIRROR="${MIRROR:-$ROOT/dev}"
PHP_TEST="$ROOT/tools/e2e/e2e_f807_contracts.php"
DOM_TEST="$ROOT/tools/e2e/e2e_f807_dom.cjs"
HTTP_TEST="$ROOT/tools/e2e/e2e_f807_http.cjs"
help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f807.sh [--check|--http|--help]

--check  Padrão. PHP lint + 40 contratos PHP/Twig reais com DB/serviços/IO de
         sessão em fixtures e MediaUpload em FS privado; 9 casos DOM simulados,
         9 auxiliares HTTP novos e 16 compartilhados F8-06. Fetch/respostas
         simulados; sem servidor, banco, navegador ou upload reais.
--http   Executa --check e depois fluxo HTTP real opt-in no laboratório:
         login único, i18n, CSRF, PNG2x2, duas categorias próprias,
         filtros/SEO, anti-ciclo, reabrir, delete e filho virando raiz.
         Não reinstala, não executa SQL direto nem recria banco.
         Não é teste de navegador/validação física/preview/contraste.
--help   Esta ajuda. Não existe --backend e nenhuma suíte destrutiva é chamada.

Requisitos: PHP 8.1+ com mbstring e Twig 3 do Composer do projeto; Node 20+ com fetch/Headers.getSetCookie;
Bash. Sem instalação automática. Os tools F8-05/F8-06 da base devem estar presentes.
O Twig é carregado de dev/system/storage/vendor/autoload.php; alternativamente
TWIG_AUTOLOAD pode apontar explicitamente a um autoload local de teste.

Para --http, definir explicitamente:
 ALLOW_F807_HTTP=YES_WRITE_REAMUR_TEST
 B=http://127.0.0.1:8090/davidcms/dev
 FUI=/caminho/absoluto/copia/servida/dev
 MIRROR=/caminho/absoluto/repositorio/dev
 F807_USERNAME e F807_PASSWORD de conta FICTÍCIA com modify catalog/category
 FUI/.f805-disposable com reamur_test (guarda de laboratório reutilizada)
 Os configs admin/catalog devem ser literais, apontando a reamur_test em
 loopback e HTTP_SERVER correspondente; fontes/arquivos CMS e categorias devem coincidir.
 Opcional: F807_REPORT=/tmp/novo-resultado-f807.json (novo, absoluto).

O HTTP cria um probe temporário de identidade no FUI e duas categorias próprias
mais PNG. Remove só seus registros/PNG/probe se passar. Em falha, preserva
artefatos para inspeção, sem retry de
POST/ID presumido/limpeza ampla. Nunca usar produção/proxy/túnel para produção.

Veja docs/F8-07-Categorias-Portfolio.md.
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
  [[ "${ALLOW_F807_HTTP:-}" == 'YES_WRITE_REAMUR_TEST' ]] || fail 'HTTP bloqueado: confirmação explícita ausente.'
  [[ -n "${B:-}" && -n "${FUI:-}" && -n "${MIRROR:-}" && -n "${F807_USERNAME:-}" && -n "${F807_PASSWORD:-}" ]] || fail 'HTTP exige B, FUI, MIRROR e credenciais fictícias explícitas.'
fi
for p in "$PHP_TEST" "$DOM_TEST" "$HTTP_TEST" "$ROOT/tools/e2e/e2e_f806_http.cjs" "$ROOT/tools/e2e/e2e_f805.cjs" "$ROOT/tools/e2e/e2e_f805_browser.js"; do [[ -f "$p" ]] || fail "Tool/dependência da base ausente: $p"; done
for p in admin/controller/catalog/category.php admin/model/catalog/category.php admin/language/en-us/catalog/category.php admin/language/pt-br/catalog/category.php admin/language/es-eu/catalog/category.php; do "$PHP_BIN" -l "$CHECK_MIRROR/$p"; done
"$PHP_BIN" -l "$PHP_TEST"
for p in "$DOM_TEST" "$HTTP_TEST" "$CHECK_MIRROR/admin/view/js/catalog-category.js"; do "$NODE_BIN" --check "$p"; done
for p in "$ROOT/dev/tests/e2e/e2e_regressao.sh"; do [[ ! -f "$p" ]] || bash -n "$p"; done
"$PHP_BIN" "$PHP_TEST" "$CHECK_MIRROR"
"$NODE_BIN" "$DOM_TEST" "$CHECK_MIRROR/admin/view/js/catalog-category.js"
"$NODE_BIN" "$HTTP_TEST" --self-test
if [[ "$MODE" == '--http' ]]; then
  "$NODE_BIN" "$HTTP_TEST" --http
else
  printf '%s\n' 'Verificações locais concluídas. HTTP/DB, navegador e aceite visual reais NÃO executados por --check.'
fi
