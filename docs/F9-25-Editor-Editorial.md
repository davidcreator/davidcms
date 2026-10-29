# Fase 9 · Tarefa 25 — Padrões, templates e fluxo editorial

**Status do pacote:** incremento local parcial, com proposta pendente para persistência/preview autenticado. Não declarar a F9-25 completa. Nenhuma alteração remota ou de banco.

**HEAD `main` verificado:** `4bb1b7c7ce8b23172f56241d5209ffdc6760c5c5` (commit F9-24 de 2026-10-25, filho de `af3421cc1b3bd4636d8bb44d6d3d4e83e0fb5eb9`). Os arquivos pontuais copiados para revisão foram comparados por Git blob SHA com a árvore desse commit; sem clone integral.

## Escopo oficial F9-25

Roadmap F9-21: padrões CMS próprios com salvar/inserir (condicionado ao modelo de permissões), title/outline, preview no contexto do catálogo e confirmação de alterações não salvas. Autosave/revisões permanecem fora até política aprovada.

## Incremento implementado localmente

### Título no outline

`core/group` v2 recebe atributo opcional `outline_label`, string UTF-8 simples até 160 bytes. O inspetor permite editar; o outline administrativo mostra o rótulo ou o nome localizado do bloco quando vazio. O outline continua uma navegação de IDs da árvore, sem markup injetado. O renderer público deliberadamente ignora essa metadata. A v1 não aceita o novo atributo e permanece estrita.

### Confirmação de alterações não salvas

O binding de formulário mantém estado local dirty ao editar campos/blocks e registra `beforeunload`; o navegador mostra seu aviso genérico, sem texto customizado. Um submit normal só desarma o guard se todos os hosts do editor estiverem válidos. Erros de validação não escrevem por autosave nem limpam os documentos. Nenhuma rede/autosave é adicionada.

## Decisões pendentes / gates

### Salvar padrões próprios

**Não implementado** conforme a opção escolhida: preparar proposta. `modify` em `blog/blog` protege o CRUD de posts, mas não estabelece escopo para bibliotecas reutilizáveis de templates. Proposta inicial: privados por usuário administrador; nenhuma partilha global presumida. Antes de endpoint, persistência ou tela de CRUD, o dono deve aprovar ACL e escopo e o agente de instalação/schema deve revisar a entidade/tabela e migration. Nesta entrega não há tabela, SQL, endpoint ou armazenamento no browser.

### Preview em contexto do catálogo

**Não implementado**: não existe preview por draft/post autenticado nesta mudança. Reutilizar um URL público ou token bearer poderia expor rascunhos; preview deve ser um fluxo same-origin, ACL/CSRF protegido, usar JSON validado pelo server renderer e não gravar post. Exige desenho e validação de segurança antes de qualquer implementação.

## Verificações e limites

Apenas parsing de JavaScript e JSON/checker estático podem ser realizados. PHP/SQL/browser/app/E2E runtime são proibidos nesta etapa. Os fixtures são sintéticos e não demonstram a validação real do renderer. O trabalho de acessibilidade e QA browser fica pendente para a fase F9-26/F9-27 sob autorização.

## Addendum 2026-10-29 — prévia privada do Blog

A seção histórica acima que marcava a prévia autenticada como não implementada é supersedida para o fluxo de Blog por `BDREditor-Blog-Catalog-Preview.md`, preparado localmente contra a HEAD `5838685e4a96d6089d27c0d7e024b7c11b13c434` após aprovação explícita do escopo. O preview usa POST, ACL/CSRF de `blog/blog`, validação/renderização sem persistência e leitura somente da estrutura pública ativa `blog_post`; Pages e Blog continuam com CRUD, modelos e permissões separados. O `custom_js` de miniaturas Three.js não é executado na prévia.

Isso não declara a F9-25 concluída: persistência e CRUD de padrões próprios continuam fora deste incremento. Não houve migration, commit, push, deploy ou execução PHP/runtime.
