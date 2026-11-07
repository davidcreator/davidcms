# BO — Nave e interações ThreeJS no ReamurCMS

Pacote de correção da BN. **Recorte B autorizado** (`ask_user caminho = b_catalogo`).

| O que | Onde |
|---|---|
| **Comece aqui** | `GUIA-BO.md` — aplicação, importação, teste, rollback |
| Relatório completo | `RELATORIO-BO.md` — diagnóstico, arquitetura, 26/0 + 49/0 |
| Diagnóstico do erro BN | `docs/DIAGNOSTICO-BO.md` — por que o tema não comportava a cena |
| Especificação da `g3` | `docs/ESPECIFICACAO-CENA.md` + `especificacao.json` |
| **Tema** | `tema/david_universe_3d_bo.reamurtheme.json` (91.504 bytes) |
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
- `checksum` do tema: `81f618c19d9b43387275397c926ae8ae3d87e2714aa89e0388c92a14cdcabe10`.
- 25 fontes Twig · 5 regras · 20 camadas `kaz_scene` · 14 orbes · 8 gates · 5 planetas · 46 asteroides · 2 astronautas · trilha 60.
- **26 OK / 0 FALHAS** (PHP WASM) · **49 OK / 0 FALHAS** (Chromium WebGL 2.0) · **8 OK / 0** (TOKEN_PARSE) · **12 OK / 0** (diagnóstico).

## Estado

Sem release, sem deploy, sem migration, sem escrita do agente no GitHub. Você aplica o patch. Aceite visual do BO pendente.
