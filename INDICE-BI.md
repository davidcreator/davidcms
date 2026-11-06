# Índice — Frente BI (tema de site real em Twig)

Pacote: `ReamurCMS-Frente-BI-Tema-Site-Real-Twig.zip` · HEAD `a704d2ae…` · CI 7/7 ✅
**1 tema importável + CSS próprio + 2 prévias renderizadas** · **nenhum código alterado** · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BI.md` | O que mudou, arquitetura, prévia, validação, limites |
| 2 | `INDICE-BI.md` | Este índice |
| 3 | **`docs/temas/Atlas-Studio.reamurtheme.json`** | **O tema**: 25 fontes, site completo |
| 4 | `docs/temas/GUIA-TEMA-ATLAS.md` | Como importar, personalizar, limite do CSS |
| 5 | **`docs/temas/preview-desktop.png`** | **Prévia renderizada 1440 px** |
| 6 | **`docs/temas/preview-mobile.png`** | **Prévia renderizada 390 px** |
| 7 | `docs/temas/preview.html` | Composição estática usada nas capturas |
| 8 | `tools/verify-site-theme.cjs` | Verificador Node puro |
| 9 | `evidencia/theme-decode.txt` | 36 OK / 0 FALHAS com `decode()` real |
| 10 | `evidencia/layout-check.json` | Medições de layout no Chromium |
| 11 | `evidencia/validacao.txt` | Guardas na réplica |

## O tema

| Campo | Valor |
|---|---|
| `name` / `slug` | `Atlas Studio` / `atlas_studio` |
| `checksum` | `4241cd9b430c66c72929ab0bee41c2a22cb9bbbe71141b28a5fe713baff72534` |
| `primary` / `surface` | `#4338ca` / `#ffffff` |
| `templates` | **25/25** com conteúdo editorial real (~55 KB) |
| CSS | **~14 KB** embutido em `partial/site_header.twig` |
| `tokens` | 22 campos AA |
| `three` | desligado (site editorial) |

**9 seções** na home · **16 cards** · **11 páginas internas** · **15 SVG inline**
· header com nav de 6 itens · rodapé em 4 colunas · responsivo 980/640 px ·
`:focus-visible` e `prefers-reduced-motion`.

## Como usar

Admin → Temas do Catálog → **Importar tema** → arrastar o `.json` →
**Importar como rascunho** → revisar → **Ativar**. Nada é publicado sozinho.

## Validação medida

`validateConfig()` **0 erros** · **`decode()` ok=true** · **harness 36 OK / 0
FALHAS** · checksum idêntico em Python e Node · 25 fontes seguras e
balanceadas · verificador 0 FALHAS · prévias **sem overflow**
(1440→1440, 390→390) · `verify-checksums` 142/0 · `repo-health`
**6 PASS / 0 FAIL** · `verify-contract-freeze` **COERENTE**.

## Limite honesto

O formato `reamurcms-theme` não tem chave `css` nem assets: o CSS fica
**embutido em `<style>`** no `site_header`, uma vez por página. Para um `.css`
separado e imagens reais é preciso autorizar o **Recorte B** (muda allowlist
e renderer).

## O que NÃO faz

Não instala automaticamente, não cria arquivos `.twig`/`.css`/`.js` no
repositório, não escreve JavaScript, não altera `header.twig`, schema,
contrato, hash, manifesto ou workflow, não decide D01–D10, não escreve no
GitHub pelo agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health e aceites visuais
ainda pendentes.**
