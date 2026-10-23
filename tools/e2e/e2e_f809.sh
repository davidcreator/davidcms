#!/usr/bin/env bash
# Fase 8 / Tarefa 09 — Ferramentas conectadas; padrão seguro, sem servidor/banco.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---check}"
PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
CHECK_MIRROR="${MIRROR:-$ROOT/dev}"
PHP_TEST="$ROOT/tools/e2e/e2e_f809_contracts.php"
DOM_TEST="$ROOT/tools/e2e/e2e_f809_dom.cjs"
HTTP_TEST="$ROOT/tools/e2e/e2e_f809_http.cjs"
help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f809.sh [--check|--http|--help]

--check  Padrão. Lint +36 contratos PHP/Twig reais, DB/serviços/IO de sessão
         simulados, MediaUpload em FS privado. DOM/upload:9 casos simulados;
         HTTP:8 novos+25 compartilhados com fetch/respostas simulados.
         Sem servidor/banco/navegador/upload reais ou URL externa aberta.
--http   Após --check, fluxo HTTP real opt-in: uma ferramenta e PNG2x2 próprios,
         seis campos, fornecedor/URL/status/ordenação, CSRF, filtros e delete.
         Não implementa/abre ferramenta externa, não usa SQL/reset/seed.
         Não é teste de navegador/preview/foco/contraste.
--help   Esta ajuda; --backend/--browser/suítes destrutivas não são chamados.

Requisitos: PHP8.1+/mbstring/Twig3 do Composer do projeto, Node20+ com fetch,
FormData/Blob/Headers.getSetCookie e Bash. Helpers F8-05/F8-06/F8-07 já na base.
Twig padrão: dev/system/storage/vendor/autoload.php. TWIG_AUTOLOAD pode apontar
explicitamente a outro autoload local de QA. Sem instalação automática.

--http requer explicitamente:
 ALLOW_F809_HTTP=YES_WRITE_REAMUR_TEST
 B=http://127.0.0.1:8090/davidcms/dev
 FUI=/caminho/absoluto/copia/servida/dev
 MIRROR=/caminho/absoluto/repositorio/dev
 F809_USERNAME/F809_PASSWORD fictícios com access/modify tool/tool
 FUI/.f805-disposable contendo reamur_test (preflight existente reutilizado)
 Configs literais admin/catalog com banco/hostname loopback/URL coerentes;
 fontes/assets CMS/ferramentas/MediaUpload iguais MIRROR/FUI, sem symlinks.
 Opcional: F809_REPORT=/tmp/novo-resultado-f809.json (novo e absoluto).

Probe identifica FUI antes de login. Cookies/tokens só em memória. IDs reais
descobertos por marcador/HTML. Em falha preservar artefatos; não repetir POST,
não adivinhar IDs/limpar amplamente. Proibido produção/dados reais/proxy/túnel.
Veja docs/F8-09-Ferramentas-Conectadas.md.
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
  [[ "${ALLOW_F809_HTTP:-}" == 'YES_WRITE_REAMUR_TEST' ]] || fail 'HTTP bloqueado: confirmação explícita ausente.'
  [[ -n "${B:-}" && -n "${FUI:-}" && -n "${MIRROR:-}" && -n "${F809_USERNAME:-}" && -n "${F809_PASSWORD:-}" ]] || fail 'HTTP exige B, FUI, MIRROR e credenciais fictícias explícitas.'
fi
for p in "$PHP_TEST" "$DOM_TEST" "$HTTP_TEST" "$ROOT/tools/e2e/e2e_f807_http.cjs" "$ROOT/tools/e2e/e2e_f806_http.cjs" "$ROOT/tools/e2e/e2e_f805.cjs" "$ROOT/tools/e2e/e2e_f805_browser.js"; do [[ -f "$p" ]] || fail "Tool/dependência da base ausente: $p"; done
for p in admin/controller/tool/tool.php admin/model/tool/tool.php admin/language/en-us/tool/tool.php admin/language/pt-br/tool/tool.php admin/language/es-eu/tool/tool.php; do "$PHP_BIN" -l "$CHECK_MIRROR/$p"; done
"$PHP_BIN" -l "$PHP_TEST"
for p in "$DOM_TEST" "$HTTP_TEST" "$CHECK_MIRROR/admin/view/js/connected-tools.js"; do "$NODE_BIN" --check "$p"; done
for p in "$ROOT/dev/tests/e2e/e2e_f213.sh"; do [[ ! -f "$p" ]] || bash -n "$p"; done
"$PHP_BIN" "$PHP_TEST" "$CHECK_MIRROR"
"$NODE_BIN" "$DOM_TEST" "$CHECK_MIRROR/admin/view/js/connected-tools.js"
"$NODE_BIN" "$HTTP_TEST" --self-test
if [[ "$MODE" == '--http' ]]; then
  "$NODE_BIN" "$HTTP_TEST" --http
else
  printf '%s\n' 'Verificações locais concluídas. HTTP/DB, navegador e aceite visual reais NÃO executados por --check.'
fi
