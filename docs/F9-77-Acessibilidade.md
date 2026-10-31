# F9-77 — Revisão de acessibilidade do conteúdo BDRE

## Escopo

Ferramenta consultiva, localizada em português, espanhol e inglês, para revisar o documento BDRE atualmente aberto no editor. A análise é somente leitura: não grava, corrige, remove ou reorganiza blocos. A pessoa revisora pode usar **Localizar bloco** para voltar ao editor e selecionar o bloco apontado.

A ferramenta está disponível na navegação do espaço de trabalho e na guia **Exibir**. A auditoria não substitui avaliação manual, não é certificação WCAG e apresenta potenciais problemas como alertas para verificação contextual.

## Verificações

- Títulos sem texto e saltos ascendentes de nível na hierarquia de títulos (por exemplo, H2 para H4).
- Parágrafos sem texto visível.
- Blocos de imagem e capa com texto alternativo vazio. O resultado é informativo: imagens decorativas podem intencionalmente não ter descrição. Um documento BDRE inválido (por exemplo, com atributo obrigatório ausente) é rejeitado antes da auditoria.
- Botões sem rótulo visível ou com rótulo genérico comum, como “clique aqui” ou “saiba mais”.
- Tabelas sem células marcadas como cabeçalho.

São verificações heurísticas limitadas ao conteúdo BDRE e aos tipos de bloco suportados. Não avaliam contraste, qualidade semântica, ordem de foco ou contexto completo da página.

## Segurança e preservação

O analisador primeiro valida a estrutura BDRE; documentos inválidos não são analisados. Os apontamentos incluem o ID e o tipo do bloco, mas o documento original é mantido intacto. A ação de localização apenas troca de volta ao editor, seleciona o bloco existente e o focaliza. A implementação não executa HTML arbitrário nem `custom_js`, não combina Pages com Blog e não altera salvamento, banco de dados ou migrações.

## Arquivos alterados

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `dev/admin/controller/cms/page.php` — invalidação de cache dos assets Pages.
- `dev/admin/controller/blog/blog.php` — invalidação de cache dos assets Blog.
- `tools/f9-57-static-battery.py` — verificações estáticas de integração e preservação.
- `tools/f9-77-accessibility-smoke.js` — smoke sem DOM/browser para os resultados, localização, ausência de mutação e documentos inválidos.

Cache-bust: `threejs-24-accessibility-review`, aplicado de forma independente nos controladores Pages e Blog.

## Validação executada

- `node --check dev/admin/view/js/blog-block-editor.js` — aprovado.
- `node tools/f9-77-accessibility-smoke.js` — aprovado: os sete tipos de apontamento, IDs de bloco, ausência de mutação, mensagens PT/ES/EN, documento sem apontamentos e rejeição de BDRE inválido.
- `python3 tools/f9-57-static-battery.py` — validações estáticas de regressão.
- CSS validado com `tinycss2` quando disponível.

Não foi possível executar PHP CLI nem browser real neste ambiente. Portanto, não se afirma validação de runtime, interação visual real ou conformidade WCAG.
