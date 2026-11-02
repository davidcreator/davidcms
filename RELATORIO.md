# Temas: condicoes robustas + suporte declarativo a Three.js

Pedido: o sistema de temas ja usa Twig por padrao; era preciso suportar ThreeJS
tambem e ter condicoes robustas para personalizar um tema por completo.

## Diagnostico do HEAD be9ba22ad

- `dev/system/library/template.php` ja e um adapter multi-engine com Twig como
  padrao. Three.js NAO e engine de template (produz canvas, nao HTML): ele entra
  como camada declarativa de cena, reaproveitando o runtime self-hosted aprovado.
- `dev/catalog/controller/startup/theme.php` aplicava `primary`, `surface`, `scene`
  e `templates` de forma GLOBAL, sem nenhuma condicao.
- `scene` so influenciava `common/home`; `has_3d` so existia em `home.php`, entao
  NENHUMA outra rota carregava o runtime WebGL.
- Cor/densidade/velocidade da cena vinham de `rms_setting` global, nao do tema.
- `Twig::setThemeTemplates()` (F10-03) proibe `<script>`, `|raw`, `autoescape` e
  event-handlers -- correto, mas por isso um tema nao conseguia pedir uma cena.
- `scene-runner.js::boot()` ja varre `[data-scene]` e transforma `dataset` em
  `config` da cena: a integracao podia ser 100% declarativa, sem JS novo.

## O que foi entregue (19 arquivos)

1. **Motor de condicoes** `dev/system/library/theme/condition_engine.php` (novo):
   15 campos allowlistados (rota, idioma, dispositivo, horario, query, ...),
   16 operadores, grupos `all`/`any`/`not` aninhados, fail-closed, sem `eval`,
   sem regex do usuario, sem IO, limites de 40 regras/120 nos/profundidade 5.
   `validate()`, `normalize()`, `normalizeThree()`, `evaluate()`, `spec()`.
2. **`framework.php`**: registra `theme_condition_engine` no Registry.
3. **`startup/theme.php`**: monta contexto seguro, avalia as regras, faz merge dos
   overrides e publica `catalog_theme_three`, `vars`, `flags`, `classes`, `matched`.
4. **`Twig::setThemeGlobals()`**: publica `theme` e `theme_scene` para TODAS as
   telas (inclusive preview sandboxado), aceitando apenas escalares/listas planas.
5. **`header.php` / `header.twig`**: Three.js pode ser ligado em QUALQUER rota;
   classes e regras casadas aparecem no `<body>` com escape.
6. **Slots Three.js**: novo `partial/theme-scene.twig` (so `data-*`, sem script),
   `home.twig` (`home_top`), `footer.twig` (`footer_top`) e `hero-3d.twig` (`hero`).
   Qualquer template allowlisted pode incluir o partial onde quiser.
7. **`theme.css`**: fallback em gradiente + respeito a reduced-motion.
8. **Admin**: `design/theme.php` salva/valida `conditions` e `three`; novo bloco no
   `theme_form.twig`; 15 chaves de idioma x3 (pt-br, en-us, es-eu).
9. **`docs/GUIA-TEMAS-CONDICOES-THREEJS.md`**: contrato, exemplos, seguranca.

## Compatibilidade e seguranca

- Tema legado sem `conditions`/`three` continua IDENTICO (F10-03 intacto):
  `primary`, `surface`, `scene`, `templates`, `catalog_theme_scene` e
  `SceneConfig::runtime()` nao mudaram de comportamento.
- Nenhum `<script>`, `|raw` novo, `autoescape` desligado ou codigo executavel vindo
  do tema. So cenas registradas em `three/main.js` e slots allowlistados.
- Nenhum contrato congelado, matriz, `schema.sql`, hash de release gate,
  `release_approved`, workflow, checker, JS do runtime ou migration foi alterado.
- Os 14 `CHECKSUMS*.txt` permanecem IDENTICOS: nenhum arquivo desta frente e
  coberto por eles, entao NAO foram reemitidos nem incluidos no ZIP.
- Nenhuma escrita remota. F9-27 segue `BLOCKED_NOT_RUN` / `release_approved:false`;
  BDREditor segue pausado; P2 do F9-18 nao foi reaberto.

## Validacao executada

| Verificacao | Resultado |
|---|---|
| `php -l` PHP 8.5 real (WASM) nos 9 arquivos PHP | 9 OK / 0 FAIL |
| Motor de condicoes | 54 PASS / 0 FAIL |
| Integracao `startup/theme.php` com mocks | 32 PASS / 0 FAIL |
| Renderizacao Twig 3.14 real + sandbox | 24 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |
| `node --check` runtime Three.js | 2/2 OK |

Os testes de integracao cobrem: regra casada, regra NAO casada, tema 100% legado,
three de base, three invalido (descartado), JSON de condicoes corrompido
(fail-closed), dispositivo mobile + UTM, e `normalizeThree()` aceitar/rejeitar.
A renderizacao Twig cobre: importmap presente/ausente, classes do tema, parametros
do hero (tema vs legado), os 3 slots, o `SecurityPolicy` da previa e o formulario.

## Limitacoes

PHP usado e o `php-wasm-cli` 8.5 para lint e testes de logica/renderizacao, NAO o
PHP do servidor de producao. Nao houve CMS completo, banco, Composer/PHPStan no
host, browser real, WebGL real, E2E Chromium, migration, staging, deploy nem
escrita no GitHub. A conferencia visual da cena e do tema e do usuario.
CI verde e health verde NAO significam release aprovada.

Aplicar: substituir os arquivos pelos completos deste ZIP e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
