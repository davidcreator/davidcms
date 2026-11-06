# Checklist do veredito visual — Frente AP

**HEAD atual:** `1b7724a10314cf5b5fee479a328651b21843d06a` (AT aplicado,
7/7 arquivos idênticos, CI **7/7 success**).  
**Você escolheu este passo em 2026-11-06.** É o **único bloqueio real**:
`image-9.png` segue como reprovação vigente até você decidir.

> **A forma mais rápida:** basta me responder **`APROVADO`** ou
> **`REPROVADO`**. Se puder, anexe uma captura do Admin real. O resto é
> opcional.

---

## Via A — resposta direta (30 segundos)

Abra **Pages › Edit** e **Blog › Edit post** no Admin autenticado e confira:

| # | Critério | ✅ / ❌ |
|---|---|---|
| 1 | Item ativo da sidebar está **azul** (`#0d6efd`), **sem** borda/linha verde | ☐ |
| 2 | Topbar, breadcrumb, título, cards, campos, abas de idioma, botões, alertas e badges estão **como antes da AO** | ☐ |
| 3 | BDREditor continua **escuro**, com accent verde e **canvas branco**, próximo de `image-10.png` | ☐ |
| 4 | **Nada mais** mudou fora do BDREditor (overlays do próprio editor podem ser escuros) | ☐ |

**Veredito:** ☐ APROVADO  ☐ REPROVADO  
**O que sobrou (se reprovado):** ______________________

---

## Via B — evidência objetiva pelo console (opcional)

Cole `validacao/veredito_visual_ap.js` no DevTools do Admin real. Ele devolve
um JSON somente leitura com 6 checagens e as medidas:

| Medida | Esperado (A/B/C da AP) |
|---|---|
| `marcador_ap` | `true` · `marcador_ao` `false` |
| `bs_primary` / `rms_accent` | `#0d6efd` (nunca `#31efb8`) |
| `menu_active_bg` | `rgb(13, 110, 253)` |
| `host_bg` | `rgb(24, 24, 27)` |
| `canvas_bg` | `rgb(255, 255, 255)` |
| `editor_mounted` | `true` |

`diagnostico: TODOS_OS_PONTOS_OK` é **evidência técnica**, não veredito. Se o
Admin usar skin/modo diferente da prévia, valores divergentes são **achado a
investigar**, não reprovação automática — me envie o JSON e eu interpreto.

**Instrumento validado** no A/B/C com Chromium headless (não substitui o Admin
real): AP → `TODOS_OS_PONTOS_OK` **6/6**; AO reprovada → `REVISAR` **3/6**
(`acento_nao_verde` e `sidebar_azul` falham, `bs_primary: #31efb8`,
`menu: rgb(49,239,184)`); Admin nativo → 5/6 (falta só o marcador `-kaz-bdre-ap`,
como esperado). Ou seja, ele **detecta** a reprovação, não é falso positivo.

---

## O que eu faço assim que você responder

| Resposta | Ação minha |
|---|---|
| **APROVADO** | Registro o veredito, atualizo `Protocolo-Aceitacao-Visual-AP.md` e `Roteiro-Proximos-Passos-AP.md`, marco `image-9.png` como **superada**, consolido o estado e preparo o passo 3 (AR) para sua aplicação |
| **REPROVADO** | Abro uma frente de ajuste **pontual**, sem reabrir o escopo da página, usando sua descrição/captura como critério |
| **JSON da Via B** | Comparo com o A/B/C, aponto divergências e devolvo uma recomendação — sem decidir por você |

---

## O que NÃO muda com este passo

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
F9-18 `release_ready: false` · 10 `PENDING_DECISION` em aberto.

CI verde (7/7), health local, PROBE, PHP WASM e Chromium headless **não
significam release aprovada**. O veredito visual é seu; nenhum pacote meu o
substitui. Sem escrita do agente no GitHub.

**Pacotes prontos, sem conflito no HEAD atual:** `AR` (modelo D01–D10) e
`AS` (verificação F918-01/02) — 5 + 8 arquivos, todos novos, 0 substituições.
