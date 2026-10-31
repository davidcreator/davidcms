# F9-83 — Importador seguro de Markdown

## Escopo

Importação local de arquivos `.md` e `.markdown` pelo espaço Exportar do BDREditor. A importação lê até 256 KiB no navegador, faz uma conversão determinística para blocos BDRE nativos e apresenta uma tela de prévia antes de qualquer alteração. Não há requisição de rede, envio do arquivo, interpretação de HTML como marcação nem execução de JavaScript.

Após confirmação explícita, o resultado é validado novamente e inserido como um único `core/group` editável no ponto de inserção suportado. O documento atual é preservado; a importação não substitui conteúdo, modelos ou rascunhos.

## Subconjunto convertido

- Títulos Markdown ATX (`#` até `######`), parágrafos, ênfase/negrito/tachado, código inline e links HTTP(S) ou caminhos locais seguros.
- Listas ordenadas/não ordenadas simples, citações e tabelas Markdown dentro dos limites BDRE.
- Blocos de código cercados, separadores e imagens locais em linha própria.
- Links locais independentes para arquivo, áudio e vídeo da biblioteca correspondente; embeds seguros do YouTube/Vimeo quando identificados pelo rótulo `Video`/`Vídeo`.

Limitações exibidas na prévia: listas aninhadas são achatadas, numeração inicial especial é normalizada, títulos de links não viram atributos, imagens inline permanecem texto, tabelas grandes são preservadas como linhas textuais, e HTML parecido com tags permanece literal. Imagens remotas nunca são baixadas; referências a imagens fora do escopo local permanecem textuais. Links com destinos não aceitos também permanecem textuais.

## Segurança e validação

- O parser é local e independente do importador de HTML existente.
- Links são filtrados pelo validador seguro de URL; destinos inseguros não se tornam `href`.
- HTML-like text é armazenado como conteúdo textual e avisado, sem gerar DOM/HTML.
- Conteúdo em cercas de código é texto em `core/code`; não é executado.
- Imagens remotas não são buscadas. Caminhos locais respeitam os escopos de mídia Pages/Blog.
- O documento nativo gerado é validado antes de aparecer como conversão válida e novamente antes do commit; limites de tamanho/estrutura BDRE permanecem aplicáveis.
- A confirmação insere um grupo com IDs novos, no ponto permitido. Se a validação ou inserção falhar, o documento em edição permanece intacto.

## Verificação

- `node tools/f9-83-markdown-import-smoke.js`
- `node tools/f9-82-markdown-export-smoke.js`
- `python3 tools/f9-57-static-battery.py`
- Sintaxe JavaScript com `node --check` e validação/parsing de CSS por ferramenta disponível.

PHP CLI e navegador real não estão disponíveis neste ambiente; nenhuma validação PHP/runtime ou interação em navegador real é declarada.
