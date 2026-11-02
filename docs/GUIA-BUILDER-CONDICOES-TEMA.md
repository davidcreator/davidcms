# Builder visual de condições de tema (Frente A)

Complementa `docs/GUIA-TEMAS-CONDICOES-THREEJS.md`. O motor
`dev/system/library/theme/condition_engine.php` **não foi alterado**: esta frente
é apenas a camada de edição e validação no Admin.

## O que mudou

Em **Design → Temas → Editar**, o bloco "Condições do tema" agora tem duas abas:

- **Editor visual**: regras em cards, grupos `all`/`any`/`not` aninháveis até o
  limite do motor, seletores de campo/operador permitidos vindos de `spec()`,
  editor de `then` (cores, cena, flags, classes, variáveis, Three.js), reordenação
  por arraste ou botões ↑/↓, e um **simulador de contexto**.
- **JSON (avançado)**: o textarea original, intacto.

O `textarea[name="conditions"]` continua sendo **o campo submetido no POST**. O
builder apenas o mantém sincronizado. Sem JavaScript, com JS bloqueado ou se o
arquivo falhar, o modo JSON segue funcionando exatamente como antes — é
*progressive enhancement*, não uma substituição.

## Simulador e validação ao vivo

Ao editar (ou ao clicar em "Simular agora"), o builder faz um POST para
`design/theme.simulate` com `csrf_token`, `conditions` (JSON) e `context[...]`.
O endpoint:

- exige permissão `access` em `design/theme` e CSRF válido;
- responde `application/json` com `Cache-Control: no-store` e `nosniff`;
- **não grava nada** (sem model, sem SQL, sem sessão alterada);
- reconstrói o contexto apenas com allowlists (`device`, `language`, `variant`,
  `hour` 0–23, `date` validada com `checkdate`, chaves de `query.*` do spec);
- chama `validate()`, `normalize()` e `evaluate()` do mesmo motor usado no site
  publicado;
- devolve `ok`, `errors` (com `code`/`path`), `matched`, `ignored`, `overrides`
  e o `context` já saneado.

O painel mostra o badge válido/inválido, os erros por regra, quais regras casaram
e os overrides resultantes. Assim a validação **ao vivo é a autoritativa do
servidor**, sem duplicar regras em JavaScript.

## Segurança

- Nenhum `innerHTML` com conteúdo do usuário: nós criados por `createElement` /
  `textContent`; limpeza por `removeChild`.
- Dados do spec, labels e CSRF chegam ao JS por atributos `data-*` com
  `|e('html_attr')` e `JSON_HEX_*` no PHP — sem `<script>` inline e sem `|raw`
  novo.
- O endpoint é somente leitura, protegido por ACL + CSRF, e não expõe cookies,
  headers, sessão bruta nem globais.
- O salvamento continua passando por `ConditionEngine::validate()` no POST do
  `edit()`; uma regra inválida não é persistida.
- Limites do motor (40 regras, 120 nós, profundidade 5) são respeitados pela UI.

## Arquivos

| Arquivo | Papel |
|---|---|
| `dev/admin/controller/design/theme.php` | dados do builder, `simulate()`, `simulateContext()`, assets |
| `dev/admin/view/template/design/theme_form.twig` | abas, container, simulador |
| `dev/admin/view/js/theme-condition-builder.js` | editor (vanilla, sem dependências) |
| `dev/admin/view/css/theme-condition-builder.css` | layout com tokens Bootstrap (claro/escuro/skins) |
| `dev/admin/language/{pt-br,en-us,es-eu}/design/theme.php` | rótulos traduzidos |

Nenhum manifesto `CHECKSUMS*.txt`, contrato congelado, `schema.sql`, workflow,
checker ou arquivo do Catalog/runtime Three.js foi modificado.

## Limitações

A validação e a simulação são executadas em PHP real nos testes desta entrega,
mas **não** houve browser real, sessão de Admin autenticada, banco, staging ou
deploy no sandbox. A conferência visual no painel é do usuário. CI verde e
`repo-health` verde não significam release aprovada.
