# ADR 0052 — F8-08: Projetos do portfólio no shell

**Data:** 2026-10-23 · **Agente:** admin / orquestrador.
**Status:** continuidade solicitada pelo usuário; implementação preparada, aplicação/homologação pendentes.
**Base:** `a9aa816383fc5c40ce39415fb2b9f3257ba9597c`.

## Contexto

O usuário declarou a Tarefa 07 realizada com sucesso. Os **17 arquivos** dessa entrega conferem com os blobs públicos em `a9aa816`; isso verifica publicação, sem inventar detalhes de backend/deploy. Prossegue-se para projetos, sequência anunciada após categorias, sem reabrir tarefas aceitas.

`catalog/project` ainda usava `.dash/.panel`, idiomas empilhados, confirmação inline e JS de capa/galeria por innerHTML. O CRUD já possui sete campos multilíngues, cliente/URL, status/featured, N:N com categorias e galeria ordenada. `MediaUpload` é o componente existente para ambos os uploads.

## Decisão

1. Migrar somente esse CRUD ao padrão F8: titlebar/cards, tabela responsiva, badges, empty state, modal delete, tabs e sidebar. Preservar as rotas `catalog/project`, `.add`, `.edit|ID`, `.delete|ID`, `.upload`, 21 IDs multilíngues, todos os names e controles de imagem/galeria/categorias.
2. `getProjects(array $filters = [])` recebe filtros opcionais nome/status/featured e paginação de 20 solicitada pelo controller. `getTotalProjects` usa os mesmos joins/predicados. Sem argumentos, retorna todos na **ordem antiga `sort_order ASC, project_id DESC`**. Não alterar os métodos de persistência, galeria, categorias, SEO, validação/normalização do payload ou serviço de upload.
3. Manter todas as categorias nos checkboxes, sem LIMIT da grade. Galeria continua declarativa `project_image[N][image|alt|sort_order]`, ordenada na reabertura por `sort_order ASC, project_image_id ASC`; campos por idioma continuam name/summary/description/tag/keyword/meta_title/meta_description. Corpo permanece textarea de seis linhas, sem CKEditor/Blog media.
4. Acrescentar CSRF estável em add/edit/delete/**upload**, além da ACL `catalog/project`. Delete inválido não chega aos unlinks. JSON de upload `{success,path,error}` preservado. Três requests de projetos na regressão legada recebem o token já obtido; a suíte destrutiva não é chamada.
5. Externalizar JS em `admin/view/js/catalog-project.js`, DOM-ready/idempotente. Revelar só a aba do primeiro inválido sem cancelar validação. Capa/galeria usam CSRF/same-origin, não seguem redirects ou repetem POST; busy compartilhado impede salvar/remover/upload concorrente. Retorno/labels usam DOM seguro, não innerHTML. Índices crescem a partir do máximo existente e não colidem após remoções; remover linha só altera a declaração do formulário, não apaga arquivo físico novo.
6. CSS novo apenas sob `.catalog-project-list` e `.catalog-project-form`, prefixo anterior intacto. Normalização local de `&amp;amp;` da paginação, sem mudar a library compartilhada; ASSET_V inalterado, com orientação de cache.
7. Adicionar rótulos necessários em en-us/pt-br/es-eu. Não alterar schema, grants, PHP do núcleo/site/instalador, categorias, CMS, Blog, Appearance ou Composer da aplicação.

## Tool e evidências locais

`tools/e2e/e2e_f808.sh --check` é padrão. PHP **8.4.26**, Twig **3.30.0** (versão do lock), Node **20.20.2**:

- **45 PASS / 0 FAIL** em controller/model/Twig reais, SQL capturado e DB/serviços/model do controller/IO sessão em fixtures. Session sem construtor; MediaUpload guard/delete só em FS privado não servido, separado do MIRROR e limpo. Não comprova banco ou upload HTTP real.
- **12 PASS / 0 FAIL** com JS real e DOM/Bootstrap/fetch simulados: galeria/índices, alt/order existentes, readonly, primeiro inválido, CSRF, preview seguro e concorrência.
- **10 PASS / 0 FAIL** auxiliares HTTP novos + **25 compartilhados** F8-07/F8-06, simulados/FS temporário.
- Lint dos seis PHP e sintaxe JS/CJS/Bash aprovados. Controle negativo com projeto original: **25 PASS / 20 FAIL**, saída 1 esperada.
- Sem servidor, MariaDB, navegador, upload/HTTP real do CMS, deploy, GitHub write ou Actions executados.

O E2E HTTP opt-in preparado tem **15 etapas**: identidade FUI/login, form/i18n, categoria auxiliar fictícia própria, CSRF negativo, três PNGs exclusivos, projeto com N:N/capa/duas galerias, reabrir campos/alt/ordem, edit inválido/v2/flags, filtros, delete negativo/positivo e limpeza de projeto/PNG/categoria próprios. IDs e idiomas vêm do HTML, nunca ID1 presumido para registro.

Reutiliza apenas helpers/transportes/guardas anteriores, sem executar seus fluxos. Exige flags novas, credenciais fictícias com access/modify projeto **e categoria**, loopback/configs `reamur_test`, marcador e fontes/MediaUpload MIRROR=FUI, sem symlinks. Nenhum SQL direto/reset/seed/upgrade. Falha preserva artefatos conhecidos, sem retry de mutação ou limpeza ampla. **Ainda não executado contra o backend do usuário; não é navegador.**

## Consequências/limites

- Clientes dos quatro POSTs precisam de csrf_token; sem bypass legado.
- Upload imediato e comportamento de órfãos/referências são mantidos: remover/substituir linha ou cancelar não coleta arquivos antigos. O teste usa PNGs novos exclusivos; nunca imagem compartilhada real. Delete continua best-effort como a base.
- Validação de URL permanece FILTER_VALIDATE_URL, sem política nova de esquemas. Galeria inválida continua normalizada para caminho vazio e descartada pelo model, sem inventar rejeição/limpeza nova. HTML não é sanitizado por esta tarefa.
- Homologar navegador/foco, preview/galeria, responsividade, temas/contraste, outro perfil ACL e volume real. Guardas não atestam backend/proxy/diretórios mal configurados; proibido produção/túnel/proxy para produção.
- Código preparado/publicado não comprova aplicação ou encerramento da Fase 8.

Guia, aplicação, E2E e rollback: [F8-08 — Projetos do portfólio](../../docs/F8-08-Projetos-Portfolio.md).
