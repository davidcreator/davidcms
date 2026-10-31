# F9-82 — Exportação para Markdown

## Escopo

Exportação local de um documento BDRE validado para arquivo UTF-8 `.md`, com prévia textual literal e notas sobre recursos que Markdown não representa. O arquivo é gerado no navegador; não há requisição de rede, upload, alteração do BDRE, salvamento no site ou mudança do fluxo de exportação JSON existente.

Acesso pela guia **Exibir** (comando Markdown) e pela área **Exportar**. A prévia pode ser revisada antes do download. Em arquivos de prévia com mais de 200.000 caracteres, a tela limita a amostra; o download contém todo o Markdown. O arquivo fica limitado a 8 MiB para exportação segura.

## Conversões

- Títulos, parágrafos, listas ordenadas/não ordenadas, citações, separadores, código pré-formatado e conteúdo inline (negrito, itálico, tachado, código e links).
- Imagens, capas, galerias, anexos, áudio e vídeo como sintaxe de imagem/link Markdown; vídeos incorporados viram link, não player.
- Tabelas como tabela Markdown (primeira linha usada como cabeçalho quando necessário).
- Grupo e colunas são achatados na ordem de leitura; callouts/details são convertidos para texto/citação, e captions de mídia são incluídas em texto.
- Espaçadores não têm conteúdo textual e são omitidos.
- Texto com caracteres estruturais de Markdown é escapado; texto parecido com HTML usa entidades, não HTML arbitrário. Código é delimitado por cercas Markdown. O bloco 3D vira uma nota de texto; `scene_json`/`custom_js` não são exportados nem executados.

## Notas de conversão

A prévia identifica, por bloco, sublinhado, aparência e layout não preservados, comportamento expansível/tons de callout, tabelas sem cabeçalho ou com células mescladas, e mídia representada apenas como link. As notas são heurísticas sobre diferenças entre BDRE e Markdown, não erros do arquivo. O usuário pode localizar o bloco associado antes do download.

## Arquivos alterados

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `dev/admin/controller/cms/page.php` — cache-bust Pages.
- `dev/admin/controller/blog/blog.php` — cache-bust Blog.
- `tools/f9-57-static-battery.py`
- `tools/f9-82-markdown-export-smoke.js`

Cache-bust: `threejs-29-markdown-export`.

## Validação

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-82-markdown-export-smoke.js` — sintaxe/conversão de blocos, escape de markup, limites não representados, links/mídia, preservação do documento, localização e rejeição de BDRE inválido.
- Smoke tests F9-81 até F9-76 e bateria estática consolidada.
- CSS analisado com `tinycss2`.

Não há PHP CLI nem navegador real neste ambiente; a geração real do download e interação visual não foram testadas em browser.
