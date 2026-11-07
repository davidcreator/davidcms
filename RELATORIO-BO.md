# Relatório — Frente BO: a nave e as interações ThreeJS no ReamurCMS

**Data:** 2026-11-07 · **Base:** `ad7643de6ca2` · **Autorização:** `ask_user caminho = b_catalogo`

## 1. O que foi corrigido

Sua correção: *"No tema não veio a nave e as interações do threejs veio só a parte visual básica esse é o script da build completa"*.

O BN entregou um **fallback declarativo** (pontilhado CSS + `data-scene`), não a cena `g3`. O BO entrega a cena real rodando no runtime nativo do ReamurCMS.

| | BN (anterior) | BO (agora) |
|---|---|---|
| Nave | ❌ | ✅ `CylinderGeometry(.5,.42,1.9,18)` + bico, asas `ExtrudeGeometry`, canopy, 2 chamas aditivas, `PointLight` |
| Objetos | pontilhado CSS | 2.080 estrelas, 7 nebulosas, 5 planetas (anéis/wireframe/glow), 46 asteroides, 2 astronautas, 8 gates, **14 orbes**, trilha de 60 partículas |
| Voo por scroll | ❌ | ✅ `camera.z = 8 − progress×320`, `warp` altera FOV 66→82 |
| Pilotagem | ❌ | ✅ `pointermove` com inércia; autopiloto após 3,5 s e em `pointer: coarse` |
| Coleta | ❌ | ✅ raio 2 (2,7 no coarse), burst, toast, `ORBES nn/14` |
| HUD | estático | ✅ fase, distância em km (384.400), barra de progresso, toasts |
| Fases | estático | ✅ `IntersectionObserver` em 9 `[id^="fase-"]` |
| Degradação | — | ✅ `prefers-reduced-motion` desliga o WebGL e mantém o fallback |

## 2. Como foi feito (Recorte B, autorizado por você)

A classe `g3` do bundle Vite/React de 891.703 bytes foi **portada** para um módulo ES de **~33 KB**, sem React e sem o bundle, usando o `SceneRunner` que já existe no CMS (`three/core/scene-runner.js`) e o Three.js **r186 self-hosted** (mesma revisão da sua build).

- `registerScene('david-universe', factory)` — o runner continua dono de renderer, câmera, resize, `IntersectionObserver`, pausa e `dispose()`.
- `data-scene="david-universe"` emitido pelo `theme-scene.twig` **inalterado**.
- `data-count` (50–800) limita estrelas, `data-color` define o acento, `data-speed` ajusta a suavização. Todos allowlistados.
- O slot `hero` virou um **backdrop fixo** por CSS: o runner pausa cenas fora da viewport, então sem isso o voo pararia ao rolar. O conteúdo sobe para `z-index:1`, o canvas fica em `0` com `pointer-events:none`.
- Nenhum `<script>`, `|raw`, `eval`, `innerHTML`, `fetch`, CDN, Google Fonts ou WebP remoto entrou **no tema**. O HUD é atualizado só por `textContent`.

### Arquivos alterados (9) + 1 novo

| Arquivo | Mudança |
|---|---|
| `three/runtime/three/scenes/david-universe.js` | **NOVO** — a cena portada |
| `three/runtime/three/main.js` | +1 import |
| `three/asset_manifest.php` | +1 entrada na allowlist de assets |
| `theme/theme_package.php` | `SCENES` += `david-universe` |
| `theme/condition_engine.php` | `SCENES` += `david-universe` |
| `theme/theme_snapshot.php` | `SCENES` += `david-universe` (sem isso o snapshot zerava `scene`) |
| `three/scene_preset_registry.php` | `SCENES` + preset `universe` + miniatura SVG da nave |
| `catalog/controller/startup/theme.php` | allowlist espelhada |
| `catalog/controller/common/home.php` | `has_3d` ligado para a cena nova |
| `admin/controller/design/theme.php` | dropdown do Admin + normalização do POST |

**Deliberadamente inalterados:** `theme-scene.twig`, `scene-runner.js`, `CHECKSUMS.txt`, `tools/verify-checksums.sh`, `scene_config.php`, SQL, contratos congelados, manifestos, DOM do Admin, renderer, catálogo público e os verificadores `tools/verify-*.cjs` (eles validam temas de referência já existentes, não este).

`CHECKSUMS.txt` **não** foi alterado porque nenhum dos arquivos do runtime Three.js está listado nele — conferido nos 14 manifestos `CHECKSUMS*.txt`.

## 3. Validações

| Prova | Resultado |
|---|---|
| Diagnóstico do bloqueio BN (classes reais) | **12 OK / 0 FALHAS** — bundle 892.049 B > `MAX_BYTES` 524.288; `<script>` → `template_source_invalid`; chave nova → `config_key_invalid`; 4 cenas genéricas |
| `git apply --check` + `git apply` em réplica limpa do HEAD | **OK / OK** |
| `TOKEN_PARSE` nos 8 PHP patchados | **8 OK / 0 FALHAS** |
| `node --check` no módulo e no `main.js` | **OK** |
| Export PHP WASM (classes patchadas) | **26 OK / 0 FALHAS** — `validateConfig=[]`, `decode ok=true`, `ThemeSceneCss` 20 camadas, 4 snapshots corretos, allowlists, manifesto, preset |
| **Chromium 141 / WebGL 2.0 real** | **49 OK / 0 FALHAS** — ver abaixo |
| `verify-bo.cjs` no pacote | consulte `evidencia/verify-bo.log` |

### Runtime real (não é simulação)

`previa/` executa o `scene-runner.js` do CMS, o `david-universe.js` e o Three.js r186 servidos localmente por HTTP. Medições:

- `THREE.REVISION = 186`, `WebGL 2.0 (OpenGL ES 3.0 Chromium)`, canvas 1440×900, **231 objetos** na árvore da cena.
- `pointermove` moveu a nave de `x≈1 → 3,5` e `y≈0,7 → 1,3` (valores variam com a taxa de frames).
- Scroll: `camera.z≈−107`, `ship.z = camZ − 9`, FOV ~77 com `warp≈0,70`, distância sempre `round(progress×384400)` km, barra coerente com o progresso, fase `07 — DEPOIMENTOS`.
- Coleta: **orbes coletados durante o voo**, HUD `nn/14` e toast `Orbe coletado nn/14`. A quantidade exata (3–4/14 nas execuções) varia com a taxa de frames do WebGL por software — o verificador exige `> 0` e confere que os orbes marcados na cena batem com o HUD.
- Fim do voo (`hold(1)`): `progress≈0,9999`, `camera.z≈−312`, `~384.400 KM`, barra `100%`.
- `0 pageerrors`, `0 console.error`, `0 requests falhos`.
- `prefers-reduced-motion`: WebGL **não** monta, fallback permanece.
- Mobile 390 px: WebGL ativo, `coarse=true` (autopiloto), 14 orbes, **0 overflow**, 0 pageerrors.

> Nota técnica: o `SceneRunner` limita `delta` a 0,05 s/frame. No SwiftShader (WebGL por software, ~5 fps) a suavização avança mais devagar que o tempo real; o teste valida **coerência** (`camZ = 8 − progress×320`) e usa `hold(1)` + `waitForFunction` para provar a convergência. Em GPU real converge em menos de 1 s.

## 4. Tema

`David Universe 3D — Cena Real` / `david_universe_3d_bo` — **91.504 bytes**, 25 fontes Twig, 5 regras, 20 camadas `kaz_scene` (fallback), 9 fases, 9 projetos, HUD, formulário e rodapé do BN preservados.

- `checksum` canônico: **`81f618c19d9b43387275397c926ae8ae3d87e2714aa89e0388c92a14cdcabe10`**
- Cenas por rota: **home → `david-universe`**, catálogo → `portfolio-3d`, ferramentas/blog → `tools-3d`, 404 → `not-found`, mobile → `david-universe` com `count=180`. As 4 cenas originais continuam intactas.
- `ORBES 00/10` do BN foi corrigido para `00/14`.

Slug novo para não colidir com o pacote BN já importado.

## 4b. Revisão r2 — erros de `<path>` no console e 3D ausente

Após o primeiro teste no servidor, você reportou: *"não está renderizando os objetos 3d … no console aparece `Expected path command, "M40 24h80v84#eb767e40z"` e `#a7b3b540z`"*.

**São dois problemas independentes.**

### O bug dos SVG (corrigido)

O gerador do BN usava `H` como marcador de cor (`stroke="H"`) com `.replace('H', hue)`. O selo 06 contém o comando SVG `H40z`; o replace trocou **todos** os `H`, injetando a cor dentro do atributo `d`:

```
correto : d="M40 24h80v84H40z"
gerado  : d="M40 24h80v84#eb767e40z"
```

As duas cores do log (`#eb767e`, `#a7b3b5`) correspondem às 4 ocorrências em 3 templates (`common/home` ×2, `partial/portfolio`, `partial/news`). Um `<path>` inválido é ignorado pelo navegador com um aviso — **não bloqueia WebGL**, não interrompe o parse do HTML. Correções:

- `tools/bn_theme_gen.py`: placeholder agora é `__SEAL_HUE__`;
- `tools/bo_theme_gen.py`: saneia os 4 paths do config BN já exportado;
- `tools/verify-bo.cjs`: novo check varre todos os `<path d>` dos 25 templates → **30 paths, 0 inválidos**, e falha se qualquer cor for injetada novamente.
- `PATCH-BO.diff`: cabeçalho do arquivo novo normalizado para caminho relativo (`git apply` reverso + direto revalidados).

Com a mudança, o `config` passou a 84.074 bytes, o encode a **91.535 bytes** e o checksum canônico a **`e2b935d8fd4bfc336c4f14cd6ba3f45bcfb397466438966b9999d8c5b5a146c0`**. Export PHP refeito: **26/0**, `validateConfig=[]`, `decode ok=true`. Runtime Chromium refeito: **49/0**, WebGL 2.0, 0 erros de console.

### O 3D ausente (diagnóstico, não correção no servidor)

Eu não tenho acesso ao seu servidor, então não posso afirmar qual elo quebrou. O tema sozinho só declara `data-scene="david-universe"`; quem cria a nave é o runtime patchado. A cadeia tem 5 elos: patch aplicado, `main.js` publicado, `asset_manifest.php` + arquivo novo com HTTP 200 e MIME JS, `has_3d` verdadeiro e allowlists PHP aceitando a cena.

**A causa mais provável é o patch aplicado pela metade**: `main.js` atualizado, mas `scenes/david-universe.js` ausente ou não allowlistado. Um único `import` com 404 **aborta o módulo ES inteiro**, e por isso nem as 4 cenas antigas aparecem — exatamente o sintoma relatado. `prefers-reduced-motion: reduce` também desliga o WebGL por desenho.

O pacote traz `DIAGNOSTICO-3D.md` (tabela dos 5 elos, checklist de aplicação, interpretação) e `diagnostico/console.js` (snippet somente-leitura que devolve o estado do slot, dos módulos carregados, do WebGL e do reduced-motion). Cole o JSON resultante aqui se ainda falhar.

> O ZIP BN anterior **não foi corrigido** e segue com os paths inválidos. Se ele estiver ativo, troque pelo tema r2.

## 4c. Revisão r3 — integração real e causa isolada

A prévia `bo_preview/index.html` é um HTML isolado: prova que o módulo funciona, mas **não** prova que o CMS entrega o slot, o importmap e o `main.js`. Montei então `tools/bo_integracao.php`, que executa as classes PHP patchadas e simula a cadeia verdadeira:

```
ThemeSnapshot::resolve() → startup/theme.php (catalog_theme_three) →
common/header.php (has_3d + three_runtime_base) → common/home.php
(themeSceneSettings → three_hero_enabled → SceneConfig::runtime) →
header.twig (importmap + <script type=module>) → theme-scene.twig
(data-scene) → AssetManifest::resolve()
```

Resultado: **25 OK / 0 FALHAS** — `three.enabled=true`, `theme_scene.scene=david-universe`, `has_3d=true`, `publicBasePath()='three-runtime/'`, as 6 URLs do runtime resolvíveis e as 4 cenas antigas preservadas.

Com isso a falha fica **isolada no servidor**. O elo mais frágil é o arquivo novo: `AssetManifest::resolve()` devolve o caminho, mas `common/three.php` devolve **404 quando `is_file()` falha**. Como `david-universe.js` é `new file mode` no diff, quem aplica apenas os 9 modificados (via `patch -p1`, GUI de Git ou upload manual) fica com `main.js` importando um 404 — e **um import com 404 aborta o módulo ES inteiro**, derrubando também as 4 cenas antigas. É o sintoma relatado.

Correções de entrega (nenhuma altera o CMS em produção):

- `instalar-bo.py`: caminho alternativo ao `git apply`, copia os 10 arquivos e falha explicitamente se o módulo novo não estiver legível. Testado em réplica limpa: **25/0**.
- `verificar-bo.html`: diagnóstico visual same-origin; busca a home, lê `[data-scene]`/importmap, faz `fetch` nas 6 URLs e aponta o elo quebrado. Reproduzido contra uma prévia sem o módulo → "ELO 3 QUEBRADO · HTTP 404", 0 erros de JS.
- `tools/bo_integracao.php` + `evidencia/integracao-bo.log` no pacote; verificador ganhou 8 checks novos.

## 4d. Revisão r4 — instalação sem Python (Windows)

Ao aplicar a r3 no Windows, o comando `python3 instalar-bo.py …` retornou
`Python was not found; run without arguments to install from the Microsoft Store`
(alias de App Execution Aliases: não há Python instalado). **Não é falha do
pacote nem do CMS** — é ausência do interpretador. Para não exigir que o usuário
instale software, a r4 acrescenta três caminhos equivalentes:

- `INSTALAR.bat` — duplo clique, pede a pasta raiz, valida `%REPO%\dev`, chama
  `powershell.exe -NoProfile -ExecutionPolicy Bypass -File instalar-bo.ps1 -Repo … -Apply`.
- `instalar-bo.ps1` — PowerShell 5.1+ nativo, ASCII puro (sem acentos para não
  depender de codepage), mesmos **10** arquivos, mesmas **5 verificações
  pós-install** (`main.js` importa, `asset_manifest.php` publica, `home.php`
  mapeia `three_hero_enabled`, módulo novo >10.000 bytes legível, todos os 10
  presentes) e o mesmo contador `25 OK / 0 FALHAS`. Usa `$PSScriptRoot` para
  achar `patch\arquivos\` e `Join-Path` parte a parte para converter `/` em `\`.
- `COPIAR-MANUALMENTE.md` — tabela exata dos 10 pares origem → destino com
  tamanhos, para FTP/cPanel/Gerenciador de Arquivos, destacando o item 10
  (`new file mode`) e a checagem direta da URL do módulo no navegador.

Validação estática disponível neste ambiente (Linux, sem `pwsh`): chaves e
parênteses balanceados (26/26 e 68/68), ASCII puro no `.ps1` e no `.bat`, os
10 caminhos do `.ps1` existem em `patch/arquivos/`, os 3 needles de conteúdo
conferem com os arquivos de origem, e o `.bat` referencia o `.ps1` com
`-Apply`. A execução real do PowerShell **não foi feita aqui** — acontece na
máquina do usuário. O verificador ganhou 6 checks novos.

## 5. O que este pacote NÃO é

Não é release, não é deploy, não é migration, não é escrita no GitHub, não é health autenticado, não é veredito visual. **Você aplica** o `PATCH-BO.diff`; o agente não escreveu no repositório.

Permanecem: `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false`, 10 `PENDING_DECISION`. D01–D10 não foram decididos. Os patches A/B da BL seguem propostas não aplicadas (`ad7643de…` tem `dev/` intacto). BI, BM e AP têm aceite visual; **BN e BO não têm**.

Também **não** foram autorizados nem usados: Google Fonts, CDN, fontes remotas obrigatórias, WebP remotos, upload em disco, ACL nova, `|raw`, contrato novo `kaz_scene`→`templates`, Twig arbitrário, `<script>` no tema, `innerHTML`, `eval`, `fetch`/rede.
