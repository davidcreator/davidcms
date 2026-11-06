# Análise — KAZEditor-Theme: importação de temas/templates (Twig + ThreeJS) e gestão estilo WordPress

**Data:** 2026-11-06 · **HEAD:** `df0d3d34e4903a948fcf5bdfa19823c072ef41a0`
**CI:** ✅ 7/7 success · **Leitura de código apenas**, nenhuma alteração aplicada.

> **Nota:** o `Health-Admin-Real-Instrucoes.md` foi aplicado no GitHub, mas o
> **JSON do health não foi colado** — o passo 2 segue tecnicamente aberto.

---

## 1. O que JÁ EXISTE (surpreendentemente completo)

### Gestão de temas — `dev/admin/controller/design/theme.php` (73 KB)

| Rota | Função |
|---|---|
| `design/theme` | lista, com `access`/`modify` ACL e CSRF |
| `.edit` | editor completo (cores, cena, templates, tokens, condições…) |
| `.preview` | prévia rascunho vs publicado |
| `.restore` | **rollback por revisão** |
| `.export` | pacote JSON versionado (rascunho **ou** publicado) |
| `.import` | pacote JSON via POST → **sempre como rascunho, nunca publica** |
| demais blocos | schedule, sites, media, section/component builders, scene editor |

### Persistência (sem migration nova)

`rms_design_theme` — `name`, `slug`, `config`, `draft_config`,
`published_config`, `status` (**no máximo um tema ativo**), `date_added`,
`date_modified`. `rms_design_theme_revision` — snapshot + `user_id` para
auditoria/rollback. ACL `design/theme` concedida a Administrator nos upgrades
`f10_02` / `f10_03`.

### Pacotes e child-theme — `dev/system/library/theme/theme_package.php`

- `FORMAT = reamurcms-theme`, `VERSION = 1`, **512 KiB**, cadeia de herança **≤ 5 níveis**;
- `export()` com **`checksum` SHA-256 do config canônico** (chaves ordenadas recursivamente);
- `decode()` **fail-closed** em tamanho, JSON, formato, versão, identidade e `hash_equals` do checksum — um byte alterado invalida o pacote;
- `validateConfig()` reusa `ConditionEngine`, `TokenRegistry` e a **mesma regex de fontes Twig do editor**;
- `resolveChain()` mescla pai → filho por chave (`tokens`, `templates`, `three`, `components`, `media`); ciclo/profundidade/pai ausente param a caminhada;
- **child-theme é uma chave `parent` dentro do JSON**, sem coluna nova.

### Twig — **25 fontes allowlistadas** (`TEMPLATE_KEYS`)

`common/home`, `common/footer`, `common/contact`, `partial/site_header`,
`partial/hero`, `partial/hero-3d`, `partial/about`, `partial/methodology`,
`partial/skills`, `partial/services`, `partial/testimonials`,
`partial/portfolio`, `partial/tools`, `partial/news`, `partial/clients`,
`partial/contact`, `cms/page_info`, `catalog/project_list`,
`catalog/project_info`, `blog/blog_list`, `blog/blog_info`,
`tool/tool_list`, `tool/tool_info`, `checkout/quote`, `error/not_found`.
Cada fonte é uma **string dentro do JSON** (≤ 131.072 bytes), validada — não
um arquivo `.twig` no disco.

### ThreeJS — dados de edição, não código

- `three`: identificador de cena allowlistado (`'', hero-particles`,
  `portfolio-3d`, `tools-3d`, `not-found`) + slot (`hero`, `home_top`,
  `footer_top`);
- `kaz_scene` (`kaztheme-scene/v1`): `layers` com `kinds`
  `rect/text/button/card/section`, `roles`, **≤ 48 camadas**, **≤ 64 KiB**;
- `ScenePresetRegistry`: 6 presets (`aurora`, `nebula`, `geometric`,
  `workshop`, `lost`, `off`) com limites `count` 50–800 e `speed` 0,01–0,20;
- `dev/system/library/three/` + `dev/catalog/controller/common/three.php`
  + `theme-scene-block.twig` / `theme-scene.twig` fazem o runtime.

### Personalização (o "Customizer" do ReamurCMS)

`TokenRegistry`, `ConditionEngine` (+ `theme-condition-builder.js`),
`SectionRegistry` (+ `theme-section-builder.js`), `ComponentRegistry`
(+ `theme-component-library.js`), `ThemeSchedule`, `ThemeMedia`,
`ThemeSites`, `ThemeSnapshot`, `ThemeCompareView`, `theme_switcher.js`,
`dev/tools/validate-themes.php`.

---

## 2. Restrição de segurança EXPLÍCITA já documentada

`docs/GUIA-PACOTES-CHILD-THEME.md`, textual:

> *"**Sem migration e sem schema**… **Nenhum arquivo é lido ou escrito no
> servidor**: export devolve JSON na resposta HTTP, import recebe JSON no
> POST. **Sem upload, `eval`, `call_user_func`, regex do usuário, HTML,
> `|raw` novo ou código executável.**"*

`theme_package.php` repete no cabeçalho: *"Sem eval, call_user_func, regex do
usuário, IO, upload, código executável ou HTML."*

Ou seja: o sistema foi **deliberadamente** desenhado para transportar temas
como **dados validados**, não como **arquivos executáveis**. Foi exatamente
isso que permitiu child-theme e versionamento sem abrir SSTI/XSS.

---

## 3. O que o seu pedido pede que HOJE NÃO EXISTE

| # | Lacuna | Por que não existe | Risco |
|---|---|---|---|
| 1 | **Upload de ZIP de tema** (instalar como WordPress) | guia proíbe upload; import é colar JSON no POST | ZIP slip, path traversal, MIME, tamanho, escrita em disco |
| 2 | **Arquivos `.twig` arbitrários** (novos templates, não só as 25 chaves) | `TEMPLATE_KEYS` é allowlist fechada; fonte é string no JSON | **SSTI / execução de código no servidor** se o Twig vier de arquivo sem sandbox |
| 3 | **Módulos ThreeJS `.js` importáveis** | cena é config + presets, nunca JS do usuário | **XSS armazenado** no catálogo público |
| 4 | Gestão WordPress completa: excluir, duplicar, screenshot, `style.css` com metadados, atualização automática, ativação com Customizer | há lista/ativar/preview/revisões/export/import, mas não esses itens | amplia escopo e ACL |

---

## 4. Travas de governança vigentes

| Item | Estado |
|---|---|
| `decision-matrix.json` | **10 `PENDING_DECISION` / 0 `DECIDED`** — *"Nenhuma decisao tomada por inferencia"* |
| `frozen_artifacts_sha256` | **11 contratos congelados**, todos coerentes no HEAD |
| `F9-25` | `patterns_persistence: PROPOSAL_PENDING` · `catalog_context_preview: DESIGN_GATE_PENDING` · **"Request a dedicated ACL decision… before endpoints/storage"** · migration/schema dependem de aprovação do install-agent |
| Restrições permanentes | sem migration, sem `schema.sql`, sem `|raw` novo, sem fontes/CDN, sem relaxar segurança, sem runtime/MySQL/staging/deploy |
| Release | `release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready: false` |

**Consequência:** permitir Twig/JS arbitrário ou upload de arquivos **não é
uma implementação de código comum** — é uma **decisão de segurança e de ACL**
que só o responsável pode tomar, e que pode exigir migration (hoje vetada).

---

## 5. Recortes possíveis

### A — **Recomendado**: import com arquivo, mantendo o modelo seguro
Trocar "colar JSON" por **selecionar um arquivo `.json` no navegador**, lido
via `FileReader` no **client** e enviado no mesmo POST. Mesmo
`ThemePackage::decode()`, mesmo checksum, mesma allowlist, **zero escrita em
disco**, zero código executável. Acrescenta drag-and-drop, preview do nome/
versão/pai antes de importar e mensagens de erro já existentes.
**Risco: baixo.** Não exige migration nem ACL nova.

### B — Ampliar a allowlist de templates Twig
Adicionar novas chaves a `TEMPLATE_KEYS` (fontes continuam sendo strings
validadas dentro do JSON). Exige você dizer **quais** templates.
**Risco: baixo**, mas mexe em `theme_package.php` (fora dos contratos
congelados) e nos checkers de validação.

### C — ZIP WordPress-style com `.twig` / `.js` reais
Instalar temas como pacote de arquivos no servidor, com editor de arquivos.
**Risco: alto.** Exige decisão explícita de segurança, sandbox Twig
(`Twig\Sandbox\SecurityPolicy`), ACL dedicada (F9-25), validação de ZIP,
provavelmente migration/estrutura de diretórios, e **revê** a restrição
"sem upload / sem código executável / sem `|raw` novo". Hoje **vetado**.

### D — Só gestão/UX estilo WordPress, sem tocar em import
Lista com screenshot/versão, duplicar, excluir com confirmação, ativar,
comparar, restaurar, exportar — aproveitando rotas e tabelas existentes.
**Risco: baixo/médio** (exclusão e duplicação pedem ACL + CSRF).

---

## 6. O que eu NÃO vou fazer sem a sua decisão

Não vou permitir Twig arbitrário, não vou aceitar upload de `.js`, não vou
relaxar `TEMPLATE_KEYS` nem o checksum, não vou escrever arquivos no servidor,
não vou fazer migration, não vou alterar os 11 contratos congelados, não vou
decidir as 10 `PENDING_DECISION`, não vou marcar `PROPOSAL_PENDING` como
aprovado, não vou escrever no GitHub e não vou declarar release.

**Perguntas respondidas via formulário:** recorte (A/B/C/D) e autorização de
segurança para Twig/ThreeJS.
