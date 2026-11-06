# Registro visual — Frente AP (correção de escopo)

Data: 2026-11-06 · Base reauditada: `9c809050ff908b66b6f3bf97c1fc5a1aed9b3160`
(commit `feat: add total dark admin theme for BDREditor`, árvore 3.209 entradas /
2.626 blobs / 41.926.874 bytes, `truncated: false`).

## Evidências do usuário (critério vigente)

| Arquivo | Papel | O que mostra |
|---|---|---|
| `image-9.png` | **Reprovação da AO** | Admin real do ReamurCMS com a página inteira escurecida: sidebar com item ativo e borda **verde-menta**, abas de idioma com sublinhado verde, topbar, breadcrumb, cards e campos alterados. A queixa: *"era para Editar somente o BDREditor mas acabou editando a barra de navegação lateral do ReamurCMS"*. |
| `image-10.png` | **Referência KAZTheme-Editor** | Mesma sidebar do Admin, mas com o item ativo **Themes em azul**; a identidade verde/escura aparece **apenas dentro** do editor (screenbar, camadas, propriedades, canvas). |

`image-7.png` e `image-8.png` são critérios históricos (AA / AL). Para a AP
prevalecem `image-9.png` e `image-10.png`.

## Diagnóstico reproduzido em laboratório

Prévia A/B/C servida em `:8123/ap/`, com `bootstrap.css`, `reamurcms.css`,
`themes/light.css`, a AN, e a marcação real de `#menu > li.active > a`:

| Estado | `menuBg` | `--bs-primary` | `.card` | `html`/`body` |
|---|---|---|---|---|
| `admin_nativo.html` (AN, sem folha total) | `rgb(13, 110, 253)` | `#0d6efd` | `rgb(255,255,255)` | `rgb(244,246,249)` |
| `admin_ao.html` (AO reprovada) | `rgb(49, 239, 184)` | `#31efb8` | `rgb(31,31,35)` | `rgb(24,24,27)` |
| `admin_ap.html` (corrigida) | `rgb(13, 110, 253)` | `#0d6efd` | `rgb(255,255,255)` | `rgb(244,246,249)` |

Causa: `#menu > li.active > a { background-color: var(--bs-primary) }` em
`reamurcms.css`, combinado com a sobrescrita de `--bs-*` no `body` pela AO.
A AP elimina **toda** sobrescrita de tokens globais.

## O que a AP preserva dentro do BDREditor

Host `rgb(24,24,27)`, chrome/ribbon escuros, accent `#31efb8`, canvas
**branco** `rgb(255,255,255)`, documento de blocos idêntico, foco
`#174ea6` / `3px` / `offset 2px`, escala ampla e dobras da AN, e os overlays
`rb-*` (dialogs, padrões, inserter, prévias) agora escuros
`rgb(38,38,43)` / campos `rgb(44,44,51)`.

## O que a AP NÃO toca

Sidebar, topbar, breadcrumb, titlebar, cards, campos, abas de idioma, botões,
alertas, badges, tabelas, modais e o acento azul do ReamurCMS. A varredura de
**todos os elementos fora do editor** exige zero divergências de
`background-color`, `color` e `border-color` contra o Admin nativo.

## Aceitação

O veredito é **visual, no Admin real, pelo usuário**. A AP só será considerada
aceita quando:

1. a sidebar/nav/topbar e o formulário estiverem como **antes da AO** (azul,
   tema ReamurCMS);
2. o BDREditor continuar escuro e mais próximo do KAZTheme-Editor de
   `image-10.png`;
3. nenhum outro elemento fora do BDREditor tiver mudado.

PROBE, PHP WASM, Chromium headless, CI verde e `copy(__kazHealth())` local
**não substituem** esse veredito. Nenhum deles **significa release aprovada**;
`release_approved` segue `false`, F9-27 `BLOCKED_NOT_RUN`, F9-18
`release_ready: false`.

## Pendências inalteradas

CI remoto não verificado (`403 Must have admin rights`), health de produção
não coletado, backend/DB/migração/ciclo salvar–Publicar fora de escopo.
Nenhuma escrita do agente no GitHub; AN e AO foram aplicadas pelo usuário.
