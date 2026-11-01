# F9-85 — Auditoria de links e âncoras internas

## Escopo

Ferramenta somente leitura no ribbon **Revisar** e na faixa de ferramentas do editor em tela cheia. Examina links de fragmento `#id` em botões e texto rico do documento e compara cada destino com `anchor` existente em blocos `core/heading`. A tela informa a quantidade de destinos internos examinados e mostra cada destino ausente com atalho para localizar o bloco de origem.

## Regras e limites

- A auditoria opera somente sobre o BDRE local já carregado; não faz requisições nem verifica disponibilidade de páginas.
- Só fragmentos puros `#slug` são destinos internos. Fragmentos em URLs externas, como `https://example.org/page#x`, não são tratados como âncoras desta página.
- Validação BDRE continua rejeitando identificadores malformados ou duplicados antes da análise. O relatório foca links cujo destino não existe num heading.
- Não modifica links, headings, âncoras nem qualquer outro conteúdo; não aplica correções automáticas.
- Cada bloco de origem/destino ausente gera um apontamento. Repetições do mesmo destino no mesmo bloco são agrupadas; referências em blocos diferentes mantêm locadores próprios.

## Verificação

- `node tools/f9-85-internal-anchor-review-smoke.js`
- Regressões F9-61, F9-66, F9-76–F9-84 e bateria `python3 tools/f9-57-static-battery.py`.
- `node --check` e parsing CSS com `tinycss2`.

PHP CLI e navegador real não estão disponíveis neste ambiente; não declaro testes PHP de runtime nem interação real no browser.
