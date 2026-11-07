# BO-r4 — Nave e interações ThreeJS no ReamurCMS

Pacote de correção da BN. **Recorte B autorizado** (`ask_user caminho = b_catalogo`).
**r2:** corrigiu `<path> attribute d`. **r3:** integrou com as classes PHP reais (25/0) e isolou a causa. **r4:** instalação **sem Python** (`INSTALAR.bat` / `instalar-bo.ps1` / `COPIAR-MANUALMENTE.md`) para Windows. Os ZIPs BO, r2 e r3 continuam inalterados.

| O que | Onde |
|---|---|
| **3D não aparece? Comece aqui** | `INSTALAR.bat` (duplo clique, sem Python) ou `instalar-bo.ps1 -Apply` — e `verificar-bo.html` na raiz do site (diagnóstico visual, sem console) |
| **Sem terminal / FTP** | `COPIAR-MANUALMENTE.md` — os 10 pares origem → destino, com o item novo destacado |
| **Erro `Python was not found ... Microsoft Store`** | É só o Windows sem Python. Não instale nada: use o `.bat` ou a cópia manual. |
| Entender a causa | `DIAGNOSTICO-3D.md` — 5 elos, causa confirmada, snippet de console, tabela de interpretação |
| **Comece aqui** | `GUIA-BO.md` — aplicação, importação, teste, rollback |
| Relatório completo | `RELATORIO-BO.md` — diagnóstico, arquitetura, 26/0 + 49/0 + r2 |
| Diagnóstico do erro BN | `docs/DIAGNOSTICO-BO.md` — por que o tema não comportava a cena |
| Especificação da `g3` | `docs/ESPECIFICACAO-CENA.md` + `especificacao.json` |
| **Tema** | `tema/david_universe_3d_bo.reamurtheme.json` (91.535 bytes, r2 — paths SVG corrigidos) |
| Config de origem | `tema/bo_config.json` |
| **Patch** | `patch/PATCH-BO.diff` (+ `patch/arquivos/` com os 10 arquivos) |
| **Prévia funcional** | `previa/index.html` — SceneRunner real + Three r186 self-hosted |
| Prancha visual | `evidencia/BO-Nave-e-Interacoes.png` |
| Capturas | `evidencia/shot-bo-{hero,voo,coleta,fim,mobile}.png` |
| Provas | `evidencia/export-bo.txt` (26/0), `runtime-bo.log` (49/0), `runtime-bo.json`, `token-parse.log` (8/0), `prova-bloqueio.log` (12/0), `git-apply.log`, `verify-bo.log` |
| Ferramentas | `tools/` — geradores, export PHP, runtime Chromium, verificador |

## Números que importam

- Bundle original: **891.703 bytes** de JS, Three r186 + React 19.2.6.
- Módulo portado: **~33 KB**, sem React, sem bundle, sem CDN.
- `checksum` do tema r2: `e2b935d8fd4bfc336c4f14cd6ba3f45bcfb397466438966b9999d8c5b5a146c0` (o `81f618c1…` era do BO antes da correção dos SVG).
- 25 fontes Twig · 5 regras · 20 camadas `kaz_scene` · **30 `<path d>` válidos / 0 inválidos** · 14 orbes · 8 gates · 5 planetas · 46 asteroides · 2 astronautas · trilha 60.
- **26 OK / 0 FALHAS** (export PHP) · **25 OK / 0 FALHAS** (integração CMS real) · **49 OK / 0 FALHAS** (Chromium WebGL 2.0) · **25 OK / 0** (instalador em réplica) · **8 OK / 0** (TOKEN_PARSE) · **12 OK / 0** (diagnóstico) · verificador do pacote com checks de SVG e de integração.

## Estado

Sem release, sem deploy, sem migration, sem escrita do agente no GitHub. Você aplica o patch. Aceite visual do BO pendente.
