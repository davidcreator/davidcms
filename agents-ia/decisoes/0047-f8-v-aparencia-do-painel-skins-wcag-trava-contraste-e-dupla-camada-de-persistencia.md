# ADR 0047 — F8-V: Aparência do Painel — skins WCAG curadas, trava de contraste 4.5:1 e persistência em dupla camada

- **Data**: 2026-10-22
- **Status**: Aceito (bateria `e2e_f805.sh` 13/13 + regressões f801/f802/f701/f703/f406 verdes)
- **Contexto**: ADR 0045 (shell F8-01) consolidou o painel em Bootstrap + assets ReamurCSS do usuário, mas deixou três dívidas de legibilidade e uma lacuna de produto: (a) `--bs-secondary: #198754` (verde, herdado do `reamurcms.css` global) vazava no admin e sujava botões/links `secondary`; (b) `nav-title` da sidebar estava em **4.28:1** (abaixo do AA 4.5:1); (c) o badge de rascunho do blog (F7/F8-02) estava em **3.15:1** no modo claro; (d) não existia personalização de aparência — referência de UX: **AdminLTE** (skins + opções), porém com requisitos nossos mais duros: **validação WCAG AA das paletas** e **persistência apenas no banco** (sem localStorage como fonte primária).

## Decisões

### 1. Correções de contraste IMEDIATAS (escopo `.admin-shell`)

O `reamurcms.css` é do usuário e **não pode ser tocado** (vive no front). As correções
são isoladas no escopo do painel:

- `body.admin-shell { --bs-secondary: #6c757d }` — desfaz o remap verde somente no
  admin (raiz-confirmada do "admin esverdeado" diagnosticado antes do ADR);
- tokens `--rms-badge-draft-fg/-bg` ajustados p/ **≥ 4.5:1** nos dois modos
  (light `#7a6400/#fffbeb`; dark `#f3c343/#3b2f04`);
- `.admin-shell :focus-visible { outline: 2px solid var(--rms-accent) }` — anel de
  foco consistente p/ teclado (WCAG 2.4.7);
- `nav-title` passou a consumir `--rms-sidebar-fg-title`, cujo valor por skin é
  garantido **≥ 4.5:1** pelo validador CLI;
- regras de densidade `html[data-rms-density="compact"]`.

### 2. Modelo de skins: dimensões ortogonais, arquivos estáticos curados

Não existe "skin dark": **modo (light/dark/sistema) × skin (6) × variante de sidebar
(auto/light/dark)** são dimensões independentes. Cada skin
(`admin/view/css/themes/{light,ocean,forest,plum,slate,contrast}.css`) cobre os
modos via `:root` + `:root[data-bs-theme="dark"]` e a variante via
`html[data-rms-sidebar="light|dark"]`. Tokens públicos:

- `--rms-accent` / `--rms-accent-contrast` (cor de marca e texto legível sobre ela) +
  `--rms-accent-hover`;
- `--rms-sidebar-bg/-fg/-fg-muted/-fg-title/-border/-hover-bg`;
- `--rms-brand-filter`, `--rms-badge-draft-fg/-bg`.

**Invariante matemática**: escolher uma única cor que bata 4.5:1 contra `#fff` **e**
contra `#212529` simultaneamente é impossível (o intervalo de luminosidade exigido é
vazio: ≲0.257 e ≳0.288). Por isso toda cor de destaque é definida como **par por modo**
(accent light + accent dark), inclusive nos accents curados e no avançado por hex.

**Validador CLI** (`dev/tools/validate-themes.php`, saída `exit ≠ 0` em falha):
parseia blocos CSS com matching por restrições de seletor (`data-bs-theme`/`data-rms-sidebar`)
e mede os pares críticos (sidebar fg/bg, muted, title, accent-contrast/accent,
accent/body-bg, body/body-bg, badge rascunho) × 7 corpora (6 skins + defaults
embutidos do `admin.css`) × 2 modos × 3 variantes de sidebar. Resultado: verde.

Na 1ª versão do validador um bug do próprio seletor (`'*'` casando tudo) gerou 33
falsos positivos — corrigido com `selApplies()` por restrições; o aprendizado ficou:
**o validador também é código e entra na bateria**.

### 3. Persistência: dupla camada, banco apenas

- **Padrão global** → `rms_setting` (`config_admin_skin|accent|accent_dark|sidebar_variant|density|font_scale|theme_mode`), editável por `hasPermission('modify', 'setting/appearance')`;
- **Override por usuário** → nova coluna `rms_user.theme_prefs` (TEXT JSON, `AFTER image`; guard `information_schema` no upgrade SQL e na library `user.php`), com flag `override: true|false` — quando `false`, o JSON é mantido mas ignorado (o usuário "desligou" a personalização sem perdê-la);
- **localStorage NUNCA é fonte primária** — sobra só como *anti-flash fast-path* quando a preferência é `system` (evita FOUC no primeiro paint; o CSS servidor-side/attrs do `<html>` são a verdade).

### 4. Resolução e aplicação do tema

`Model\Setting\Appearance::resolveTheme($userPrefs)` = `sanitize(user_prefs) ?? global ?? DEFAULTS`,
com whitelist de valores por dimensão. O controller `common/header` injeta:
`data-rms-skin|sidebar|density|mode-pref` no `<html>`, `style="font-size: N%"` quando
≠100%, `data-bs-theme` explícito quando o modo não é `system`, o `<link themes/{skin}.css>`
e **somente quando há accent custom** um `<style>` inline com o par light/dark +
`--rms-accent-contrast` computado (melhor razão branco/preto ≥ 4.5:1, com trava).

O **middleware** (`startup/permission`) ganhou `setting/appearance` na allowlist —
mesma classe de rota de `user/profile`: é preferência **própria**, acessível a todo
usuário logado; a seção **global** da tela verifica `modify` separadamente (rota
permitida ≠ persistência global liberada). O menu Sistema ganha o item, e o dropdown
do usuário ganha "Aparência" (para quem não tem o item no menu por ACL).

### 5. Modo avançado por hex com trava de contraste (4.5:1)

O accent avançado é um **par** (base clara + variante escura). A validação ocorre duas
vezes com a **mesma fórmula** (relLum/ratio WCAG) no JS do form (`appearance.js`,
preview ao vivo + bloqueio do submit) e no servidor
(`Model\Setting\Appearance::validateAccentPair`):

1. base precisa bater ≥4.5:1 contra `#fff`;
2. a variante escura (ou a própria base, se ausente) precisa bater ≥4.5:1 contra `#212529`;
3. sobre o accent (menu ativo/botões), ao menos um entre `#fff`/`#000` precisa ≥4.5:1 — senão o accent é bloqueado;
4. o botão "mágico" sugere a variante do modo oposto (passo de 12 na luminância, espelhado no PHP `suggestVariant`).

Cor inválida é **rejeitada no POST** com erro i18n e **nada é gravado** (estado bancário inalterado — coberto por check 7 da bateria).

### 6. Bug corrigido durante a bateria (lição registrada)

A primeira versão da bateria falhava no check "global salva e reflete": o POST gravava
no banco, mas o GET seguinte não refletia. Causa: `startup/setting` (admin) injeta no
objeto `Config` **apenas chaves numa ALLOWED list** — as 7 `config_admin_*` não estavam
nela. O `config->get()` é o canal que o header usa para resolver o tema, então a
aplicação lia sempre os defaults. Fix: as 7 chaves entraram no ALLOWED do
`admin/controller/startup/setting.php` (o override do usuário **não** entra aí, por
design — vive no objeto `User`). Documentado aqui porque é o tipo de regressão silenciosa
que só aparece integrado (POST ok, DB ok, render velho).

### 7. Anti-flash e anti-FOUC

O script inline do `header.twig` lê `data-rms-mode-pref` (servidor) antes de decidir
`data-bs-theme` — o tema server-driven nunca pisca; o `localStorage('rms-admin-theme')`
da F8-01 foi rebaixado a fallback apenas no contexto `mode=system`. O toggle da topbar
ainda grava o localStorage (fast-path), mas **agora também persiste o modo no servidor**
via `POST setting/appearance.saveMode` (CSRF por meta tag) — o clique de um usuário
passa a virar preferência dele (cria o override `mode` automaticamente).

## Arquivos-chave (espelho `dev/`)

- `install/sql/upgrade_f8_05_admin_theming.sql` — coluna `theme_prefs` (guard), 7 settings (INSERT IGNORE), grants `setting/appearance` (JSON_ARRAY_APPEND idempotente);
- `admin/controller/startup/permission.php` — allowlist + `setting/appearance`;
- `admin/controller/startup/setting.php` — ALLOWED + 7 chaves (o bug do §6);
- `admin/model/setting/setting.php` — allowlist `editSettings` + 7 chaves;
- `admin/model/setting/appearance.php` — resolução, sanitize, WCAG (relLum/ratio), validação de par, `suggestVariant`, `bestContrastOn`, `CURATED_ACCENTS` (8 itens com par por modo);
- `system/library/user.php` — `getThemePrefs()`/`saveThemePrefs()` (guard de coluna via information_schema, cache por instância, escape, JSON_UNESCAPED);
- `admin/controller/common/header.php` — resolve e injeta tema; menu + item; dropdown + item; meta csrf/appearance-mode-url;
- `admin/view/template/common/header.twig` — attrs, `<link skin>`, `<style>` accent, anti-flash server-aware;
- `admin/controller/setting/appearance.php` + `admin/view/template/setting/appearance.twig` — tela com dois escopos (global ACL modify / meu), macro compartilhada, saveMode AJAX;
- `admin/view/js/appearance.js` — preview ao vivo (troca skin/accent/sidebar/densidade/fonte/modo sem salvar), validador WCAG mirror, botão de sugestão;
- `admin/view/js/admin.js` — toggle da topbar agora persiste via saveMode;
- `admin/view/css/admin.css` — tokens de sidebar/accent, fixes §1, densidade, `.rms-skin-card/.rms-accent-card`, `.rms-accent-verdict`;
- `admin/view/css/themes/*.css` — as 6 skins;
- `dev/tools/validate-themes.php` — validador CLI;
- `admin/language/{en-us,pt-br,es-eu}/setting/appearance.php` + acréscimos em `common/header.php` ×3.

## Verificação

- `e2e_f805.sh` — **13/13**: upgrade idempotente (coluna+settings+grants), tela 200 self-hosted, global salva e reflete, override desligado volta ao global, override vence global sem tocar global, accent válido inline com contraste computado, **accent inválido bloqueado** (3 sub-casos: cor fraca no claro, formato inválido, ausência da variante escura) com banco inalterado, saveMode AJAX com CSRF (ok/modo inválido/csrf inválido), ACL dupla camada (editor sem modify → global negado, meu salvo, dashboard dele reflete), i18n ×3 + fallback de idioma inexistente + erro i18n em pt-br, validador CLI verde, guards+lint (`php -l`, `node --check`), base-fixes §1 verificáveis por grep no CSS/twig;
- Regressões **f801 10/10, f802 13/13, f701 10/10, f703 10/10, f406 10/10**;
- Smoke manual (curl): cadeia login → POST global → GET reflete; editor ↔ admin em paralelo com temas diferentes (slate-admin vs contrast-editor) — separação por usuário comprovada.

## Consequências / próximos passos

- Fila continua: **F9** (sistema de custom fields de conteúdo, tipo ACF) é fase própria;
  os lotes F8-03 (user/setting) e F8-04 (sale/marketing) seguem depois;
- O modelo de tokens (`--rms-*`) é o suporte para o **catalog visual** (próxima fase F8):
  as skins do painel e os fixes de `:root` não vazam para o front;
- Se uma skin nova surgir, **obrigatório** re-rodar `dev/tools/validate-themes.php`
  (está na bateria);
- O even-driven `theme-switcher.js` (`window.__adminAppearanceTheme`) do pacote de
  assets do usuário permanece como futura integração, não usada nesta fase.

## Adendo v2 (2026-10-22) — retrabalho de aceitação visual

A v1 foi **rejeitada na avaliação visual do usuário** (screenshots do próprio painel):
modo claro com superfícies legíveis mas **tela de personalização praticamente sem controles
visíveis** (radios sem texto, skin cards invisíveis, pills de accent vazias) e seção global
travada com badge **"ACL: view"**, além de itens do menu Sistema ausentes.

### Causas raízes identificadas (diagnóstico por screenshot headless + HTML vivo)

1. **Bug-macro do engine Twig (dominante):** a tela usava
   `{% macro theme_form(...) %}` + `{% from _self import theme_form %}`. O motor Twig do
   projeto **não propaga o contexto da renderização para macros** — nem mesmo via `_context`
   (só *Twig globals* explícitos entram). Resultado: `{{ text_skin_card }}`, `{{ skins }}`,
   `{{ accents }}`, todos os `text_*` e o `attribute(_context, 'accent_' ~ aid)` resolviam
   para vazio dentro do macro. Visível: botões rádio sem texto, row de skins vazia, chips
   de accent sem nome. A bateria v1 **não detectou** porque grepava apenas os containers
   (`rms-skin-picker`, h1, p-i18n), nunca o conteúdo das labels.
   **Correção:** macro removida; criado o partial
   `admin/view/template/setting/appearance_form.twig`, renderizado pelo controller com
   `$data` explícito (mesmo padrão de header/footer) e embutido em `appearance.twig` via
   `{{ form_global_html|raw }}`/`{{ form_user_html|raw }}`. O controller ganhou
   `themeFormView()` (flags curated/custom, valores dos inputs hex) e `accentChoices()`
   (accents já rotulados por i18n — elimina `attribute(_context,...)`).

2. **`.btn` é `inline-flex` no pacote do usuário (reamurcms.css) e tem
   `white-space: nowrap` (Bootstrap):** com o texto finalmente presente, labels dos skin
   cards escapavam horizontalmente (swatch + nome lado a lado) e descrições vazavam do
   card. **Correção:** no escopo `.rms-appearance`, `label.rms-skin-card { flex-flow:
   column }`, `white-space: normal` em card/radio-group, `overflow-wrap: anywhere` +
   `hyphens: auto` nas descrições, e labels curtas (i18n: "Clara/Escura", "Azul",
   descrições de 1 linha).

3. **Token legado realocado:** o retrabalho CSS reintroduziu `var(--rms-admin-accent)`
   (nome da era F4). Migrado para o canônico `var(--rms-accent)` (o check 13 da bateria
   pegou).

4. **Grant `setting/appearance` silenciosamente não aplicado (MariaDB):** o UPDATE do
   upgrade SQL usava `JSON_SEARCH(permission,'one',route,NULL,'$.access')` — forma que no
   ambiente do usuário (e no lab real) **não casa o path aninhado** ⇒ menu da rota e seção
   global sumiam (`ACL: view` para o grupo admin). **Correção idempotente:**
   `JSON_SEARCH(JSON_EXTRACT(permission,'$.access'),'one','setting/appearance') IS NULL`,
   alvo `name='Administrator' OR user_group_id=1`, com fallback documentado no SQL para
   grupos de nome personalizado (phpMyAdmin).

5. **Rate-limit do login atrapalhou a depuração** (6 tentativas/15min — o IP do lab
   travou durante os testes; contornado com limpeza de `rms_rate_limit`/sessões no lab —
   sem alteração de produto).

### Mudanças da v2 (arquivos)

- `admin/view/template/setting/appearance.twig` — sem macro; cards globais/usuário por
  partial.
- **novo** `admin/view/template/setting/appearance_form.twig` — o bloco de controles
  (skin/accent/layout), consumido duas vezes via controller.
- `admin/controller/setting/appearance.php` — `themeFormView()`, `accentChoices()`,
  forms pré-renderizados (`form_global_html`, `form_user_html`).
- `admin/view/css/admin.css` — bloco "F8-V v2" (coluna no card, wraps, radio-groups com
  rótulos visíveis e tokens `--rms-accent` canônicos).
- `admin/language/{en-us,pt-br,es-eu}/setting/appearance.php` — labels curtas para os
  grupos apertados + `text_accent_suggest_title`, `text_preview_hint`.
- `install/sql/upgrade_f8_05_admin_theming.sql` — §3 reescrito (JSON_EXTRACT +
  id/nome + fallback manual documentado).
- `tools/e2e/e2e_f805.sh` — **check 14 (regressão visual de labels)** e **check 15
  (zero macro no escopo)**, cobrindo exatamente os falsos-verdes da v1.

### Verificação v2

- `e2e_f805.sh` — **15/15** (13 originais + 14 regressão visual de labels renderizadas +
  15 banimento de macro no escopo).
- Regressões **f801 10/10, f802 13/13, f701 10/10, f703 10/10, f406 10/10**.
- **Screenshots headless (chrome-headless-shell + puppeteer-core) da live**: tela
  Aparência em modo claro e escuro mostra grid de skins com swatches + descrições,
  accents com pílulas coloridas + nomes, rádios de modo/sidebar/densidade/fonte com
  texto legível, e a seção global com form completo e botão "Save panel default" (sem
  badge ACL) para o grupo Administrator.

### Regra duradoura (para o projeto)

- **Macros Twig não veem dados de render** no nosso engine → **proibido** usar
  `{% macro %}` em telas com dados de controller; usar partials `$this->load->view()` com
  `$data` explícito.
- Checagem e2e de telas legíveis devem grepar **conteúdo textual das labels**, nunca só
  os containers.
- Grants ACL por JSON: usar sempre o idiom
  `JSON_SEARCH(JSON_EXTRACT(col,'$.grupo'),'one',route) IS NULL`.

### Adendo v2.1 — os 2 bugs de CSS que matavam o dark mode (causa dos prints)

Medido com DOM-computed (headless), não adivinhado:

6. **`:root` é pseudo-classe (0,1,0)** — o `reamurcms.css` do usuário redeclara
   `--bs-body-color: #212529` e `--bs-body-bg: #f4f6f9` em `:root` e carrega DEPOIS do
   `bootstrap.css`. Como `[data-bs-theme=dark]` do Bootstrap tem a MESMA especificidade
   (0,1,0), o pacote vence por ordem de cascata → o painel nunca escurecia (fundo claro
   com texto escuro). Fix em escopo: `html[data-bs-theme="dark"] body.admin-shell { color-scheme:
   dark; --bs-body-*: tokens dark; --bs-border-color: #495057; links dark }` (0,2,2 > 0,1,0).
7. **`.card{--bs-card-bg:#fff}` do Bootstrap 5.3 é ESTÁTICO** (não reage ao tema) →
   todos os painéis/cards do painel ficavam brancos no escuro e qualquer texto
   secundário `rgba(222,226,230,.75)` sobre eles ficava invisível. Fix no mesmo escopo:
   `--bs-card-bg: #2b3035; background-color: #2b3035` para `.card`/`.panel`, fundos de
   header/footer sutis, e `--bs-list-group-bg`, `--bs-modal-bg`, `--bs-dropdown-bg`,
   `--bs-accordion-bg` compatíveis.
8. Pills do picker: superfícies forçadas `--bs-tertiary-bg` por pele (`.rms-surface`)
   para manter contraste uniforme em qualquer tema.

**Lição processual que fecha o ciclo:** o lab local NÃO fica "bonito" depois da bateria
de regressões — a `e2e_f406.sh` reinstala o banco SEM os upgrade SQL do F8-V (ela é da F4
e não conhece as rotas novas). Se o screenshot do lab mostrar "ACL: view" após uma rodada
de regressões, é artefato do reset da última bateria, não regressão de produto.
