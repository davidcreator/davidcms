# ADR 0066 — F9-10: Dicionário canônico e preflight integrado de custom fields

**Data:** 2026-10-24 · **Agente:** install / orquestrador.  
**Status:** atualização documental + gate local; sem execução DB.  
**Base verificada:** `davidcreator/davidcms` @ `2c39f444a78fbc01c8586871f780a09e9be4a91f`.

## Contexto

A migração F9-02 criou cinco tabelas como proposta separada do schema base e sua ADR dizia que o dicionário deveria ser atualizado após aprovação/aplicação. Durante o diagnóstico do 404 da rota `cms/page.add`, o operador conferiu o banco e confirmou a falta das tabelas; após a normalização, a funcionalidade voltou. O dicionário oficial ainda não documentava os nomes/colunas F9.

Os gates das tarefas anteriores eram locais. O teste F9-05 também trazia uma asserção temporal exigindo que diretórios de controller/template não existissem — requisito verdadeiro na tarefa F9-05, mas falso no repositório atual após F9-06, tornando o gate não reutilizável.

## Decisão

1. Registrar no `docs/Dicionario-de-Dados.md` as cinco tabelas e colunas exatamente como no upgrade F9-02; documentar `language_id=0`, precision `DECIMAL(38,6)`, visibilidade, índices e ausência de FK.
2. Preservar o conteúdo histórico do contrato F9-05, mas remover assertions de ausência física de arquivos futuros. O teste verifica `controller_available=false` e `ui_available=false` no contrato F9-05 daquela tarefa, sem proibir evolução posterior.
3. Criar um runner único F9-10 que executa apenas gates estáticos entregues na F9-02 e F9-05…09 e confirma a congruência do dicionário/schema. Raiz pode ser apontada para uma cópia do repositório com `--root`.
4. Manter a não execução: o runner não consulta bancos nem comprova estado de tabelas; essa parte permanece sob verificação direta e operacional do mantenedor.

## Alternativas consideradas

- **Alterar `schema.sql` base retroativamente:** rejeitado; F9-02 é migração versionada separada e não deve ser aplicada/reescrita silenciosamente.
- **Remover validações F9-05 antigas por completo:** rejeitado; preserva-se a verificação do contrato histórico, apenas removendo pressupostos sobre arquivos que só eram ausentes naquela fase.
- **Conectar o gate ao banco ou aplicar migration automaticamente:** rejeitado; exigiria credenciais, autorização operacional e poderia causar mudança destrutiva.

## Consequências

- O dicionário volta a refletir o schema instalado/conhecido.
- O gate unificado torna mais fácil detectar regressão estrutural antes de mudar o escopo para novas entidades.
- Gate PASS significa somente consistência estática; não substitui laboratório, teste de PHP, browser ou validação no DB.
- O arquivo `error.log` deve permanecer fora de commits futuros e não fazer parte dos pacotes; F9-10 não altera nem republica log.

## Validação

`tools/e2e/e2e_f910.sh --check --root <repo>` roda os verificadores sem banco/rede/PHP. O teste local usa apenas arquivos pontuais; não houve clone integral, execução PHP ou escrita GitHub.
