# Protocolo de Aceitação Visual — Frente AP (vigente)

Substitui o `Protocolo-Aceitacao-Visual-AM.md` como critério vigente.
Data: 2026-11-06 · Base AO: `9c809050ff908b66b6f3bf97c1fc5a1aed9b3160` ·
HEAD do estado avaliado: `1b7724a10314cf5b5fee479a328651b21843d06a`.

> ## ✅ VEREDITO: **APROVADO** (usuário, 2026-11-06)
> `image-9.png` deixa de ser a reprovação vigente e passa a **registro
> histórico superado**. `image-10.png` é a referência atendida. O escopo
> "editar somente o BDREditor" está **fechado e congelado**.
> Registro completo: `Registro-Veredito-Visual-AP.md`.
> **Isto NÃO é release**: `release_approved: false`, F9-27
> `BLOCKED_NOT_RUN`, F9-18 `release_ready: false`, 10 `PENDING_DECISION`.

## Histórico de vereditos

| Frente | Veredito | Evidência |
|---|---|---|
| AA | **Reprovada** | `image-7.png` |
| AL | Aplicada, com ajuste de canvas | `image-8.png` |
| AN | Aplicada com sucesso (pré-requisito) | relatório AN |
| **AO** | **REPROVADA**: *"passou dos limites, era para Editar somente o BDREditor mas acabou editando a barra de navegação lateral do ReamurCMS"* | **`image-9.png`** |
| KAZTheme-Editor | **Referência** do tema desejado | **`image-10.png`** |
| **AP** | ✅ **APROVADA visualmente** (`179269fe…`, +9/−0/~6, 6/6 arquivos idênticos) | capturas A/B/C no pacote + `Registro-Veredito-Visual-AP.md` |
| `image-9.png` | **Reprovação SUPERADA** (histórico) | veredito APROVADO |
| `image-10.png` | **Referência ATENDIDA** | veredito APROVADO |
| **AQ** | Roteiro aplicado (`bfe9eec7…`, +6/−0/~0, 10/10 idênticos); CI remoto **7/7 success** | `Roteiro-Proximos-Passos-AP.md` + `evidencia/ci-remoto-bfe9eec7.json` |
| **AT** | Avanço do passo 2 aplicado (`1b7724a1…`, +5/−0/~2, 7/7 idênticos); CI **7/7 success** | `RELATORIO-FINAL-AT.md` + `Checklist-Veredito-Visual-AP.md` |
| **Veredito** | ✅ **APROVADO pelo usuário em 2026-11-06** (resposta explícita `aprovado`) | `Checklist-Veredito-Visual-AP.md` + `Registro-Veredito-Visual-AP.md` |
| **AU** | Registro do veredito (esta atualização) | `RELATORIO-FINAL-AU.md` |

## O que verificar no Admin real, após aplicar a AP

1. **Barra de navegação lateral** — o item ativo deve estar **azul**
   (`#0d6efd`), sem borda/linha verde, como em `image-10.png`.
2. **Topbar, breadcrumb, título, cards, campos, abas de idioma, botões,
   alertas e badges** — devem estar **como antes da AO** (tema ReamurCMS
   claro, acento azul).
3. **BDREditor** — continua escuro, com accent verde, canvas branco, e mais
   próximo do KAZTheme-Editor de `image-10.png`.
4. **Nada mais muda** fora do BDREditor — inclusive os overlays/dialogs do
   próprio editor (esses podem e devem ser escuros).

## Evidência laboratorial (não substitui o veredito)

A/B/C com `bootstrap.css`, `reamurcms.css`, `themes/light.css`, AN e o markup
real de `#menu > li.active > a`:

| Estado | sidebar | página Admin |
|---|---|---|
| nativo | `rgb(13,110,253)` azul | clara |
| AO | `rgb(49,239,184)` verde | escura |
| **AP** | `rgb(13,110,253)` azul | clara |

Varredura de todos os elementos fora do editor: **AO = 54 divergências**,
**AP = 0**. PROBE 53/0, Chromium 41/0, 117 asserções finais 0 falhas.

## Limites

PHP WASM, Chromium headless, CI verde e health local **não significam release
aprovada**. O CI remoto foi **verificado** (run `42217429359`, 7/7 success no
HEAD `bfe9eec7…`), mas isso não substitui o veredito visual. `__kazHealth()`
não existe no código; o health observacional é `tools/health-admin-ap.js` e
ainda depende de ser colado no console do Admin real. Produção autenticada,
backend/DB e ciclo salvar–Publicar seguem fora de escopo.
`release_approved: false`, F9-27 `BLOCKED_NOT_RUN`,
F9-18 `release_ready: false`.

**O veredito foi dado pelo usuário, no Admin real, e foi APROVADO.**
Os quatro critérios acima estão satisfeitos. Isso encerra a avaliação visual
da AP — **não** encerra os contratos de runtime, as 10 decisões pendentes nem
autoriza release.

## Estado após o veredito

| Passo | Estado |
|---|---|
| 1 — Veredito visual da AP | ✅ **CONCLUÍDO (APROVADO)** |
| 2 — CI remoto | ✅ 7/7 success (run `42217429359`) |
| 2 — Health do Admin real | ⏳ `tools/health-admin-ap.js` no console |
| 3 — Decisões D01–D10 | ⏳ pacote **AR** pronto, sem conflito |
| 4 — Verificação F918 / runtime | ⏳ pacote **AS** pronto, sem conflito; exige staging |
| 5 — Escopo extra (KAZ/catálogo) | vetado até decisão explícita |

**Todos os passos restantes dependem de uma ação do usuário.** Sem
`copy(__kazHealth())` (função inexistente), sem sessão autenticada automatizada,
sem staging e sem decisões por inferência.
