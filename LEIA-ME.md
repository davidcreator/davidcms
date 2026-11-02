# Correcao: node_modules em dev/system/storage/

Mudancas reais de codigo/config (4 arquivos):
  .gitignore                                  -> a pasta passa a existir no repo
  dev/system/storage/node_modules/index.html  -> placeholder versionado
  tools/install-deps.sh                       -> instalador idempotente + smoke real
  tools/verify-f11-01.sh                      -> repoe o placeholder apos npm ci

Os 14 CHECKSUMS*.txt foram reemitidos em cascata porque .gitignore e
CHECKSUMS.txt mudaram; sem isso o repo-guards ficaria vermelho.

Aplicar:
  bash tools/install-deps.sh                  # cria as dependencias no disco
  bash tools/install-deps.sh --purge-legacy  # + remove node_modules/ legado da raiz
  bash tools/verify-checksums.sh
  bash tools/repo-health.sh

As dependencias continuam IGNORADAS pelo Git (decisao F11-02). O que passa a
existir no GitHub e a PASTA, com um unico index.html. Nenhuma escrita remota
foi feita nesta entrega. Validado localmente com npm/Node reais.
