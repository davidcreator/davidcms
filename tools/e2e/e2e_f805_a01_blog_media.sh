#!/usr/bin/env bash
# Fase 8 / tarefa 05 / ajuste 01 — inicialização da biblioteca de mídia.
# --dom (padrão): regressão JS com DOM/HTTP/Bootstrap simulados; não usa banco/rede.
# --backend: regressão DOM + E2E HTTP/DB F8-05 existente, DESTRUTIVO e opt-in.
# Este arquivo não instala dependências nem substitui a homologação em navegador.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:---dom}"
NODE_BIN="${NODE_BIN:-node}"
MIRROR_EXPLICIT="${MIRROR:+1}"
MIRROR="${MIRROR:-$ROOT/dev}"
SOURCE="${BLOG_MEDIA_SOURCE:-$MIRROR/admin/view/js/blog-media.js}"
RUNNER="$ROOT/tools/e2e/e2e_f805_a01_blog_media_dom.cjs"

help() {
  cat <<'HELP'
Uso: bash tools/e2e/e2e_f805_a01_blog_media.sh [--dom|--backend|--help]

--dom      Padrão. Verifica sintaxe e executa 10 casos no JS real com DOM,
           HTTP e Bootstrap simulados. Sem PHP, banco, navegador ou rede.
--backend  Adicionalmente chama tools/e2e/e2e_f805_blog_media.sh existente.
           ATENÇÃO: esse teste recria reamur_test, reinstala o sandbox e
           remove configs/arquivos de teste. NUNCA usar em dados reais.

Requisito do modo DOM: Node.js 20+; sem npm/composer install.
Variáveis opcionais: NODE_BIN, MIRROR (pasta dev), BLOG_MEDIA_SOURCE.

O modo backend EXIGE:
  B, FUI, MIRROR, ALLOW_DESTRUCTIVE_F805=YES_RECREATE_REAMUR_TEST
  B deve ser HTTP(S) em localhost, 127.0.0.1 ou [::1].
  FUI deve apontar ao sandbox descartável já existente.
  FUI/.f805-a01-disposable deve conter uma linha exata: reamur_test
  O JS do MIRROR e o do FUI devem ser idênticos antes da execução.

Veja docs/F8-05-A01-Inicializacao-Biblioteca-Midia.md.
HELP
}

fail() { printf 'ERRO: %s\n' "$*" >&2; exit 2; }

if [[ "$#" -gt 1 ]]; then help >&2; exit 2; fi
case "$MODE" in
  --help|-h) help; exit 0 ;;
  --dom|--backend) ;;
  *) help >&2; exit 2 ;;
esac

command -v "$NODE_BIN" >/dev/null 2>&1 || fail 'Node.js não encontrado. Requer Node.js 20+.'
"$NODE_BIN" -e 'if (Number(process.versions.node.split(".")[0]) < 20) { console.error("Requer Node.js 20+."); process.exit(2); }'
[[ -f "$SOURCE" ]] || fail "JavaScript não encontrado: $SOURCE"
[[ -f "$RUNNER" ]] || fail "Runner não encontrado: $RUNNER"

if [[ "$MODE" == '--backend' ]]; then
  [[ "${ALLOW_DESTRUCTIVE_F805:-}" == 'YES_RECREATE_REAMUR_TEST' ]] || fail 'E2E destrutivo bloqueado: confirmação explícita ausente.'
  [[ -n "${B:-}" && -n "${FUI:-}" && "$MIRROR_EXPLICIT" == '1' ]] || fail 'Defina B, FUI e MIRROR para o sandbox descartável.'
  B="$B" "$NODE_BIN" -e '
    try {
      const url = new URL(process.env.B);
      if (!["http:", "https:"].includes(url.protocol)
          || !["localhost", "127.0.0.1", "[::1]"].includes(url.hostname)
          || url.username || url.password || url.search || url.hash) {
        throw new Error("destino não permitido");
      }
    } catch (error) { console.error("B deve ser uma URL HTTP(S) local, sem credenciais/query/hash."); process.exit(2); }
  '
  [[ "$FUI" == /* && "$FUI" != '/' && -d "$FUI" ]] || fail 'FUI precisa ser um caminho absoluto do sandbox.'
  [[ -f "$FUI/.f805-a01-disposable" ]] || fail 'Marcador de sandbox descartável ausente em FUI.'
  grep -qx 'reamur_test' "$FUI/.f805-a01-disposable" || fail 'Marcador inválido; esperado reamur_test.'
  [[ -f "$ROOT/tools/e2e/e2e_f805_blog_media.sh" ]] || fail 'E2E F8-05 de backend não encontrado no projeto.'
  [[ -f "$FUI/admin/view/js/blog-media.js" ]] || fail 'JS não encontrado na cópia servida pelo sandbox.'
  cmp -s "$SOURCE" "$FUI/admin/view/js/blog-media.js" || fail 'MIRROR/FUI têm versões diferentes do JS; sincronize somente no sandbox.'
fi

printf '%s\n' '══ F8-05 A01 — sintaxe e regressão DOM simulada ══'
"$NODE_BIN" --check "$SOURCE"
"$NODE_BIN" --check "$RUNNER"
"$NODE_BIN" "$RUNNER" "$SOURCE"

if [[ "$MODE" == '--backend' ]]; then
  printf '%s\n' '══ E2E HTTP/DB existente — sandbox explicitamente autorizado ══'
  B="$B" FUI="$FUI" MIRROR="$MIRROR" bash "$ROOT/tools/e2e/e2e_f805_blog_media.sh"
else
  printf '%s\n' 'E2E HTTP/DB não executado. Homologação no navegador permanece pendente.'
fi
