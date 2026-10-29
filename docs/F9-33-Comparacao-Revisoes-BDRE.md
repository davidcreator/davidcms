# F9-33 — Comparação segura de revisões BDRE

**Status:** interface de comparação textual integrada em Pages e Blog; runtime não testado.

## Comportamento

- Cada versão no painel de histórico oferece **Comparar** e **Restaurar** como ações separadas.
- Comparar carrega a revisão pelo endpoint POST existente e exibe, lado a lado, os metadados e conteúdo textual da versão selecionada e do formulário atual (inclusive alterações ainda não salvas).
- Documentos BDRE são resumidos extraindo somente texto de `content`/`children`. Não são exibidos/executados HTML, Twig, `custom_js`, markup, links ou atributos de media; HTML legado aparece apenas em `<pre>` via `textContent`.
- A leitura no cliente é limitada a 30.000 caracteres por lado. A comparação não altera o documento, o draft local, o histórico local nem o banco.
- **Restaurar** permanece separado e conserva a semântica existente: confirmação explícita, edição em memória e persistência somente no Save.
- Labels e estados de erro são localizados em PT-BR, EN-US e ES-EU.

## Segurança e limitações

- Reutiliza o endpoint de revisão protegido por POST, CSRF, permissão de modificação, registro e idioma validados.
- Conteúdo é colocado em elementos via `textContent`; nenhuma string de revisão é interpretada como HTML.
- A visualização é textual, não representa estilos/layout da versão e não faz diff por palavra/bloco. Ela serve para inspeção segura antes da restauração.

## Validação

`node --check` e verificações estáticas de wiring são aplicáveis. PHP CLI não está disponível; nenhuma conexão a banco, navegador, staging ou deployment foi usada. O gate de runtime permanece sob as condições F9-27.
