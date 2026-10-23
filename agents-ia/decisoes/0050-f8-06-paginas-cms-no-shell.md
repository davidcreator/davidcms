# ADR 0050 — F8-06: Páginas CMS no shell administrativo

**Data:** 2026-10-23 · **Agente:** admin / orquestrador.
**Status:** escopo aprovado pelo usuário; implementação preparada para aplicação e homologação.
**Base:** `98a035f2007e627815f57993ac0125182cdaf5f8`.

## Contexto

O usuário declarou a Fase 8 / Tarefa 05 concluída e escolheu **Fase 8 / Tarefa 06 — Páginas CMS** entre as frentes propostas. Essa confirmação é aceite do usuário, não um relato de testes de produção executados pelo agente. A numeração passa somente por fases e tarefas, sem novo nível de ajuste.

`cms/page` ainda usava `.dash`, `.panel`, blocos de idiomas empilhados, lista sem filtros/paginação e `confirm()` inline. Seus POSTs não exigiam CSRF. O shell, estilos, modal de delete, contadores e Bootstrap locais já existem (ADRs 0045/0046/0048). O conteúdo de páginas é textarea simples e o schema não prevê capa/upload.

## Decisão

1. Migrar somente lista/form de `cms/page` para os componentes existentes: titlebar, cards, tabela responsiva, badges, empty state, tabs por idioma e sidebar de status/ordenação/ações. Não alterar shell, Appearance, Localization, Blog, catalog público ou schema.
2. Preservar rotas, `page_description[language_id][campo]`, os 15 IDs por três idiomas, `status`, `sort_order`, título EN obrigatório, HTML/Unicode e unicidade global de keywords. O corpo continua **textarea**, sem `data-rte`, CKEditor, mídia ou upload novos.
3. Aplicar filtros server-side por título exibido (idioma ativo com fallback EN) e status; paginação de 20, contagem com os mesmos predicados, ordenação antiga e recuo para a última página válida. `getPages(array $filters = [])` continua retornando tudo, sem LIMIT, quando chamado sem argumentos. Acrescentar `getTotalPages` ao model, sem SQL no controller.
4. Exigir CSRF estável da sessão em add/edit/delete, mantendo ACL `cms/page` e delete somente POST. Form e delete recebem hidden token; ações modify são ocultadas para readonly. Os dois scripts legados que fazem POST em `cms/page` passam a enviar o token já obtido da mesma sessão; não são executados automaticamente.
5. Usar o modal global de confirmação por `data-rms-confirm-delete`, sem JS inline. Um script local mínimo, `admin/view/js/cms-page.js`, revela a aba de um campo inválido antes do foco da validação nativa. Não cancela essa validação, não submete o formulário e não modifica `admin.js`.
6. Acrescentar só CSS de paginação sob `.cms-page-list`, usando variáveis Bootstrap. A library gera markup sem `.page-link` e escapa ampersands duas vezes; o controller normaliza apenas `&amp;amp; → &amp;` no HTML gerado dessa paginação, com query codificada. Não decodifica HTML do usuário nem modifica o núcleo. Demais módulos ficam intactos.
7. Adicionar somente rótulos CMS necessários nos três idiomas. Reutilizar o ASSET_V existente e orientar invalidação de cache do CSS; nenhuma versão global é alterada.

## Verificação

- **30 PASS / 0 FAIL**: controller/model e Twig executados em PHP **8.4.26**, Twig **3.30.0** (versão do lock), com DB/model do controller, serviços e IO de sessão em fixtures. SQL do model real é capturado, não executado em MariaDB. Session real é instanciado sem construtor para dados em memória.
- **6 PASS / 0 FAIL**: script real de validação com DOM/Bootstrap simulados.
- **16 PASS / 0 FAIL**: helpers HTTP com respostas/fetch simulados, incluindo cookies, redirects externos, 307 sem retry e descoberta de language_id.
- Lint dos seis PHP de entrega, sintaxe dos JS/CJS e Bash. Controle negativo com fontes originais: **12 PASS / 18 FAIL**, saída 1 esperada.
- Sem PHP-FPM/servidor, MariaDB, navegador real, upload, deploy ou GitHub Actions executados pelo agente. As dependências CLI de QA foram preparadas isoladamente, sem alteração do Composer/vendor do projeto ou clone completo.

## Tool E2E e consequências

`tools/e2e/e2e_f806.sh --check` é o padrão local seguro. `--http` é opt-in: reutiliza **somente** o preflight F8-05 já versionado, conferindo loopback, configs literais `reamur_test`, marcador de descarte e MIRROR/FUI; compara também os nove arquivos CMS. Não chama o fluxo F8-05 nem a suíte que recria o banco.

O modo HTTP confere um probe temporário do FUI antes de enviar credenciais fictícias, mantém cookies/tokens só em memória, cria/edita/reabre uma página própria, verifica CSRF/filtros e exclui somente o ID descoberto e validado. Em falha preserva a página para inspeção, sem retry de POST ou limpeza ampla. **Ainda não executado contra o backend do usuário; não equivale a teste de navegador.**

- Clientes que fazem POST em `cms/page` precisam enviar `csrf_token`; isso é uma mudança intencional de contrato de segurança.
- Sem DDL, upgrade SQL, grants novos ou editor rico. Nenhum arquivo `system/**`, `catalog/**`, `install/**` ou de Appearance/Blog é alterado.
- Homologação real de backend, foco da aba EN inválida, responsividade, temas e contraste permanece com o usuário. A Fase 8 não é encerrada por esta entrega.
- Permanecem separados: saneamento global da paginação, sanitização de HTML e suporte/licença/segurança do CKEditor legado, cujo uso não é ampliado.

Guia, aplicação, E2E, cache e rollback: [F8-06 — Páginas CMS](../../docs/F8-06-Paginas-CMS.md).
