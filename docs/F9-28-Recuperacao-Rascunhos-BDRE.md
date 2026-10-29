# F9-28 — Recuperação de rascunho do BDREditor

## Objetivo

Permitir recuperar o documento BDRE após fechamento acidental, recarregamento ou interrupção do formulário, sem esperar pela bateria final de testes da ferramenta e sem mudar o salvamento explícito para o servidor.

## Comportamento entregue

- O editor guarda snapshots locais válidos do documento em `sessionStorage`, limitados ao contexto (Pages ou Blog), ao administrador, ao registro e ao idioma.
- O snapshot contém somente o documento BDRE; não contém CSRF, cookies, tokens, nem dados de sessão.
- Ao abrir o mesmo editor, um documento válido e diferente do servidor oferece recuperação via confirmação nativa. Se o usuário recusar, o snapshot é descartado.
- Snapshot idêntico ao conteúdo já carregado é removido; snapshot inválido, vencido (24 horas) ou acima de 4 MiB não é recuperado.
- Ao aceitar a recuperação, o formulário fica marcado como não salvo e continua exigindo clique explícito em Salvar para persistir no sistema.
- Se `sessionStorage` estiver indisponível ou cheio, o editor continua funcionando normalmente com o fluxo de salvamento atual.
- Pages e Blog permanecem isolados; cada idioma usa uma chave independente. A separação por administrador impede reaproveitamento após troca de usuário no mesmo navegador.

## Limites

Este é um mecanismo temporário, restrito à sessão da aba. Não é autosave de servidor, histórico de revisões, colaboração, nem garantia de recuperação depois do encerramento definitivo da sessão do navegador. Não adiciona endpoints, tabelas ou migrations. `sessionStorage` não é usado como fonte de verdade: BDRE no servidor continua sendo autoridade após salvamento.

## Cadência

A implementação é uma entrega funcional local. A bateria ampla F9-27 (browser/staging/DB/release) fica adiada até o marco de conclusão do BDREditor, conforme escopo atualizado pelo responsável. Nenhum teste runtime ou migration foi executado nesta entrega.
