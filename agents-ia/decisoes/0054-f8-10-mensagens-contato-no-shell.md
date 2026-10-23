# ADR 0054 — F8-10: Mensagens de contato no shell e Ler POST protegido

**Data:** 2026-10-23 · **Agente:** admin / orquestrador.
**Status:** comportamento escolhido explicitamente pelo usuário; implementação preparada, aplicação/homologação pendentes.
**Base:** `653fb677b6068af28d706d766064a47ef0aefffe`.

## Contexto e decisão do usuário

O usuário declarou a Tarefa09 concluída/GitHub atualizado. Seus17 arquivos conferem com a entrega por blob Git em653fb677, sem inferir detalhes de backend/deploy. Prossegue-se somente com `communication/contact_message`.

O CRUD é assimétrico: mensagens vêm do formulário público; admin só lê, muda status0Novo/1Lido/2Respondido ou exclui. A base fazia auto-mark em GET, sem modify/CSRF, e `(int)'x'` virava0 apesar da intenção de whitelist. Ao escolher entre marcação manual e automática protegida, o usuário escolheu **manter o automático pelo botão Ler, com POST+ACL+CSRF**.

## Decisão

1. GET `.read|ID` torna-se somente visualização, inclusive para readonly; nunca grava status. O botão Ler na lista faz POST na rota existente quando há modify, exige CSRF, marca Novo→Lido uma vez e redireciona para GET. Readonly recebe link GET. POST Ler não sobrescreve Lido/Respondido. Não se cria endpoint.
2. `.status|ID` e `.delete|ID` continuam POST+modify e passam a exigir CSRF. Valores canônicos de status só0/1/2 (int ou string exata); arrays/bools/floats/x/01 não são coagidos paraNovo. `from=read` continua indo ao detalhe, agora sem desfazer “Marcar como Novo” por efeito do GET.
3. Migrar lista/detalhe para shell/cards/tabela responsiva/badges Bootstrap temáticos e modal global delete. Corpo continua texto escapado/pre-wrap, não HTML/editor. Conteúdo recebido não é editável. Reply permanece mailto pré-preenchido, não envia e-mail nem marca Respondido automaticamente.
4. Busca por nome/e-mail/assunto (não corpo/IP/telefone), status e paginação20. Contagem e lista compartilham predicados; contadores das abas acompanham busca e os links a preservam. A chamada antiga `getMessages(?int)` segue completa, mesma ordem date_added DESC/id DESC; `getCounts()` sem argumentos segue global. Acrescentar opções opcionais e getTotalMessages, sem schema.
5. `getMessage`, `updateStatus`, `deleteMessage`, constantes de status e agregação de counts mantêm o comportamento do model. Alterações da leitura e validação estrita são deliberadas no controller, não bypass do model.
6. CSS só sob `.contact-inbox-list` e `.contact-message-read`, prefixo anterior intacto; corpo com tokens do tema. Escape duplo da paginação corrigido apenas localmente, sem tocar library/core/ASSET_V. Sem JS runtime novo: forms nativos e modal global existente.
7. Completar idiomas en-us/pt-br/es-eu e atualizar texto da semântica de leitura. Atualizar f215 legado pontualmente para CSRF login/status/delete, GET que não marca e POST Ler; setup/SQL/grants destrutivos ficam intactos e nunca são executados pelo Tool novo.

## Tool e privacidade

Entrada `tools/e2e/e2e_f810.sh`:

- `--check`: testes locais. **40 PASS/0FAIL** com PHP8.4.26/Twig3.30.0 reais, DB/serviços/model do controller/IO sessão em fixtures; SQL capturado, Session sem construtor, sem framework/config de aplicação.
- **8 PASS/0FAIL** auxiliares novos +16F8-06 compartilhados, respostas simuladas. Lint6PHP/JS/Bash aprovados. Negativo na caixa antiga: **16PASS/24FAIL**, saída1 esperada. Regressões locais F8-09/08/07/06/05 verdes.
- `--fixture`: gera somente JSON com dados fictícios/marker, sem salvar, enviar, ler configs, acessar DB ou preparar registro automaticamente. Exige sóNode. O operador prepara uma mensagem correspondente no laboratório, garantindo notificação em captura/offline e integrações produtivas desligadas. Formulário público existente pode notificar o dono do site; não é invocado pelo Tool.
- `--http`: opt-in de12etapas, preparado mas não executado no backend. Requer loopback/configs literais reamur_test/marker/fontes MIRROR=FUI, conta fictícia modify e ID real+marker da fixture. Verifica nome/e-mail/assunto/ID na lista antes do detalhe, depois corpo/telefone exatos antes de qualquer POST. Sem ID presumido ou conteúdo desconhecido. Não usa SQL, formulário público, mailto ou envio de e-mail.
- Cookies/tokens e dados de mensagem só em memória. Relatório contém marker/ID/etapas/status, sem nome/e-mail/telefone/IP/assunto/corpo/mailto; falhas ocultam detalhes de backend/dados. Falha preserva fixture para inspeção, sem retry de mutação/limpeza ampla. Sucesso exclui somente a fixture validada e probe próprio.

## Consequências

- Links GET diretos deixam de marcar Lido (decisão explícita). Clientes que desejam auto-mark devem usar POST `.read|ID` com modify+CSRF. Status/delete também exigem token; sem bypass legado.
- Marcar Novo no detalhe agora permaneceNovo após redirect; status inválido textual não viraNovo. Isso é correção intencional do contrato, documentada para operadores/clientes.
- Sem add/edit de conteúdo, upload, SEO, nova tabela/coluna/grant, site público, SMTP, JavaScript global, outros CRUDs, Appearance, Composer ou editor do Blog alterados.
- Conteúdo e dados de contato só são exibidos ao perfil access já existente. O pacote não cria política nova de retenção/exportação nem envia resposta: mailto continua ação manual do usuário.
- Homologar navegador/modal/contraste/responsividade, outro perfil e volume real. Guardas não atestam DB/PHP/proxy mal configurado; proibido produção/dados reais/túnel/proxy produção.
- Nenhum backend, mensagem real, navegador, e-mail, escrita GitHub, deploy ou Actions executados pelo agente. F215 destrutivo não homologado integralmente aqui.
- Aplicação/publicação não é encerramento da Fase8.

Guia/fixture/aceite/rollback: [F8-10 — Mensagens de contato](../../docs/F8-10-Mensagens-Contato.md).
