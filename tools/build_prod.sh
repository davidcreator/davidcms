#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# build_prod.sh — F6-04 (ADR 0039): gera a BUILD DE PRODUÇÃO em prod/
# a partir de dev/ — SOMENTE o runtime. Planejamento (agents-ia/, docs/,
# tools/, *.zip) NUNCA sai da raiz do repo, então sequer é candidato.
#
# Fica EM prod/ (relativo a dev/):
#   admin/ catalog/ extension/ image/ system/ .htaccess index.php robots.txt
#   system/storage/vendor/ (SÓ os pacotes de `require` — ADR 0092: o
#     Composer tira da cópia tudo que é `require-dev` e refaz o autoload)
# Fica FORA (superfície/dev-only):
#   install/ (o wizard NÃO sobe — produção instala via SQL + config manual)
#   system/storage/{cache,logs,session,temp}/* (conteúdo volátil; guards
#     index.html são regrados depois)
#   system/storage/phpstan* e composer.{json,lock} (ferramentas de dev)
#   system/storage/vendor/{phpunit,phpstan,...} e vendor/bin/{phpunit,
#     phpstan,php-parse} (ADR 0092: PHPUnit/PHPStan e o que só eles usam)
#   system/storage/{node_modules,package.json,package-lock.json}
#     (F11-01 / ADR 0081: deps Node/Playwright de QA — NUNCA em producao)
#   catalogs/admin config.php reais (credenciais — gerados NO destino a
#     partir dos *.EXEMPLO incluídos) e install.lock
#
# Configs de produção: a build deriva de dev/install/config.php.tpl dois
# exemplos PRONTOS para editar — prod/catalog/config.php.EXEMPLO e
# prod/admin/config.php.EXEMPLO — com caminhos dev/→prod/ já reescritos.
#
# Precisa de: bash, rsync e Composer 2 (o mesmo que instalou o vendor).
#   Composer fora do PATH? Aponte:  COMPOSER_BIN="php /caminho/composer.phar"
#   Sem Composer a build PARA antes de copiar — nunca sobe o vendor inteiro.
#
# Uso:   bash tools/build_prod.sh            # gera/atualiza prod/
# Saída: prod/  (artefato derivado — NÃO versionar; .gitignore cobre)
# ════════════════════════════════════════════════════════════════════
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"   # raiz do repo (onde moram dev/ e prod/)
SRC="$ROOT/dev"; DST="$ROOT/prod"
[ -f "$SRC/catalog/index.php" ] || { echo "dev/ inválido em $ROOT" >&2; exit 1; }

# ADR 0092: o vendor de dev/ traz PHPUnit, PHPStan e dependentes; a build só
# pode levar os pacotes de `require`. Quem separa uma coisa da outra é o
# Composer, então ele é pré-requisito — conferido ANTES de tocar em prod/.
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
# shellcheck disable=SC2086  # COMPOSER_BIN pode ser "php /caminho/composer.phar"
set -- $COMPOSER_BIN
command -v "${1:-composer}" >/dev/null 2>&1 || {
  echo "Composer não encontrado ('$COMPOSER_BIN')." >&2
  echo "  A build precisa dele para tirar PHPUnit/PHPStan do pacote de produção (ADR 0092)." >&2
  echo "  Instale o Composer 2 ou aponte o executável, por exemplo:" >&2
  echo "    COMPOSER_BIN=\"php /caminho/composer.phar\" bash tools/build_prod.sh" >&2
  exit 1
}
STO="$DST/system/storage"
# Os manifestos entram em prod/ só enquanto o Composer trabalha; saem sempre,
# inclusive se a build parar no meio.
limpar_manifestos(){ rm -f "$STO/composer.json" "$STO/composer.lock"; }
trap limpar_manifestos EXIT

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
  --exclude 'system/storage/node_modules/' \
  --exclude 'system/storage/package.json' \
  --exclude 'system/storage/package-lock.json' \
  --exclude 'node_modules/' \
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

echo "== vendor de produção: só pacotes de 'require' (ADR 0092) =="
# O rsync acima copiou o vendor de dev/ inteiro. Aqui o Composer, lendo o
# MESMO composer.lock, remove da cópia os pacotes de `require-dev`, acerta
# qualquer versão que esteja diferente do lock e regrava o autoload sem as
# entradas de desenvolvimento. dev/ não é tocado. Com o vendor de dev/ em
# dia com o lock, este passo não usa a rede.
cp "$SRC/system/storage/composer.json" "$SRC/system/storage/composer.lock" "$STO/"
# shellcheck disable=SC2086
$COMPOSER_BIN install --working-dir="$STO" --no-dev --prefer-dist \
  --no-interaction --no-progress --no-scripts --no-plugins --optimize-autoloader || {
  echo "  Composer falhou ao montar o vendor de produção — prod/ NÃO está pronta para subir." >&2
  exit 1
}
limpar_manifestos

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
         "$DST/system/storage/composer.lock" \
         "$STO/vendor/phpunit" "$STO/vendor/phpstan" "$STO/vendor/bin/phpunit" \
         "$STO/vendor/bin/phpstan" "$STO/vendor/bin/phpstan.phar" "$STO/vendor/bin/php-parse" \
         "$DST/system/storage/node_modules" "$DST/system/storage/package.json" \
         "$DST/system/storage/package-lock.json" "$DST/node_modules" \
         "$DST/system/storage/logs/error.log" "$DST/catalog/config.php" "$DST/admin/config.php"; do
  [ -e "$p" ] && { echo "  NÃO DEVERIA EXISTIR: $p"; FAIL=1; }
done
# ADR 0092: o registro do próprio Composer tem de dizer "instalado sem dev" e
# não pode listar nenhum pacote de desenvolvimento (cobre os que não têm
# nome fixo acima: sebastian/*, nikic/php-parser, myclabs/deep-copy, ...).
INST="$STO/vendor/composer/installed.php"
if [ -f "$INST" ]; then
  grep -q "'dev' => false" "$INST" || { echo "  vendor instalado COM pacotes de desenvolvimento ($INST)"; FAIL=1; }
  if grep -q "'dev_requirement' => true" "$INST"; then echo "  pacote de desenvolvimento registrado em $INST"; FAIL=1; fi
else
  echo "  FALTA $INST"; FAIL=1
fi
grep -rl "dev/webhook\|openbay\|googleshopping\|squareup" "$DST/system" 2>/dev/null | head -3 && { echo "  legado na build!"; FAIL=1; } || true
[ -f "$DST/catalog/config.php.EXEMPLO" ] && [ -f "$DST/admin/config.php.EXEMPLO" ] || { echo "  FALTA config EXEMPLO"; FAIL=1; }
if [ "$FAIL" = 0 ]; then echo "  OK — build pronta em $DST (subir o CONTEÚDO de prod/ para o docroot público)"; else echo "  BUILD COM PROBLEMAS"; exit 1; fi
