# Fase 9 · Tarefa 25 — Padrões, templates e fluxo editorial

**Status deste pacote:** incremento parcial de implementação local, sujeito a revisão/integração e validação no ambiente do projeto; não declara a F9-25 completa. Nenhuma alteração remota, migration executada ou escrita em banco.

**HEAD de base verificada:** `a6cb064b04aa1d0ece686168cef50a49e99b49a9` (commit confirmado em 2026-10-29). Fontes pontuais foram consultadas para esta alteração; sem clone integral.

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


## Addendum 2026-10-29 — persistência privada de padrões BDRE (implementação local)

O plano de persistência foi aprovado pelo usuário. Esta entrega local implementa a biblioteca pessoal no controller/model `cms/block_pattern`, aplicável separadamente aos editores de Pages e Blog. Os fluxos de conteúdo e seus CRUDs continuam independentes. A lista retorna metadados; o JSON só é carregado sob demanda. Cada endpoint contextualizado (`*Pages`/`*Blog`) exige `access cms/block_pattern` e `modify cms/page` ou `modify blog/blog`, respectivamente; mutações exigem adicionalmente `modify cms/block_pattern`. O dono nunca vem do cliente.

A validação usa `BlockDocument` no contexto de destino e exige subárvore única independente, permite uso em qualquer idioma e registra o idioma de origem sem tradução. IDs são regenerados ao inserir. `custom_js` é rejeitado; nenhum HTML/Twig/JS livre é persistido. Limites: 50 por administrador e 4 MiB por documento. Migration idempotente, rollback não destrutivo, ambos schemas, seed Administrator, cascade manual de usuário e dicionário de dados foram atualizados. Grupos personalizados não recebem grant automaticamente.

**Estado de operação:** migration criada em `dev/install/sql/upgrade_f9_25_bdreditor_patterns.sql`, mas não executada. Nenhuma escrita em GitHub, push, deploy ou staging. A prévia privada do Blog e a prévia de Pages publicadas anteriormente não foram refeitas.

**Plano/validação documentados para mudança multi-arquivo:** alteração limitada a API/model, JS compartilhado do BDRE, init, templates/config dos dois contextos, strings, schemas/seed/migration/rollback e documentação. Revisão manual de ACL/owner/context, validação canônica, limite/tamanho, cascata sem FK, e paths por contexto. Não há teste PHP/runtime disponível nesta etapa; `php -l` não foi executado se PHP continuar ausente. Não houve bateria de checker/testes nem chamadas à aplicação/banco.
