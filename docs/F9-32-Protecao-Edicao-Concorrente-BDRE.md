# F9-32 — Proteção contra sobrescrita concorrente no BDRE

**Status:** implementação estática integrada em CMS Pages e Blog; runtime PHP/DB não testado.

## Objetivo

Impedir que um formulário de Pages ou Blog aberto antes de outra gravação sobrescreva silenciosamente o conteúdo mais recente. A verificação não troca o botão Save por autosave e preserva os fluxos independentes.

## Contrato

- Cada formulário de edição recebe fingerprint SHA-256 calculado no servidor ao carregar.
- O fingerprint Page cobre status, sort order, descrições/SEO de todos os idiomas e valores de custom fields. O Blog cobre status, imagem, data de publicação, descrições/SEO e sidecars BDRE.
- O POST valida CSRF e permissão como antes; fingerprint diferente bloqueia a gravação antes de escrever.
- Há nova verificação imediatamente antes da gravação. No caminho principal, os models fazem compare-and-save sob transação e `SELECT ... FOR UPDATE` na linha raiz, fechando a corrida entre verificações para o conteúdo do registro.
- O formulário informa o conflito e permite recarregar a versão atual. Essa ação remove apenas os drafts de recuperação locais da aba (por escolha explícita); não remove o histórico de snapshots, nem grava dados.
- Depois do conflito, enviar novamente sem recarregar continua bloqueado porque o token enviado permanece o fingerprint original.
- Criar novos registros não usa fingerprint e não muda comportamento.

## Limites

- Custom fields de Pages são incluídos no fingerprint da requisição e rechecados antes de salvar, mas o armazenamento desses campos mantém seu fluxo atual fora da transação de edição da página; uma disputa simultânea exclusivamente nesse pequeno intervalo não é serializada pelo row lock da página.
- A proteção não é um mecanismo de autorização: ACL/CSRF e validações existentes continuam obrigatórias.
- Mudanças feitas por código que altere descrições sem bloquear/atualizar a linha raiz podem não respeitar a serialização. Revisar futuros caminhos de escrita para usar os models CMS existentes.

## Validação

Verificações estáticas direcionadas e `node --check` são executáveis localmente. PHP CLI não está disponível neste ambiente, sem lint PHP/DB. Nenhum banco, staging, migration, navegador ou deployment foi acessado. F9-27 release gate permanece bloqueado até autorização explícita e confirmação de isolamento/F9-12.
