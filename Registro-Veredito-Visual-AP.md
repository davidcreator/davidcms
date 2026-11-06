# Registro de veredito visual — Frente AP

| Campo | Valor |
|---|---|
| **Decisão** | **APROVADO** |
| **Decidido por** | usuário (David Almeida) |
| **Data** | 2026-11-06 (America/Sao_Paulo) |
| **Forma** | resposta explícita `aprovado` à pergunta estruturada de próximo passo |
| **Objeto** | Frente AP — `ReamurCMS-Frente-AP-Escopo-Corrigido-BDREditor` |
| **HEAD do estado avaliado** | `1b7724a10314cf5b5fee479a328651b21843d06a` |
| **HEAD da aplicação da AP** | `179269fea36584076f9ef41b95ae94638dd08f45` |
| **Critério vigente até aqui** | `image-9.png` (reprovação) / `image-10.png` (referência) |

## O que foi aprovado

A correção de escopo: a **barra de navegação lateral e todas as superfícies
do Admin ReamurCMS voltaram ao estado anterior à AO**, e a identidade KAZ
ficou **somente no BDREditor**. Os quatro critérios do
`Checklist-Veredito-Visual-AP.md` foram considerados satisfeitos pelo usuário
no Admin real autenticado:

1. Item ativo da sidebar **azul** (`#0d6efd`), sem borda/linha verde.
2. Topbar, breadcrumb, cards, campos, abas de idioma, botões, alertas e
   badges **como antes da AO**.
3. BDREditor escuro, accent verde, canvas branco, próximo de
   `image-10.png` (KAZTheme-Editor).
4. **Nada mais** alterado fora do BDREditor.

## Evidência técnica de apoio (não substitui a decisão)

A/B/C com `reamurcms.css` + `themes/light.css` + AN:

| Estado | item ativo da sidebar | `--bs-primary` | página Admin |
|---|---|---|---|
| nativo + AN | `rgb(13,110,253)` azul | `#0d6efd` | clara |
| AO (reprovada) | `rgb(49,239,184)` verde | `#31efb8` | escura |
| **AP (aprovada)** | `rgb(13,110,253)` azul | `#0d6efd` | clara |

Varredura de todos os elementos fora do editor: AO **54 divergências**,
AP **0**. PROBE 53/0, Chromium 41/0, validação final 117/0, CI remoto do
HEAD 7/7 success, 6/6 arquivos da AP byte a byte idênticos no remoto.
O instrumento `veredito_visual_ap.js` deu `TODOS_OS_PONTOS_OK` (6/6) na AP
e `REVISAR` (3/6) na AO.

## Efeitos deste veredito

- ✅ **Passo 1 do roteiro: CONCLUÍDO.** `image-9.png` deixa de ser a
  reprovação vigente e passa a **registro histórico superado**.
- ✅ `image-10.png` permanece como referência visual atendida.
- ✅ O escopo "**editar somente o BDREditor**" está fechado e congelado para
  as frentes A–AP/AT.
- ✅ A aplicação AQ (+6/−0/~0, 10/10 idênticos) e AT (+5/−0/~2, 7/7
  idênticos) ficam confirmadas.

## O que este veredito **NÃO** é

| Item | Permanece |
|---|---|
| `release_approved` | **`false`** — aprovação visual ≠ release |
| F9-27 | **`BLOCKED_NOT_RUN`**, `runtime_tests_executed: false` |
| F9-18 | **`release_ready: false`**, F918-01…04 abertos |
| F9-26 | `wcag_conformance_claimed: false` |
| `decision-matrix.json` | **10 `PENDING_DECISION`**, `DECIDED: 0` — decisões são do responsável |
| F9-25 | `patterns_persistence: PROPOSAL_PENDING` |
| CI verde / health / headless | **não significam release aprovada** |

Nenhum `PENDING`/`SKIP`/`NOT_RUN`/`STATIC_ONLY`/`BLOCKED_NOT_RUN` foi
convertido em `PASS`. Nenhum hash, `base_head`, contrato, manifesto,
workflow, `schema.sql`, JS de ~600 KB, DOM, Twig, renderer ou catálogo foi
alterado. **Nenhuma escrita do agente no GitHub**, nenhum deploy.

## Observação de rastreabilidade

Entre `179269fe…` (AP) e `1b7724a1…` (estado avaliado) só entraram arquivos
de documentação/ferramentas de console das frentes AQ e AT; **nenhum arquivo
de `dev/` foi modificado**. O comportamento visual avaliado é, portanto, o da
AP.

## Próximos passos (todos ainda dependentes do usuário)

| Passo | Estado |
|---|---|
| 2 — CI remoto | ✅ 7/7 success |
| 2 — Health do Admin real | ⏳ colar `tools/health-admin-ap.js` no console |
| 3 — Decisões D01–D10 | ⏳ pacote **AR** pronto, sem conflito; decisões são do responsável |
| 4 — Verificação F918 / runtime | ⏳ pacote **AS** pronto, sem conflito; exige staging autorizado |
| 5 — Aproximação extra do KAZ / catálogo | vetado até decisão explícita de escopo |
