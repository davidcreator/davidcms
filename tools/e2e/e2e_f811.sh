#!/usr/bin/env bash
# Fase 8 / Tarefa 11 — idiomas; padrão local, sem defaults/idiomas reais alterados.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---check}"
PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
CHECK_MIRROR="${MIRROR:-$ROOT/dev}"
PHP_TEST="$ROOT/tools/e2e/e2e_f811_contracts.php"
HTTP_TEST="$ROOT/tools/e2e/e2e_f811_http.cjs"
help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f811.sh [--check|--http|--help]

--check  Padrão. Lint6PHP +40 contratos PHP/Twig reais, DB/serviços/IO sessão
         em fixtures e diretórios privados não servidos. 8 auxiliares HTTP
         novos+16 compartilhados em simulação. Sem idioma/default/servidor/DB
         ou navegador reais alterados.
--http   Após --check, somente laboratório: lê padrões/seletores/idiomas e
         modifica SOMENTE sort_order de um idioma ativo NÃO-PADRÃO informado
         pelo operador; revalida todos os campos e RESTAURA a ordem original.
         Nunca POST de padrões, troca code/locale/status, cria idioma/pasta,
         desativa idioma ou usa SQL/reset. Não é teste visual de navegador.
--help   Ajuda. --backend/--browser/--fixture e suíte antiga não são chamados.

Requer PHP8.1+/mbstring/Twig3 do projeto, Node20+ fetch/Headers.getSetCookie,
Bash e helpers F8-05/F8-06 da base. Twig padrão em
 dev/system/storage/vendor/autoload.php; TWIG_AUTOLOAD absoluto é alternativa
 local explícita de QA. Não instala pacotes ou inicializa o framework.

--http requer explicitamente:
 ALLOW_F811_HTTP=YES_WRITE_REAMUR_TEST
 B=http://127.0.0.1:8090/davidcms/dev
 FUI=/caminho/absoluto/copia/servida/dev
 MIRROR=/caminho/absoluto/repositorio/dev
 F811_USERNAME/F811_PASSWORD fictícios com access/modify localization/language
 F811_LANGUAGE_ID e F811_LANGUAGE_CODE reais obtidos da lista, NÃO defaults
 (en-us também é recusado); alvo ativo/diretório presente, sort0..99999.
 FUI/.f805-disposable contém reamur_test; configs literais, DB/hostname loopback,
 URLs/fontes/assets CMS/idiomas coerentes MIRROR/FUI, sem symlinks.
 Opcional F811_REPORT=/tmp/novo-resultado-f811.json (novo e absoluto,0600).

Probe identifica FUI antes do login, cookies/tokens só em memória. Falha após
POST pode deixar ordem pendente: não repetir/forçar restauração sem inspeção.
Sem produção/dados reais/túnel/proxy produção/concorrência. Não altera defaults
automaticamente. Veja docs/F8-11-Idiomas-no-Painel.md e siga antes de --http.
HELP
}
fail() { printf 'ERRO: %s\n' "$*" >&2; exit 2; }
[[ "$#" -le 1 ]] || { help >&2; exit 2; }
case "$MODE" in --help|-h) help; exit 0;; --check|--http) ;; *) help >&2; exit 2;; esac
command -v "$PHP_BIN" >/dev/null 2>&1 || fail 'PHP CLI não encontrado.'
command -v "$NODE_BIN" >/dev/null 2>&1 || fail 'Node não encontrado.'
"$PHP_BIN" -r 'if(PHP_VERSION_ID<80100||!extension_loaded("mbstring")){fwrite(STDERR,"Requer PHP8.1+/mbstring.\n");exit(2);}'
"$NODE_BIN" -e 'if(Number(process.versions.node.split(".")[0])<20)process.exit(2);'
if [[ "$MODE" == '--http' ]]; then
  [[ "${ALLOW_F811_HTTP:-}" == 'YES_WRITE_REAMUR_TEST' ]] || fail 'HTTP bloqueado: confirmação explícita ausente.'
  [[ -n "${B:-}" && -n "${FUI:-}" && -n "${MIRROR:-}" && -n "${F811_USERNAME:-}" && -n "${F811_PASSWORD:-}" && -n "${F811_LANGUAGE_ID:-}" && -n "${F811_LANGUAGE_CODE:-}" ]] || fail 'HTTP exige laboratório, credenciais e alvo não-padrão explícitos.'
fi
for p in "$PHP_TEST" "$HTTP_TEST" "$ROOT/tools/e2e/e2e_f806_http.cjs" "$ROOT/tools/e2e/e2e_f805.cjs" "$ROOT/tools/e2e/e2e_f805_browser.js"; do [[ -f "$p" ]] || fail "Tool/dependência ausente: $p"; done
for p in admin/controller/localization/language.php admin/model/localization/language.php admin/language/en-us/localization/language.php admin/language/pt-br/localization/language.php admin/language/es-eu/localization/language.php; do "$PHP_BIN" -l "$CHECK_MIRROR/$p"; done
"$PHP_BIN" -l "$PHP_TEST"
"$NODE_BIN" --check "$HTTP_TEST"
[[ ! -f "$ROOT/tools/e2e/e2e_f402.sh" ]] || bash -n "$ROOT/tools/e2e/e2e_f402.sh"
"$PHP_BIN" "$PHP_TEST" "$CHECK_MIRROR"
"$NODE_BIN" "$HTTP_TEST" --self-test
if [[ "$MODE" == '--http' ]]; then "$NODE_BIN" "$HTTP_TEST" --http; else printf '%s\n' 'Checks locais concluídos; sem idioma/default/DB/HTTP/navegador reais alterados.'; fi
