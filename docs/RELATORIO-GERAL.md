# Relatório Geral — ReamurCMS / DavidCMS

**Data:** 2026-11-01 (America/Sao_Paulo) · **Abrangência:** frentes iniciais → **BO-r4**
**Natureza:** consolidação documental e auditoria. **Não é aprovação, não é release, não é deploy.**
**Autoria das aplicações:** todas as aplicações no repositório foram feitas por você. O agente nunca escreveu no GitHub.

---

## 1. Resumo executivo

A jornada cobriu três grandes blocos:

1. **Engenharia e governança do repositório** (frentes iniciais → AX): análise do DavidCMS, CI/dependências/F11, contratos congelados, ferramentas internas `BDREditor-Content` e `KAZEditor-Theme`, e uma **limpeza segura do GitHub** já aplicada em 2026-11-05.
2. **Temas reais e editores** (AY → BM): CRUD e importação de temas no Admin, temas Twig completos, correções de vazamento de stage, e os temas ThreeJS. **AP, BI e BM têm aceite visual.** AO e BK foram reprovadas.
3. **Tema oficial 3D e integração no CMS** (BN → BO-r4): o BN foi contestado por entregar só a parte declarativa; o BO autorizou o Recorte B (runtime self-hosted allowlistado) e as revisões r2/r3/r4 corrigiriam SVG, provaram a integração com as classes PHP reais e removeram a dependência de Python no Windows.

**Estado final:** 90 pacotes ZIP + 90 `.sha256`, **90/90 com `sha256sum -c` OK**. Entrega corrente = `ReamurCMS-Frente-BO-r4-Windows.zip`, verificador **142 OK / 0 FALHAS**. O 3D **ainda não tem aceite visual no seu servidor** — a integração foi provada localmente, não em produção.

**O que NÃO aconteceu em nenhum momento:** escrita do agente no GitHub, migration, deploy, CI remoto autenticado, runtime autenticado, sessão Admin real, decisão das D01–D10, declaração de release. `release_approved` permanece `false`.

---

## 2. Linha do tempo

| Bloco | O que foi feito | Resultado / veredito |
|---|---|---|
| **Inicial → AM** | Análise do DavidCMS (`analise-davidcms-2026-11-01.md`), CI e dependências, F11, `KAZTheme-Editor` (AJ/AL/AM), protocolos de aceitação visual | CI 7/7; documentação e PROBEs acumulados → motivaram a limpeza de 2026-11-05 |
| **AN / AO / AP** | AO editou além do BDREditor (navbar lateral) → **reprovada**; AP corrigiu o escopo | **AP APROVADA visualmente** (não release). `pagina_e_estados`/`sempre_escuro` revogados; `ao_tema.css` vetado |
| **AQ → AX** | 3 pacotes locais, contratos e hashes; renomeação para **`BDREditor-Content`** e **`KAZEditor-Theme`**; versão `1.0.0`, `updated_at 2026-11-06`, `notes:[]`, Admin-only P2 | `ask_user` AW → `nome_kaz=theme`, `alcance_rename=r2`, `origem_versao=v2`, `onde_exibir=p2`. R2 autorizou contratos/hashes **só AW/AX**; V1/V3/P3 recusados |
| **AY / BB** | `ask_user` AY → `skipped:true`; ambos em `1.0.0`. BB → foco `health` | JSON do health real **não foi colado**; não afirmar B1/B2/B3 nem aceite visual AY |
| **BC → BH** | CRUD essencial + "Importar tema"; correção do botão sem evento; **Recorte A** (`seguranca=manter`): import por arquivo seguro, Twig/ThreeJS como **dados validados, sem código executável no tema** | `ask_user` BF → `escopo_pacotes=dados`, sem `.twig`/`.js` executáveis. Sem aceite visual |
| **BI** | "Não tem nada parecido com um site ou tema profissional" → tema Twig real, sem esqueleto/`|default('')` | **ACEITE VISUAL BI** — "Agora sim ficou perfeito e como esperado" |
| **BJ** | Tema completo baseado em davidalmeida.xyz | Sem aceite visual |
| **BK** | Investigação do vazamento do stage `rms-scene` | **REPROVADA** — "Ainda está ocorrendo vazamento indesejado". Não declarar corrigido |
| **BL** | Vazamento no Catalog + editor não carregar dados reais; diagnóstico: `save()`/`import()` só `draft_config`, `activate()` publica, `edit()` completa `template_sources` | Entregue, **sem prova no servidor**: `ad7643de…` tem `dev/` 0. Não afirmar que o vazamento foi corrigido lá |
| **BM** | "Tema completo utilizando ThreeJS seguindo os critérios aplicados no Astra" → `ask_user` `referencia=atlas`, `threejs=completo` | **ACEITE VISUAL BM** — "ficou muito bom" |
| **BN** | Tema oficial de `davidalmeida.xyz/threejs/?lang=pt` (fases 00–08, 9 níveis, HUD, depoimentos) | **CONTESTADO**: "No tema não veio a nave e as interações do threejs, veio só a parte visual básica". Entrega foi apenas fallback declarativo — erro de escopo do agente, registrado |
| **BO** | `ask_user` → **`caminho=b_catalogo`**: Recorte B autorizado. `david-universe` registrado no runtime self-hosted; `g3` portado sem React nem bundle de 892 KB; `asset_manifest.php`, allowlists e presets; tema usa `data-scene="david-universe"` | Sem migration, sem `.js` solto no tema. **Sem aceite visual** |
| **BO-r2** | 2 erros `<path> attribute d` no console (`#eb767e40z`, `#a7b3b540z`). Causa: `.replace('H',hue)` corrompia `H40` → `__SEAL_HUE__` + saneamento | 30 `<path d>` / 0 inválidos; **128/0**; `DIAGNOSTICO-3D.md`. Os erros eram **SVG, não WebGL** |
| **BO-r3** | "Não está aparecendo a parte do 3D" → auditoria da própria integração: `validacao/bo_integracao.php` com as classes PHP reais (**25/0**), `instalar-bo.py`, `verificar-bo.html` | Causa isolada no código: arquivo **novo** ausente → 404 no `import` → aborta o módulo ES inteiro. **136/0** |
| **BO-r4** | `Python was not found … Microsoft Store` no Windows → `INSTALAR.bat`, `instalar-bo.ps1`, `COPIAR-MANUALMENTE.md` | **142/0**; instalação sem Python, sem Git, sem terminal. Execução real do PowerShell é na sua máquina |

---

## 3. Decisões registradas (`ask_user`)

| Pergunta | Resposta | Efeito |
|---|---|---|
| AW (rename/versão) | `nome_kaz=theme`, `alcance_rename=r2`, `origem_versao=v2`, `onde_exibir=p2` | Contratos/hashes só AW/AX |
| AY | `skipped:true` | Ambos `version:"1.0.0"`; "as versões vão contar a partir de agora" |
| BB | `health` | Foco em health; JSON não colado |
| BF | `escopo_pacotes=dados` | Sem `.twig`/`.js` executáveis no pacote |
| KAZEditor-Theme | `recorte=a`, `seguranca=manter` | Recortes B/C/D e ZIP WordPress-style vetados **naquele momento** |
| BM | `referencia=atlas`, `threejs=completo` | "Astra" = Atlas Studio (BI aprovada) |
| BN → BO | **`caminho=b_catalogo`**, `skipped:false`, `customResponse:null` | **Autoriza** código executável **no runtime self-hosted allowlistado**; você aplica; **sem migration** |

A decisão BO **não** autoriza: `.js` solto no tema, `<script>`/JS inline/`|raw` no `.reamurtheme.json`, CDN/Google Fonts/WebP remotos obrigatórios, upload, ACL nova, escrita no GitHub, release, deploy, runtime autenticado, decisão D01–D10, nem aceite visual.

---

## 4. Entrega corrente (BO-r4)

| Campo | Valor |
|---|---|
| ZIP | `entregas/ReamurCMS-Frente-BO-r4-Windows.zip` |
| Bytes / entradas | **3.203.580 / 80** |
| SHA-256 | `e69ed9f306b5f2c6c91533d79c03f4eb0bd2332af42d93d27d5f69287f01f76c` |
| `.sha256` | OK · **90/90 pacotes** válidos no diretório |
| Verificador | **142 OK / 0 FALHAS** no host **e dentro do ZIP extraído** |
| Integração CMS | `validacao/bo_integracao.php` → **25 OK / 0 FALHAS** |
| Export do tema | **26/0**, `validateConfig=[]`, `decode ok=true` |
| Checksum canônico do tema | `e2b935d8fd4bfc336c4f14cd6ba3f45bcfb397466438966b9999d8c5b5a146c0` |
| Runtime Chromium | **49/0** — WebGL 2.0, Three r186, 231 objetos, 520 estrelas, 0 pageerrors |
| SVG | 30 `<path d>` / **0 inválidos** |
| Patch | `PATCH-BO.diff` 46.406 bytes; `git apply --check` e `git apply` OK; 10 arquivos (9 + 1 novo de 33.395 bytes) |

**Cadeia de boot confirmada:** `theme-scene.twig` → `ThemeSnapshot` → `startup/theme.php` (`catalog_theme_three`) → `common/header.php` (`has_3d` + `three_runtime_base`) → `common/header.twig` (importmap `three`/`three/addons/` + `<script type="module">`) → `scene-runner.boot()` consulta `[data-scene]` → `AssetManifest` publica 6 URLs → `common/three.php` serve o allowlistado (404 se `!is_file`).

**Causa mais provável do 3D ausente (código, não confirmada no seu servidor):** `dev/system/library/three/runtime/three/scenes/david-universe.js` é `new file mode`. Aplicar só os 9 modificados → 404 no `import './scenes/david-universe.js'` → **o módulo ES inteiro aborta**, inclusive as 4 cenas antigas.

---

## 5. Aceites, reprovações e pendências

| Status | Frentes |
|---|---|
| **Aceite visual** | **AP, BI, BM** |
| **Reprovadas** | **AO** (excesso de escopo), **BK** (vazamento persistente) |
| **Contestada** | **BN** (faltou nave/interações; entregue só fallback declarativo) |
| **Sem aceite visual** | AY, BB, BC, BE, BF, BG, BH, BJ, BL, BO, BO-r2, BO-r3, BO-r4 |

**Aplicações confirmadas por você:** AQ, AT, AU, AV, AX, AY, BA, BB, BC, `Health-Admin-Real-Instrucoes`, BE, BF, BG, BH, BI, BJ (`ab8b765…`), BK (`3219ccae…`), BL (`ad7643de6ca2`). Cada "aplicado com sucesso, github atualizado. Podemos prosseguir…" autoriza **prosseguir**, não é veredito visual nem release.

**Não comprovado no servidor:** health real autenticado (JSON nunca colado), CI remoto (403 `Must have admin rights`), MySQL, sessão Admin, capturas desktop/390 px do Admin real, deploy, e que os patches BL/BO estejam vivos no runtime (`ad7643de…` tem `dev/` 0).

---

## 6. Governança e integridade

| Item | Estado |
|---|---|
| HEADs auditados | `ab8b765…` → `3219ccae…` → **`ad7643de6ca2`** (16 arquivos, +925/−22, `dev/` 0) |
| Limpeza GitHub 2026-11-05 | `5593de…` → **`892fbd…`** — **−96 arquivos / −3.848.525 bytes (−8,7%)**, Nível 1 apenas |
| `release_approved` | **`false`** |
| F9-27 | **`BLOCKED_NOT_RUN`** |
| F9-18 / F9-25 / F9-26 | `release_ready:false` / `PROPOSAL_PENDING` / `wcag_conformance_claimed:false` |
| Decisões | **10 `PENDING_DECISION` / 0 `DECIDED`** — D01–D10 intactas |
| Contratos | 14 `CHECKSUMS*.txt` → 90 paths; 11 `frozen_artifacts_sha256`; `schema.sql` e F9/F11 intocados |
| CI | 7/7 local; `repo-health`, PHP WASM 8.4.1, jsdom, Chromium headless e guardas **não substituem veredito nem release** |

**Regras que seguem valendo:** não converter `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP`, `BLOCKED_NOT_RUN` em `PASS`; não alterar `schema.sql`/`schema-complete.sql`/SQL de upgrade, contratos congelados, manifestos, hashes, DOM, renderer, catálogo público, F9/F11, traduções como identificadores; não reemitir manifestos para "limpar" os 15 `F11-XX-INDICE.md`.

---

## 7. Lições e correções que ficaram registradas

- **BN:** tratar fallback declarativo como entrega completa foi erro de escopo. O HTML Vite/React 19.2.6/Three r186/`g3` é a fonte funcional; `bundle.js` 892.049 B excede `MAX_BYTES=524288`, o que inviabiliza embutir o bundle no tema.
- **SVG (r2):** `.replace('H', hue)` corrompia `H40` em `M40 24h80v84H40z`. Um scan ingênuo de "39 paths inválidos" casava o `d` final de `id="…"`; a regex correta é `<path\b[^>]*\sd="([^"]*)"`.
- **Prévia isolada (r3):** 49/0 no HTML de prévia **não prova** integração CMS. Faltava exercitar as classes PHP; `bo_export.php` checava só `$snapshot['scene']`.
- **WASM:** espelha `.php/.json/.txt/.twig`, **não `.js`** — `is_file()` de módulo JS no PHP WASM dá falso negativo.
- **Limpeza 2026-11-05:** `tests/` foi **mantido** porque `php.yml:198` roda `find tools tests …` com `set -euo pipefail`. O `limpar-github.sh` original tinha falso positivo em `grep -F -f` (`INDICE.md` casava `F11-04-INDICE.md`), corrigido por regex ancorada.
- **Windows (r4):** `python3` sem interpretador aciona o alias da Microsoft Store. PowerShell 5.1+ é nativo; o `.ps1` foi escrito em ASCII puro para não depender de codepage.

---

## 8. Próximos passos (sugestão, não decisão)

1. Aplicar o **BO-r4** com `INSTALAR.bat` e abrir `verificar-bo.html` na raiz do site — é o único jeito de transformar a causa hipotética em diagnóstico real do seu servidor.
2. Se o `verificar-bo.html` ficar verde e o 3D aparecer, aí sim colher o **aceite visual** (ainda inexistente).
3. Opcional: executar o **Nível 2** da limpeza do GitHub (`bash limpar-github.sh --aplicar --nivel2`) e corrigir o relatório desatualizado dentro do repo — ver `PLANO-LIMPEZA.md`.
4. Opcional: rodar `python3 docs/limpar-workspace.py` (dry-run) e, se concordar, `--apply` para quarentena local.
5. Pendências de governança continuam abertas: health real, B2/B3, AR/AS, **D01–D10**, veredito visual das frentes sem aceite, e a decisão de release — que **não** foi tomada.

---

## 9. Limites deste relatório

É uma **consolidação de arquivos e registros locais**. Não é auditoria fresca do GitHub (a última consulta remota registrada é de 2026-11-05), não é health autenticado, não é CI remoto, não é runtime, não é migration, não é deploy, não é aceite visual e **não é release**. Nenhuma escrita do agente foi feita no GitHub. Toda aplicação em repositório é sua.

**Documentos relacionados:** `INDICE-DOCUMENTACAO.md`, `PLANO-LIMPEZA.md`, `ESTADO-GOVERNANCA.md`, `inventario-limpeza.csv`, `limpar-workspace.py`.
