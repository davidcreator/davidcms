# F9-34 — Diff textual de revisões BDRE

**Status:** diff textual limitado integrado à comparação F9-33; runtime de navegador não executado.

## Comportamento

- A comparação lado a lado agora inclui uma visualização textual com trechos inalterados, removidos e adicionados.
- Diff executado em memória sobre tokens de texto/whitespace extraídos do documento BDRE; markup legado continua sendo texto literal.
- Algoritmo LCS limitado a 450 tokens por lado (máximo de ~202.500 células); acima do limite, a interface mantém a comparação lado a lado e informa que o diff detalhado não será calculado.
- Os nós `del`/`ins` são criados via DOM, e texto sempre entra por `textContent`/`createTextNode`. Nenhum HTML de revisão é interpretado.
- Nenhum conteúdo é alterado, persistido ou removido; restaurar continua sendo uma ação separada.

## Validação

`node --check` e verificações direcionadas de localização/wiring. Sem PHP CLI, DB, browser ou staging neste ambiente. A elegibilidade para o release gate segue F9-27 e suas autorizações separadas.
