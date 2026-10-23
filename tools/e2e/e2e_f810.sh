#!/usr/bin/env bash
# Fase 8 / Tarefa 10 — inbox; padrão local seguro, sem app/DB/e-mail reais.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---check}"
PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
CHECK_MIRROR="${MIRROR:-$ROOT/dev}"
PHP_TEST="$ROOT/tools/e2e/e2e_f810_contracts.php"
HTTP_TEST="$ROOT/tools/e2e/e2e_f810_http.cjs"
help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f810.sh [--check|--fixture|--http|--help]

--check    Padrão. Lint6PHP +40 contratos PHP/Twig reais, DB/serviços/IO de
           sessão simulados; 8 auxiliares HTTP novos+16 compartilhados com
           respostas simuladas. Sem app/banco/navegador/e-mail reais.
--fixture  Gera SOMENTE JSON com dados fictícios/marker; nada é salvo/enviado.
           Preparar uma mensagem no laboratório com captura/offline de e-mail
           e sem integrações produtivas, conforme guia. Requer só Node.
--http     Após --check, opera UMA fixture já preparada pelo operador:
           valida marker/nome/e-mail/assunto/ID antes de ler; depois corpo
           exato antes de POST. Ler via POST protegido, GET puro, status,
           CSRF/filtros/delete somente dessa mensagem. Sem enviar formulário
           público/e-mail/mailto/SQL, seed/reset ou consumir mensagens reais.
--help     Ajuda. --backend/--browser/suítes destrutivas não são chamados.

Requisitos: PHP8.1+/mbstring/Twig3 do projeto (autoload padrão em
 dev/system/storage/vendor/autoload.php ou TWIG_AUTOLOAD explícito),
 Node20+ com fetch/Headers.getSetCookie, Bash e helpers F8-05/F8-06 na base.
 Não instala pacotes nem carrega config/framework do CMS nos testes locais.

--http requer explicitamente:
 ALLOW_F810_HTTP=YES_WRITE_REAMUR_TEST
 B=http://127.0.0.1:8090/davidcms/dev
 FUI=/caminho/absoluto/copia/servida/dev
 MIRROR=/caminho/absoluto/repositorio/dev
 F810_USERNAME/F810_PASSWORD fictícios com access/modify communication/contact_message
 F810_MARKER=f810-<16hex> gerado por --fixture
 F810_MESSAGE_ID=ID_REAL_DA_FIXTURE, obtido da lista; nunca presumir1
 FUI/.f805-disposable contendo reamur_test; configs literais/loopback/URL coerentes,
 fontes/assets CMS/inbox iguais MIRROR/FUI, sem symlinks.
 Opcional: F810_REPORT=/tmp/novo-resultado-f810.json (novo e absoluto,0600).

Conta/fixture reais ou sem prova de identidade bloqueiam o fluxo. Cookies/tokens
só em memória. Relatório não contém corpo/nome/e-mail/telefone/IP/mailto. Em falha
preserva a fixture, não repete POST/não adivinha IDs/não limpa amplamente.
Proibido produção/dados reais/túnel/proxy produção. Leia docs/F8-10-Mensagens-Contato.md.
HELP
}
fail() { printf 'ERRO: %s\n' "$*" >&2; exit 2; }
[[ "$#" -le 1 ]] || { help >&2; exit 2; }
case "$MODE" in --help|-h) help; exit 0;; --check|--fixture|--http) ;; *) help >&2; exit 2;; esac
command -v "$NODE_BIN" >/dev/null 2>&1 || fail 'Node não encontrado.'
"$NODE_BIN" -e 'if (Number(process.versions.node.split(".")[0]) < 20) process.exit(2);'
for p in "$HTTP_TEST" "$ROOT/tools/e2e/e2e_f806_http.cjs" "$ROOT/tools/e2e/e2e_f805.cjs" "$ROOT/tools/e2e/e2e_f805_browser.js"; do [[ -f "$p" ]] || fail "Tool/dependência da base ausente: $p"; done
if [[ "$MODE" == '--fixture' ]]; then "$NODE_BIN" "$HTTP_TEST" --fixture; exit 0; fi
command -v "$PHP_BIN" >/dev/null 2>&1 || fail 'PHP CLI não encontrado.'
"$PHP_BIN" -r 'if(PHP_VERSION_ID<80100||!extension_loaded("mbstring")){fwrite(STDERR,"Requer PHP8.1+/mbstring.\n");exit(2);}'
if [[ "$MODE" == '--http' ]]; then
  [[ "${ALLOW_F810_HTTP:-}" == 'YES_WRITE_REAMUR_TEST' ]] || fail 'HTTP bloqueado: confirmação explícita ausente.'
  [[ -n "${B:-}" && -n "${FUI:-}" && -n "${MIRROR:-}" && -n "${F810_USERNAME:-}" && -n "${F810_PASSWORD:-}" && -n "${F810_MARKER:-}" && -n "${F810_MESSAGE_ID:-}" ]] || fail 'HTTP exige laboratório/credenciais/marker/ID próprios explícitos.'
fi
for p in admin/controller/communication/contact_message.php admin/model/communication/contact_message.php admin/language/en-us/communication/contact_message.php admin/language/pt-br/communication/contact_message.php admin/language/es-eu/communication/contact_message.php; do "$PHP_BIN" -l "$CHECK_MIRROR/$p"; done
"$PHP_BIN" -l "$PHP_TEST"
"$NODE_BIN" --check "$HTTP_TEST"
[[ ! -f "$ROOT/dev/tests/e2e/e2e_f215.sh" ]] || bash -n "$ROOT/dev/tests/e2e/e2e_f215.sh"
"$PHP_BIN" "$PHP_TEST" "$CHECK_MIRROR"
"$NODE_BIN" "$HTTP_TEST" --self-test
if [[ "$MODE" == '--http' ]]; then "$NODE_BIN" "$HTTP_TEST" --http; else printf '%s\n' 'Checks locais concluídos; não houve app/banco/navegador/mensagens/e-mails reais.'; fi
