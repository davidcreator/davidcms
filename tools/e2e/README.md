# Checkers estáticos cumulativos — BDREditor

A partir da raiz do pacote, execute todos os E2E estáticos:

```sh
for f in tools/e2e/*.cjs; do node "$f" || exit 1; done
```

Checkers da sequência cumulativa incluem outline por teclado, navegação hierárquica, copy/paste, atalhos, áudio, arquivo/download, reordenação hierárquica, estado de alterações não salvas, callout, detalhes expansíveis, espaçador e código pré-formatado.

Os checkers de bloco verificam fixtures, contrato e integração por leitura de fontes; os checkers comportamentais usam execução isolada do JavaScript quando aplicável. Não iniciam PHP, aplicação, banco, servidor, staging ou deploy, e não substituem teste em navegador/leitor de tela.

## CMS > Páginas (incremento 2026-10-25)

Execute `node tests/check_cms_page_integration.js` para verificações estáticas da montagem do editor, envelope versionado no campo existente, validação server-side, renderer seguro e preservação do fallback legado. A verificação não substitui PHP lint, browser real, salvar/reabrir numa aplicação, nem autoriza banco/staging/produção.
