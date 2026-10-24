# ADR 0067 — F9-11: Contrato de editor próprio em blocos para o Blog

**Data:** 2026-10-24 · **Agente:** blog / editor de conteúdo.  
**Status:** requisito/arquitetura aprovado para decomposição; implementação e schema ainda não autorizados por esta ADR.  
**Base:** `davidcreator/davidcms` @ `61f75381265bb9b4f20741d266a5f8db61c8664b`.

## Contexto

O Blog atual edita descrição HTML multilíngue em CKEditor 4 local. A coluna `blog_post_description.description` é `MEDIUMTEXT`; admin e catálogo tratam esse conteúdo como HTML, e a frente imprime descrição com `|raw`. O mantenedor pediu uma expansão grande, semelhante à experiência Gutenberg, sem transformar o ReamurCMS em WordPress.

Decisões do mantenedor nesta conversa: editor próprio, blocos/layout avançado no MVP, conteúdo antigo convertido ao editar.

## Decisão

1. Criar experiência nativa ReamurCMS (Gutenberg-inspired), sem runtime/pacotes WordPress, mantendo escopo inicial apenas `blog/blog`.
2. Utilizar árvore de blocos JSON versionada e multilíngue, com nós estáveis e allowlist de tipos/atributos; containers aninháveis permitidos somente pelo contrato.
3. Incluir desde o MVP grupos/colunas (2/3), empilhamento móvel, preview responsive, inserter, inspector, reordenação por teclado/drag-drop e undo/redo.
4. Ao editar HTML legado, conversor roda em memória e oferece preview. Persistência do documento convertido ocorre apenas após salvar explicitamente com validação bem-sucedida; abrir/fechar não altera o post.
5. Elementos desconhecidos/perigosos são preservados em fonte recuperável e impedem publicação até revisão ou representação segura. Nunca descartar silenciosamente nem interpretar HTML arbitrário como block trusted.
6. Não reutilizar `description` sem nova decisão: a decisão de schema/versão/fallback e migration/rollback virá em F9-12. Guardar original legado recuperável durante conversão.
7. Renderer público de blocos futuro será allowlisted e escapará texto/atributos; a descrição HTML existente e seu `|raw` não têm sua segurança retroativamente garantida por esta ADR.
8. CKEditor 4 e formato HTML legado continuam intactos até que editor/conversor/renderer tenham testes e rollout gradual aprovados.

## Alternativas consideradas

- **Instalar Gutenberg oficial/WordPress packages:** rejeitado agora; acopla build/React/pacotes, incompatível com o escopo próprio e com o pipeline atual simples.
- **Trocar só CKEditor por outro WYSIWYG:** rejeitado como solução final; não entrega modelo de blocos/layout/inspector comparável.
- **Sobrescrever `description` com JSON:** rejeitado sem migration; quebra o catálogo legado que espera HTML.
- **Converter ao abrir e salvar automaticamente sem confirmação:** rejeitado em sua parte destrutiva; conversão pode ocorrer ao abrir, mas persistência só após ação explícita de salvar e backup/rollback.
- **Permitir HTML/JS custom dentro de bloco:** rejeitado por XSS e por confiança excessiva no admin.

## Consequências

- A trilha é maior e dividida em schema, parser/conversor, UI, renderer e rollout.
- A escolha de migrar ao editar é respeitada sem mutação em GET ou perda silenciosa.
- Deve existir uma estratégia explícita para blocks existentes não suportados e para versão futura do schema.
- Nenhum conteúdo real nem tabela foi alterado na F9-11.

## Validação

`tools/e2e/e2e_f911.sh --check` valida o contrato machine-readable e o roteiro técnico. Não executa PHP, JS de runtime, navegador, banco nem conversão real.
