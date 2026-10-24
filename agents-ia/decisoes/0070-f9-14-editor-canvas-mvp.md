# ADR 0070 — F9-14: UI de blocos isolada até o save seguro

**Data:** 2026-10-24 · **Status:** componente standalone, não ligado ao formulário.  
**Base:** `davidcreator/davidcms` @ `921ce1629ad0672627c5a1313dfa6e271c39f7dc`.

## Contexto

O Blog ainda usa `blog_post_description.description` (HTML) no form e CKEditor. F9-12 prepara uma sidecar, mas não executou migration. O validador PHP F9-13 converte um subset legado e não aceita ainda todos os blocos iniciais avançados. Sem save transaction/PHP validator alinhado, ligar um canvas ao botão Salvar pode perder alterações ou criar falsa indicação de persistência.

## Decisão

1. Implementar canvas nativo como componente JS/CSS, sem dependência externa, encapsulado por `ReamurBlogBlockEditor.mount`.
2. Incluir inserter, inspetor, edição local, remoção/reordenação por teclado/drag root, undo/redo e previews responsivos.
3. Suportar os tipos e layouts do contrato F9-11 na memória, com validação JS restritiva. Não assumir que o validador PHP atual já os suporta.
4. Não carregar a UI no `blog_form.twig`, não remover CKEditor e não alterar controller/model/form nem descrição legada.
5. Incluir preview autocontido com texto explícito de memória; sem rede, dados de posts, renderer ou submission.
6. Documento de entrada desconhecido/inválido bloqueia interação e serialize; nunca substitui por bloco vazio de forma silenciosa.
7. Persistência, converter ao abrir e eventual substituição do editor ficam para integração posterior após F9-15/F9-16 e autorização de schema.

## Alternativas

- Trocar textarea/CKEditor agora: rejeitado, pois a persistência e renderer não estão integrados.
- Enviar um hidden JSON field que o Controller ignora: rejeitado por falsa confirmação de salvamento.
- Salvar descrição como HTML no browser: rejeitado até renderer allowlisted F9-15; risco de raw HTML.
- Incluir CDN/editor third-party: rejeitado pelo contrato de runtime nativo/local.

## Consequências

- F9-14 fornece UI para revisão e integração, sem alterar os posts existentes.
- O preview e o componente não são ainda editor de produção.
- F9-16 deve alinhar o validador PHP, schema aplicado em ambiente autorizado e gravação de JSON + snapshot HTML numa operação segura.
