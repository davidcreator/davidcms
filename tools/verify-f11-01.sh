#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
# verify-f11-01.sh — verificacao LOCAL e read-only da frente F11-01
# (CI + dependencias Node em dev/system/storage, ADR 0081).
#
# Uso, a partir da RAIZ do repositorio:
#   bash tools/verify-f11-01.sh
#   SKIP_NPM=1 bash tools/verify-f11-01.sh    # sem instalar dependencias
#
# Nao acessa banco, staging, producao, GitHub, sessao nem credenciais.
# Nao executa PHP/PHPStan (declare essa limitacao quando aplicavel).
# ════════════════════════════════════════════════════════════════════════
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1

PASS=0
FAIL=0
SKIP=0

ck() {
  local rc="$1"; shift
  if [ "$rc" -eq 0 ]; then
    printf '  PASS  %s\n' "$*"; PASS=$((PASS + 1))
  else
    printf '  FAIL  %s\n' "$*"; FAIL=$((FAIL + 1))
  fi
}
skip() { printf '  SKIP  %s\n' "$*"; SKIP=$((SKIP + 1)); }

echo "== F11-01 — verificacao local (raiz: $ROOT) =="

echo "-- 1. Manifestos e estrutura --"
[ -f dev/system/storage/package.json ] && [ -f dev/system/storage/package-lock.json ]
ck $? "package.json e package-lock.json em dev/system/storage/"
if command -v python3 >/dev/null 2>&1; then
  python3 -m json.tool dev/system/storage/package.json >/dev/null 2>&1 \
    && python3 -m json.tool dev/system/storage/package-lock.json >/dev/null 2>&1
  ck $? "JSON dos manifestos Node e valido"
else
  skip "python3 ausente — validacao de JSON pulada"
fi

if [ -f .gitignore ]; then
  grep -qF '**/system/storage/node_modules/' .gitignore \
    && grep -qF '/node_modules/' .gitignore
  ck $? ".gitignore cobre o node_modules do storage e o legado da raiz"
else
  skip ".gitignore nao presente neste diretorio"
fi

if [ -f .github/workflows/php.yml ]; then
  n=$(grep -c 'working-directory:' .github/workflows/php.yml || true)
  [ "$n" -eq 2 ]
  ck $? "php.yml usa working-directory apenas nos 2 jobs (composer, static-analysis) — sem default global"
  grep -q 'system/storage/node_modules\|\*/storage/node_modules/\*' .github/workflows/php.yml
  ck $? "php.yml exclui node_modules do php-lint"
  grep -q 'node_modules' .github/workflows/php.yml
  ck $? "php.yml exclui node_modules do link check"
  if python3 -c 'import yaml' >/dev/null 2>&1; then
    python3 - <<'PY'
import yaml
with open('.github/workflows/php.yml', encoding='utf-8') as fh:
    d = yaml.safe_load(fh)
assert 'jobs' in d and 'defaults' not in d, 'defaults global ainda presente'
assert set(('composer', 'php-lint', 'docs-links', 'static-analysis', 'node-deps')) <= set(d['jobs']), 'jobs ausentes'
PY
    ck $? "YAML do workflow e valido e nao tem defaults global"
  else
    skip "PyYAML ausente — parse do YAML pulado"
  fi
else
  skip ".github/workflows/php.yml nao presente neste diretorio"
fi

echo "-- 2. Correcao do PHPStan (BlockDocument) --"
if [ -f dev/system/library/blog/block_document.php ]; then
  if grep -qF '$version ?? 0' dev/system/library/blog/block_document.php; then
    ck 1 "block_document.php nao contem mais \$version ?? 0"
  else
    ck 0 "block_document.php nao contem mais \$version ?? 0"
  fi
  grep -qF 'validateNode($node, 0, null, $ids, $errors, $count, $version, $anchors)' dev/system/library/blog/block_document.php
  ck $? "validateNode() recebe \$version (int garantido) preservando fail-closed"
else
  skip "block_document.php nao presente neste diretorio"
fi

echo "-- 3. Resolucao explicita do Playwright --"
if [ -f tools/e2e/lib/load-playwright.cjs ]; then
  node --check tools/e2e/lib/load-playwright.cjs >/dev/null 2>&1
  ck $? "load-playwright.cjs tem sintaxe valida"
  # Exclui o proprio helper (seu comentario cita o padrao antigo) e o README.
  if grep -rqF "require('playwright')" tools/e2e --include='*.cjs' --exclude='load-playwright.cjs' 2>/dev/null; then
    ck 1 "nenhum runner usa require('playwright') direto"
  else
    ck 0 "nenhum runner usa require('playwright') direto"
  fi
else
  skip "tools/e2e/lib/load-playwright.cjs nao presente neste diretorio"
fi

if [ -f tools/build_prod.sh ]; then
  grep -qF "system/storage/node_modules/" tools/build_prod.sh
  ck $? "build_prod.sh exclui node_modules da build de producao"
fi
if [ -f tools/check_producao.sh ]; then
  grep -qF 'system/storage/node_modules/' tools/check_producao.sh
  ck $? "check_producao.sh verifica vazamento de node_modules no ar"
fi

echo "-- 4. Sintaxe dos scripts --"
if command -v bash >/dev/null 2>&1; then
  rc=0; total=0
  while IFS= read -r -d '' f; do
    bash -n "$f" || rc=1
    total=$((total + 1))
  done < <(find tools -type f -name '*.sh' -print0 2>/dev/null)
  [ "$rc" -eq 0 ] && [ "$total" -gt 0 ]
  ck $? "bash -n em $total script(s) .sh"
fi
if command -v node >/dev/null 2>&1; then
  rc=0; total=0
  while IFS= read -r -d '' f; do
    node --check "$f" >/dev/null 2>&1 || { echo "    -> $f"; rc=1; }
    total=$((total + 1))
  done < <(find tools tests -type f \( -name '*.js' -o -name '*.cjs' \) -print0 2>/dev/null)
  [ "$rc" -eq 0 ] && [ "$total" -gt 0 ]
  ck $? "node --check em $total script(s) .js/.cjs"
else
  skip "node ausente — verificacao de sintaxe JS pulada"
fi

echo "-- 5. Instalacao e resolucao npm --"
if [ "${SKIP_NPM:-0}" = "1" ]; then
  skip "SKIP_NPM=1 — npm ci nao executado"
elif command -v npm >/dev/null 2>&1 && [ -f dev/system/storage/package-lock.json ]; then
  npm ci --ignore-scripts --no-audit --no-fund --prefix dev/system/storage >/dev/null 2>&1
  ck $? "npm ci --prefix dev/system/storage"
  # npm ci recria node_modules/ e apaga o index.html versionado que mantem a
  # pasta visivel no repo. Repor para nao sujar o Git a cada verificacao.
  if [ ! -f dev/system/storage/node_modules/index.html ]; then
    cat > dev/system/storage/node_modules/index.html <<'HTML'
<!doctype html><title>ReamurCMS storage</title>
<!-- Diretorio canonico das dependencias Node (npm ci --prefix dev/system/storage).
     Unico conteudo versionado; as dependencias sao ignoradas pelo Git. -->
HTML
  fi
  node -e "const {loadPlaywright}=require('./tools/e2e/lib/load-playwright.cjs'); const pw=loadPlaywright(); if(!pw.chromium) process.exit(1);" >/dev/null 2>&1
  ck $? "Playwright resolvido via createRequire(storage/package.json)"
else
  skip "npm ou package-lock.json ausentes — instalacao pulada"
fi

echo
echo "== resumo: $PASS PASS / $FAIL FAIL / $SKIP SKIP =="
echo "   Limites: este script NAO executa PHP, php -l, PHPStan, browser, banco ou deploy."
[ "$FAIL" -eq 0 ]
