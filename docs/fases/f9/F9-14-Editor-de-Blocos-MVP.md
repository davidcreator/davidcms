# Fase 9 · Tarefa 14 — Canvas MVP do editor de blocos Blog

**Data:** 2026-10-24  
**Repositório verificado:** `https://github.com/davidcreator/davidcms`  
**HEAD de `main`:** `921ce1629ad0672627c5a1313dfa6e271c39f7dc` — `feat(f9-13): add legacy blog HTML parser and tooling`  
**F9-13:** biblioteca, contrato, ADR, fixtures e gate estático conferidos byte a byte no commit.  
**Estado:** componente standalone em memória + preview; não carregado no formulário CMS.

## Escopo entregue

- `dev/admin/view/js/blog-block-editor.js`: componente JS nativo sem dependência, com inserter, canvas, inspetor, seleção, adicionar/remover, mover por teclado (`Alt+↑/↓`), drag/drop entre blocos de raiz, undo/redo (até 50 snapshots) e evento `onChange`.
- `dev/admin/view/css/blog-block-editor.css`: layout responsivo, estados de foco/seleção e canvas desktop/tablet/mobile; colunas 2/3 empilham em viewport estreita.
- `tools/preview/f914-editor-mvp.html`: demonstração autocontida para inspeção visual/interativa, sem rede, servidor ou dados reais.

O componente oferece parágrafo, heading, lista, imagem, citação, vídeo, botão, separador, grupo e colunas 2/3. A lista e a coluna usam nós internos. Documento versionado em formato F9-11; validação estrita de tipos, atributos, URLs e relações de filhos.

## Limites não destrutivos

Não alterei `blog_form.twig`, Controller, Model nem fluxo de post. As colunas SQL de HTML continuam sem alteração e o CKEditor atual permanece funcionando como antes. O componente não se monta automaticamente no CMS e não escuta o submit do formulário. A prévia informa que é somente memória: **não salva, não publica, não faz fetch/XHR e não renderiza HTML do visitante**.

Isso foi intencional: a sidecar F9-12 é aditiva e não há aqui autorização para aplicar schema; o validador PHP F9-13 ainda cobre a subset convertível, não todo o conjunto de blocos avançados. Não conectar um save parcial evita que o editor aparente persistir conteúdo que o Model ainda não salva ou rejeite.

## Guardrails

- Construção do DOM via `createElement`/`textContent`; nenhum `innerHTML` com dados editoriais, `eval`, dependência CDN, `fetch` ou SQL.
- Documento inicial inválido bloqueia a edição e serialização; não é substituído silenciosamente por um documento vazio.
- Embed aceita somente HTTPS nos hosts YouTube/Vimeo listados; links de botão aceitam HTTP(S) ou path absoluto local; imagens aceitam somente caminho same-origin em `/image/...`.
- O asset não reproduz o embed/imagem nem publica blocos. Segurança de renderer continua sendo F9-15.
- IDs novos são locais à instância e estáveis durante a sessão; a integração futura deve definir política de IDs persistentes/undo.

## Integração futura obrigatória

Antes de ativar no `blog_form.twig`, F9-16 precisa: alinhar o validador PHP com todos os blocos suportados; confirmar Media Library/path guard; buscar sidecar sem quebrar versões antigas; converter legado em memória com revisão; manter HTML recuperável; validar ACL/CSRF; e gravar JSON + snapshot HTML atomicamente após POST válido. Até isso, este componente é biblioteca/demonstração, não substituição do CKEditor.

## Verificação

`tools/e2e/e2e_f914.sh --check` roda `node --check` e verificações estáticas de contrato/guardrails. Não executa a UI em browser nem testa integração. **Nenhum teste browser/app/PHP/SQL foi executado; nenhum banco ou GitHub foi modificado.** A página preview pode ser aberta localmente em ambiente de revisão.
