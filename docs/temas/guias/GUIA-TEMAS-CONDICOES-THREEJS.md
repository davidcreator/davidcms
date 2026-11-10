# Temas: condições declarativas + Three.js

Guia do recurso entregue nesta frente. O motor de templates continua sendo **Twig por
padrão** (`dev/system/config/default.php` → `template_engine = twig`). Three.js **não**
virou um "engine de template": ele produz canvas, não HTML. Ele entra como uma
**camada declarativa de cena** acoplada ao tema, reaproveitando o runtime self-hosted
já aprovado (`dev/system/library/three/`). O adapter multi-engine
(`dev/system/library/template.php`) permanece intacto.

---

## 1. O que era possível antes

`design_theme.config` (JSON no banco) aceitava apenas:

```
{ "primary": "#2563eb", "surface": "#ffffff", "scene": "hero-particles",
  "templates": { "common/home": "...twig..." } }
```

- `scene` era um seletor **global** e só influenciava `common/home`.
- As outras rotas nunca carregavam o runtime Three.js (`has_3d` só existia em `home.php`).
- Cor, densidade e velocidade da cena vinham de `rms_setting` global, não do tema.
- Não havia **nenhuma condição**: ou o valor valia para o site inteiro, ou não valia.
- Templates de tema eram allowlisted e **proibiam `<script>`**, `|raw`, `autoescape`
  desligado e atributos de evento — com razão.

## 2. O que passa a ser possível

No Admin, em **Design → Temas → Editar**, o formulário ganha dois blocos:
**Three.js declarativo** e **Condições do tema (JSON)**.

O `config` publicado passa a aceitar, de forma retrocompatível:

```
{
  "primary": "#2563eb",
  "surface": "#ffffff",
  "scene": "",
  "templates": { ... },
  "three":  { "enabled": true, "scene": "hero-particles", "slot": "hero",
              "count": 500, "color": "#8a4fff", "speed": 0.06 },
  "conditions": { "version": 1, "rules": [ ... ] }
}
```

`three` é a configuração de **base**; cada regra pode sobrepô-la quando casa.

---

## 3. Contrato das condições

Implementado em `dev/system/library/theme/condition_engine.php`
(`Reamur\System\Library\Theme\ConditionEngine`), registrado no `framework.php` como
`theme_condition_engine`.

### 3.1 Estrutura

```
{
  "version": 1,
  "rules": [
    {
      "id": "home-pt",
      "when": { "all": [ { "field": "...", "op": "...", "value": ... } ] },
      "then": { "primary": "#7c3aed", "vars": { "hero_variant": "aurora" } }
    }
  ]
}
```

- `id`: `^[a-z0-9_-]{1,48}$`, único.
- `when`: nó de condição ou grupo `all` (AND), `any` (OR), `not` (NOT), aninháveis.
- `then`: overrides permitidos (§3.4).
- Limites: **40 regras**, **120 nós**, profundidade **5**, strings **256** caracteres,
  listas **20** itens, `vars` **40** chaves.

### 3.2 Campos (`field`)

`route`, `path`, `language`, `device`, `mobile`, `logged_in`, `user_group`, `hour`,
`weekday`, `date`, `month`, `variant`, `https`, `ajax`, `query`, além de `query.<chave>`.

Chaves de query permitidas: `utm_source`, `utm_medium`, `utm_campaign`,
`utm_content`, `utm_term`, `ref`, `lang`, `theme`, `variant`, `view`, `page`, `debug`.

`variant` aceita somente `a` ou `b`, sempre **explícito via query** (`?variant=b`).
Não é uma decisão A/B inferida nem gravada em cookie.

O contexto é montado em `dev/catalog/controller/startup/theme.php` a partir de
`config('_route')`, do idioma ativo, de `REQUEST_URI`, de `HTTP_USER_AGENT`
(mobile/tablet/desktop), da data/hora do servidor e de chaves allowlistadas da query.
**Não** há acesso a cookies brutos, headers arbitrários, senhas, tokens ou sessão
completa.

### 3.3 Operadores (`op`)

| Operador | `value` | Significado |
|---|---|---|
| `eq`, `neq` | escalar | igualdade / diferença (comparação em string) |
| `in`, `not_in` | lista | pertence / não pertence |
| `contains` | escalar ou lista | substring, ou elemento de lista |
| `starts_with`, `ends_with` | escalar | prefixo / sufixo |
| `gt`, `gte`, `lt`, `lte` | escalar | numérico quando ambos numéricos; senão `strcmp` |
| `between` | par `[min, max]` | intervalo numérico inclusivo |
| `exists` | — | campo presente no contexto |
| `empty` | — | ausente, `''`, `'0'` ou `'false'` |
| `truthy`, `falsy` | — | `1/true/yes/on` vs. o resto |

**Não há** `regex`, `match`, `eval`, expressões, chamadas de função nem acesso a
arquivos. Qualquer operador/campo/valor fora do contrato é rejeitado.

### 3.4 Overrides (`then`)

| Chave | Validação |
|---|---|
| `primary`, `surface` | `^#[0-9a-fA-F]{6}$` |
| `scene` | uma das cenas aprovadas |
| `three` | objeto com `enabled`, `scene`, `slot`, `count` (50–800), `color` (hex), `speed` (0.01–0.20) |
| `vars` | até 40 chaves `^[a-z0-9_]+$`, só escalares |
| `flags`, `classes` | até 20 identificadores `^[a-zA-Z0-9_-]+$` cada |
| `body_class` | um identificador seguro |

Regras casadas são aplicadas **em ordem**: escalares — última vence; `vars` — mescla;
`flags`/`classes` — acumulam sem duplicar. `matched` guarda os ids que casaram.

### 3.5 Fail-closed

- Regra inválida no Admin → **nada é salvo**, com código de erro estável.
- Regra inválida no Catalog (JSON corrompido no banco, por exemplo) → **descartada**;
  o restante do site segue com o comportamento legado.
- `evaluate()` nunca lança exceção, nunca aplica override parcial e nunca executa código.
- Valores que chegam ao Twig são escalares ou listas planas; `autoescape=html` continua
  ligado e nenhum `|raw` novo foi introduzido.

---

## 4. Exemplos

**Tema diferente na home em português/espanhol, com cena 3D:**

```json
{ "version": 1, "rules": [
  { "id": "home-pt",
    "when": { "all": [
      { "field": "route", "op": "eq", "value": "common/home" },
      { "field": "language", "op": "in", "value": ["pt-br", "es-eu"] }
    ] },
    "then": {
      "primary": "#7c3aed",
      "surface": "#faf5ff",
      "three": { "enabled": true, "scene": "hero-particles", "slot": "hero",
                 "count": 500, "color": "#8a4fff", "speed": 0.06 },
      "vars": { "hero_variant": "aurora", "show_badge": true },
      "flags": ["aurora"],
      "classes": ["theme-aurora"]
    } }
] }
```

**Desligar animação em mobile e fora do expediente:**

```json
{ "version": 1, "rules": [
  { "id": "sem-3d-mobile",
    "when": { "any": [
      { "field": "device", "op": "eq", "value": "mobile" },
      { "field": "hour", "op": "lt", "value": 8 },
      { "field": "hour", "op": "gte", "value": 20 }
    ] },
    "then": { "three": { "enabled": false, "scene": "", "slot": "hero" } } }
] }
```

**Campanha por UTM + negação aninhada:**

```json
{ "version": 1, "rules": [
  { "id": "campanha",
    "when": { "all": [
      { "field": "query.utm_source", "op": "eq", "value": "newsletter" },
      { "not": { "field": "logged_in", "op": "truthy" } }
    ] },
    "then": { "vars": { "cta": "Oferta para novos visitantes" }, "flags": ["campanha"] } }
] }
```

---

## 5. Three.js no tema

### 5.1 Como funciona

O tema **nunca escreve JavaScript**. Ele declara parâmetros; o shell confiável emite
apenas atributos `data-*` allowlistados:

```html
<div class="theme-scene theme-scene--hero" data-scene="hero-particles"
     data-count="500" data-color="#8a4fff" data-speed="0.06" aria-hidden="true">
  <div class="theme-scene__fallback" data-fallback></div>
</div>
```

`dev/system/library/three/runtime/three/core/scene-runner.js` já faz `boot()` em todo
`[data-scene]`, resolve a factory em `SCENES[name]` e transforma `container.dataset`
no `config` da cena. Cenas inexistentes simplesmente não montam. O runner já respeita
`prefers-reduced-motion`, `IntersectionObserver`, `ResizeObserver`, pausa em aba oculta
e faz `dispose()` dos recursos.

O `dev/catalog/view/template/common/header.twig` continua sendo o **único** ponto que
carrega o importmap e `three/main.js`. A diferença: `has_3d` agora pode ser ligado pelo
tema em **qualquer rota** (`dev/catalog/controller/common/header.php`), não só na home.

### 5.2 Cenas aprovadas

`''` (nenhuma), `hero-particles`, `portfolio-3d`, `tools-3d`, `not-found`.
Espelham os módulos registrados em `three/main.js` e a allowlist do `AssetManifest`.

### 5.3 Slots

| Slot | Onde aparece |
|---|---|
| `hero` | substitui os parâmetros de `partial/hero-3d.twig` (comportamento atual) |
| `home_top` | incluído no topo de `common/home.twig` |
| `footer_top` | incluído antes de `common/footer.twig` |

**Personalização completa:** qualquer template da allowlist pode inserir a cena onde
quiser com `{% include 'partial/theme-scene.twig' %}` — por exemplo dentro de
`common/home`, `catalog/project_list` ou `common/footer`. O partial é do sistema (não
editável pelo tema) e só emite atributos seguros.

### 5.4 Fallback

`dev/catalog/view/css/theme.css` ganhou `.theme-scene` com gradiente e
`.theme-scene__fallback`. Sem WebGL ou com movimento reduzido, o runner não inicializa
e o gradiente permanece visível.

---

## 6. Condições dentro do Twig

O startup publica dois globais seguros via `Twig::setThemeGlobals()`, visíveis em
**todas** as telas (header, footer, home e qualquer rota), inclusive na prévia
sandboxada do Admin:

```twig
{% if 'aurora' in theme.flags %}
  <span>{{ theme.vars.hero_variant|default('padrao') }}</span>
{% endif %}

<body data-...> {# classes/matched já aplicados em common/header.twig #}
```

- `theme.vars`, `theme.flags`, `theme.classes`, `theme.matched`
- `theme_scene` (`enabled`, `scene`, `slot`, `count`, `color`, `speed`)

`setThemeGlobals()` descarta objetos, closures, `Twig\Markup`, HTML e arrays aninhados;
`theme.classes` é emitido com `|e('html_attr')` e `theme.matched` com `join` + escape.

---

## 7. Compatibilidade

- Um tema antigo (sem `conditions` e sem `three`) continua **exatamente** como antes:
  `primary`, `surface`, `scene` e `templates` seguem os mesmos caminhos F10-03.
- `catalog_theme_scene` continua sendo consumido por `common/home.php`.
- `SceneConfig::runtime()` e `AssetManifest` não foram alterados.
- Nenhum contrato congelado, matriz de decisões, `schema.sql`, hash de release gate,
  `release_approved`, workflow, checker, JS do runtime ou migration foi modificado.
- Os 14 `CHECKSUMS*.txt` permanecem idênticos (nenhum arquivo desta frente é coberto
  por eles) e por isso não foram reemitidos no pacote.

---

## 8. Validação desta entrega

- `php -l` (PHP 8.5 real via WASM) nos 9 arquivos PHP: **9 OK / 0 FAIL**.
- Motor de condições: **54 PASS / 0 FAIL** (operadores, grupos, limites, merge,
  injeção, fail-closed, normalize).
- Integração do `startup/theme.php` com mocks: **32 PASS / 0 FAIL** (regra casada,
  regra não casada, legado, three de base, three inválido, JSON ruim, contexto).
- Renderização Twig 3.14 real: **24 PASS / 0 FAIL** (partial, header com/sem
  importmap, hero legado/tema, slots, sandbox, formulário do Admin).
- Guards do repositório na réplica do HEAD: `verify-checksums`, `repo-health`,
  `verify-ci-workflow`, `verify-contract-freeze`.

## 9. Limitações honestas

O PHP utilizado é o `php-wasm-cli` (PHP 8.5) para lint e testes de lógica/renderização.
**Não** houve execução do CMS completo, banco de dados, Composer/PHPStan no servidor de
produção, browser real, E2E, migration, staging ou deploy. A conferência visual da
cena WebGL e a aplicação no ambiente do usuário são etapas dele. CI verde e
`repo-health` verde **não** significam release aprovada.
