# Matriz de importação BDREditor

| Entrada | Conversão | Limitações relevantes |
|---|---|---|
| DOCX | Parágrafos, títulos, ênfase básica, links HTTPS seguros e tabelas | Mídia incorporada não é extraída; tabelas limitadas pelo modelo atual (20 × 12). |
| XLSX | Primeira aba em `core/table` | Até 20 × 12; fórmulas ignoradas; sem estilos, gráficos, fórmulas ou múltiplas abas. |
| PPTX | Texto por slide e separadores | Sem layout visual, animações, gráficos ou mídia incorporada. |
| HTML/XHTML | Elementos textuais e estruturas allowlist | Scripts, estilos, formulários, SVG, Canvas HTML, iframes arbitrários e mídia externa ignorados. |
| Canvas LMS IMSCC | Páginas HTML encontradas em `wiki_content`/`pages` | Não reconstrói curso, módulos, quizzes, notas, permissões ou relações LMS. |
| Áudio em HTML | Referência local existente `view/audio/blog/`, MP3/OGG/WAV | Não aceita upload; não cria arquivo. |
| Imagem em HTML | Referência local existente `image/` ou `view/image/blog/` | URL externa ou arquivo incorporado não é copiado. |
| Vídeo em HTML | URL HTTPS YouTube/Vimeo já permitida | Vídeo local não é persistido; não extrai mídia de pacote. |

## Segurança e integridade

- ZIP lido com limite de bytes, número de entradas, tamanho descompactado, razão de compactação e rejeição de caminhos absolutos/traversal.
- XML/HTML nunca é injetado com `innerHTML`; textos vão para nós de texto e estrutura resulta apenas em blocos allowlist.
- A conversão só modifica o rascunho em memória. O salvamento continua sujeito ao formulário, CSRF, validação v2 e clique manual em Salvar.
- A importação aborta se o resultado exceder o limite de 4 MiB ou a quantidade máxima de blocos.
