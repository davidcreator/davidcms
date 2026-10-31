# F9-80 — Checklist pré-publicação

## Escopo

Tela de revisão somente leitura que reúne os apontamentos das ferramentas BDRE F9-77, F9-78 e F9-79 em um só lugar:

- **Acessibilidade** — títulos, imagens, botões e tabelas.
- **Links e destinos** — HTTP externo, rótulos que mostram URL e destinos repetidos.
- **Legibilidade** — parágrafos/frases extensos e seções longas sem subtítulos.

Os resultados permanecem agrupados por categoria e cada apontamento conserva seu ID de bloco para **Localizar bloco** no editor. A tela também mostra totais de apontamentos, avisos e sugestões, além das estimativas de palavras e tempo de leitura já fornecidas pela revisão de legibilidade. Conteúdo BDRE inválido é rejeitado antes da consolidação.

A checklist não publica, salva nem altera o documento; não visita sites externos e não cria uma nova classe de análise. Apenas agrega os três analisadores já existentes. Os resultados são orientativos; não significam aprovação de publicação, certificação de acessibilidade, verificação de links funcionando ou garantia editorial.

## Ícones

- Ícone **checklist** (lista marcada) para a ferramenta agregadora.
- Ícones das categorias — check/acessibilidade, link/destinos e livro aberto/legibilidade — nos títulos das seções.
- Ícones de resumo para total, avisos, sugestões e leitura estimada.

Os ícones são SVGs inline e decorativos para tecnologia assistiva; os rótulos e títulos adjacentes comunicam seu significado. A tela e os cartões são responsivos e respeitam a preferência de cores forçadas.

## Arquivos alterados

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `dev/admin/controller/cms/page.php` — cache-bust independente.
- `dev/admin/controller/blog/blog.php` — cache-bust independente.
- `tools/f9-57-static-battery.py`
- `tools/f9-80-preflight-smoke.js`

Cache-bust: `threejs-27-prepublication-checklist`.

## Validação

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-80-preflight-smoke.js` — verifica agregação das três áreas, total/severidade, métricas, IDs de bloco, não mutação, localização e documentos limpos/inválidos.
- Smoke tests das ferramentas F9-79, F9-78, F9-77 e F9-76.
- `python3 tools/f9-57-static-battery.py` — regressão estática consolidada.
- CSS analisado com `tinycss2`.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação de runtime ou interação real no navegador.
