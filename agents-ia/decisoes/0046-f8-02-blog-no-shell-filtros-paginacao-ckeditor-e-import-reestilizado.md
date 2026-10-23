# ADR 0046 — F8-02: Blog migrado para o shell admin — filtros server-side, paginação, CKEditor local, importador reestilizado

**Status:** aceito
**Data:** 2026-10-22
**Contexto:** F8-02, segunda tela da migração visual incremental (ADR 0045). Base: HEAD `8995df4ccf6d` (F8-01 aplicado). Escopo confirmado pelo usuário: **blog completo** (lista + form add/edit + importador WP), com **CKEditor** (já entregue nos assets) e **filtros/paginação server-side**.

## Decisões

1. **Compat de contrato preservada**: todos os `id`/`name`/`action` dos insumos (list/form/import) permancem exatamente como nas baterias F7-01/F7-03 (inputs `input-{title,keyword,description,meta-title,meta-description}-{N}`, `name="status"`, `action=preview/run`, delete por POST). As baterias antigas seguem verdes sem toque.

2. **Lista (`blog/blog`)**:
   - **Filtros server-side** (`filter_title` LIKE contra título do idioma atual + fallback EN, `filter_status` all/0/1) em `model/blog/blog::getPosts($filters)` + `getTotalPosts($filters)` — assinatura nova reverse-compatível (sem args = comportamento antigo, mas agora traz `image`).
   - **Paginação 20/página** com a `Pagination` library do framework (placeholder `{page}`, URL limpa na página 1), preservando filtros nos links. Página além do fim recua para a última válida.
   - **Badges de status** (publicado/rascunho), thumbs de capa (URL absoluta → direto; path relativo → `catalogUrl()` derivada de `site_url` trocando `/admin/` por `/`), "ver no site" só em publicados, **modal Bootstrap de delete** (`#rmsDeleteModal`, form por linha com `data-rms-confirm-delete`; fallback `confirm()` nativo quando o bundle JS não subir), **empty state** com CTA, alertas dismissíveis.
   - Controle de excesso de página após deleções.

3. **Form (`blog/blog.add|.edit`)**:
   - Layout 2 colunas: conteúdo à esquerda (tabs por idioma via `nav-tabs` Bootstrap, com persistência de âncora `#lang-<code>` via admin.js), **sidebar de publicação** à direita (status em `form-switch`, `datetime-local`, card de capa com preview por `data-preview-base`, ações grandes).
   - **CKEditor 4 local** (`view/js/ckeditor/ckeditor.js` do pacote do usuário — nenhum CDN) aplicado por `textarea[data-rte]` no admin.js, com toolbar enxuta; se o build falhar/ausentar, o textarea segue funcional (graceful degradation). Carregado via `Document::addScript` → header.twig passou a renderizar o loop `scripts` (não existia — era suficiente porque nada usava; **mudança estrutural mínima e aditiva** no shell F8-01).
   - **Contadores de caracteres** em inputs `maxlength[data-counter]`, hint de status reativo via data-attrs (i18n sem JS hardcodado), preview da capa digitada.

4. **Importador WP (`blog/import`)**: formulário em dois cards (Fonte / Janela da amostra), resultado com tabela + badges por ação (importado/suffix/pulado/erro), ícone de imagem ok/falha, **log colapsável** (Bootstrap collapse) — somente após run.
   - `baseData()` passa as chaves novas `text_source_card`/`text_pagination_card` — aprendizado: **nunca mais `|default()` com texto fixo no twig multilíngue** (o default 'Fonte' vazou em todos os idiomas até o controller prover a chave).

5. **CSS**: seção F8-02 no fim do `admin.css` (~190 linhas): `.page-titlebar`, `.filter-bar`, `.data-card`, `.badge-status--*`, `.cover-thumb`, `.empty-state`, `.tabs-lang`, `.char-counter`, `.cover-preview`, `.log-block` — tudo tema-aware (`data-bs-theme` light/dark via variáveis Bootstrap), sem hardcode de cor (exceção: ajuste dark do badge draft).

6. **JS** (`admin.js`, ~160 linhas novas, tudo guardado por seletor): contadores, modal delete, init CKEditor, tabs por âncora, hint do switch de status, preview de capa.

## Verificação (lab :8090, MariaDB 11.8.6 — banco recriado a cada run)

- `tools/e2e/e2e_f802.sh` — **13/13**: shell+zero-CDN, badge total=25 + paginação p1/p2 com contagens exatas, filtro status (10 rascunhos, select preservado), busca LIKE (input ecoado), badges/thumbs/link-site, modal no DOM + **delete POST real** (25→24 sem órfãos), form com 15 ids compat + 3×`data-rte` + switch + `data-preview-base` + `data-counter` + script CKEditor referenciado, asset CKEditor 200, **add multilíngue completo persistido** (compat F7-01) + buscável, **edit carrega valores e troca status→badge**, import com cards/ids/botões compat (F7-03), i18n ×3 das 3 telas + guards + `php -l`.
- Regressão: **F2 ×7, F3 ×5, F4 ×8, F5 ×3, F7 ×3, F8-01 — 26 suítes, todas verdes** após a migração.

## Consequências

- Próximos lotes (F8-03 user/setting, F8-04 sale/marketing) seguem este padrão: filtros server na lista, form 2 colunas + sidebar, `tabs-lang` multilíngue, `data-card` para tabelas, modal de delete, `data-rte` para rich text, sem `|default()` textual no twig, ids/names imutáveis.
- O header do shell agora honra `Document::addScript` (loop no twig) — qualquer módulo pode carregar JS local por página sem CDN.
- Fica para F8-05+: editor de mídia real (picker de capa no lugar do campo texto), filtro por data/autor quando houver volume.

## Adendo 2026-10-23 — F8-05 · Ajuste 02: configuração isolada do CKEditor

**Base:** `c2df68993259e53fcc05080e6ea42971de7eebd8` (A01 incorporado). **Estado deste adendo:** implementação incremental preparada; aplicação/homologação no ambiente do usuário pendentes. Não encerra a Tarefa 05/Fase 8.

### Evidência e decisão de implementação

O `dev/admin/view/js/ckeditor/config.js` herdado usa `$(this).attr('data-lang')`, habilita `reamur,codemirror` e desliga a verificação de versão. O plugin `reamur` depende de jQuery/modal antigo e da rota ausente `common/filemanager`. O shell não carrega jQuery. No core local 4.22.1, o callback de configuração é executado antes dos overrides comuns de `replace()`; `customConfig`, por sua vez, é considerado antes de carregar o arquivo.

Por isso, o consumidor atual em `dev/admin/view/js/admin.js` passa **`customConfig: ''`**, sem alterar vendor/config global, importar jQuery ou implementar um filemanager. Usa `extraPlugins: ''`, remove explicitamente os dois plugins legados e deixa vazias as URLs de browse/upload. O diálogo Image padrão/URL e Source continuam na toolbar; a biblioteca de capas do Blog não é integrada ao corpo do editor.

O idioma da UI vem do `<html lang>` do painel: `en-us → en`, `pt-br → pt-br`, `es-eu → es`, com normalização e fallback `en`. Mantêm-se toolbar enxuta, altura 320, formatos, `allowedContent: true`, contrato HTML, ids/names e submit nativo (`autoUpdateElement: true`). Não se traduz conteúdo nem se troca o código das tabs/`language_id`.

O boot aguarda o DOM se necessário, evita instância duplicada e mantém textarea quando o editor/`replace` está ausente. Em erro síncrono, tenta remover instância parcial com `destroy(true)` e restaura a visibilidade sem forçar atualização do campo. **Falhas assíncronas posteriores de skin/idioma/plugin não têm recuperação automática garantida; o marcador indica retorno de instância, não prova de `instanceReady`.**

### Verificação e fronteiras

- Tool: `tools/e2e/e2e_f805_a02_ckeditor.sh` (`--dom` e `--regression`); runner `tools/e2e/e2e_f805_a02_ckeditor_dom.cjs`.
- A02: **18 PASS / 0 FAIL** no admin.js real com **DOM/CKEDITOR simulados**, incluindo configuração legada real fornecida em memória. Controle negativo no admin.js original da base: **4 PASS / 14 FAIL**, saída 1 esperada.
- Regressão A01 DOM simulada: **10 PASS / 0 FAIL**, preservando `blog-media.js`. Não houve navegador/core CKEditor real, PHP, HTTP, banco, upload, persistência ou deploy nesta preparação. O wrapper A02 não chama backend/suítes destrutivas.
- Os resultados F8-02/26 suítes acima são **histórico da decisão anterior**, não execução desta rodada. A suíte `e2e_f802.sh` citada historicamente não foi encontrada na árvore-base atual e não é dependência desta entrega.
- Um único arquivo de runtime muda, somente seu bloco RTE. Tema/sidebar, delete, tabs, status, preview, biblioteca de capas A01, importador, rotas, schema e ACL/CSRF não são alterados.
- O core **4.22.1 continua sem suporte comunitário**; versão/licença/segurança ficam como dívida separada. A02 não suprime a verificação/aviso de versão. `allowedContent: true` permanece um contrato, não sanitização.

Aplicação, Tool, cache, rollback e critérios de homologação: [guia F8-05 A02](../../docs/F8-05-A02-Configuracao-Isolada-CKEditor.md). O passo seguinte, após aplicar/homologar este ajuste, é a **Validação 03** integrada (criar/editar, editor/capa, upload/seleção, salvar/reabrir).
