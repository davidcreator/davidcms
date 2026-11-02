#!/usr/bin/env bash
# Instala as dependencias Node no destino canonico: dev/system/storage/node_modules/
# Uso: bash tools/install-deps.sh [--purge-legacy]
# Idempotente. Nao versiona node_modules, nao toca em vendor/, nao escreve no GitHub.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || { echo "ERRO: nao foi possivel entrar na raiz do repo"; exit 1; }
STORAGE='dev/system/storage'
PURGE=0
[ "${1:-}" = '--purge-legacy' ] && PURGE=1
fail(){ printf 'ERRO: %s\n' "$*"; exit 1; }

[ -f "$STORAGE/package.json" ]      || fail "$STORAGE/package.json ausente — layout invalido (ADR 0081)"
[ -f "$STORAGE/package-lock.json" ] || fail "$STORAGE/package-lock.json ausente — npm ci exige lockfile"
command -v npm >/dev/null 2>&1        || fail 'npm indisponivel neste ambiente'
command -v node >/dev/null 2>&1       || fail 'node indisponivel neste ambiente'

# Legado: node_modules/ na raiz do repo (removido do indice em 510640c68, mas pode
# ainda existir fisicamente num checkout antigo). Nunca apagar sem a flag explicita.
if [ -d node_modules ]; then
  if [ "$PURGE" -eq 1 ]; then
    rm -rf node_modules && echo 'OK   node_modules/ legado da RAIZ removido'
  else
    echo 'AVISO  node_modules/ ainda existe FISICAMENTE na raiz (legado, ignorado pelo Git).'
    echo '       Para remover: bash tools/install-deps.sh --purge-legacy'
  fi
fi

printf '== Instalando em %s/node_modules/ ==\n' "$STORAGE"
( cd "$STORAGE" && npm ci --ignore-scripts --no-audit --no-fund ) || fail 'npm ci falhou'
[ -f "$STORAGE/node_modules/playwright/index.js" ] || fail 'playwright nao encontrado apos npm ci'

# npm ci RECRIA node_modules/ do zero e apaga o index.html versionado que mantem
# a pasta visivel no repositorio. Repor sempre, para nao sujar o Git a cada install.
PLACEHOLDER="$STORAGE/node_modules/index.html"
if [ ! -f "$PLACEHOLDER" ]; then
  cat > "$PLACEHOLDER" <<'HTML'
<!doctype html><title>ReamurCMS storage</title>
<!-- Diretorio canonico das dependencias Node (npm ci --prefix dev/system/storage).
     Unico conteudo versionado; as dependencias sao ignoradas pelo Git. -->
HTML
  echo 'OK   index.html versionado reposto apos npm ci'
fi

# Smoke REAL: prova que o carregamento canonico do repo resolve o pacote a partir
# de dev/system/storage, sem NODE_PATH, sem symlink e sem node_modules na raiz.
if [ -f tools/e2e/lib/load-playwright.cjs ]; then
  node -e "const {loadPlaywright}=require('./tools/e2e/lib/load-playwright.cjs');const p=loadPlaywright();if(!p.chromium){process.exit(1)}console.log('smoke OK: Playwright resolvido via '+require('path').resolve('$STORAGE'))" \
    || fail 'smoke de carregamento canonico falhou'
else
  echo 'AVISO  tools/e2e/lib/load-playwright.cjs ausente neste checkout; smoke pulado'
fi

printf '\n== OK ==\n'
printf '  %s/node_modules/ criado a partir de %s/package-lock.json\n' "$STORAGE" "$STORAGE"
printf '  playwright: %s\n' "$(node -e "console.log(require('./$STORAGE/node_modules/playwright/package.json').version)" 2>/dev/null || echo '?')"
printf '  node_modules/ na raiz: %s\n' "$([ -d node_modules ] && echo PRESENTE || echo AUSENTE)"
printf '  vendor/ do Composer nao foi tocado.\n'
printf '  Lembrete: node_modules/ e IGNORADO pelo Git por decisao F11-02 — nao deve ser enviado ao GitHub.\n'
exit 0
