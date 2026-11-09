#!/usr/bin/env bash
# ==============================================================================
# passo-1b-limpeza-dev.sh
# Limpeza controlada da Etapa 1B (codigo morto em dev/system/ e assets orfaos em dev/*/view/)
#
# Padrao: DRY-RUN (nao altera nada sem --aplicar).
# Nunca faz git commit nem git push automaticamente.
#
# Uso:
#   bash passo-1b-limpeza-dev.sh                        # dry-run (1B.1 + 1B.2)
#   bash passo-1b-limpeza-dev.sh --etapa 1b1 --aplicar  # remove PHP/config morto + ajusta phpstan-baseline.neon
#   bash passo-1b-limpeza-dev.sh --etapa 1b2 --aplicar  # remove assets JS/CSS/SCSS orfaos
#   bash passo-1b-limpeza-dev.sh --etapa tudo --aplicar # executa 1B.1 + 1B.2
# ==============================================================================
set -euo pipefail

ETAPA="tudo"
APLICAR=0

while [ "$#" -gt 0 ]; do
  case "$1" in
    --etapa)
      ETAPA="${2:-}"
      shift 2
      ;;
    --aplicar)
      APLICAR=1
      shift
      ;;
    --help|-h)
      sed -n '2,15p' "$0"
      exit 0
      ;;
    *)
      echo "Argumento desconhecido: $1 (use --help)" >&2
      exit 2
      ;;
  esac
done

case "$ETAPA" in
  1b1|1b2|tudo) ;;
  *) echo "Etapa invalida: '$ETAPA'. Use: 1b1 | 1b2 | tudo" >&2; exit 2 ;;
esac

[ -d .git ] || { echo "ERRO: execute na raiz do clone Git do davidcms." >&2; exit 1; }
[ -f dev/system/framework.php ] || { echo "ERRO: dev/system/framework.php nao encontrado; diretorio invalido." >&2; exit 1; }

# --- Etapa 1B.1: 39 arquivos PHP / config / XML sem uso ativo em dev/ ---
ARQS_1B1=(
  "dev/system/helper/db_schema.php"
  "dev/system/helper/admin_appearance.php"
  "dev/system/helper/bbcode.php"
  "dev/system/helper/config_loader.php"
  "dev/system/helper/seo.php"
  "dev/system/helper/slug.php"
  "dev/system/helper/utf8.php"
  "dev/system/helper/general.php"
  "dev/system/library/cart/cart.php"
  "dev/system/library/cart/currency.php"
  "dev/system/library/cart/customer.php"
  "dev/system/library/cart/length.php"
  "dev/system/library/cart/tax.php"
  "dev/system/library/cart/user.php"
  "dev/system/library/cart/weight.php"
  "dev/system/library/ai.php"
  "dev/system/library/address_lookup.php"
  "dev/system/library/encryption.php"
  "dev/system/library/db/pgsql.php"
  "dev/system/library/db/nosql.php"
  "dev/system/library/security/upload_security.php"
  "dev/system/library/template.php"
  "dev/system/library/template/blade.php"
  "dev/system/library/template/hbs.php"
  "dev/system/library/template/latte.php"
  "dev/system/library/template/mustache.php"
  "dev/system/library/template/php.php"
  "dev/system/library/template/phtml.php"
  "dev/system/library/template/template.php"
  "dev/system/library/template/tpl.php"
  "dev/system/library/template/twig_cache.php"
  "dev/system/engine/actionhook.php"
  "dev/system/engine/webhook.php"
  "dev/system/config/googleshopping/googleshopping.php"
  "dev/system/config/api.php"
  "dev/system/config/api/schemas/order_form.php"
  "dev/system/config/api/schemas/product_form.php"
  "dev/system/config/api/settings/ip_blocked.php"
  "dev/system/modification.xml"
)

# --- Etapa 1B.2: diretorios e arquivos de assets frontend orfaos (0 referencias) ---
ALVOS_1B2=(
  "dev/admin/view/js/jquery/jquery-ui"
  "dev/admin/view/js/jquery/flot"
  "dev/admin/view/js/jquery/magnific"
  "dev/catalog/view/js/jquery/magnific"
  "dev/admin/view/js/codemirror"
  "dev/admin/view/js/chat"
  "dev/admin/view/css/chat"
  "dev/catalog/view/js/chat"
  "dev/catalog/view/css/chat"
  "dev/admin/view/css/scss"
  "dev/catalog/view/css/scss"
  "dev/admin/view/js/modal-fix.js"
  "dev/catalog/view/js/modal-fix.js"
  "dev/catalog/view/css/mooc.css"
  "dev/catalog/view/css/cms_extensions.css"
  "dev/catalog/view/css/blog.css"
  "dev/catalog/view/css/enhanced-upload.css"
  "dev/catalog/view/js/enhanced-upload.js"
  "dev/catalog/view/js/blog-interact.js"
  "dev/admin/view/css/bootstrap.scss"
  "dev/catalog/view/css/bootstrap.scss"
  "dev/catalog/view/css/reamurcms.scss"
)

collect_tracked() {
  local p
  for p in "$@"; do
    if [ -d "$p" ]; then
      git ls-files "$p/"
    elif git ls-files --error-unmatch "$p" >/dev/null 2>&1; then
      printf '%s\n' "$p"
    fi
  done
}

run_removal() {
  local label="$1"
  shift
  mapfile -t presentes < <(collect_tracked "$@")
  local bytes=0
  local f
  for f in "${presentes[@]}"; do
    [ -f "$f" ] && bytes=$((bytes + $(wc -c < "$f")))
  done

  echo "== $label: ${#presentes[@]} arquivo(s) versionado(s) ($bytes bytes) =="
  if [ "${#presentes[@]}" -eq 0 ]; then
    echo "  Nada pendente nesta etapa."
    return 0
  fi

  local preview_limit=25
  local idx=0
  for f in "${presentes[@]}"; do
    if [ "$idx" -lt "$preview_limit" ]; then
      echo "  - $f"
    fi
    idx=$((idx + 1))
  done
  if [ "${#presentes[@]}" -gt "$preview_limit" ]; then
    echo "  ... e mais $((${#presentes[@]} - preview_limit)) arquivo(s)."
  fi

  if [ "$APLICAR" -eq 1 ]; then
    printf '%s\0' "${presentes[@]}" | xargs -0 git rm -q --
    echo "  [OK] Removidos do indice e do disco (staged para commit)."
  else
    echo "  [DRY-RUN] Rode com --aplicar para executar git rm."
  fi
}

limpar_phpstan_baseline() {
  local baseline="dev/system/storage/phpstan-baseline.neon"
  [ -f "$baseline" ] || return 0

  echo "== Ajuste do $baseline (remover entradas dos arquivos excluidos em 1B.1) =="
  python3 - "$baseline" "$APLICAR" "${ARQS_1B1[@]}" <<'PY'
import re, sys
from pathlib import Path

baseline_path = Path(sys.argv[1])
aplicar = sys.argv[2] == '1'
removed_rel = {
    '../' + p[len('dev/system/'):]
    for p in sys.argv[3:]
    if p.startswith('dev/system/')
}

text = baseline_path.read_text(encoding='utf-8')
lines = text.splitlines(keepends=True)

header = []
blocks = []
cur = []
in_errors = False

for line in lines:
    if not in_errors:
        header.append(line)
        if line.strip() == 'ignoreErrors:':
            in_errors = True
        continue
    if line == '\t\t-\n' or line == '\t\t-\r\n':
        if cur:
            blocks.append(''.join(cur))
        cur = [line]
    else:
        if cur:
            cur.append(line)
        elif line.strip() == '':
            continue
        else:
            header.append(line)

if cur:
    blocks.append(''.join(cur))

kept = []
removed_count = 0
for blk in blocks:
    m = re.search(r'^\s*path:\s*(\S+)\s*$', blk, re.M)
    if m and m.group(1) in removed_rel:
        removed_count += 1
    else:
        kept.append(blk.strip('\r\n'))

print(f'  Blocos no baseline: {len(blocks)} total -> removidos: {removed_count}, mantidos: {len(kept)}')
if aplicar and removed_count > 0:
    body = '\n\n'.join(kept) + ('\n' if kept else '')
    baseline_path.write_text(''.join(header) + body, encoding='utf-8')
PY

  if [ "$APLICAR" -eq 1 ]; then
    git add "$baseline"
    echo "  [OK] $baseline atualizado (staged)."
  fi
}

if [ "$ETAPA" = "1b1" ] || [ "$ETAPA" = "tudo" ]; then
  run_removal "Etapa 1B.1 (PHP / Config / XML legados em dev/system/)" "${ARQS_1B1[@]}"
  limpar_phpstan_baseline
fi

if [ "$ETAPA" = "1b2" ] || [ "$ETAPA" = "tudo" ]; then
  run_removal "Etapa 1B.2 (Assets Frontend orfaos em dev/*/view/)" "${ALVOS_1B2[@]}"
fi

if [ "$APLICAR" -eq 1 ]; then
  echo
  echo "== Conferencias locais pos-remocao =="
  if command -v php >/dev/null 2>&1; then
    echo "  Rodando php -l nos arquivos PHP restantes em dev/..."
    find dev -type f -name '*.php' \
      -not -path '*/storage/vendor/*' \
      -not -path '*/storage/node_modules/*' -print0 \
      | xargs -0 -r -n1 -P4 php -l -d display_errors=0 >/dev/null
    echo "  [OK] php -l: zero erros de sintaxe."
  fi
  if [ -d dev/system/storage/vendor ] && command -v composer >/dev/null 2>&1; then
    echo "  Rodando composer analyse (PHPStan nivel 2)..."
    composer --working-dir=dev/system/storage analyse
  else
    echo "  [INFO] vendor/ nao instalado localmente; rode 'composer --working-dir=dev/system/storage install && composer --working-dir=dev/system/storage analyse' se quiser validar PHPStan antes do push."
  fi
  [ -f tools/verify-checksums.sh ] && bash tools/verify-checksums.sh || true
  [ -f tools/verify-deps-layout.sh ] && bash tools/verify-deps-layout.sh || true
  echo
  echo "Proximo passo sugerido: revise 'git status' e 'git diff --cached', depois faca seu commit."
else
  echo
  echo "Modo DRY-RUN finalizado. Nenhuma alteracao foi gravada."
fi
