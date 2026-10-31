# F9-81 — Comparador de revisões locais

## Escopo

Ferramenta somente de leitura para comparar o rascunho BDRE atual com uma das revisões temporárias disponíveis na sessão da página. Usa o histórico local existente, limitado pelo editor a até cinco revisões recentes (janela de 24 horas). Pages e Blog continuam usando chaves de rascunho separadas.

A comparação exibe blocos adicionados, removidos ou alterados, com prévias textuais anterior/atual. Identifica alterações em conteúdo, tipo, atributos, contêiner e ordem de irmãos; diferenças de ordem não são confundidas com deslocamentos de índice causados apenas por inserções/remoções. Para itens presentes na versão atual, **Localizar bloco** retorna ao editor e seleciona o bloco. Itens removidos são apresentados sem ação de foco porque já não existem no rascunho atual.

A ferramenta não restaura, salva, publica, escreve no histórico nem altera conteúdo. Versões BDRE v1 são atualizadas em memória para comparação com v2, sem modificar os snapshots. Documentos que não passam na validação BDRE não são comparados. Uma comparação sem diferenças é indicada explicitamente.

## Navegação e interface

- Disponível na guia Revisão e na navegação do espaço de trabalho.
- Exibe revisões locais disponíveis com data/hora e contagem de blocos.
- A revisão selecionada é comparada com o rascunho atual; não há alteração automática ao trocar a seleção.
- Ícone **comparar** (duas colunas) identifica a ferramenta e cada comando de comparação.
- CSS responsivo; prévias usam `textContent` do editor, sem HTML arbitrário.

## Arquivos alterados

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `dev/admin/controller/cms/page.php` — invalidação de cache Pages.
- `dev/admin/controller/blog/blog.php` — invalidação de cache Blog.
- `tools/f9-57-static-battery.py`
- `tools/f9-81-revision-compare-smoke.js`

Cache-bust: `threejs-28-revision-comparison`.

## Validação

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-81-revision-compare-smoke.js` — adição, remoção, edição, reordenação, mudança de contêiner, equivalência v1/v2, não mutação e revisões iguais/inválidas.
- Smoke tests de regressão F9-80 a F9-76 e bateria estática consolidada.
- CSS analisado com `tinycss2`.

Não há PHP CLI nem navegador real disponível neste ambiente; a interação visual/runtime não foi validada em browser.
