# F9-86 — Reparador assistido de links internos

## Escopo

Complementa a auditoria F9-85 com uma tela de correção assistida no ribbon **Revisar** e na faixa lateral do editor em tela cheia. Também pode ser aberta diretamente da tela da auditoria. Para cada link interno quebrado, a pessoa escolhe um título de destino e confere a prévia `#destino atual → #novo destino` antes de aplicar uma correção por vez.

## Comportamento

- A lista de destinos inclui headings não vazios; âncoras existentes são reutilizadas e headings sem âncora recebem uma proposta slug segura e única.
- Ao aplicar, somente os links do bloco de origem que correspondem ao fragmento quebrado selecionado são atualizados (destinos iguais no mesmo bloco são agrupados). Quando necessário, a âncora é atribuída ao heading escolhido.
- O documento completo é validado novamente antes do commit. A operação usa o histórico Desfazer existente; outros blocos, links e texto visível permanecem inalterados.
- Se o achado ficou desatualizado, o destino não puder ser encontrado ou a validação falhar, nada é gravado.
- Uma correção por ação explícita; não há correção em lote, remoção de conteúdo, requisição de rede ou acesso a sites externos.

## Segurança e limites

Os destinos aceitos são fragmentos locais no formato `#id`, compatíveis com a allowlist BDRE. A ferramenta não infere a intenção editorial: a pessoa escolhe o título. Se várias ocorrências com o mesmo alvo estiverem no mesmo bloco de origem, todas são atualizadas em conjunto e isso é informado na tela.

## Verificação

- `node tools/f9-86-internal-anchor-repair-smoke.js`
- Regressões F9-61, F9-66, F9-76–F9-85 e `python3 tools/f9-57-static-battery.py`.
- `node --check` e parsing CSS com `tinycss2`.

PHP CLI e navegador real não estão disponíveis neste ambiente; não declaro testes PHP de runtime nem interação real no browser.
