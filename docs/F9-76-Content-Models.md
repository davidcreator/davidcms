# F9-76 — Ferramenta Modelos e modelos de conteúdo

## O que foi adicionado

- A ferramenta **Modelos** fica acessível pela navegação de tela cheia existente e também pela guia **Exibir** da faixa do editor incorporado. A tela tem busca/filtros e botão claro para voltar à edição.
- A biblioteca diferencia **Modelos**, padrões rápidos existentes e padrões salvos já suportados pelo projeto. Os padrões antigos não foram removidos nem alterados.
- Seis modelos nativos e editáveis, em PT-BR, espanhol e inglês:
  1. Apresentação institucional.
  2. Artigo/publicação.
  3. Serviços em três colunas.
  4. Perguntas frequentes.
  5. Chamada para contato.
  6. Guia numerado passo a passo.
- Cada modelo é inserido como um novo `core/group`, com IDs novos. A inserção ocorre no ponto selecionado permitido; sem seleção, ao final. Nunca substitui o documento. Uma inserção válida gera uma única alteração no histórico e pode ser desfeita/refeita.
- A inserção exige que `core/group` seja permitido no destino e valida o documento BDRE v2 completo e o limite de 4 MiB antes de confirmar a alteração. Caso contrário, informa o erro e não altera o estado.
- Os modelos usam somente blocos já existentes e validados (heading, paragraph, separator, columns/column, details, quote, callout, button, list/list-item). Não inserem HTML arbitrário, mídia, `custom_js`, nem requerem tabela ou migration.
- Os fluxos atuais de padrões rápidos e salvos, histórico, validação e salvamento Pages/Blog permanecem intactos.
- Cache-bust Pages/Blog: `threejs-23-content-models`.

## Verificações

- `node --check` nos scripts JavaScript.
- Smoke dinâmico valida **18 combinações** de modelos/locales como documentos BDRE v2.
- Bateria estática: **221 verificações aprovadas**; smoke tests de URL também passaram.
- CSS e estado anterior do editor permanecem incluídos no pacote completo. PHP CLI e navegador real indisponíveis; não alego teste runtime visual/integração.
