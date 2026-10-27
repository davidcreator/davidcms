# F10-01 — Conteúdo CMS associado às rotas públicas do Catalog

Base: HEAD `4640ac241d5b0d433fe739b55e26628c98a86097`.

## O que este patch entrega

- Adiciona `rms_page.system_key` e semeia dez registros editoriais por rota, com títulos em en-us, pt-br e es-eu.
- Apresenta as páginas de sistema dentro de Pages (`cms/page`), identifica a chave fixa e impede exclusão ou SEO keyword independente.
- Resolve o conteúdo ativo no Catalog para Home, Portfolio/listagem e detalhe, Blog/listagem e post, Ferramentas/listagem e detalhe, Contato, Orçamento e 404.
- A Home pode substituir suas seções editoriais estáticas pelo conteúdo CMS, mantendo grids, posts recentes, ferramentas, depoimentos, clientes e formulários nos módulos especializados.
- Reutiliza o editor/renderer de blocos atual. A migration também degrada sem erro quando `system_key` ainda não existe, até sua aplicação.
- Atualiza `schema.sql`, `schema-complete.sql`, ADR 0078 e documentação do CMS/dicionário/arquitetura.

## Banco

`dev/install/sql/upgrade_f10_01_cms_catalog_routes.sql` é aditiva/idempotente. Ajuste o prefixo `rms_` se o banco usa outro `DB_PREFIX`. **Não foi executada**; antes de executá-la, faça backup e confirme o schema-alvo. Rollback de código não remove coluna nem conteúdo.

## Fora deste primeiro patch

A ferramenta **Temas** (editor visual, templates Twig versionados/prévia/rollback e cenas Three.js aprovadas) ainda não está implementada neste F10-01; permanece como próximo vertical slice. Este pacote não deve ser confundido com a entrega completa do escopo Pages + Temas.

## Verificação realizada

- Conferência estática de que as dez chaves do roteador coincidem com a migration e com os dois schemas.
- Leitura do diff das rotas CMS/Admin/Catalog e documentação.
- Não houve acesso ao banco/localhost, execução de migration, browser QA, push ou deploy. PHP CLI não está disponível nesta sessão; `php -l` ficou pendente.
