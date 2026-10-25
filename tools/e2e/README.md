# Checkers estáticos cumulativos — BDREditor

A partir da raiz do pacote, execute todos os E2E estáticos:

```sh
for f in tools/e2e/*.cjs; do node "$f" || exit 1; done
```

Checkers da sequência cumulativa incluem outline por teclado, navegação hierárquica, copy/paste, atalhos, áudio, arquivo/download, reordenação hierárquica, estado de alterações não salvas, callout, detalhes expansíveis, espaçador e código pré-formatado.

Os checkers de bloco verificam fixtures, contrato e integração por leitura de fontes; os checkers comportamentais usam execução isolada do JavaScript quando aplicável. Não iniciam PHP, aplicação, banco, servidor, staging ou deploy, e não substituem teste em navegador/leitor de tela.
