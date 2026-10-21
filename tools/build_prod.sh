#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# build_prod.sh — F6-04 (ADR 0039): gera a BUILD DE PRODUÇÃO em prod/
# a partir de dev/ — SOMENTE o runtime. Planejamento (agents-ia/, docs/,
# tools/, *.zip) NUNCA sai da raiz do repo, então sequer é candidato.
#
# Fica EM prod/ (relativo a dev/):
#   admin/ catalog/ extension/ image/ system/ .htaccess index.php robots.txt
#   system/storage/vendor/ (autoload runtime — composer já resolvido)
# Fica FORA (superfície/dev-only):
#   install/ (o wizard NÃO sobe — produção instala via SQL + config manual)
#   system/storage/{cache,logs,session,temp}/* (conteúdo volátil; guards
#     index.html são regrados depois)
#   system/storage/phpstan* e composer.{json,lock} (ferramentas de dev)
#   catalogs/admin config.php reais (credenciais — gerados NO destino a
#     partir dos *.EXEMPLO incluídos) e install.lock
#
# Configs de produção: a build deriva de dev/install/config.php.tpl dois
# exemplos PRONTOS para editar — prod/catalog/config.php.EXEMPLO e
# prod/admin/config.php.EXEMPLO — com caminhos dev/→prod/ já reescritos.
#
# Uso:   bash tools/build_prod.sh            # gera/atualiza prod/
# Saída: prod/  (artefato derivado — NÃO versionar; .gitignore cobre)
# ════════════════════════════════════════════════════════════════════
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"   # raiz do repo (onde moram dev/ e prod/)
SRC="$ROOT/dev"; DST="$ROOT/prod"
[ -f "$SRC/catalog/index.php" ] || { echo "dev/ inválido em $ROOT" >&2; exit 1; }

echo "== sync dev/ → prod/ (runtime only) =="
rsync -a --delete --delete-excluded \
  --exclude 'install/' \
  --exclude 'tests/' \
  --exclude 'catalog/config.php' \
  --exclude 'admin/config.php' \
  --exclude 'system/storage/install.lock' \
  --exclude 'system/storage/phpstan.neon' \
  --exclude 'system/storage/phpstan-baseline.neon' \
  --exclude 'system/storage/phpstan-bootstrap.php' \
  --exclude 'system/storage/composer.json' \
  --exclude 'system/storage/composer.lock' \
  --exclude 'system/storage/cache/*' \
  --exclude 'system/storage/logs/*' \
  --exclude 'system/storage/session/*' \
  --exclude 'system/storage/temp/*' \
  "$SRC/" "$DST/"

# guards: regravar index.html nos dirs voláteis esvaziados
for d in cache logs logs/ session temp cache/template; do :; done
for d in cache cache/template logs session temp; do
  mkdir -p "$DST/system/storage/$d"
  [ -f "$SRC/system/storage/$d/index.html" ] && cp "$SRC/system/storage/$d/index.html" "$DST/system/storage/$d/index.html"
done

echo "== exemplos de config de produção (dev/ → prod/) =="
TPL="$SRC/install/config.php.tpl"
[ -f "$TPL" ] || { echo "tpl do wizard ausente: $TPL" >&2; exit 1; }
for app in catalog admin; do
  OUT="$DST/$app/config.php.EXEMPLO"
  {
    echo "<?php"
    echo "// ═══ CONFIG DE PRODUÇÃO (${app}) — gerado por tools/build_prod.sh ═══"
    echo "// 1) Preencha TODOS os {{...}} abaixo para o ambiente de PRODUÇÃO"
    echo "//    (URLs com https://, credenciais do banco de produção)."
    echo "// 2) DIR_REAMUR = caminho absoluto, no servidor, da pasta onde você"
    echo "//    enviou o CONTEÚDO de prod/ (a que contém catalog/, admin/, system/)."
    echo "//    Ex.: /home/u12345/public_html/  (com barra final!)"
    echo "// 3) Salve este arquivo como config.php (sem o .EXEMPLO)."
    echo "// 4) config.php NUNCA vai ao git (credenciais)."
    echo "// HTTP_SERVER: ${app} → https://SEU-DOMINIO/${app}/  ⚠ SEMPRE com '/${app}/' no fim —"
    echo "//   o router calcula a rota subtraindo essa base (dev usa .../dev/${app}/)."
    tail -n +2 "$TPL" \
      | sed -e "s#dev/{{APP_DIR}}#${app}#g" \
            -e "s#dev/system/#system/#g" \
            -e "s#dev/extension/#extension/#g"
  } > "$OUT"
done

echo "== vereditos da build =="
FAIL=0
for p in "$DST/catalog/index.php" "$DST/admin/index.php" "$DST/system/framework.php" \
         "$DST/system/storage/vendor/autoload.php" "$DST/.htaccess" "$DST/catalog/.htaccess"; do
  [ -f "$p" ] || { echo "  FALTA $p"; FAIL=1; }
done
for p in "$DST/install" "$DST/tests" "$DST/system/storage/phpstan.neon" "$DST/system/storage/composer.json" \
         "$DST/system/storage/logs/error.log" "$DST/catalog/config.php" "$DST/admin/config.php"; do
  [ -e "$p" ] && { echo "  NÃO DEVERIA EXISTIR: $p"; FAIL=1; }
done
grep -rl "dev/webhook\|openbay\|googleshopping\|squareup" "$DST/system" 2>/dev/null | head -3 && { echo "  legado na build!"; FAIL=1; } || true
[ -f "$DST/catalog/config.php.EXEMPLO" ] && [ -f "$DST/admin/config.php.EXEMPLO" ] || { echo "  FALTA config EXEMPLO"; FAIL=1; }
if [ "$FAIL" = 0 ]; then echo "  OK — build pronta em $DST (subir o CONTEÚDO de prod/ para o docroot público)"; else echo "  BUILD COM PROBLEMAS"; exit 1; fi
