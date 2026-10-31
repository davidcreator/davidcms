# F9-79 — Revisão de legibilidade e estrutura

## Escopo

Ferramenta consultiva, localizada em português, espanhol e inglês. Faz uma leitura heurística do texto BDRE aberto e apresenta métricas aproximadas e apontamentos. A análise é somente leitura: não reescreve, remove nem reorganiza conteúdo. Cada apontamento traz o ID/tipo do bloco e a ação **Localizar bloco**.

Disponível na guia Revisão e na navegação do espaço de trabalho. Pages e Blog continuam independentes.

## Regras heurísticas

- Parágrafo/bloco de prosa com mais de 120 palavras: sugestão de divisão.
- Frase com mais de 35 palavras: sugestão de simplificação/divisão; o analisador indica a frase mais longa por bloco.
- Seção com mais de 500 palavras sem subtítulo de nível inferior antes do próximo título do mesmo nível: sugestão de subdivisão.
- Contagem aproximada de palavras e tempo estimado de leitura: 200 palavras/minuto para português/espanhol e 225 para inglês, arredondado para cima e com mínimo de um minuto quando há texto.

As verificações cobrem parágrafos, itens de lista, citações, avisos, detalhes e células de tabela. Segmentação de frases e contagem de palavras são simplificações baseadas em texto e pontuação. Os limites não representam uma nota definitiva de qualidade e devem ser interpretados considerando idioma, público e contexto. Documentos BDRE inválidos são rejeitados antes da análise.

## Ícones

- **Livro aberto** na navegação e no comando: comunica leitura e revisão de conteúdo.
- **Parágrafo** nos apontamentos de parágrafo/frase; **título** no apontamento de seção: reforçam visualmente o tipo de conteúdo.
- **Relógio** junto à estimativa de tempo; **ícone de parágrafo** junto à contagem de palavras.

Todos são SVGs embutidos, decorativos para tecnologias assistivas (os rótulos adjacentes comunicam a função), sem dependência de fontes ou imagens externas.

## Arquivos alterados

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `dev/admin/controller/cms/page.php` — cache-bust Pages.
- `dev/admin/controller/blog/blog.php` — cache-bust Blog.
- `tools/f9-57-static-battery.py`
- `tools/f9-79-readability-smoke.js`

Cache-bust: `threejs-26-readability-review`.

## Validação

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-79-readability-smoke.js` — três verificações, estimativas, localização, não mutação, métricas para documento limpo/vazio/inválido e PT/ES/EN.
- `node tools/f9-78-link-review-smoke.js` e os smokes F9-77/F9-76 para regressão.
- `python3 tools/f9-57-static-battery.py` — cobertura estática consolidada.
- CSS analisado com `tinycss2`.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação de runtime ou medição linguística científica.
