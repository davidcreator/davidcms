# Frente A — Builder visual de condições de tema

Série A→J solicitada pelo usuário: **esta é a frente A**; as próximas seguem
B (design tokens), C (prévia real), D (editor de cena), E (pacotes
versionáveis), F (section builder), G (componentes), H (agendamento/rollout),
I (mídia) e J (escopo multisite), **um pacote instalável por frente**.
Não é uma frente de auditoria nem um dossiê de governança.

## Diagnóstico

O `main` 5be826e1f já tem o motor `ConditionEngine` e o suporte declarativo a
Three.js aplicados, mas o Admin só oferecia um **textarea JSON** para as
condições e uma prévia com dados sintéticos. Para quem cria temas, isso exige
conhecer o contrato de cor e não dá feedback algum até salvar.

## Entregue (10 arquivos)

- **Editor visual** no lugar do textarea como modo padrão: regras em cards,
  grupos `all`/`any`/`not` **aninháveis** até a profundidade do motor, selects
  de campo/operador vindos de `spec()`, valores com tipos corretos (`in` lista,
  `between` par, operadores sem valor), editor de `then` (cores, cena, flags,
  classes, variáveis e os 6 parâmetros Three.js), reordenação por arraste ou
  botões ↑/↓.
- **Aba JSON preservada**: o `textarea[name="conditions"]` continua sendo o
  campo **submetido no POST**. O builder só o sincroniza. Sem JS, com JS
  bloqueado ou se o script falhar, o modo JSON segue idêntico ao anterior
  (*progressive enhancement*). Nenhuma mudança no POST do `edit()`.
- **Simulador de contexto**: rota, idioma, dispositivo, variante, hora, login e
  UTM. Validação **ao vivo** chamando `design/theme.simulate`, que usa o
  **mesmo** `validate()/normalize()/evaluate()` do site publicado — sem
  duplicar regras em JavaScript. Mostra badge válido/inválido, erros por regra,
  quais regras casaram e os overrides resultantes.
- **Endpoint somente leitura**: ACL `access`, CSRF, `application/json`,
  `Cache-Control: no-store`, `nosniff`, zero escrita. O contexto é reconstruído
  apenas com allowlists (device, idioma, variante, hora 0–23, data validada com
  `checkdate`, chaves `query.*` do spec). Nada de cookies, headers ou sessão bruta.
- **JS vanilla sem dependências** (`theme-condition-builder.js`) e CSS com
  tokens Bootstrap (funciona em claro/escuro e skins). 43 rótulos ×3 idiomas.
- **Guia** `docs/GUIA-BUILDER-CONDICOES-TEMA.md`.

## Achado de robustez

Com PHP 8.5 e `error_display=true` (o `admin.php` do projeto usa `true`), o
escaper `html_attr` do Twig emite `Deprecated: ord(): Providing a string that
is not one byte long...` ao escapar JSON com acentos — o aviso **vazava para o
HTML** e corrompia o `data-labels`. Os JSON dos `data-*` passam a ser
serializados em **ASCII puro** (`JSON_HEX_*`, sem `JSON_UNESCAPED_UNICODE`):
`JSON.parse` reconstrói os acentos e nenhum aviso aparece. O campo JSON do
textarea continua legível em UTF-8.

## Compatibilidade e segurança

- `ConditionEngine`, o startup do Catalog, o runtime Three.js, os globais Twig,
  os slots, os 14 `CHECKSUMS*.txt`, contratos congelados, `schema.sql`,
  `release_approved`, workflows e checkers **não foram alterados**.
- Nenhum `|raw` novo, nenhum `<script>` inline, nenhum `innerHTML` com conteúdo
  do usuário, nenhum `eval`. Dados chegam ao JS por `data-*` com
  `|e('html_attr')` + `JSON_HEX_*`.
- Tema existente, rascunho, revisões e publicação seguem exatamente iguais.
- Nenhuma escrita remota. F9-27 `BLOCKED_NOT_RUN` / `release_approved:false`;
  BDREditor pausado; P2 do F9-18 não reaberto.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 4 arquivos PHP | 4 OK / 0 FAIL |
| `node --check` no builder JS | OK |
| `simulate()` com mocks (ACL, CSRF, casou/não casou, inválido, JSON ruim, sanitização) | 32 PASS / 0 FAIL |
| Render Twig 3.14 do formulário | 13 PASS / 0 FAIL |
| **jsdom 29 (DOM real)**: render, adicionar regra, reordenar, operador `exists`, editar lista, abas, `fetch`, resultado | 32 PASS / 0 FAIL |
| Cruzado: JSON produzido pelo JS validado pelo `ConditionEngine` PHP | 5 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom para lint/lógica/DOM. **Não** houve CMS
completo, banco, sessão Admin autenticada real, browser com rede, WebGL, E2E,
migration, staging, deploy ou escrita no GitHub. A conferência visual no painel
é do usuário. **CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
