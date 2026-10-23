#!/usr/bin/env bash
# Fase 8 / Tarefa 08 — Projetos do portfólio; padrão seguro, sem servidor/banco.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---check}"
PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
CHECK_MIRROR="${MIRROR:-$ROOT/dev}"
PHP_TEST="$ROOT/tools/e2e/e2e_f808_contracts.php"
DOM_TEST="$ROOT/tools/e2e/e2e_f808_dom.cjs"
HTTP_TEST="$ROOT/tools/e2e/e2e_f808_http.cjs"
help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f808.sh [--check|--http|--help]

--check  Padrão. Lint PHP + 45 contratos PHP/Twig reais com DB/serviços/IO de
         sessão simulados e MediaUpload em FS privado. JS/DOM:12 casos;
         HTTP:10 novos+25 compartilhados, fetch/respostas em simulação.
         Sem servidor/banco/navegador/upload reais.
--http   Após --check, fluxo HTTP real opt-in: um projeto, categoria auxiliar
         própria e três PNG2x2; criar/editar/reabrir, N:N, capa/galeria/alt/ordem,
         flags/filtros/CSRF/delete e limpeza só de conteúdo próprio.
         Sem SQL direto/reset/reinstalação; não é teste de navegador/preview.
--help   Esta ajuda. --backend/--browser e suítes destrutivas não são chamados.

Requisitos: PHP8.1+/mbstring/Twig3 do Composer do projeto, Node20+ com fetch,
FormData/Blob/Headers.getSetCookie, Bash e os helpers F8-05/F8-06/F8-07 da base.
Twig padrão: dev/system/storage/vendor/autoload.php. TWIG_AUTOLOAD pode apontar
explicitamente a outro autoload de QA local. Nenhuma instalação automática.

--http requer explicitamente:
 ALLOW_F808_HTTP=YES_WRITE_REAMUR_TEST
 B=http://127.0.0.1:8090/davidcms/dev
 FUI=/caminho/absoluto/copia/servida/dev
 MIRROR=/caminho/absoluto/repositorio/dev
 F808_USERNAME/F808_PASSWORD fictícios, access/modify catalog/project E catalog/category
 FUI/.f805-disposable contendo reamur_test (preflight existente reutilizado)
 Configs literais admin/catalog, DB reamur_test/hostname loopback e URL coerente;
 fontes CMS/categorias/projetos/assets/MediaUpload iguais MIRROR/FUI, sem symlinks.
 Opcional: F808_REPORT=/tmp/novo-resultado-f808.json (novo e absoluto).

Probe identifica FUI antes de login. Cookies/tokens apenas em memória. Só IDs
reais descobertos por marcador/HTML. Em falha preserva artefatos; não repetir POST
nem limpar com wildcard/reset. Proibido produção/dados reais/túnel/proxy produção.

Veja docs/F8-08-Projetos-Portfolio.md.
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
  [[ "${ALLOW_F808_HTTP:-}" == 'YES_WRITE_REAMUR_TEST' ]] || fail 'HTTP bloqueado: confirmação explícita ausente.'
  [[ -n "${B:-}" && -n "${FUI:-}" && -n "${MIRROR:-}" && -n "${F808_USERNAME:-}" && -n "${F808_PASSWORD:-}" ]] || fail 'HTTP exige B, FUI, MIRROR e credenciais fictícias explícitas.'
fi
for p in "$PHP_TEST" "$DOM_TEST" "$HTTP_TEST" "$ROOT/tools/e2e/e2e_f807_http.cjs" "$ROOT/tools/e2e/e2e_f806_http.cjs" "$ROOT/tools/e2e/e2e_f805.cjs" "$ROOT/tools/e2e/e2e_f805_browser.js"; do [[ -f "$p" ]] || fail "Tool/dependência da base ausente: $p"; done
for p in admin/controller/catalog/project.php admin/model/catalog/project.php admin/language/en-us/catalog/project.php admin/language/pt-br/catalog/project.php admin/language/es-eu/catalog/project.php; do "$PHP_BIN" -l "$CHECK_MIRROR/$p"; done
"$PHP_BIN" -l "$PHP_TEST"
for p in "$DOM_TEST" "$HTTP_TEST" "$CHECK_MIRROR/admin/view/js/catalog-project.js"; do "$NODE_BIN" --check "$p"; done
for p in "$ROOT/dev/tests/e2e/e2e_regressao.sh"; do [[ ! -f "$p" ]] || bash -n "$p"; done
"$PHP_BIN" "$PHP_TEST" "$CHECK_MIRROR"
"$NODE_BIN" "$DOM_TEST" "$CHECK_MIRROR/admin/view/js/catalog-project.js"
"$NODE_BIN" "$HTTP_TEST" --self-test
if [[ "$MODE" == '--http' ]]; then
  "$NODE_BIN" "$HTTP_TEST" --http
else
  printf '%s\n' 'Verificações locais concluídas. HTTP/DB, navegador e aceite visual reais NÃO executados por --check.'
fi
