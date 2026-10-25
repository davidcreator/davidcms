# Conversão Office/Canvas — limites atuais

| Origem | Estrutura importada | Continua fora do escopo |
|---|---|---|
| DOCX | Títulos simples, parágrafos, ênfase, links HTTPS, listas simples, tabelas e indicação de cabeçalho | Listas aninhadas são achatadas; mídia interna não é extraída; layout e estilos complexos não são reproduzidos. |
| XLSX | Até 5 abas visíveis; cada uma vira título + tabela de até 20 linhas × 12 colunas | Fórmulas, estilos, gráficos, pivots, células mescladas (achatadas), macros e abas ocultas. |
| PPTX | Texto por slide/shape, placeholder de título, bullets simples e tabelas até 20 × 12 | Layout visual, animações, notas, transições, mídia embutida; bullets herdados do tema podem virar parágrafo simples. |
| Canvas IMSCC | Páginas HTML da área wiki/pages, na ordem do manifesto quando presente | Módulos, quizzes, notas, permissões, relacionamentos e mídias do curso. |
| HTML/XHTML | Texto/estrutura allowlist e referências locais de mídia permitidas | Scripts, CSS/estilo livre, formulários, SVG/Canvas HTML, iframes arbitrários e mídia remota. |

## Integridade

- Arquivos Office/IMSCC são examinados no browser; bytes não são enviados ao servidor.
- O descompactador rejeita entradas inseguras/traversal e aplica limites de tamanho/razão de compressão.
- O resultado é validado contra o schema v2, limite de 4 MiB e máximos de blocos/linhas/colunas antes de inserir no rascunho.
- Para salvar, o usuário ainda precisa enviar manualmente o formulário CMS existente.
