# Fase 9 · Tarefa 24 — Blocos de conteúdo e mídia

**HEAD de `main` consultado novamente antes da finalização:** `af3421cc1b3bd4636d8bb44d6d3d4e83e0fb5eb9` (commit F9-23; igual ao baseline usado no início). A árvore remota não contém `ROADMAP.md`; o escopo foi cruzado com o roadmap/aceite F9-24 preservado em `/home/user/entregas/F9-21/docs/F9-21-Roadmap-Editor-Avancado.md` (“Blocos de conteúdo e mídia”). A fonte local foi composta de arquivos pontuais e seus blobs-base estão listados em `F9-24-Base-Hashes.json`; nenhum clone foi feito.

**Escopo de referência F9-24:** galeria; cover; imagem com caption/alinhamento/controles responsivos; previews administrativos de embed e botão; padrões reutilizáveis. Cada tipo tem contrato JSON e renderer allowlist, previsão mobile e referência segura a paths locais. Aceite previsto: JS/PHP/renderer sincronizados, sem CSS/HTML livre, desktop/mobile funcional e sem remoção inadvertida de mídia.

## O que o pacote implementa localmente

- `core/gallery` em formato v2, com 2/3/4 colunas, alinhamento `content/wide/full`, de 1 a 20 itens e somente filhos `core/image`.
- `core/cover` v2, com imagem local, texto alternativo, alinhamento e filhos limitados a heading/paragraph/quote/button (1 a 12).
- `core/image` v2 ganha caption em texto puro, alinhamento, escala e escala mobile em enums fixos. V1 continua limitado a `src/alt/title` e segue válido.
- Renderer server-first: validação estrita antes de HTML; escapes contextualizados; classes públicas fixas; nenhum `style`/HTML em dados. Cover usa imagem como elemento e overlay CSS, sem URL em CSS inline.
- Galeria responsiva em tablet/mobile; controles de alinhamento, tamanho e tamanho móvel no inspector.
- Prévia de imagem/capa na administração; vídeo usa preview local estático sem iframe externo; botão usa o próprio editor de texto com tratamento visual e não navega.
- Quatro padrões locais inseríveis: destaque em duas colunas, citação+CTA, introdução de seção e hero cover. Não são salvos como templates globais. O padrão cover fica bloqueado para salvar até receber uma imagem local.
- A Media Library existente seleciona a imagem para `core/image` e `core/cover`; imagens dentro de galeria são editadas selecionando cada filho.

## Limites de conteúdo e integridade

- Galeria/capa são v2-only; nenhum incremento da migration ou schema SQL.
- `caption`, alt e title são plain text escapado; limite de texto 4096 bytes.
- `align`, `size`, `mobile_size`, colunas e classes CSS vêm de allowlists sincronizadas em JS/PHP/CSS. URLs continuam paths de imagem local (`image/` ou `view/image/blog/`) apenas.
- Conteúdo não é apagado por mudanças de estilo. Transformar ou inserir padrões mantém o contrato JSON existente. Mídia ainda não escolhida mantém o documento inválido e impede salvar; não há fallback para conteúdo vazio.
- Preview de vídeo no admin é deliberadamente uma representação segura do provider, não playback. A página pública continua usando o embed existente e sandboxed.

## Fontes pontuais no pacote

Alterados: `dev/system/library/blog/block_document.php`, `block_renderer.php`, `dev/catalog/view/css/blog-blocks.css`, `dev/admin/view/js/blog-block-editor.js`, CSS do editor, controller e traduções. `blog_form.twig` (host/hidden fields existentes) e `blog-block-editor-init.js` (binding existente) são fontes unchanged copiadas como pontos de integração para revisão; não há arquivo separado `block_editor.twig` neste pacote. Nenhum SQL/DDL, endpoint remoto ou controle de publicação foi alterado.

## Verificação e pendências

- Executados apenas `node --check` (JS do editor/init/checker), parse JSON/fixtures e checker estático de contratos/idiomas. Nenhum PHP/SQL/browser/app/E2E funcional, banco ou migration foi executado.
- Isso não valida sintaxe/runtime PHP, renderização real, media picker, focus/ARIA, CSS no catálogo ou integração efetiva no CMS. O plano browser/staging está em `tools/e2e/README.md` e requer autorização explícita e ambiente descartável.

## Próxima evolução sugerida

F9-25: padrões e templates próprios do CMS, preview em contexto e confirmação de alterações não salvas; qualquer persistência de templates/padrões ou autosave precisa de decisão e política de permissões separadas.
