# Relatório Final — Frente BM (Atlas Studio 3D)

**Data:** 2026-11-07 · **HEAD:** `ad7643de6ca2` · **Pedido:** *"fazer um tema
completo utilizando ThreeJS seguindo os mesmos critérios aplicados no Astra"*.
`ask_user` → **`referencia=atlas`** (o Atlas Studio aprovado na BI) e
**`threejs=completo`** (site completo + cena declarativa em várias seções).

## 1. O que foi construído

`Atlas Studio 3D` (`atlas_studio_3d`) — **tema completo de site real**, não
esqueleto. Parte dos **25 fontes Twig aprovados** na BI (*"Agora sim ficou
perfeito e como esperado"*) e acrescenta apenas **dados validados**:

| Aspecto | Atlas Studio (BI, aprovado) | Atlas Studio 3D (BM) |
|---|---|---|
| Fontes Twig | 25, site completo | **25**, site completo (13 partials/rodapé idênticos) |
| `three.enabled` | `false` | **`true`** |
| `scene` | `''` | `hero-particles` |
| Cenas por rota | nenhuma | **4 cenas via 5 regras `conditions`** |
| Slot `[data-scene]` | 1 (inerte, no rodapé) | **11 templates**, 1 por página |
| `kaz_scene` | 3 camadas | **16 camadas** declarativas |
| Tokens/cores/tipografia | `#4338ca`, system, 1140px | **idênticos** |

**13 templates ficaram byte-a-byte iguais** ao tema aprovado (todos os
partials de seção e o rodapé). As mudanças ficaram concentradas no CSS do
cabeçalho, nos templates de página e nos metadados `three`/`conditions`/
`kaz_scene`.

## 2. Como o ThreeJS entra (Recorte A — sem código executável)

O tema **não contém JavaScript**. O slot é o partial nativo
`partial/theme-scene.twig`, que emite apenas atributos `data-*` allowlistados:

```twig
{% include 'partial/theme-scene.twig' %}
```

O `scene-runner.js` **self-hosted do próprio Catálogo** faz `boot()` nos
`[data-scene]` e respeita `prefers-reduced-motion` / `IntersectionObserver`.
O `common/header.twig` (shell confiável, fora da allowlist de 25 fontes)
carrega o importmap e `three/main.js` quando `has_3d` é verdadeiro. O tema
escolhe **cena, slot, contagem, cor e velocidade** — nunca escreve código.

`conditions` allowlistadas trocam a cena por rota, validadas pelo
`ConditionEngine` real:

| Rota | Cena | Slot | Partículas | Cor | Velocidade |
|---|---|---|---|---|---|
| `common/home` | `hero-particles` | `hero` | 520 | `#4338ca` | 0.06 |
| `catalog/project_*` | `portfolio-3d` | `home_top` | 500 | `#22d3ee` | 0.06 |
| `tool/tool_*` | `tools-3d` | `home_top` | 350 | `#f59e0b` | 0.08 |
| `error/not_found` | `not-found` | `footer_top` | 200 | `#94a3b8` | 0.04 |
| `mobile = true` | `hero-particles` + classe `atlas-scene-lite` | `hero` | 180 | `#4338ca` | 0.04 |

O rodapé aprovado já condicionava o slot a `footer_top`, então **não há dois
containers `[data-scene]` na mesma rota** — medido: `slots = 1` nas 4 capturas.

`kaz_scene` tem **16 camadas** (`rect`/`text`/`button`/`card`/`section`,
960×540, grid 8) que desenham a composição 3D no editor. São **dados de edição
e versionamento**: `ThemeSceneCss` compila 16 camadas em `--rms-scene-*`, e a
cena só aparece na **prévia do Admin** (gate fail-closed da frente BL) — nunca
no front público.

**`parent` ficou vazio de propósito.** Um child-theme dependeria de o Atlas
original estar importado/publicado no banco; sem ele, o editor cairia nos
`.twig` do disco — exatamente o defeito diagnosticado na BL. O tema é
autossuficiente.

## 3. Validação com as classes reais (PHP 8.4 WASM)

`evidencia/export-bm.txt` — **`ThemePackage`, `ThemeSnapshot`,
`ThemeSceneCss` e `ConditionEngine` do HEAD `ad7643de…`**:

| Verificação | Resultado |
|---|---|
| `validateConfig()` | **`[]`** |
| `normalizeScene()` | OK |
| `ThemeSceneCss::compile()` | **16 camadas**, CSS 1322 bytes allowlistado |
| `ThemeSnapshot::resolve()` nas 4 rotas | cenas corretas por rota |
| `export()` / `encode()` | 76.637 bytes (< 512 KiB) |
| `decode()` | **`ok=true`, `errors=[]`** |
| `checksum` (SHA-256 do config canonizado) | `59ca4cd1ae312abd4c5a5771ed9981335d90f6fd35cc30e3aa8f233c1fb4adbf` |
| **Total** | **14 OK / 0 FALHAS** |

## 4. Prévia visual real (Chromium 141)

As libs de sistema ausentes nas sessões anteriores foram extraídas dos `.deb`
(`LD_LIBRARY_PATH`), então foi possível rodar **Chromium headless de verdade**
com viewport real via Playwright — mesma técnica da BI/BJ.

`evidencia/layout-bm.json` e 4 capturas:

| Captura | Cena | Altura do slot | Overflow | `<script>` | Pageerrors |
|---|---|---|---|---|---|
| Home 1440px | `hero-particles` | 340px | 0 | **0** | 0 |
| Home 390px | `hero-particles` | 220px | 0 | **0** | 0 |
| Projetos 1440px | `portfolio-3d` | 240px | 0 | **0** | 0 |
| 404 1440px | `not-found` | 180px | 0 | **0** | 0 |

Home: 8 seções `.atlas-section`, 16 cards, header/footer/CTA presentes, botão
primário `rgb(67,56,202)` com texto branco, **zero handlers inline** e **zero
scripts**. Prancha: `BM-Preview-Cenas.png`.

> As capturas mostram o **fallback declarativo** (pontilhado/gradiente CSS).
> O WebGL animado é desenhado em runtime pelo `scene-runner.js` do Catálogo;
> a prévia estática prova o layout, os slots e os parâmetros, não a animação.

## 5. O que NÃO foi mexido

Nenhuma escrita do agente no GitHub; nenhum arquivo `dev/`, contrato,
`TEMPLATE_KEYS`, `CONFIG_KEYS`, `CHECKSUMS`, manifesto, schema, `ALTER`,
migration, runtime, MySQL ou deploy. Nenhum `.js`/`.twig` solto, `|raw`,
`<script>`, handler `on*=`, `innerHTML`, `eval`, `fetch`, fonte/CDN, upload em
disco ou contrato novo `kaz_scene`→`templates`. O Recorte A permanece
**dados validados, sem código executável**. Nenhuma decisão D01–D10.

## 6. Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. CI verde, PHP WASM,
Chromium e guardas **não significam release aprovada**. Aceite visual da BM,
health no Admin real e aplicação dos patches BL **ainda pendentes**.

## 7. Como usar

Ver `GUIA-BM.md`: importar o `.json` no **tema ativo** e **Ativar** (sem
Ativar, `save()` grava só `draft_config` — causa raiz da BL). Os patches
`A`/`B` da BL continuam **propostas não aplicadas**: o HEAD `ad7643de…`
commitou apenas `docs/`, com **`dev/` intacto**.
