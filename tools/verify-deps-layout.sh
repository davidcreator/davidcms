#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
# verify-deps-layout.sh — F11-02
# Auditoria READ-ONLY do layout de dependencias (Composer + npm).
#
#   bash tools/verify-deps-layout.sh
#
# Nao instala nada, nao remove nada, nao faz commit/push, nao escreve no GitHub.
# Falha (exit 1) se vendor/ ou node_modules/ estiverem versionados no indice.
# ════════════════════════════════════════════════════════════════════════
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1

PASS=0; FAIL=0; WARN=0
ck()  { if [ "$1" -eq 0 ]; then printf '  PASS  %s\n' "$2"; PASS=$((PASS+1)); else printf '  FAIL  %s\n' "$2"; FAIL=$((FAIL+1)); fi; }
warn(){ printf '  WARN  %s\n' "$1"; WARN=$((WARN+1)); }

echo "== F11-02 — auditoria do layout de dependencias ($ROOT) =="

echo "-- 1. Manifestos versionaveis em dev/system/storage/ --"
for f in composer.json composer.lock package.json package-lock.json; do
  [ -f "dev/system/storage/$f" ]; ck $? "dev/system/storage/$f presente"
done

echo "-- 2. Regras de .gitignore --"
if [ -f .gitignore ]; then
  grep -qF '**/system/storage/vendor/' .gitignore;        ck $? "vendor/ do Composer ignorado"
  grep -qF '**/system/storage/node_modules/' .gitignore;  ck $? "node_modules/ do storage ignorado"
  grep -qF '/node_modules/' .gitignore;                   ck $? "node_modules/ legado da raiz ignorado"
  if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    for p in dev/system/storage/vendor/autoload.php \
             dev/system/storage/node_modules/playwright/index.js \
             dev/system/storage/node_modules/playwright/index.html \
             node_modules/playwright/index.js; do
      git check-ignore -q --no-index "$p"; ck $? "git check-ignore: $p"
    done
    for p in dev/system/storage/package.json dev/system/storage/composer.lock; do
      if git check-ignore -q --no-index "$p"; then
        ck 1 "$p NAO deve ser ignorado (manifesto versionado)"
      else
        ck 0 "$p NAO deve ser ignorado (manifesto versionado)"
      fi
    done
  fi
else
  ck 1 ".gitignore presente"
fi

echo "-- 3. Indice do Git (o que realmente vai para o GitHub) --"
if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  n_raiz=$(git ls-files 'node_modules/*' | wc -l)
  n_storage=$(git ls-files 'dev/system/storage/node_modules/*' | wc -l)
  n_vendor=$(git ls-files | grep -cE '(^|/)system/storage/vendor/' || true)
  printf '  node_modules/ raiz: %s | storage/node_modules: %s | storage/vendor: %s arquivos no indice\n' \
         "$n_raiz" "$n_storage" "$n_vendor"
  [ "$n_raiz" -eq 0 ];    ck $? "nenhum node_modules/ da raiz versionado"
  [ "$n_storage" -eq 0 ]; ck $? "nenhum node_modules/ do storage versionado"
  [ "$n_vendor" -eq 0 ];  ck $? "nenhum vendor/ do storage versionado"
  if [ "$n_raiz" -gt 0 ]; then
    echo
    echo "  >> CORRECAO (executar manualmente, com autorizacao):"
    echo "     git rm -r --cached node_modules"
    echo "     git commit -m 'chore(deps): remover node_modules da raiz (ADR 0081)'"
    echo "  >> O .gitignore NAO remove do indice o que ja esta versionado."
    echo
  fi
else
  warn "nao e um work tree Git — verificacao de indice pulada"
fi

echo "-- 4. Estado fisico (informativo) --"
if [ -d dev/system/storage/node_modules ]; then
  ck 0 "dev/system/storage/node_modules/ instalado no destino canonico"
else
  warn "dependencias npm ainda nao instaladas; rode: npm ci --prefix dev/system/storage"
fi
if [ -d dev/system/storage/vendor ]; then
  ck 0 "dev/system/storage/vendor/ instalado no destino canonico"
else
  warn "dependencias Composer ainda nao instaladas; rode: composer install --working-dir=dev/system/storage"
fi
if [ -d node_modules ]; then
  warn "node_modules/ ainda existe FISICAMENTE na raiz (legado). Se nao estiver no indice, e inofensivo; para apagar: rm -rf node_modules"
fi

echo
echo "== resumo: $PASS PASS / $FAIL FAIL / $WARN WARN =="
echo "   Este script NAO executa PHP, Composer, PHPStan, npm, browser, banco ou deploy."
[ "$FAIL" -eq 0 ]
