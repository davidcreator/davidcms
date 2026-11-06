# Índice — Frente BJ (tema baseado em davidalmeida.xyz)

Pacote: `ReamurCMS-Frente-BJ-Tema-David-Creator.zip` · HEAD `7745d731…` · CI 7/7 ✅
**1 tema importável + CSS próprio + 2 prévias** · **nenhum código alterado** · **sem migration**.
**BI aprovada visualmente** em 2026-11-06 (não é release).

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BJ.md` | Fonte consultada, identidade, conteúdo, validação, limites |
| 2 | `INDICE-BJ.md` | Este índice |
| 3 | **`docs/temas/David-Creator.reamurtheme.json`** | **O tema**: 25 fontes, site completo |
| 4 | `docs/temas/GUIA-TEMA-DAVID.md` | Como importar, o que foi transcrito, limite do CSS |
| 5 | **`docs/temas/preview-desktop.png`** | **Prévia renderizada 1440 px** |
| 6 | **`docs/temas/preview-mobile.png`** | **Prévia renderizada 390 px** |
| 7 | `docs/temas/preview.html` | Composição estática das capturas |
| 8 | `tools/verify-david-theme.cjs` | Verificador Node puro |
| 9 | `evidencia/theme-decode.txt` | 46 OK / 0 FALHAS com `decode()` real |
| 10 | `evidencia/layout-check.json` | Medições de layout no Chromium |
| 11 | `evidencia/validacao.txt` | Guardas na réplica |

## O tema

| Campo | Valor |
|---|---|
| `name` / `slug` | `David Creator` / `david_creator` |
| `checksum` | `c9fb44b0a82849b19991db53c415474ed71fcaf83d4f4b987838cd7bb3ab0201` |
| `primary` / `surface` | **`#5cc6d0`** / **`#1b1f24`** |
| `scene` / `three` | `hero-particles` ligado, slot `hero`, ciano |
| `templates` | **25/25** com conteúdo real (~55 KB) |
| CSS | **~16 KB** embutido em `partial/site_header.twig` |
| `tokens` | 22 campos AA válidos |

**11 seções** na home · **34 cards** · **11 páginas internas** · **12 SVG inline**
· header "DAVID CREATOR" com nav de 9 itens · rodapé em 4 colunas · responsivo
980/640 px · `:focus-visible` e `prefers-reduced-motion`.

Textos fiéis ao site: WHO I AM?, processo de 6 etapas, MY SKILLS, WHAT DO I
DO?, depoimentos, WHAT IT LOOKS LIKE?, CONNECTED TOOLS, BELOVED CLIENTS,
LATEST NEWS, LET'S HAVE A CHAT?, contato e © 2025. **Fotos e fontes externas
não foram copiadas** — visuais vetoriais determinísticos, tipografia de sistema.

## Como usar

Admin → Temas do Catálog → **Importar tema** → arrastar o `.json` →
**Importar como rascunho** → revisar → **Ativar**. Nada é publicado sozinho.

## Validação medida

`validateConfig()` **0 erros** · **`decode()` ok=true** · **harness 46 OK / 0
FALHAS** · checksum idêntico em Python e Node · 25 fontes seguras e
balanceadas · verificador 0 FALHAS · prévias **sem overflow**
(1440→1440, 390→390), fundo `rgb(27,31,36)`, acento `rgb(92,198,208)` ·
`verify-checksums` 142/0 · `repo-health` **6 PASS / 0 FAIL** ·
`verify-contract-freeze` **COERENTE**.

## Limite honesto

O formato não tem chave `css` nem assets: o CSS fica **embutido em `<style>`**
no `site_header`, uma vez por página. Para um `.css` separado e as fotos reais
é preciso autorizar o **Recorte B** (muda allowlist e renderer).

## O que NÃO faz

Não instala automaticamente, não cria arquivos `.twig`/`.css`/`.js` no
repositório, não escreve JavaScript, não altera `header.twig`, schema,
contrato, hash, manifesto ou workflow, não decide D01–D10, não escreve no
GitHub pelo agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health e aceite visual deste
tema ainda pendentes.**
