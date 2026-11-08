# Frentes — Texto Integral (arquivo morto)

**HEAD:** `687fba34f90b0eefcef5e32907d74be8ce42432b` · **63 documentos concatenados, sem edição de conteúdo.**
**Uso:** preservação integral para quem quiser remover os 63 originais da raiz sem perder uma linha.

> Cada seção abaixo é o conteúdo **byte a byte** do arquivo indicado, na ordem alfabética.
> Divisórias `<!-- ... -->` foram adicionadas apenas para separar documentos.


<!-- INICIO: Analise-Viabilidade-Ferramentas-Internas.md (8041 bytes) -->

# Análise de viabilidade — Ferramentas internas na página Ferramentas

**Data:** 2026-11-06 · **HEAD:** `481b690209ec1a1caf4d9f334cef6012d4597653`
**CI:** ✅ **7/7 success** (após a Frente AV — `repo-guards` e `docs-links` voltaram ao verde)
**Tipo:** levantamento de código, **sem alteração aplicada**, sem escrita no GitHub.

> **Nota de escopo:** a restrição "editar somente o BDREditor" pertencia à
> correção visual AN/AP e foi **encerrada com o veredito APROVADO**. Esta é uma
> frente nova, explicitamente proposta pelo usuário, e portanto pode tocar a
> página Ferramentas. Ainda assim, nada será executado sem as decisões abaixo.

---

## 1. Como a página Ferramentas funciona hoje

| Camada | Arquivo | Papel |
|---|---|---|
| Admin (CRUD) | `dev/admin/controller/tool/tool.php`, `dev/admin/model/tool/tool.php` | cadastro, filtros, paginação, CSRF |
| Admin (view) | `dev/admin/view/template/tool/tool_list.twig`, `tool_form.twig` | tabela e formulário |
| Admin (JS/CSS) | `dev/admin/view/js/connected-tools.js`, `dev/admin/view/css/admin.css` | upload de ícone, filtros |
| Admin (idiomas) | `dev/admin/language/{en-us,pt-br,es-eu}/tool/tool.php` | rótulos |
| Catálogo (grade pública) | `dev/catalog/controller/tool/tool.php`, `dev/catalog/model/tool/tool.php`, `dev/catalog/view/template/tool/tool_list.twig` | páginas públicas `/fluxa`, `/quotia`… |
| Documentação | `docs/F8-09-Ferramentas-Conectadas.md`, ADR `0053` | contrato da tarefa F8-09 |

**Descoberta central:** `docs/F8-09-Ferramentas-Conectadas.md` diz literalmente
que a tarefa *"trata cartões/links de ferramentas conectadas existentes, **não
implementação de Fluxa, Quotia, Vertex, Solis, APIs, execução de ferramenta**"*.
Ou seja, **Fluxa, Vertex, Solis e Quotia são linhas no banco**, não módulos de
código. Isso torna o cadastro das internas algo potencialmente **sem código
nenhum**.

### Schema real (`dev/install/sql/schema.sql:225-243`, espelhado em `schema-complete.sql:304`)

```sql
CREATE TABLE `rms_tool` (
  tool_id, image, vendor, url, status, sort_order, date_added
);
CREATE TABLE `rms_tool_description` (
  tool_id, language_id, name, description
);
```

**Não existe `version`, `date_modified`, `changelog` nem tabela de updates.**
A lista do Admin exibe: imagem, nome, fornecedor, URL, status, ordem e
`date_added`. O catálogo público exibe nome, fornecedor, descrição e link.
`dev/admin/controller/common/dashboard.php:162` mostra `REAMUR_VERSION` —
versão **do CMS**, não das ferramentas.

➡️ **Não encontrei, no repositório, nenhuma tela que mostre versão ou
atualizações das ferramentas externas.** Se você está vendo isso em outra tela
(painel, documentação ou produto externo), preciso de uma captura para
reproduzir o mesmo padrão.

---

## 2. Dimensionamento do rename

| Termo | Arquivos que citam (amostra de texto do HEAD) |
|---|---|
| `BDREditor` | **147** |
| `KAZTheme-Editor` / `KAZTheme` | **30** |
| `BDREditor-Content` | 0 |
| `KAZEditor-Thene` | 0 |

Exemplos de pontos sensíveis:

- **Contratos congelados:** `docs/BDREditor-Table-Contract.json`,
  `docs/BDREditor-Table-Verification.json`, dezenas de `docs/BDREditor-*.md`.
- **Probes com asserções de string literal:** `tools/kaz-bdre-visual-probe.php`,
  `tools/kaz-bdre-total-probe.php`, `tools/f9-89-single-ribbon-brand-smoke.js`.
- **Identificadores de código:** `dev/admin/view/js/blog-block-editor-kaz.js`,
  `kaz-theme-editor.js`, `kaz-theme-editor-ergonomia.js`,
  `dev/admin/view/template/design/kaz_theme_editor.twig`,
  `dev/system/library/theme/theme_preview_state.php`.
- **Integridade:** 14 `CHECKSUMS*.txt` (142 arquivos conferidos) e
  `tools/verify-contract-freeze.py`. Qualquer alteração nesses arquivos exige
  regenerar manifesto **e** decisão sobre contrato congelado — foi exatamente
  isso que deixou o CI vermelho na Frente AV.
- Há ainda um diretório `tree/` com réplica de código que duplica várias
  ocorrências.

### Consequência

| Alcance | Impacto |
|---|---|
| **R1 — só exibição** (rótulos/registros/novo JSON) | Seguro. Código, contratos, hashes e probes permanecem intactos; documenta-se a equivalência nome novo ↔ identificador antigo |
| **R2 — global** (renomear arquivos, identificadores, docs, probes) | **Quebra** `verify-contract-freeze`, os 14 manifestos, `docs-links`, probes e contratos F9. Exige reauditoria completa e decisão explícita sobre os 10 `PENDING_DECISION` |

**Recomendação técnica: R1.** O nome de produto exibido ao usuário não precisa
ser igual ao identificador interno.

---

## 3. De onde viriam "versão" e "atualizações"

| Opção | Migration? | O que mexe | Viabilidade aqui |
|---|---|---|---|
| **V1 — reaproveitar campos** (`description` = changelog curto, `date_added` = "desde") | ❌ | só dados no CRUD | Imediato, mas `description` tem 255 chars e `date_added` é data de criação, não de atualização — **não é uma versão real** |
| **V2 — arquivo de metadados versionado** (`tools/internal-tools.json` com `version`, `updated_at`, `notes[]`), lido pelo controller e renderizado numa seção "Ferramentas internas" | ❌ | 2–3 arquivos de código + 3 idiomas + 1 JSON | **Recomendado**: versão auditável, sem tocar em `schema.sql`, sem migration |
| **V3 — colunas novas** (`version`, `date_modified`) + tabela de updates | ✅ | `schema.sql`, `schema-complete.sql`, upgrade SQL, model, form, list, ACL | **Vetado** pelas restrições vigentes (sem migration, sem runtime/staging) e sem MySQL neste ambiente. Só com autorização explícita |

As versões iniciais teriam de ser **fornecidas por você** (ou lidas de uma
referência existente). Não vou inventar números de versão.

---

## 4. Onde as internas apareceriam

- **P1 — como registros na mesma lista `tool/tool`**, junto de Fluxa etc.,
  com `vendor` = "ReamurCMS" e `url` apontando para a rota interna. Zero código
  se feito pela UI do Admin; mas herda as mesmas colunas (sem versão).
- **P2 — seção separada "Ferramentas internas"** na página Ferramentas,
  alimentada pelo JSON da V2. Mantém externas e internas visualmente
  distintas e permite versão/atualizações. Exige pacote de código.
- **P3 — também na grade pública do catálogo.** Atenção: `tool/tool` público
  é uma **vitrine de ferramentas externas** com SEO/keyword; expor ferramentas
  internas do Admin ali pode não fazer sentido e amplia o escopo.

---

## 5. O que NÃO foi feito

Nenhum pacote de código, nenhuma escrita no GitHub, nenhuma migration, nenhum
rename aplicado, nenhuma alteração de contrato/manifesto/hash, nenhuma decisão
das 10 `PENDING_DECISION`, nenhuma declaração de release. O veredito
**APROVADO** da AP permanece válido.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · CI 7/7 success (lido, não disparado).

---

## 6. Decisões do usuário (registradas em 2026-11-06)

| # | Decisão | Escolha |
|---|---|---|
| 1 | Grafia do nome | ✅ **`KAZEditor-Theme`** (não `KAZEditor-Thene` — grafia corrigida) |
| 2 | Alcance do rename | ✅ **R2 — global** |
| 3 | Origem da versão | ✅ **V2 — JSON de metadados versionado**, sem migration |
| 4 | Onde exibir | ✅ **P2 — seção "Ferramentas internas" só no Admin** |

Nomes finais: **`BDREditor-Content`** e **`KAZEditor-Theme`**.

**Levantamento posterior ao R2:** 179 arquivos / 762 ocorrências;
13 dos 14 manifestos `CHECKSUMS*.txt` teriam de ser regenerados; os **3
contratos congelados** de `frozen_artifacts_sha256` **não** citam os nomes e
portanto **não** precisam ser alterados — `verify-contract-freeze.py` pode
continuar verde. Detalhe e fases em `Plano-Frente-AX.md` e
`Inventario-Rename-Global.md`.

**Ainda pendente (não decidido por inferência):** as versões iniciais das duas
ferramentas e se o R2 inclui também **identificadores de código** (nomes de
arquivos JS/PHP, classes, rotas) ou só texto/rótulos/docs. Sem isso, nenhum
rename foi aplicado.

<!-- FIM: Analise-Viabilidade-Ferramentas-Internas.md -->


<!-- INICIO: DIAGNOSTICO-3D.md (10843 bytes) -->

# BO-r3 — Por que os objetos 3D não aparecem (e os erros de `<path>`)

## 0. r3: diagnóstico sem console + causa mais provável confirmada

Refiz a integração com as **classes PHP reais** (`ThemeSnapshot` → `startup/theme.php` → `common/header.php` → `common/home.php` → `header.twig` → `theme-scene.twig` → `AssetManifest`), não só com a prévia HTML isolada. Resultado: **25 OK / 0 FALHAS**. Ou seja: com o patch completo, `theme_scene.enabled=true`, `theme_scene.scene=david-universe`, `has_3d=true`, o importmap e o `<script type="module">` são emitidos e o manifest publica o módulo novo.

Isso **isola a falha no servidor**, e o ponto mais frágil ficou demonstrado:

> `AssetManifest::resolve('three/scenes/david-universe.js')` devolve um caminho, mas `common/three.php` responde **404 se o arquivo não existir no disco**. É um **arquivo NOVO** (`new file mode` no diff). Quem aplica só os 9 arquivos modificados — por `patch -p1`, GUI de Git ou upload manual — fica com o `main.js` importando algo que não existe. **Um único import com 404 aborta o módulo ES inteiro**: nenhuma cena, nem as 4 antigas.

Duas ferramentas novas no pacote, para resolver sem precisar do Console/DevTools:

| Arquivo | O que faz |
|---|---|
| **`INSTALAR.bat`** ⭐ | **Windows, sem Python, sem terminal:** duplo clique, digite a pasta do davidcms, pronto. Chama o PowerShell com `-ExecutionPolicy Bypass`. |
| **`instalar-bo.ps1`** | O mesmo em PowerShell puro (já vem no Windows). `powershell -NoProfile -ExecutionPolicy Bypass -File instalar-bo.ps1 -Repo "C:\xampp\htdocs\davidcms" -Apply`. |
| **`instalar-bo.py`** | Versão Python/macOS/Linux: `python3 instalar-bo.py --repo /caminho/do/davidcms --apply`. Testado em réplica limpa: **25/0**. |
| **`COPIAR-MANUALMENTE.md`** | Sem script nenhum: tabela dos 10 arquivos para copiar por FTP/cPanel/Gerenciador de Arquivos. |
| **`verificar-bo.html`** | Coloque na **raiz do site** e abra `https://seusite/verificar-bo.html`. Busca a home, lê `[data-scene]`/importmap, faz `fetch` nas 6 URLs do runtime e mostra uma tabela verde/vermelho + o veredito do elo quebrado. **Sem console, sem DevTools, somente-leitura.** |

> **Se apareceu `Python was not found; run without arguments to install from the Microsoft Store`**: é só o Windows sem Python. **Não precisa instalar nada.** Use o `INSTALAR.bat` (duplo clique) ou o `COPIAR-MANUALMENTE.md`. O `.ps1` e o `.bat` fazem exatamente a mesma cópia dos 10 arquivos e as mesmas 5 verificações pós-install.

Reproduzi o `verificar-bo.html` contra uma prévia sem o módulo novo: ele apontou **"ELO 3 QUEBRADO — HTTP 404"** com 0 erros de JS (captura em `evidencia/shot-bo-diagnostico.png`).

**Se você não quer ler nada:** rode o `instalar-bo.py --apply`, limpe o cache do CMS, ative o tema no Admin e abra o `verificar-bo.html`. Se continuar vermelho, me mande um print da página.

---

## 1. Os dois erros do console NÃO são a causa do 3D

```
Error: <path> attribute d: Expected path command, "M40 24h80v84#eb767e40z".
Error: <path> attribute d: Expected path command, "M40 24h80v84#a7b3b540z".
```

São dois **selos SVG decorativos** (catálogo nível 06 e grid do Instagram). Um `<path d>` inválido faz o navegador ignorar aquele `<path>` e imprimir o aviso; **não interrompe o parser HTML nem impede WebGL**. O conteúdo 2D continua, o `<canvas>` continua podendo ser criado.

**Causa raiz:** o gerador do tema BN usava a letra `H` como marcador de cor (`stroke="H"`) e fazia `.replace('H', hue)`. O selo 06 tem o comando SVG `H40z` (linha horizontal absoluta). O replace trocou **todos** os `H`, inclusive o comando:

```
correto  : d="M40 24h80v84H40z"
gerado   : d="M40 24h80v84#eb767e40z"   ← a cor entrou dentro do path
```

As duas cores do seu log (`#eb767e`, `#a7b3b5`) são exatamente as duas ocorrências. O bug vinha do **BN** e foi herdado pelo BO; afetava 4 paths em 3 templates (`common/home` ×2, `partial/portfolio`, `partial/news`).

**Corrigido no BO-r2:**
- `bn_theme_gen.py`: placeholder passou a ser `__SEAL_HUE__` (não colide com comandos SVG);
- `bo_theme_gen.py`: saneia os 4 paths do config BN já exportado → `M40 24h80v84H40z`;
- `verify-bo.cjs`: novo check varre **todos** os `<path d>` dos 25 templates e falha se houver `#` ou caractere fora da gramática SVG. Resultado: **30 paths, 0 inválidos**.

O ZIP BN anterior **não foi alterado** e continua com o bug. Se ele estiver ativo no seu site, troque pelo BO-r2.

---

## 2. Então por que o 3D não renderiza?

O 3D depende de uma cadeia de **5 elos no servidor**. Se qualquer um falhar, o resultado é exatamente o que você descreveu: página 2D normal, nenhum objeto 3D, nenhuma interação. O tema sozinho (o `.reamurtheme.json`) **não** cria a cena — ele só declara `data-scene="david-universe"`. Quem cria a nave é o runtime patchado.

| # | Elo | O que confere | Se falhar |
|---|-----|---------------|-----------|
| 1 | **`PATCH-BO.diff` aplicado** | 9 arquivos alterados + `scenes/david-universe.js` novo | `data-scene` existe, mas nenhuma cena registrada → `boot()` ignora o slot |
| 2 | **`main.js` publicado** | contém `import './scenes/david-universe.js'` e `boot()` | nenhum módulo carrega |
| 3 | **`asset_manifest.php` + arquivo novo presentes** | `GET .../three/scenes/david-universe.js` → **200**, `Content-Type: text/javascript` | ⚠️ **um único import com 404 quebra o módulo ES inteiro** — as 4 cenas antigas também somem |
| 4 | **`common/home.php` / `startup/theme.php`** | `has_3d` / `three_hero_enabled` = `1` para `david-universe` | o header não inclui `main.js`; nada é executado |
| 5 | **Allowlists PHP** (`theme_package`, `condition_engine`, `theme_snapshot`, `scene_preset_registry`, admin) | aceitam `david-universe` | `decode()` é fail-closed → cena zerada, `theme-scene.twig` não emite o slot |

**Causa mais provável, em ordem:**

1. **Patch aplicado pela metade** — `main.js` foi atualizado, mas `david-universe.js` não foi copiado ou `asset_manifest.php` não foi publicado. O navegador acusa `Failed to fetch dynamically imported module` / 404 e **todo** o `main.js` aborta. É o sintoma mais compatível com "nenhum objeto 3D" (nem as cenas antigas).
2. **`has_3d` falso** — `common/home.php` sem patch, ou `three_hero_enabled` desligado no Admin. O `<script type="module">` nem é emitido.
3. **Patch não aplicado** — só o tema foi importado. `ConditionEngine::scenes()` ainda devolve as 4 cenas originais e o `SceneRunner` não tem factory para `david-universe`.
4. **`prefers-reduced-motion: reduce`** ativo no sistema/navegador — por desenho, o `SceneRunner` substitui a cena por um noop e **não cria WebGL**. Comportamento esperado, não é falha.
5. **Slot fora da viewport** — o `IntersectionObserver` pausa a cena; no BO-r2 o slot é `position:fixed`, então isso não deve ocorrer.

> Observação: a URL `home?language=es-eu` mostra que o **tema está renderizando** (os SVGs do erro vêm dele). Logo o problema não é importação do tema, é o runtime.

---

## 3. Diagnóstico em 10 segundos (cole no console)

Arquivo pronto: `diagnostico/console.js` neste pacote. Ou cole isto:

```js
(() => {
  const s = document.querySelector('[data-scene]');
  const res = performance.getEntriesByType('resource').map(r => r.name);
  const hit = p => res.filter(n => n.includes(p));
  const c = document.createElement('canvas');
  let gl = false; try { gl = !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) {}
  return {
    slot: s ? { scene: s.dataset.scene, canvas: !!s.querySelector('canvas'),
                w: Math.round(s.getBoundingClientRect().width),
                h: Math.round(s.getBoundingClientRect().height) } : null,
    mainJs:   hit('three/main.js'),
    sceneJs:  hit('david-universe'),
    three:    hit('three.module.js'),
    modulos:  [...document.scripts].filter(x => x.type === 'module').map(x => x.src || '(inline)'),
    webgl:    gl,
    reduced:  matchMedia('(prefers-reduced-motion: reduce)').matches,
    erros404: res.filter(n => /david-universe|three\/main/.test(n))
  };
})()
```

**Como ler o resultado**

| Resultado | Diagnóstico | Ação |
|---|---|---|
| `slot: null` | allowlists / snapshot não aceitaram a cena | aplicar `PATCH-BO.diff` (elo 1 e 5) |
| `slot.scene` ≠ `david-universe` | tema BN antigo ativo, ou condição não bateu | ativar `david_universe_3d_bo` e conferir a regra `du_home_universe` |
| `mainJs: []` | `has_3d` falso | elo 4: `common/home.php` e `three_hero_enabled` |
| `mainJs` OK, `sceneJs: []` | **`asset_manifest` não publica / arquivo novo ausente** | elo 3: conferir o arquivo e a allowlist; **é o caso mais comum** |
| `three: []` | runtime r186 não servido | conferir `ControllerCommonThree::asset()` e `common/three.php` |
| tudo OK, `canvas: false` | erro de import ou MIME errado | abrir a aba **Network**, ver o status/MIME de `david-universe.js` e `three.module.js` |
| `reduced: true` | sem WebGL por desenho | desligar "reduzir movimento" para testar |
| `webgl: false` | navegador/sem aceleração | testar em outro navegador |

Se ainda falhar, me mande esse JSON + as linhas da aba **Network** para `david-universe.js`, `main.js` e `three.module.js` (status e `Content-Type`). Com isso eu identifico o elo exato sem precisar acessar o servidor.

---

## 4. Checklist de aplicação (você aplica; eu não escrevo no GitHub)

```bash
# na raiz do ReamurCMS
git apply --check patch/PATCH-BO.diff      # deve dar OK
git apply        patch/PATCH-BO.diff

# confirma que o arquivo NOVO realmente existe no path certo
ls -l dev/system/library/three/runtime/three/scenes/david-universe.js

# confirma os 9 alterados
grep -n "david-universe" dev/system/library/three/runtime/three/main.js \
  dev/system/library/three/asset_manifest.php \
  dev/catalog/controller/common/home.php
```

Depois: limpar cache de opcode/Twig do CMS, importar `tema/david_universe_3d_bo.reamurtheme.json` no Admin, **ativar** o tema (a BL mostrou que `activate()` é quem publica; `save()` guarda só o rascunho) e recarregar com `Ctrl+F5`.

No Admin, a cena deve aparecer como **"David Universe (nave + orbes)"** no dropdown; se o dropdown só listar as 4 cenas antigas, o `admin/controller/design/theme.php` não foi patchado.

---

## 5. O que este pacote NÃO faz

Não aplica o patch, não escreve no GitHub, não roda migration/deploy/CI/runtime autenticado, não altera `schema.sql`, contratos congelados, `CHECKSUMS.txt` nem o catálogo público. Não é aceite visual nem veredito de release: `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` e os 10 `PENDING_DECISION` permanecem. Chromium headless, PHP WASM e os verificadores estáticos validam o pacote **localmente** — não substituem o teste no seu servidor.

<!-- FIM: DIAGNOSTICO-3D.md -->


<!-- INICIO: DIAGNOSTICO-BK.md (7230 bytes) -->

# Diagnóstico — BK: stage do KAZEditor-Theme vazando no Catálogo

**HEAD auditado:** `ab8b76588010a5349930c6326e947e5af36fcb49` (BJ aplicada)
**Evidência:** `image-12.png` + o trecho HTML colado na mensagem.
**Veredito:** **falha confirmada, de produto — não é erro de CSS do tema.**

## 1. O que você viu

O `<section class="rms-scene" aria-label="Composição visual do tema">` com as
3 camadas (`DAVID`, `Creative Developer`, `Get in touch`) é o **stage de
composição do editor**. Ele está sendo renderizado **no topo do `<main>` do
site público**, antes do hero, com fundo branco — exatamente como na imagem.
Os valores `4.167% / 7.407% / 43.750%` saem de `x=40,y=40,w=420` sobre o
canvas `960×540` do `kaz_scene` do tema David Creator. Confere 100% com o
pacote BJ que você aplicou.

## 2. Cadeia causal (linhas reais do HEAD)

| # | Arquivo | Linha | O que faz |
|---|---|---|---|
| 1 | `dev/system/library/theme/theme_snapshot.php` | 120 | `$sceneView = $this->sceneCss->compile($config['kaz_scene'] ?? null)` |
| 2 | `dev/system/library/theme/theme_scene_css.php` | 57 | devolve `['enabled' => $layers !== [], 'layers' => …]` — **qualquer camada visível liga `enabled`** |
| 3 | `dev/system/library/theme/theme_snapshot.php` | 123 | publica `scene_view` dentro do snapshot |
| 4 | `dev/catalog/controller/startup/theme.php` | 155 | `$this->config->set('catalog_theme_scene_view', $snapshot['scene_view'])` — **config global do Catálogo** |
| 5 | `dev/catalog/controller/common/home.php` | 369 | `$data['theme_scene_view'] = (array)$this->config->get('catalog_theme_scene_view', [])` |
| 6 | `dev/catalog/view/template/common/home.twig` | 4 | `{% include 'partial/theme-scene-block.twig' %}` **no topo do `<main>`** |
| 7 | `dev/catalog/view/template/partial/theme-scene-block.twig` | 3 | `{% if theme_scene_view.enabled|default(false) %}` → `<section class="rms-scene">` |
| 8 | `dev/catalog/view/css/theme.css` / `ThemeSceneCss::previewCss()` | — | `.rms-scene{background:var(--bg,#fff)}` → **o fundo branco da sua imagem** |

Ou seja: **`kaz_scene` (dado de edição) está sendo publicado como bloco
visível no front**. Não depende do tema ser o David Creator: qualquer tema
com `kaz_scene.layers` visíveis dispara. Confirmamos em todos os 4 pacotes
entregues (BG, BH, BI, BJ), que também incluíam o partial — o BH em **12
pontos**.

## 3. A garantia que foi quebrada

O próprio editor documenta, em
`dev/admin/view/template/design/kaz_theme_editor.twig:566`:

> *"O KAZEditor-Theme edita a **cena do tema**, não um protótipo navegável.
> Não há interações, fluxos ou View mode: a cena é dado de edição e
> versionamento (`kaz_scene`), **não renderização no site público**."*

A "Frente AB" (`partial/theme-scene-block.twig`, comentário no próprio
arquivo: *"a cena desenhada no KAZTheme-Editor, publicada no Catalogo"*)
**contradiz essa garantia**. Há ainda a "Frente AF" em
`dev/system/library/template/twig.php:230-233`, que injeta
`theme_scene_view` no contexto da **prévia sandbox do Admin** — esse uso é
legítimo e deve ser mantido.

**Conclusão:** o partial é correto no Admin (prévia + `ThemeCompareView`),
mas **não deve ser chamado nem alimentado no Catálogo**.

## 4. Sobre "o KAZEditor-Theme não está alterando coisas dos temas inseridos"

O diagnóstico confirma a sua observação, e explica o mecanismo: o editor
grava `kaz_scene` no `config` do tema (`theme.php:651-667`), mas esse dado
**não altera os `templates`, os tokens, nem o layout renderizado do tema**.
O único efeito observável no site é justamente o bloco avulso do item 2 —
um painel solto, global, que aparece antes do hero em qualquer tema.
Editar a cena, portanto, não edita o site: ela fica presa num dado de
composição que só o Admin consome (e que agora vazava para o front).

Isso é uma **decisão de produto**, não um ajuste de CSS: para a ferramenta
passar a alterar de fato os temas inseridos, é preciso definir explicitamente
qual é o contrato — `kaz_scene` continua sendo apenas dado de edição, ou
passa a gerar `templates`/tokens no config do tema (o que muda allowlist,
validação e renderer, e depende de autorização do **Recorte B**). **Não
decidimos isso aqui.** A BK fecha o vazamento sem mexer nesse contrato.

## 5. Correção em duas camadas

### Camada A — pacotes (entregues e aplicáveis já)

Removemos **toda** linha `{% include 'partial/theme-scene-block.twig' %}`
das 25 fontes dos 4 temas: 2 no David Creator, 2 no Atlas Studio, 2 no
Reamur Reference e **12** no ThreeJS Reference. Mantivemos:

- `kaz_scene` intacto (3/3/5/24 camadas) — continua sendo o dado de edição
  do Admin e continua na prévia sandbox;
- `partial/theme-scene.twig` intacto — é o **slot declarativo ThreeJS**,
  emite apenas `data-*` e não renderiza o stage.

Os 4 pacotes foram **reexportados com `ThemePackage::export()` real** e
revalidados com `decode()`/`validateConfig()`; os checksums mudaram porque
o `config` mudou. **Sem migration, schema, contrato, manifesto ou renderer
alterado.** Reimportar cada tema substitui o `common/home` e remove o bloco
do front imediatamente — mesmo sem aplicar o patch de runtime.

### Camada B — patch de runtime (PROPOSTA, **não aplicado**)

`docs/correcao/correcao-runtime.patch` remove **4 linhas em 3 arquivos do
Catálogo**:

1. `common/home.twig` — remove o `include` (defesa em profundidade);
2. `common/home.php` — deixa de injetar `theme_scene_view` no front;
3. `startup/theme.php` — deixa de publicar `catalog_theme_scene_view`.

**Nada muda no Admin**: `twig.php` (`renderSandboxPreview`),
`theme_compare_view.php`, `theme.php` e o próprio
`partial/theme-scene-block.twig` permanecem intactos — a cena continua
aparecendo na prévia e na comparação de temas, como documentado.
`catalog_theme_scene_css` (variáveis `--rms-scene-*`) foi mantido para
minimizar o patch; fica sem consumidor e pode ser removido numa etapa
posterior, se você quiser.

Os 3 arquivos **não constam em `CHECKSUMS.txt`**, então o patch não exige
atualização de manifesto. Aplicação sugerida: `git apply docs/correcao/correcao-runtime.patch`.
**O agente não escreveu nada no GitHub.**

## 6. Prova

`evidencia/antes-depois.png` justapõe a **sua captura real** (`image-12.png`)
com a prévia corrigida. `evidencia/layout-check.json` mede estruturalmente:

| | `rms-scene__stage` | `data-rms-layer` | hero | seções | CTA | footer |
|---|---|---|---|---|---|---|
| antes | 1 | 3 | ✓ | 10 | ✓ | ✓ |
| depois | **0** | **0** | ✓ | 10 | ✓ | ✓ |

`tools/verify-no-scene-leak.cjs` varre os 4 pacotes, valida os checksums
canônicos, falha se qualquer tema voltar a incluir o partial e confere que
o patch não toca no Admin nem em contratos.

## 7. Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Nenhuma escrita do agente no
GitHub, nenhum runtime/MySQL/deploy, nenhuma conversão de `PENDING`/
`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão sobre o Recorte B.
CI verde, PHP WASM, PIL e guardas estáticos **não significam release
aprovada**; o aceite visual no Admin real continua pendente.

<!-- FIM: DIAGNOSTICO-BK.md -->


<!-- INICIO: DIAGNOSTICO-BL.md (7044 bytes) -->

# Diagnóstico — BL: por que o vazamento continua e o editor não carrega os dados reais

**HEAD auditado:** `3219ccae80550f81510068c311a8afcc1de8d71a` · CI 7/7 ✅
**Conclusão:** as duas reclamações têm **uma única causa**: o que foi
commitado no repositório **não é** o que o Admin e o Catálogo leem.

## 1. O que o commit `3219ccae…` realmente fez

Ele adicionou os **arquivos do ZIP BK** (`DIAGNOSTICO-BK.md`, os 4 JSONs
corrigidos em `docs/temas/`, `docs/correcao/correcao-runtime.patch`, evidências).
Verificamos o diff: **`dev/` não foi tocado**. No HEAD atual as 3 linhas do
patch continuam exatamente iguais:

| Arquivo | Linha | Conteúdo no HEAD |
|---|---|---|
| `common/home.twig` | 4 | `{% include 'partial/theme-scene-block.twig' %}` |
| `common/home.php` | 369 | `$data['theme_scene_view'] = …get('catalog_theme_scene_view', [])` |
| `startup/theme.php` | 155 | `$this->config->set('catalog_theme_scene_view', $snapshot['scene_view'])` |

Ou seja: **`git apply` não foi executado**. E mesmo que tivesse sido, ainda
faltaria a peça principal abaixo.

## 2. Repositório ≠ banco de dados

Temas **não** são lidos de `docs/temas/*.json`. São registros da tabela
`design_theme`, com três payloads distintos:

| Coluna | Quem grava | Quem lê |
|---|---|---|
| `draft_config` | `save($id,…)` e `import($id,…)` | **editor** (`edit()`), prévia de rascunho |
| `published_config` | **somente `activate()`** | **Catálogo** (`startup/theme.php:79-80` → `getThemeMap('published')`) |
| `config` | criação e `activate()` | fallback legado |

`dev/admin/model/design/theme.php::save()` com `$id` faz:

```sql
UPDATE design_theme SET name=…, slug=…, `draft_config`=…, date_modified=NOW()
WHERE theme_id=…
```

**Não toca em `published_config` nem em `config`.** Só `activate()` executa
`published_config = draft_config, config = draft_config, status=1`.

E `import()` com `theme_id=0` (importar pela lista) cria um registro **novo com
`status=0`** — inativo. Portanto:

- atualizar/colocar os JSONs em `docs/` → **não muda nenhum registro**;
- **importar** num tema existente → muda só o rascunho;
- **salvar** no editor → muda só o rascunho;
- **sem clicar em Ativar**, o Catálogo continua usando o `published_config`
  antigo, com o `common/home` antigo e o `kaz_scene` antigo → **vazamento
  persiste**.

Provamos isso com o **model real** em DB em memória (`evidencia/model-harness.txt`,
10 OK / 0 FALHAS): após `import()` o rascunho fica limpo, mas o publicado
continua com o include; só `activate()` publica.

## 3. Por que o KAZEditor-Theme "não carrega as informações reais"

`edit()` lê `draft_config` do registro — correto. O problema está em
`theme.php:369`:

```php
$data['template_sources']=$data['templates'];
foreach($data['template_keys'] as $templateKey){
  if(!isset($data['template_sources'][$templateKey])){
    $templateFile=…/catalog/view/template/{$templateKey}.twig;
    if(is_file($templateFile)) $data['template_sources'][$templateKey]=file_get_contents($templateFile);
  }
}
```

Se o registro não tem `templates` (temas criados antes dos pacotes, ou
rascunho gravado sem templates), o editor **preenche as 25 lacunas com os
`.twig` nativos do disco** e mostra `#2563eb`/`#ffffff` e tokens default.
Você vê o tema **do sistema**, não o tema que importou. A prévia isolada
ainda devolve *"No saved source is available"* porque `preview()` exige
`templates[$key]` no rascunho.

**E há um efeito colateral grave:** esses textareas têm
`name="templates[…]"` (`kaz_theme_editor.twig:507`). Ao clicar em **Salvar**,
`buildDraftConfig()` recebe os 25 fontes nativos e os grava como se fossem do
tema. O `common/home.twig` nativo contém justamente
`{% include 'partial/theme-scene-block.twig' %}` — então **cada salvamento no
editor reinjeta o vazamento**, mesmo depois de importar o pacote corrigido.
Isso fecha o ciclo das duas reclamações.

## 4. Correção da frente BL

### Patch A — `A-vazamento-failclosed.patch` (**obrigatório**)

Diferente da BK, **não depende** de reimportar, ativar ou do conteúdo do
banco. O ponto central é um **gate fail-closed** no renderizador:

```twig
{% if theme_scene_preview|default(false) and theme_scene_view.enabled|default(false) %}
```

`twig.php::renderSandboxPreview()` passa a injetar `theme_scene_preview=true`
(2 pontos). O Catálogo nunca injeta esse flag → o partial **nunca** renderiza
no front, mesmo com `published_config` antigo, mesmo com `kaz_scene` cheio,
mesmo se algum tema ainda incluir o partial. A prévia do Admin e o
`ThemeCompareView` (fallback PHP próprio) continuam mostrando a cena.

Mais 5 edições de defesa em profundidade: as 3 remoções da BK
(`home.twig`, `home.php`, `startup/theme.php`) e os 2 flags no `twig.php`.
Total: **6 edições em 5 arquivos**. `theme-scene-block.twig` continua existindo
e sendo usado no Admin — não foi removido.

### Patch B — `B-editor-dados-reais.patch` (**recomendado**)

- `edit()`: se o rascunho não traz `templates`, **herda do `published_config`**
  (o dado real do tema) antes de qualquer fallback;
- expõe `template_saved_keys` = chaves que realmente pertencem ao tema;
- no editor, fontes nativos ficam **visíveis**, porém `disabled` e **sem
  `name=`**, com aviso *"Fonte nativa do disco — não pertence a este tema e
  não é salva"*. Assim salvar não reintroduz o include.

`buildDraftConfig()`, `save()`, `activate()`, `ThemePackage`, allowlists,
contratos, manifestos, schema e renderer **não foram alterados**.

## 5. Diagnóstico no seu servidor (somente leitura)

`tools/diagnostico-tema.sql` faz apenas `SELECT` e responde: qual tema está
ativo, quantos templates há em cada payload e **onde** está o include. Rode
antes de aplicar os patches e guarde a saída.

## 6. Ordem operacional (importa)

1. Rodar `tools/diagnostico-tema.sql` e anotar o `theme_id` ativo.
2. `git apply docs/correcao/A-vazamento-failclosed.patch`
3. `git apply docs/correcao/B-editor-dados-reais.patch`
4. Limpar `storage/cache/template/` (Twig reconstrói o `ChainLoader`).
5. Admin → **Temas do Catálog** → abrir o **tema ativo** → **Importar tema**
   com o `.json` corrigido → **Ativar** (sem Ativar, nada muda no site).
6. Conferir em aba anônima: não deve existir `<section class="rms-scene">`.
7. `node tools/verify-bl.cjs <raiz>`.

> Passo 2 sozinho já fecha o vazamento. Os passos 3–5 garantem que o editor
> passe a mostrar/editar os dados reais e que o tema publicado fique limpo.

## 7. Limites

Nenhuma escrita do agente no GitHub, nenhuma migration/`ALTER`, nenhum SQL de
escrita, nenhum runtime/MySQL/deploy executado por mim, nenhuma decisão do
**Recorte B**, nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em
`PASS`. `release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. CI verde, PHP WASM e guardas
estáticos **não significam release aprovada**. Aceite visual e health no Admin
real continuam pendentes.

<!-- FIM: DIAGNOSTICO-BL.md -->


<!-- INICIO: GUIA-BL.md (3151 bytes) -->

# Guia operacional — BL (ordem exata)

## Resumo em uma frase

**Os JSONs em `docs/temas/` e o `.patch` são arquivos; o Admin e o Catálogo
leem a tabela `design_theme`.** Nada do que foi commitado em `3219ccae…` foi
aplicado ao runtime. Por isso "atualizar as informações" não mudou o site.

## 0. Diagnosticar (30 segundos, somente leitura)

Edite `<PREFIX>` em `tools/diagnostico-tema.sql` e rode no MySQL. Anote:

- qual `theme_id` tem `ativo = 1`;
- se `include_no_publicado = 1` (é o que o site está renderizando);
- se `include_no_draft = 0` (você já corrigiu o rascunho, mas não publicou);
- se `templates_no_draft = 0` (é por isso que o editor mostra fontes nativos).

## 1. Aplicar os patches (na raiz do repositório)

```bash
git apply docs/correcao/A-vazamento-failclosed.patch
git apply docs/correcao/B-editor-dados-reais.patch
```

- **A** fecha o vazamento de forma **fail-closed**: mesmo com o
  `published_config` antigo no banco, o stage não renderiza no Catálogo.
- **B** faz o editor carregar os dados reais do tema e impede que um Salvamento
  reintroduza o include.

Verifique com `git diff --stat`: 5 arquivos no patch A, 2 no patch B.
Nenhum deles está em `CHECKSUMS.txt`.

## 2. Limpar o cache de templates

```bash
rm -rf storage/cache/template/*
```

`twig.php::setThemeTemplates()` reconstrói o `ChainLoader`; se o cache estiver
ligado, sem limpar você continua vendo o HTML antigo.

## 3. Publicar o tema (o passo que faltou)

Admin → **Temas do Catálog**:

1. Abra o **tema que está ATIVO** (o `theme_id` do passo 0) — não crie outro.
2. **Importar tema** → selecione o `.json` corrigido correspondente
   (`David-Creator.reamurtheme.json` etc.).
3. Confira a prévia: 25 fontes, sem include de `theme-scene-block`.
4. **ATIVAR**. ⚠️ Sem este clique, `save()`/`import()` gravaram apenas
   `draft_config` e o Catálogo continua no `published_config` antigo.

> Se você importou pela lista (`Importar tema` sem abrir um registro), foi
> criado um **novo tema inativo**. Ative esse novo (e desative o antigo) ou
> refaça a importação dentro do registro ativo.

## 4. Conferir

- Aba anônima / Ctrl+F5 → **não** deve existir
  `<section class="rms-scene">` antes do hero.
- DevTools → Elementos → procurar `data-rms-layer` → **0 resultados**.
- Admin → editar o tema → aba **Templates** deve mostrar os fontes **do tema**;
  os nativos aparecem desabilitados com o aviso *"Fonte nativa do disco"*.

## 5. Verificar no código

```bash
node tools/verify-bl.cjs .
```

## O que NÃO fazer

- Não editar `published_config` diretamente no banco.
- Não rodar `UPDATE`/`ALTER`/migration.
- Não reimportar sem **Ativar**.
- Não salvar no editor antes do patch B (reintroduz o include).
- Não assumir que commitar um `.json` em `docs/` instala um tema.

## Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Nenhuma escrita do agente no
GitHub, nenhum deploy/runtime executado por mim. CI verde, PHP WASM e guardas
**não significam release aprovada**. Aceite visual e health no Admin real
ainda pendentes.

<!-- FIM: GUIA-BL.md -->


<!-- INICIO: GUIA-BM.md (3110 bytes) -->

# Guia — BM (Atlas Studio 3D)

## O que é

Um **tema completo** no mesmo padrão do Atlas Studio aprovado na BI, agora com
**ThreeJS declarativo em várias seções**. Arquivo:
`docs/temas/Atlas-Studio-3D.reamurtheme.json` (76.637 bytes, 25 fontes Twig,
`validateConfig = []`, `decode ok=true`).

## Como instalar (a ordem importa)

1. Admin → **Temas do Catálog** → abra o **tema que está ATIVO**.
2. **Importar tema** → selecione `Atlas-Studio-3D.reamurtheme.json`.
3. Conferir a prévia: 25 fontes, 16 camadas em `kaz_scene`, `three.enabled`.
4. **ATIVAR.** ⚠️ Sem este clique, `save()`/`import()` gravam apenas
   `draft_config` e o Catálogo continua no `published_config` antigo — foi
   exatamente isso que manteve o vazamento na frente BL.
5. Limpar `storage/cache/template/` e conferir em aba anônima.

> Se preferir um registro separado, importe pela lista (cria tema **inativo**)
> e depois **Ative** esse novo e desative o anterior.

## O que você deve ver

| Rota | Cena | Slot | Visual |
|---|---|---|---|
| Início | `hero-particles` | `hero` | campo de partículas 340px no topo |
| Projetos | `portfolio-3d` | `home_top` | 240px, padrão diagonal |
| Ferramentas | `tools-3d` | `home_top` | 240px, padrão de oficina |
| 404 | `not-found` | `footer_top` | 180px, mais discreto |
| Mobile | `hero-particles` + `atlas-scene-lite` | `hero` | 220px, 180 partículas |

O conteúdo do site (hero, serviços, sobre, método, projetos, depoimentos,
blog, contato, CTA, rodapé) é **o mesmo do Atlas aprovado** — 13 fontes ficaram
byte-a-byte idênticos.

## O que o tema NÃO faz

- Não contém JavaScript. O WebGL é montado pelo `scene-runner.js` **do próprio
  Catálogo**, que lê os atributos `data-scene`/`data-count`/`data-color`/
  `data-speed` emitidos por `partial/theme-scene.twig`.
- Não adiciona `<script>`, `|raw`, handlers inline, fontes remotas nem CDN.
- Não cria coluna, tabela ou migration.
- Não depende de outro tema: `parent` está vazio para o editor não cair nos
  `.twig` do disco (defeito da BL).

## Verificação local

```bash
node tools/verify-bm.cjs .
```

- `evidencia/export-bm.txt` — 14 OK / 0 FALHAS com as classes PHP reais.
- `evidencia/layout-bm.json` — 4 capturas Chromium 141, 0 overflow,
  0 pageerrors, **0 scripts**, 1 slot por página.
- `BM-Preview-Cenas.png` — prancha visual das 3 cenas + mobile.
- `preview/home.html`, `preview/projetos.html`, `preview/not-found.html` —
  prévias estáticas fiéis (slot renderizado como o Twig emitiria).

## Pendências que este pacote NÃO resolve

- Os patches **A** e **B** da BL ainda não foram aplicados: o HEAD
  `ad7643de…` tem **`dev/` intacto**. Enquanto isso, o `common/home.twig`
  nativo continua incluindo `theme-scene-block.twig`.
- `release_approved: false`, F9-27 `BLOCKED_NOT_RUN`, F9-18
  `release_ready: false`, 10 `PENDING_DECISION`.
- Aceite visual da BM e health no Admin real pendentes.

Nenhuma escrita do agente no GitHub, nenhum deploy/runtime/migration
executado por mim. CI verde, PHP WASM, Chromium e guardas **não significam
release aprovada**.

<!-- FIM: GUIA-BM.md -->


<!-- INICIO: GUIA-BN.md (3350 bytes) -->

# Guia — BN (David Universe 3D)

## O que é

O **novo tema oficial** do seu site, seguindo a amostra online
`https://davidalmeida.xyz/threejs/?lang=pt`. Arquivo:
`docs/temas/David-Universe-3D.reamurtheme.json` (88.864 bytes, 25 fontes Twig,
`validateConfig = []`, `decode ok=true`, checksum
`e081f0d26759034b15f1e200e2248199bbee43dea30765fb8977704c3f29a1d4`).

Site completo com as 9 fases da amostra, HUD de jogo, 9 projetos no catálogo,
inventário, serviços, depoimento, Instagram, formulário de contato e rodapé.
Paleta medida: `#121516`, `#5cc6d0`, `#dfdfdf`, `#a7b3b5`, `#eb767e`,
`#ffdf00`.

## Instalar (a ordem importa)

1. Admin → **Temas do Catálog** → abra **o tema que está ATIVO**.
2. **Importar tema** → `David-Universe-3D.reamurtheme.json`.
3. Conferir a prévia: 25 fontes, 20 camadas em `kaz_scene`, `three.enabled`.
4. **ATIVAR** ⚠️ — sem isso, `save()`/`import()` gravam só `draft_config` e o
   Catálogo continua no `published_config` antigo (causa raiz da BL).
5. Limpar `storage/cache/template/` e conferir em aba anônima.

Se quiser um registro separado, importe pela lista (cria tema **inativo**) e
depois Ative o novo e desative o anterior.

## Cenas por rota

| Rota | Cena | Slot | Altura |
|---|---|---|---|
| Início | `hero-particles` | `hero` | 320px (210px no mobile) |
| Catálogo / projeto | `portfolio-3d` | `home_top` | 230px |
| Ferramentas / blog | `tools-3d` | `home_top` | 230px |
| 404 | `not-found` | `footer_top` | 170px |

## Três diferenças conscientes em relação à amostra

1. **Sem Google Fonts.** A amostra carrega Poiret One / Open Sans / Lato de
   `fonts.googleapis.com`; fontes remotas/CDN são proibidas no Recorte A. O
   CSS usa `--du-display` com stack local (`"Poiret One", "Century Gothic",
   "Trebuchet MS", system-ui`) — usa a fonte se existir, sem rede.
2. **Sem `.webp` remotas.** As 9 miniaturas do catálogo são **SVG inline**
   determinísticos com as cores do tema.
3. **Sem JS no tema.** O WebGL/scroll-driven da amostra é feito pelo
   `scene-runner.js` self-hosted do Catálogo lendo os `data-scene` do
   `partial/theme-scene.twig`. O tema escolhe cena, slot, contagem, cor e
   velocidade — nunca escreve código. As capturas mostram o **fallback
   declarativo**; a animação aparece no runtime publicado.

## Verificação local

```bash
node tools/verify-bn.cjs .
```

- `evidencia/export-bn.txt` — **14 OK / 0 FALHAS** com as classes PHP reais.
- `evidencia/layout-bn.json` — 5 capturas Chromium 141, 0 overflow em
  390/360/320px, 0 pageerrors, **0 scripts**, 1 slot por página.
- `evidencia/ref-*.png` / `ref.json` — capturas e medições da amostra online.
- `BN-Amostra-vs-Tema.png` — comparação lado a lado do hero.
- `preview/*.html` — prévias estáticas fiéis das 4 cenas.

## Pendências que este pacote NÃO resolve

- Os patches **A/B da BL** continuam não aplicados (HEAD `ad7643de…` tem
  `dev/` intacto); o `common/home.twig` nativo ainda inclui
  `theme-scene-block.twig`.
- `release_approved: false`, F9-27 `BLOCKED_NOT_RUN`, F9-18
  `release_ready: false`, 10 `PENDING_DECISION`.
- Aceite visual da BN e health no Admin real pendentes.

Nenhuma escrita do agente no GitHub, nenhum deploy/runtime/migration executado
por mim. CI verde, PHP WASM, Chromium e guardas **não significam release
aprovada**.

<!-- FIM: GUIA-BN.md -->


<!-- INICIO: GUIA-BO.md (5070 bytes) -->

# Guia de aplicação — Frente BO-r3

Ordem recomendada. **Você aplica**; o agente não escreve no GitHub.

## 0. Caminho mais curto (se o 3D já falhou uma vez)

Escolha **uma** das três formas; fazem a mesma coisa (copiam os **10** arquivos
e verificam que `scenes/david-universe.js` ficou legível):

**A · Windows, sem Python — duplo clique.** Extraia o ZIP e dê duplo clique em
`INSTALAR.bat`. Ele pergunta a pasta raiz do davidcms (a que contém `dev`) e
chama o PowerShell com `Bypass`. Se apareceu
`Python was not found; ... Microsoft Store`, **é esta a opção**.

**B · PowerShell / terminal.**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File instalar-bo.ps1 -Repo "C:\xampp\htdocs\davidcms" -Apply
```
```bash
# macOS / Linux, se tiver Python:
python3 instalar-bo.py --repo /caminho/para/davidcms --apply
```

**C · Sem script nenhum.** Siga `COPIAR-MANUALMENTE.md` (10 pares
origem → destino, para FTP/cPanel/Gerenciador de Arquivos).

Depois de qualquer uma: limpe o cache de opcode/Twig, **ative** o tema
`david_universe_3d_bo` no Admin e abra `verificar-bo.html` (copie para a raiz
do site) — ele mostra qual dos 5 elos quebrou, sem precisar abrir o Console.

> **Se os objetos 3D não aparecerem**, pare aqui e abra `DIAGNOSTICO-3D.md`.
> Na maioria dos casos é o `PATCH-BO.diff` aplicado pela metade: `main.js` foi
> atualizado, mas `dev/system/library/three/runtime/three/scenes/david-universe.js`
> não existe no servidor ou não é publicado pelo `asset_manifest.php`. Um import
> com 404 derruba o módulo ES inteiro. Use `diagnostico/console.js` para confirmar
> em 10 segundos. Os erros `<path> attribute d` do console **não** são a causa do
> 3D — são selos SVG decorativos, já corrigidos nesta revisão r2.

## 1. Aplicar o patch do runtime

No checkout do `davidcms` em `ad7643de6ca2` (ou compatível):

```bash
git apply --check patch/PATCH-BO.diff     # deve sair sem erro
git apply        patch/PATCH-BO.diff
git status --short                        # 9 modificados + 1 novo
```

O patch **não** mexe em SQL, migration, CI, deploy, `CHECKSUMS.txt`, `theme-scene.twig`, `scene-runner.js`, manifestos nem contratos congelados.

Alternativa sem `git`: copie os arquivos de `patch/arquivos/` para os mesmos caminhos no repositório.

> `dev/system/library/three/runtime/three.module.js` e `three.core.js` **já existem** no servidor (r186). Os que vão em `previa/` são apenas para teste local — não copie por cima.

## 2. Conferir (opcional, estático)

```bash
bash tools/verify-checksums.sh             # 14 manifestos, sem divergência
node tools/verify-bo.cjs .                 # a partir da raiz deste pacote
```

## 3. Importar o tema

1. Admin → **Temas** → **Importar tema**.
2. Envie `tema/david_universe_3d_bo.reamurtheme.json` (91.504 bytes).
3. Confira `validateConfig=[]` e `decode ok=true`.
4. **Ative** o tema. O slug é novo (`david_universe_3d_bo`), então não sobrescreve o BN. Se quiser substituir, desative/exclua o `david_universe_3d` antes.
5. No editor de temas, a cena **`David Universe (nave + orbes)`** aparece no dropdown e o preset **`universe`** mostra a miniatura da nave.

O HUD do tema expõe `data-du-orbes`, `data-du-fase`, `data-du-distancia`, `data-du-progresso`, `data-du-toast` e `data-du-hint-distancia`. A cena procura esses ganchos e cai para seletores de classe se eles não existirem.

## 4. Testar a prévia sem o CMS (opcional)

```bash
cd previa
python3 -m http.server 8123
# abrir http://localhost:8123/  (precisa de servidor HTTP: sao modulos ES)
```

- Mova o mouse: a nave segue o ponteiro.
- Role: a câmera voa de `z=8` a `z=−312`, o HUD mostra a distância até 384.400 km e a barra enche.
- Passe pelos orbes: `ORBES nn/14` + toast.
- `prefers-reduced-motion: reduce`: o WebGL não monta e o fallback declarativo permanece.

A prévia usa `data-debug="1"` para expor `window.__duScene` (somente leitura + ganchos de diagnóstico). O `theme-scene.twig` de produção **não emite** esse atributo, então o gancho fica desligado no site.

## 5. Verificar no navegador do site

Depois de aplicar e ativar:

- Deve existir **1** `<div class="theme-scene theme-scene--hero" data-scene="david-universe">` e **1** `<canvas>` dentro dele.
- O canvas deve estar `position:fixed` e cobrir a viewport.
- `ORBES`, `FASE`, `DISTÂNCIA` e a barra devem mudar ao rolar.
- Sem erros no console.

Se o canvas não aparecer, verifique `has_3d` no `common/home.php` (o patch adiciona `'david-universe' => 'three_hero_enabled'`) e se o Admin não desligou `three_hero_enabled`.

## 6. Rollback

```bash
git apply -R patch/PATCH-BO.diff
```

E desative o tema `david_universe_3d_bo` no Admin. O BN anterior continua disponível e intacto.

## 7. Limites

Não execute migration, não rode deploy, não dispare CI como parte deste pacote. Não declare release. `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` e os 10 `PENDING_DECISION` permanecem. D01–D10 não foram decididos.

Aceite visual do BO ainda **não** foi dado.

<!-- FIM: GUIA-BO.md -->


<!-- INICIO: GUIA-CORRECAO-BK.md (3132 bytes) -->

# Guia de correção — BK (stage do editor no front)

## O que fazer agora (2 passos)

### 1) Reimportar os 4 temas corrigidos

Na ordem que preferir, em **Admin → Temas do Catálog → Importar tema**:

| Arquivo | Checksum (novo) | Includes removidos |
|---|---|---|
| `docs/temas/David-Creator.reamurtheme.json` | `f2e53d07…` | 2 |
| `docs/temas/Atlas-Studio.reamurtheme.json` | `dced34ce…` | 2 |
| `docs/temas/Reamur-Reference.reamurtheme.json` | `0197e140…` | 2 |
| `docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json` | `1c6fc07a…` | 12 |

São os **mesmos temas** — cores, conteúdo, CSS, `kaz_scene` e ThreeJS
inalterados. Só saiu a linha que chamava o stage do editor. O checksum mudou
porque o `config` mudou; cada pacote foi reexportado por
`ThemePackage::export()` e revalidado por `decode()`.

> Se algum tema já estiver ativo, importar como rascunho → revisar → **Ativar**
> substitui o `common/home`. Nada é publicado automaticamente.

### 2) Aplicar o patch de runtime (recomendado)

```bash
git apply docs/correcao/correcao-runtime.patch
```

Remove 4 linhas em 3 arquivos do **Catálogo**. Sem migration, schema,
contrato ou manifesto. O Admin (prévia sandbox e comparação de temas)
continua mostrando a cena normalmente.

**Por que os dois passos?** O passo 1 resolve os temas que existem hoje.
O passo 2 fecha a torneira para **qualquer tema futuro** e para os temas
nativos do Catálogo (que usam o `common/home.twig` do sistema).

## Verificar

```bash
node tools/verify-no-scene-leak.cjs <raiz>
node tools/verify-checksums.sh
bash tools/repo-health.sh
```

Abra a home em modo anônimo e confira que **não existe** mais
`<section class="rms-scene">` antes do hero.

## O que NÃO mudou

- `kaz_scene` continua salvo em cada tema (3/3/5/24 camadas) e editável no
  KAZEditor-Theme;
- `partial/theme-scene.twig` (slot declarativo ThreeJS, só `data-*`) continua;
- `twig.php`, `theme_compare_view.php`, `theme.php` do Admin e o próprio
  `theme-scene-block.twig` **não foram tocados**;
- nenhuma migration, `schema.sql`, contrato congelado, `CHECKSUMS.txt`,
  workflow, `ThemePackage`, allowlist ou renderer;
- nenhum arquivo `.twig`/`.css`/`.php` do repositório foi escrito pelo agente.

## Sobre a segunda observação

Você notou que o **KAZEditor-Theme não está alterando coisas dos temas
inseridos**. Confirmamos: a edição grava `kaz_scene`, que é dado de
composição; ela não altera `templates`, tokens nem layout do tema, e o único
efeito no site era o bloco solto que agora foi removido. Fazer a ferramenta
editar de fato o tema exige definir um contrato novo (`kaz_scene` →
`templates`/tokens), o que muda allowlist/validação/renderer e depende de
autorização explícita do **Recorte B**. **Não foi decidido nem executado
nesta frente.**

## Governança

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Sem escrita do agente no
GitHub, sem runtime/MySQL/staging/deploy. CI verde, PHP WASM e guardas
estáticos **não significam release aprovada**; aceite visual no Admin real
ainda pendente.

<!-- FIM: GUIA-CORRECAO-BK.md -->


<!-- INICIO: INDICE-AN.md (3070 bytes) -->

# ÍNDICE — Frente AN · Padrão visual BDREditor ↔ KAZTheme-Editor

**Pacote:** `ReamurCMS-Frente-AN-Padrao-Visual-BDREditor.zip` · **2026-11-05** · **Não é release**

## Instalação

Copiar `dev/` e `docs/` sobre a instalação. `tools/`, `evidencia/` e os `.md` da raiz são apenas validação. **Limpar cache de CDN/proxy** (novo marcador `-kaz-bdre-an`). Abrir uma edição de Post (Blog) ou Página (CMS).

## Arquivos do pacote

| Caminho | Tipo | Papel |
| --- | --- | --- |
| `dev/admin/controller/blog/blog.php` | alterado | +3 linhas: registra CSS/JS aditivos |
| `dev/admin/controller/cms/page.php` | alterado | +3 linhas: registra CSS/JS aditivos |
| `dev/admin/view/css/blog-block-editor-kaz.css` | novo | tema escuro/verde, scrollbars, escala, dobras, foco |
| `dev/admin/view/js/blog-block-editor-kaz.js` | novo | barra de controles, `MutationObserver`, `__kazBdre()` |
| `docs/KAZ-BDREditor-Padrao-Visual.md` | novo | decisões, arquitetura, limites |
| `tools/kaz-bdre-visual-probe.php` | novo | PROBE somente leitura, 57 verificações |
| `tools/verify-kaz-bdre-visual.cjs` | novo | A/B Chromium, 51 verificações |
| `evidencia/controle_sem_an.png` | imagem | antes — desktop |
| `evidencia/controle_mobile.png` | imagem | antes — 390 px |
| `evidencia/an_normal.png` | imagem | depois — desktop |
| `evidencia/an_amplo.png` | imagem | escala "Amplo" |
| `evidencia/an_recolhido.png` | imagem | ferramentas recolhidas |
| `evidencia/an_expandido.png` | imagem | ferramentas expandidas |
| `evidencia/an_mobile.png` | imagem | depois — 390 px |
| `RELATORIO-FINAL-AN.md` | doc | resultados medidos e limites |
| `INDICE-AN.md` | doc | este arquivo |

## Verificação

```bash
php tools/kaz-bdre-visual-probe.php
node tools/verify-kaz-bdre-visual.cjs
```

## Uso na tela

- **ELEMENTOS** — Compacto / Normal / Confortável / Amplo (escala todo o chrome).
- **Ícones / Abas / Status** — recolhem e reexibem cada ferramenta.
- **Recolher tudo / Expandir tudo** — alterna as três de uma vez. A barra de controles permanece sempre visível.

Preferências são estado local descartável. O **canvas de conteúdo continua branco** — ele é o que será publicado.

## O que NÃO mudou

`blog-block-editor.css` (119 KB), `blog-block-editor.js` (600 KB), `blog-block-editor-init.js`, `blog-block-import.js`, `bdre-concurrency.js`, os 4 Twigs de host, o catálogo público, `schema.sql`, traduções, `theme_form.twig`, manifestos, artefatos congelados e contratos F9-14 / F9-25 / **F9-26** / F9-18 / F9-27.

Foco segue `outline: 3px solid #174ea6` com `outline-offset: 2px` (F9-26).

## Limites

Camada de apresentação, sem sessão Admin autenticada, MySQL, deploy ou CI remoto. As capturas são da **prévia local**, não do Admin em produção; seguem pendentes as 2 capturas reais desktop + 390 px, `copy(__kazHealth())` de produção, CI remoto e o veredito visual. `release_approved:false` · F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready:false`. **CI verde, PROBE e Chromium headless não significam release aprovada.**

<!-- FIM: INDICE-AN.md -->


<!-- INICIO: INDICE-AO.md (3500 bytes) -->

# ÍNDICE — Frente AO · Tema TOTAL do BDREditor ↔ KAZTheme-Editor

**Pacote:** `ReamurCMS-Frente-AO-Tema-Total-BDREditor.zip` · **2026-11-06** · **Não é release**

## Instalação

A AN precisa estar aplicada (está no HEAD `1b6ffd97…`). Copiar `dev/` e `docs/` sobre a instalação; `tools/`, `evidencia/` e os `.md` da raiz são apenas validação. **Limpar cache de CDN/proxy** (novo marcador `-kaz-bdre-ao`). Abrir uma edição de Post (Blog) ou Página (CMS): a página inteira deve escurecer, mantendo o canvas branco e a barra `ELEMENTOS` da AN.

## Arquivos do pacote

| Caminho | Tipo | Papel |
| --- | --- | --- |
| `dev/admin/controller/blog/blog.php` | alterado | +4 linhas: `addStyle` do CSS AO depois da AN |
| `dev/admin/controller/cms/page.php` | alterado | +4 linhas: idem |
| `dev/admin/view/css/blog-block-editor-kaz-total.css` | **novo** | tokens `--bs-*`/`--kaz-*`, superfícies Admin, estados sob demanda, `<html>`, scrollbars, foco, canvas |
| `docs/KAZ-BDREditor-Tema-Total.md` | novo | diagnóstico, arquitetura, limites |
| `tools/kaz-bdre-total-probe.php` | novo | PROBE somente leitura, 52 verificações |
| `tools/verify-kaz-bdre-total.cjs` | novo | A/B Chromium, 46 verificações |
| `evidencia/controle_sem_ao.png` | imagem | antes — Admin com AN, desktop |
| `evidencia/ao_admin.png` | imagem | depois — desktop |
| `evidencia/ao_amplo.png` | imagem | escala "Amplo" (não-regressão AN) |
| `evidencia/ao_recolhido.png` | imagem | dobras (não-regressão AN) |
| `evidencia/ao_mobile.png` | imagem | depois — 390 px |
| `RELATORIO-FINAL-AO.md` | doc | resultados medidos |
| `INDICE-AO.md` | doc | este arquivo |

## Verificação

```bash
php tools/kaz-bdre-total-probe.php
node tools/verify-kaz-bdre-total.cjs
```

## O que a AO cobre

- **Página Admin inteira** (navbar, breadcrumb, título, alertas, cards, abas de idioma, campos, botões, badges, sidebar de publicação) **nas telas que hospedam o BDREditor**.
- **Todos os estados sob demanda**: command palette e overlays criados em `document.body`, biblioteca de padrões, inserter, prévias, tabelas, agrupamento, fullscreen, arraste, seleção, sidebar/rail/inspector do editor.
- **Sempre escuro KAZ**, mesmo com `data-bs-theme="light"` e skin clara — a preferência do usuário não é alterada.
- O **canvas de conteúdo continua branco** (é o que será publicado).

## O que NÃO mudou

Nenhum JavaScript novo ou alterado; nenhum DOM, Twig, `Document`, renderer, documento de blocos, catálogo público, `schema.sql`, tradução, manifesto, artefato congelado ou contrato. `blog-block-editor.css` (119 KB), `blog-block-editor.js` (600 KB), o CSS e o JS da AN, `reamurcms.css`, `bootstrap.css`, as skins e `admin.css` seguem intactos. Foco F9-26: `outline: 3px solid #174ea6`, `outline-offset: 2px`.

## Compatibilidade

`:is(body:has(...), html[data-kaz-bdre-tema="1"] body)`. `:has()` é reavaliado quando o init monta o host e cobre overlays posteriores; Baseline 2023 (Chrome 105+, Safari 15.4+, Firefox 121+). Sem `:has()`, **a AN continua funcionando** e a página volta ao tema do Admin — sem perda funcional.

## Limites

Prévia local sem sessão autenticada, MySQL, deploy ou CI remoto. Seguem pendentes as 2 capturas reais desktop + 390 px, `copy(__kazHealth())` de produção, CI remoto e o veredito visual. `release_approved:false` · F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready:false`. **CI verde, PROBE e Chromium headless não significam release aprovada.**

<!-- FIM: INDICE-AO.md -->


<!-- INICIO: INDICE-AP.md (2627 bytes) -->

# Índice — Frente AP (correção de escopo da AO)

Pacote: `ReamurCMS-Frente-AP-Escopo-Corrigido-BDREditor.zip`  
Base: `9c809050ff908b66b6f3bf97c1fc5a1aed9b3160` · 2026-11-06

| # | Entrada no ZIP | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AP.md` | Diagnóstico, A/B/C, validação, limites e aceitação |
| 2 | `INDICE-AP.md` | Este índice |
| 3 | `dev/admin/controller/blog/blog.php` | Marcador `-kaz-bdre-ap` (substitui o da AO) |
| 4 | `dev/admin/controller/cms/page.php` | Marcador `-kaz-bdre-ap` (substitui o da AO) |
| 5 | `dev/admin/view/css/blog-block-editor-kaz-total.css` | **CSS recolhido**: somente `[data-blog-block-editor]` + overlays `rb-*` |
| 6 | `docs/KAZ-BDREditor-Tema-Total.md` | Documentação corrigida (causa `--bs-primary`, escopo, aplicação) |
| 7 | `docs/Registro-Visual-AP.md` | Critério visual: `image-9` reprovação, `image-10` referência |
| 8 | `tools/kaz-bdre-total-probe.php` | PROBE de **não-vazamento** (53 asserções) |
| 9 | `tools/verify-kaz-bdre-total.cjs` | Verificador A/B/C (41 asserções, Chromium) |
| 10 | `evidencia/ap_reprovacao_image-9.png` | Admin real após a AO — **reprovação** |
| 11 | `evidencia/ap_referencia_image-10.png` | KAZTheme-Editor — **referência** |
| 12 | `evidencia/ap_admin_nativo.png` | A/B — Admin nativo + AN (sidebar azul) |
| 13 | `evidencia/ap_admin_ao.png` | A/B — AO reprovada (sidebar verde, página escura) |
| 14 | `evidencia/ap_admin_ap.png` | A/B — AP corrigida (Admin azul, editor escuro) |
| 15 | `evidencia/ap_mobile.png` | AP a 390 px (sem overflow) |

## Como aplicar

Copiar os 9 arquivos de código/docs/evidência do pacote **sobre** os caminhos
correspondentes do repositório (6 substituições + 3 novos). Não é necessário
remover nenhum arquivo: os caminhos da AO são reutilizados. O marcador
`-kaz-bdre-ap` dos dois controllers invalida o cache do navegador.

## Como validar localmente

```bash
AP_ROOT=<raiz> AP_OUT=/tmp/ap_site \
  php-wasm-cli tools/kaz-bdre-total-probe.php
AP_URL=http://127.0.0.1:8123/ap/ AP_SHOTS=/tmp/ap_shots \
  node tools/verify-kaz-bdre-total.cjs
```

O verificador espera a prévia A/B/C (`admin_nativo.html`, `admin_ao.html`,
`admin_ap.html`) servida com `bootstrap.css`, `reamurcms.css`,
`themes/light.css`, a AN e o markup real de `#menu > li.active > a`. O estado
com a AO antiga existe para provar que a asserção **detecta** o defeito.

## Aceitação

Veredito visual do usuário no Admin real. PROBE / Chromium headless / CI /
health local **não significam release aprovada**. `image-9.png` é a reprovação
vigente; `image-10.png` é a referência.

<!-- FIM: INDICE-AP.md -->


<!-- INICIO: INDICE-AQ.md (1612 bytes) -->

# Índice — Frente AQ (Roteiro dos próximos passos)

Pacote: `ReamurCMS-Frente-AQ-Roteiro-Proximos-Passos.zip` · HEAD `179269fe…`

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AQ.md` | Objetivo, estado confirmado, correção do `kazHealth`, validação, limites |
| 2 | `INDICE-AQ.md` | Este índice |
| 3 | `Roteiro-Proximos-Passos-AP.md` | Roteiro principal: passos 1–3, dependências, o que não fazer |
| 4 | `Protocolo-Aceitacao-Visual-AP.md` | Critério vigente; AP aplicada tecnicamente, veredito pendente |
| 5 | `docs/Extrato-Contratos-HEAD-179269fe.md` | Transcrição dos contratos e do CI lidos do remoto |
| 6 | `tools/health-admin-ap.js` | Health observacional somente leitura (substitui `__kazHealth()`) |
| 7 | `evidencia/ap_admin_nativo.png` | A/B — Admin nativo + AN (sidebar azul) |
| 8 | `evidencia/ap_admin_ao.png` | A/B — AO reprovada (sidebar verde, página escura) |
| 9 | `evidencia/ap_admin_ap.png` | A/B — AP corrigida (Admin azul, BDREditor escuro) |
| 10 | `evidencia/ap_mobile.png` | AP a 390 px, sem overflow |

## Como usar

1. Ler `Roteiro-Proximos-Passos-AP.md`.
2. Executar o **passo 1** no Admin real e registrar o veredito.
3. No passo 2, colar `tools/health-admin-ap.js` no console do DevTools e
   conferir o CI do commit `179269fe…` em Actions.
4. Para a pauta de decisões, usar o pacote **AR**; para os achados P1, o
   pacote **AS**.

## Limites

Planejamento. Não executa runtime, não altera contrato, não escreve no
GitHub e **não significa release aprovada**. O veredito visual do usuário
continua sendo o critério vigente.

<!-- FIM: INDICE-AQ.md -->


<!-- INICIO: INDICE-AT.md (1699 bytes) -->

# Índice — Frente AT (avanço do passo 2)

Pacote: `ReamurCMS-Frente-AT-Avanco-Passo-2-CI.zip` · HEAD `bfe9eec7…`

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AT.md` | Motivo, resultados, pendências, limites |
| 2 | `INDICE-AT.md` | Este índice |
| 3 | `Roteiro-Proximos-Passos-AP.md` | **Substitui** a versão da AQ: novo HEAD, CI 7/7, passo 2 parcialmente concluído |
| 4 | `Protocolo-Aceitacao-Visual-AP.md` | **Substitui** a versão da AQ: AQ aplicada, CI verificado, health pendente |
| 5 | `docs/Registro-CI-Remoto-HEAD-bfe9eec7.md` | Evidência de CI, confirmação da aplicação da AQ, ressalva do contrato |
| 6 | `evidencia/ci-remoto-bfe9eec7.json` | Dados estruturados: runs, 7+7 check-runs, endpoints, limites |
| 7 | `tools/verify-ci-evidencia.cjs` | Verificador Node puro da evidência |

## Como aplicar

Copiar os 7 arquivos sobre os caminhos correspondentes: **2 substituições**
(roteiro e protocolo) + **5 novos**. Nenhum arquivo de código, contrato,
manifesto, workflow ou evidência PNG é alterado.

```bash
node tools/verify-ci-evidencia.cjs <raiz>
```

## Estado dos passos após a AT

| Passo | Estado |
|---|---|
| 1 — Veredito visual da AP | ⏳ depende do usuário |
| 2 — CI remoto | ✅ `42217429359` · 7/7 success |
| 2 — Health do Admin real | ⏳ colar `tools/health-admin-ap.js` no console |
| 3 — Decisões D01–D10 | ⏳ pacote AR pronto, não aplicado |
| 4 — F918 / backend | ⏳ pacote AS pronto, não aplicado |

## Limites

Registro de avanço. CI verde **não significa release aprovada**, não
substitui o veredito visual e não converte `NOT_RUN`/`PENDING_DECISION` em
`PASS`. Sem escrita do agente no GitHub.

<!-- FIM: INDICE-AT.md -->


<!-- INICIO: INDICE-AU.md (1721 bytes) -->

# Índice — Frente AU (veredito APROVADO)

Pacote: `ReamurCMS-Frente-AU-Registro-Veredito-Aprovado.zip` · HEAD `1b7724a1…`

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AU.md` | O que aconteceu, o que mudou, **o que não mudou**, validação, próximos passos |
| 2 | `INDICE-AU.md` | Este índice |
| 3 | `Registro-Veredito-Visual-AP.md` | **Novo** — decisão `APROVADO`, decidido por, data, HEAD, efeitos, limites |
| 4 | `Protocolo-Aceitacao-Visual-AP.md` | **Substitui** — AP aprovada, `image-9` superada, estado dos passos |
| 5 | `Roteiro-Proximos-Passos-AP.md` | **Substitui** — passo 1 concluído, dependências atualizadas |
| 6 | `Pauta-Decisoes-D01-D10.md` | **Nova** — pauta de 1 página para o passo 3, ordem de votação |
| 7 | `tools/verify-veredito.cjs` | Verificador Node puro do registro |

## Como aplicar

**3 substituições** (protocolo, roteiro e — se já existir — registro) +
**4 novos**. Nenhum arquivo de código, contrato, manifesto, workflow ou
evidência PNG é alterado.

```bash
node tools/verify-veredito.cjs <raiz>
```

## O que este pacote NÃO faz

Não promove `release_approved` (segue `false`), não converte `PENDING`/`NOT_RUN`/
`BLOCKED_NOT_RUN` em `PASS`, não decide D01–D10, não altera F9-27/F9-18,
não escreve no GitHub pelo agente e não declara release.

**Aprovação visual ≠ release.** `release_approved: false` ·
F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready: false` ·
10 `PENDING_DECISION`.

## Próximos passos (dependem do usuário)

Colar o health no console (2) · aplicar **AR** e decidir D01–D10 (3) ·
aplicar **AS** e autorizar staging (4). AR e AS estão prontos em
`entregas/`, sem conflito com o HEAD atual.

<!-- FIM: INDICE-AU.md -->


<!-- INICIO: INDICE-AV.md (2311 bytes) -->

# Índice — Frente AV (diagnóstico e restauração do CI)

Pacote: `ReamurCMS-Frente-AV-Restauracao-CI.zip` · HEAD `accb6257…`

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AV.md` | Por que esta frente, achados, correção verificada, limites |
| 2 | `INDICE-AV.md` | Este índice |
| 3 | `docs/Diagnostico-CI-Vermelho-accb6257.md` | Causa raiz dos 2 checks vermelhos, prova, Via A / Via B |
| 4 | `evidencia/lista-arquivos-removidos.txt` | 41 paths removidos no commit `accb6257…` |
| 5 | `evidencia/sha256-restaurados.txt` | SHA-256 de cada arquivo restaurado |
| 6 | `evidencia/guardas-antes.txt` | `repo-health` antes: 5 PASS / 1 FAIL / REPROVADO |
| 7 | `evidencia/guardas-depois.txt` | `repo-health` depois: 6 PASS / 0 FAIL / COERENTE |
| 8 | `tools/verify-restauracao.cjs` | Verificador Node puro |
| 9–49 | **41 arquivos restaurados** | 14 `CHECKSUMS*.txt`, 15 `F11-*-INDICE.md`, 12 relatórios/índices/`CONTRIBUTING.md` |

## Como aplicar (Via A — recomendada)

Copiar os **41 arquivos** de volta aos paths originais. Não é necessário
alterar `docs/README.md`, `.github/workflows/php.yml`, nenhum guarda, nenhum
contrato e nenhum hash: os manifestos restaurados conferem com os arquivos
restaurados.

```bash
node tools/verify-restauracao.cjs <raiz>
bash tools/verify-checksums.sh
bash tools/repo-health.sh
```

Depois conferir o novo run em **Actions** — a validação aqui é local.

## Resultado esperado (medido)

```
verify-checksums.sh : rc=0 · 14 manifestos · 142 arquivos · 0 divergentes
repo-health.sh      : rc=0 · 6 PASS / 0 FAIL / 0 SKIP · COERENTE
links Markdown      : 0 quebrados
```

## O que NÃO fazer sem decisão explícita

Manter a limpeza exigiria fazer `verify-checksums.sh` **tolerar zero
manifestos**, o que desliga uma verificação de integridade. Essa via **não**
foi preparada por este pacote.

## Limites

Simulação local ≠ CI remoto verde. Não escreve no GitHub pelo agente, não
altera contrato, não converte status em `PASS`, não decide D01–D10 e **não
significa release aprovada**. `release_approved: false` · F9-27
`BLOCKED_NOT_RUN` · F9-18 `release_ready: false`. O veredito **APROVADO** da
AP permanece válido.

**Pacotes prontos e sem conflito:** `AR` (modelo D01–D10) e `AS`
(verificação F918-01/02).

<!-- FIM: INDICE-AV.md -->


<!-- INICIO: INDICE-AX.md (1578 bytes) -->

# Índice — Frente AX (plano das Ferramentas internas)

Pacote: `ReamurCMS-Frente-AX-Plano-Ferramentas-Internas.zip` · HEAD `481b6902…` · CI 7/7 ✅

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AX.md` | Contexto, levantamento, decisões, inventário, por que não há código ainda |
| 2 | `INDICE-AX.md` | Este índice |
| 3 | `Plano-Frente-AX.md` | **Principal** — arquitetura V2/P2, JSON de metadados, fases B1–B4, pendências |
| 4 | `Inventario-Rename-Global.md` | 179 arquivos / 762 ocorrências, categorias e pontos sensíveis |
| 5 | `Analise-Viabilidade-Ferramentas-Internas.md` | Como a página Ferramentas funciona, schema real, 3 opções de versão |
| 6 | `evidencia/inventario-rename.json` | Dados brutos por arquivo |
| 7 | `tools/verify-inventario.cjs` | Verificador Node puro |

## Decisões registradas

| Item | Escolha |
|---|---|
| Nomes | `BDREditor-Content` · **`KAZEditor-Theme`** |
| Rename | **R2 — global** |
| Versão | **V2 — JSON versionado**, sem migration |
| Local | **P2 — seção "Ferramentas internas" só no Admin** |

## O que falta (do usuário)

1. Versões iniciais das duas ferramentas.
2. R2 = B1+B2 (texto/docs) **ou** B1+B2+B3 (também identificadores de código).

## O que este pacote NÃO é

Não é código aplicável, não renomeia nada, não regenera manifesto, não faz
migration, não altera contrato congelado, não escreve no GitHub e **não
significa release aprovada**. `release_approved: false` · F9-27
`BLOCKED_NOT_RUN` · F9-18 `release_ready: false` · 10 `PENDING_DECISION`.

<!-- FIM: INDICE-AX.md -->


<!-- INICIO: INDICE-AY.md (2531 bytes) -->

# Índice — Frente AY (Parte A: Ferramentas internas no Admin)

Pacote: `ReamurCMS-Frente-AY-Ferramentas-Internas.zip` · HEAD `a73341af…` · CI 7/7 ✅
**Código aplicável** · **8 alvos**, nenhum em manifesto `CHECKSUMS`.

| # | Entrada | Ação | Conteúdo |
|---|---|---|---|
| 1 | `RELATORIO-FINAL-AY.md` | novo | Implementação, validação, limites, pendências |
| 2 | `INDICE-AY.md` | novo | Este índice |
| 3 | `dev/admin/model/tool/internal-tools.json` | **novo** | Metadados das 2 ferramentas (`PENDENTE_USUARIO`) |
| 4 | `dev/admin/controller/tool/tool.php` | substitui | `internalTools()` fail-closed + 1 linha no `index()` |
| 5 | `dev/admin/view/template/tool/tool_list.twig` | substitui | `<section class="internal-tools-panel">` |
| 6 | `dev/admin/view/css/admin.css` | substitui | Adendo `.internal-tools-*`, prefixo intacto |
| 7 | `dev/admin/language/en-us/tool/tool.php` | substitui | 8 rótulos EN |
| 8 | `dev/admin/language/pt-br/tool/tool.php` | substitui | 8 rótulos PT-BR |
| 9 | `dev/admin/language/es-eu/tool/tool.php` | substitui | 8 rótulos ES-EU |
| 10 | `tools/verify-internal-tools.cjs` | **novo** | Verificador Node puro |
| 11 | `evidencia/php-lint.txt` | novo | `php -l` PHP 8.5.12 WASM, 4/4 |
| 12 | `evidencia/wasm-harness.txt` | novo | 11/11 cenários fail-closed |
| 13 | `evidencia/guardas.txt` | novo | `repo-health` 6 PASS / 0 FAIL |

## Como aplicar

**3 novos + 4 substituições de código + 3 de idioma.** Nenhum manifesto,
contrato, hash, `schema.sql`, workflow ou arquivo de `dev/install/` precisa
ser tocado.

```bash
node tools/verify-internal-tools.cjs <raiz>
bash tools/repo-health.sh
```

Depois conferir o novo run em **Actions** e a seção no Admin real.

## Resultado medido (réplica fiel, 2.667 blobs)

`php -l` **4/4** · harness WASM **11/11** · `verify-checksums` **142 arquivos /
0 divergentes** · `verify-contract-freeze` **COERENTE** · `repo-health`
**6 PASS / 0 FAIL / COERENTE** · Twig balanceado · **0 `|raw`** na seção nova.

## O que este pacote NÃO faz

Não aplica o **rename R2** (Parte B: 179 arquivos / 762 ocorrências), não faz
migration, não altera contrato congelado, não decide D01–D10, não escreve no
GitHub pelo agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION` · veredito **APROVADO** da AP
mantido.

## Pendente do usuário

Versões iniciais das duas ferramentas · R2 inclui B3? · aceite visual no Admin
real.

<!-- FIM: INDICE-AY.md -->


<!-- INICIO: INDICE-BA.md (1827 bytes) -->

# Índice — Frente BA (versões iniciais 1.0.0)

Pacote: `ReamurCMS-Frente-BA-Versoes-Iniciais-1.0.0.zip` · HEAD `99717380…` · CI 7/7 ✅
**2 arquivos alterados**, nenhum em manifesto `CHECKSUMS`.

| # | Entrada | Ação | Conteúdo |
|---|---|---|---|
| 1 | `RELATORIO-FINAL-BA.md` | novo | Decisão, validação, limites, pendências |
| 2 | `INDICE-BA.md` | novo | Este índice |
| 3 | `dev/admin/model/tool/internal-tools.json` | **substitui** | `1.0.0` + `2026-11-06` para ambas |
| 4 | `tools/verify-internal-tools.cjs` | **substitui** | Asserção de versão atualizada |
| 5 | `evidencia/validacao.txt` | novo | Harness WASM + verificador + guardas |

## Decisão

| Ferramenta | Versão | Atualizado em |
|---|---|---|
| `BDREditor-Content` | **1.0.0** | 2026-11-06 |
| `KAZEditor-Theme` | **1.0.0** | 2026-11-06 |

`notes: []` — nenhum histórico inventado; a UI mostra "Nenhuma atualização
registrada.".

## Como aplicar

Copiar os **2 arquivos** substituindo os da Frente AY. Nenhum PHP, Twig, CSS,
idioma, manifesto, contrato ou workflow precisa ser tocado.

```bash
node tools/verify-internal-tools.cjs <raiz>
bash tools/repo-health.sh
```

## Validação medida

Harness WASM: 2 itens com `version="1.0.0"` / `updated="2026-11-06"` ·
verificador 0 FALHAS · `verify-checksums` 142 arquivos / 0 divergentes ·
`repo-health` **6 PASS / 0 FAIL / COERENTE**.

## O que NÃO faz

Não aplica o **rename R2** (179 arquivos / 762 ocorrências), não faz
migration, não altera contrato/hash, não decide D01–D10, não escreve no
GitHub pelo agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`.

## Pendente do usuário

Alcance do R2 (B1 / B1+B2 / B1+B2+B3) · aceite visual da seção no Admin real.

<!-- FIM: INDICE-BA.md -->


<!-- INICIO: INDICE-BB.md (2096 bytes) -->

# Índice — Frente BB (rename R2 · Fase B1)

Pacote: `ReamurCMS-Frente-BB-Rename-B1-UI.zip` · HEAD `700d104c…` · CI 7/7 ✅
**9 arquivos de UI do Admin substituídos** · **66 → 0** ocorrências dos nomes antigos.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BB.md` | Decisão, invariantes, o que foi preservado, validação, pendências |
| 2 | `INDICE-BB.md` | Este índice |
| 3–11 | **9 arquivos de UI** | `design/kaz_theme_editor.twig`, `cms/page_form.twig`, `cms/page_content_form.twig`, `language/{en-us,pt-br,es-eu}/{cms/page,blog/blog}.php` |
| 12 | `tools/verify-rename-b1.cjs` | Verificador Node puro |
| 13 | `evidencia/invariantes.json` | Contagens originais de chaves/classes/ids/`|raw` por arquivo |
| 14 | `evidencia/validacao.txt` | `php -l` 6/6 + guardas |

## Mapeamento

| De | Para |
|---|---|
| `BDREditor` | `BDREditor-Content` |
| `KAZTheme-Editor` | `KAZEditor-Theme` |
| `KAZTheme` (isolado) | `KAZEditor-Theme` |

## Como aplicar

**9 substituições.** Nenhum manifesto, contrato, hash, `schema.sql`, workflow
ou arquivo de `dev/install/` precisa ser tocado.

```bash
node tools/verify-rename-b1.cjs <raiz>
bash tools/repo-health.sh
```

## Preservado (B2/B3 não rodaram)

Documentos históricos F9/F11/ADRs · contratos `BDREditor-Table-*` · nomes de
arquivos e identificadores (`blog-block-editor-kaz.js`, `kaz-theme-editor.js`)
· 14 manifestos · `decision-matrix.json` · catálogo público · schema e
workflows. Restam **113 ocorrências** para B2+B3.

## Validação medida

`php -l` **6/6** · Twig balanceado **3/3** · invariantes **9/9** ·
`verify-checksums` **142 arquivos / 0 divergentes** · `repo-health`
**6 PASS / 0 FAIL / COERENTE** · `verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não decide B2/B3, não faz migration, não altera contrato/hash, não decide
D01–D10, não escreve no GitHub pelo agente e **não significa release
aprovada**. Aceite visual no Admin real segue pendente.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`.

<!-- FIM: INDICE-BB.md -->


<!-- INICIO: INDICE-BC.md (2494 bytes) -->

# Índice — Frente BC (KAZEditor-Theme · import por arquivo, recorte A)

Pacote: `ReamurCMS-Frente-BC-Import-Tema-Arquivo.zip` · HEAD `df0d3d34…` · CI 7/7 ✅
**6 arquivos alterados** · **0** em manifesto `CHECKSUMS` · **backend intacto**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BC.md` | Levantamento, implementação, validação, limites |
| 2 | `INDICE-BC.md` | Este índice |
| 3 | `dev/admin/view/js/theme-package.js` | Drag-and-drop, prévia, validação fail-closed no client |
| 4 | `dev/admin/view/template/design/kaz_theme_editor.twig` | Zona de drop + `<dl>` de prévia + status ARIA |
| 5 | `dev/admin/controller/design/theme.php` | **1 linha**: injeta as 10 chaves de idioma |
| 6 | `dev/admin/language/en-us/design/theme.php` | 10 rótulos EN |
| 7 | `dev/admin/language/pt-br/design/theme.php` | 10 rótulos PT-BR |
| 8 | `dev/admin/language/es-eu/design/theme.php` | 10 rótulos ES-EU |
| 9 | `tools/verify-theme-import-file.cjs` | Verificador Node puro |
| 10 | `evidencia/jsdom-harness.txt` | 31 OK / 0 FALHAS |
| 11 | `evidencia/validacao.txt` | `php -l` 4/4 + guardas |

## Decisões

- **Recorte A** — import por arquivo, modelo seguro.
- **Twig/ThreeJS continuam DADOS validados** (allowlist + checksum), sem
  código executável.

## Contrato preservado

O POST segue `theme_package` (string) → `design/theme.import`. ACL `modify`,
CSRF e `ThemePackage::decode()` (checksum `hash_equals`, allowlists) **não
mudaram**. Import continua **sempre como rascunho**, nunca publica.

## Como aplicar

**6 substituições.** Nenhum manifesto, contrato, hash, schema, workflow,
`TEMPLATE_KEYS` ou biblioteca precisa ser tocado.

```bash
node tools/verify-theme-import-file.cjs <raiz>
bash tools/repo-health.sh
```

## Validação medida

`node --check` ✅ · `php -l` **4/4** · **jsdom 31 OK / 0 FALHAS** ·
Twig balanceado · verificador 0 FALHAS · `verify-checksums` 142 arquivos /
0 divergentes · `repo-health` **6 PASS / 0 FAIL / COERENTE** ·
`verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não aceita ZIP com `.twig`/`.js`, não amplia allowlist, não escreve arquivo no
servidor, não faz migration, não altera contrato congelado, não decide
D01–D10, não escreve no GitHub pelo agente e **não significa release
aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health do Admin real ainda
pendente** (guia aplicado, JSON não colado).

<!-- FIM: INDICE-BC.md -->


<!-- INICIO: INDICE-BE.md (2788 bytes) -->

# Índice — Frente BE (CRUD de temas + Importar tema)

Pacote: `ReamurCMS-Frente-BE-CRUD-Temas-Importar.zip` · HEAD `7646c984…` · CI 7/7 ✅
**6 arquivos alterados** · **0** em `CHECKSUMS*.txt` · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BE.md` | `image-11.png`, levantamento, implementação, segurança, validação |
| 2 | `INDICE-BE.md` | Este índice |
| 3 | `dev/admin/view/template/design/theme_list.twig` | Botão Importar tema, card de import, coluna Ações, Ativar/Desativar/Excluir |
| 4 | `dev/admin/controller/design/theme.php` | `index()` + novas rotas `deactivate()`/`delete()` + redirect do `import()` |
| 5 | `dev/admin/model/design/theme.php` | `deactivate()` e `delete()` fail-closed |
| 6 | `dev/admin/language/en-us/design/theme.php` | 14 chaves EN |
| 7 | `dev/admin/language/pt-br/design/theme.php` | 14 chaves PT-BR |
| 8 | `dev/admin/language/es-eu/design/theme.php` | 14 chaves ES-EU |
| 9 | `tools/verify-theme-crud.cjs` | Verificador Node puro |
| 10 | `evidencia/model-harness.txt` | 20 OK / 0 FALHAS (PHP WASM + DB falso) |
| 11 | `evidencia/validacao.txt` | `php -l` 5/5 + guardas |

## O que muda na tela

| Antes (`image-11.png`) | Depois |
|---|---|
| `Novo tema` | `Novo tema` + **`Importar tema`** |
| só `Editar` | **Editar · Ativar/Desativar · Excluir** |
| header vazio | coluna **Ações** + notices de sucesso/erro |

## Decisões de segurança

- **Não exclui tema ativo** — precisa desativar antes (fail-closed);
- **Exclusão em 2 etapas** via `<details>`, sem JS inline;
- **POST + ACL `modify` + CSRF** em todas as mutações;
- **desativar preserva** `config`/`published_config`, só zera `status`;
- import continua **sempre como rascunho**; `ThemePackage::decode()` intacto;
- sem `\|raw` novo, `<script>`, `onclick`, `ALTER`, `CREATE`, `DROP`.

## Como aplicar

**6 substituições.** Nenhum manifesto, contrato, hash, schema, workflow ou
biblioteca precisa ser tocado. O `theme-package.js` **já está no HEAD** (BC) e
não é reenviado.

```bash
node tools/verify-theme-crud.cjs <raiz>
bash tools/repo-health.sh
```

## Validação medida

`php -l` **5/5** · **model harness 20 OK / 0 FALHAS** · Twig balanceado ·
verificador 0 FALHAS · `verify-checksums` 142 arquivos / 0 divergentes ·
`repo-health` **6 PASS / 0 FAIL / COERENTE** · `verify-contract-freeze`
**COERENTE**.

## O que NÃO faz

Não aceita ZIP com `.twig`/`.js`, não amplia allowlist, não faz migration, não
altera contrato congelado, não decide D01–D10, não escreve no GitHub pelo
agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health do Admin real e
aceites visuais ainda pendentes.**

<!-- FIM: INDICE-BE.md -->


<!-- INICIO: INDICE-BF.md (2851 bytes) -->

# Índice — Frente BF (correção do botão + Gerenciador de Temas)

Pacote: `ReamurCMS-Frente-BF-Gerenciador-Temas.zip` · HEAD `6ee26372…` · CI 7/7 ✅
**6 arquivos alterados** · **0** em `CHECKSUMS*.txt` · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BF.md` | Causa raiz do bug, gerenciador, segurança, validação |
| 2 | `INDICE-BF.md` | Este índice |
| 3 | `dev/admin/view/js/theme-package.js` | Evento real no botão + inspeção Twig/ThreeJS |
| 4 | `dev/admin/view/template/design/theme_list.twig` | Botão `data-theme-import-open`, cartões, prévia enriquecida |
| 5 | `dev/admin/controller/design/theme.php` | `index()` injeta allowlists e contagens |
| 6 | `dev/admin/language/en-us/design/theme.php` | 25 rótulos EN |
| 7 | `dev/admin/language/pt-br/design/theme.php` | 25 rótulos PT-BR |
| 8 | `dev/admin/language/es-eu/design/theme.php` | 25 rótulos ES-EU |
| 9 | `tools/verify-theme-manager.cjs` | Verificador Node puro |
| 10 | `evidencia/jsdom-harness.txt` | 47 OK / 0 FALHAS |
| 11 | `evidencia/validacao.txt` | `php -l` 4/4 + guardas |

## O bug

`href="#theme-import"` → `<details>` **fechado** = nenhum evento visível.
Agora: `data-theme-import-open` + `panel.open = true` + foco na textarea,
com `href` mantido como fallback sem JS.

## Gerenciador

| Capacidade | Conteúdo |
|---|---|
| **Pacotes Twig** | 25 fontes allowlistadas como strings validadas |
| **Pacotes ThreeJS** | 5 cenas + 6 presets + `kaz_scene` como dados |
| **Prévia Twig** | nº de fontes, lista de chaves, status allowlist |
| **Prévia ThreeJS** | cena, slot, camadas/versão, tipos, status allowlist |
| **Demais blocos** | tokens, condições, seções, componentes, schedule, mídia, sites |

Allowlists injetadas pelo servidor (`template_keys_list`, `scene_list`).
O cliente **sinaliza**, o `ThemePackage::decode()` **decide**.

## Como aplicar

**6 substituições.** Nenhum manifesto, contrato, hash, schema, workflow,
model ou biblioteca precisa ser tocado.

```bash
node tools/verify-theme-manager.cjs <raiz>
bash tools/repo-health.sh
```

## Validação medida

`node --check` ✅ · `php -l` **4/4** · **jsdom 47 OK / 0 FALHAS**
(reprodução do bug → clique abre) · verificador 0 FALHAS ·
`verify-checksums` 142 arquivos / 0 divergentes · `repo-health`
**6 PASS / 0 FAIL / COERENTE** · `verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não cria pacotes com `.twig`/`.js` executáveis, não faz upload de ZIP, não
escreve em disco, não amplia `TEMPLATE_KEYS`, não faz migration, não altera
contrato congelado, não decide D01–D10, não escreve no GitHub pelo agente e
**não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health e aceites visuais
ainda pendentes.**

<!-- FIM: INDICE-BF.md -->


<!-- INICIO: INDICE-BG.md (2426 bytes) -->

# Índice — Frente BG (tema de referência em Twig)

Pacote: `ReamurCMS-Frente-BG-Tema-Referencia-Twig.zip` · HEAD `11b98aac…` · CI 7/7 ✅
**1 pacote importável + documentação e verificador** · **nenhum código alterado** · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BG.md` | Construção, validação real, segurança, limites |
| 2 | `INDICE-BG.md` | Este índice |
| 3 | **`docs/temas/Reamur-Reference.reamurtheme.json`** | **O tema**: 25 fontes Twig + ThreeJS, 26.226 bytes |
| 4 | `docs/temas/GUIA-TEMA-REFERENCIA.md` | Como importar, o que contém, como estender |
| 5 | `tools/verify-reference-theme.cjs` | Verificador Node puro (checksum independente) |
| 6 | `evidencia/theme-package-decode.txt` | `validateConfig()`/`decode()` reais em PHP WASM |
| 7 | `evidencia/validacao.txt` | Guardas na réplica |

## O tema

| Campo | Valor |
|---|---|
| `name` / `slug` | `Reamur Reference` / `reamur_reference` |
| `format` / `version` | `reamurcms-theme` / `1` |
| `checksum` | `07b6b2d294681e01ccec14cc0a02e3e50feeb72d8a2b0b0e4453e90853a444d4` |
| `templates` | **25/25** allowlistadas, ~19,8 KB |
| `three` | `hero-particles` / `hero` / 400 / `#1d4ed8` / 0.05 |
| `kaz_scene` | v1, 960×540, 5 camadas |
| `tokens` | 22 tokens AA |
| `parent` | vazio (tema base / child-themes) |

## Como usar

Admin → **Temas do Catálog** → **Importar tema** → escolher o `.json` →
conferir a prévia Twig/ThreeJS → **Importar como rascunho** → revisar →
**Ativar**. Nada é publicado automaticamente.

## Validação medida

`validateConfig()` **0 erros** · **`decode()` ok=true** com as classes reais
com `export()` (PHP 8.5.12 WASM) · **checksum idêntico** em Python e Node ·
25 fontes sem conteúdo proibido / `|raw` / `<script>` / CDN · Twig
balanceado 25/25 · verificador 0 FALHAS · `verify-checksums` 142/0 ·
`repo-health` **6 PASS / 0 FAIL** · `verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não instala o tema automaticamente, não cria arquivos `.twig`/`.js`, não faz
upload em disco, não altera `TEMPLATE_KEYS`, controller, model, schema,
contrato, hash, manifesto ou workflow, não decide D01–D10, não escreve no
GitHub pelo agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health e aceites visuais
ainda pendentes.**

<!-- FIM: INDICE-BG.md -->


<!-- INICIO: INDICE-BH.md (2799 bytes) -->

# Índice — Frente BH (tema de referência ThreeJS)

Pacote: `ReamurCMS-Frente-BH-Tema-Referencia-ThreeJS.zip` · HEAD `dc7d2a96…` · CI 7/7 ✅
**1 pacote importável + docs + verificador** · **nenhum código alterado** · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BH.md` | Dois mecanismos, 6 presets, validação real, limites |
| 2 | `INDICE-BH.md` | Este índice |
| 3 | **`docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json`** | **O tema 3D**: 42.665 bytes |
| 4 | `docs/temas/GUIA-TEMA-THREEJS-REFERENCIA.md` | Como importar, presets, slots, como estender |
| 5 | `tools/verify-threejs-reference.cjs` | Verificador Node puro (checksum + presets + cena) |
| 6 | `evidencia/threejs-decode-harness.txt` | 52 OK / 0 FALHAS com classes reais |
| 7 | `evidencia/validacao.txt` | Guardas na réplica |

## O tema

| Campo | Valor |
|---|---|
| `name` / `slug` | `Reamur ThreeJS Reference` / `reamur_threejs_reference` |
| `checksum` | `16461680c8a1e5a42bf0c53bb1906bdeca794e99d49e3d9e3a4e1b3d94611742` |
| **`three`** | preset **aurora** (`hero-particles` / `hero` / 400 / `#8a4fff` / 0.05) |
| **`conditions`** | **6 regras = os 6 presets** (aurora, nebula, geometric, workshop, lost, off) |
| **`kaz_scene`** | v1 · 960×540 · **24 camadas** · 5 kinds · 5 roles · 7 `bindId` |
| `templates` | **25/25** allowlistadas, classes `thr-*` |
| `tokens` | 22 tokens AA |
| `parent` | vazio (tema base / child-themes) |

**Dois mecanismos:** `theme_scene` (runtime `data-*`) + `theme_scene_view`
(CSS `--rms-scene-*`). **4 cenas** e **3 slots** demonstrados.

## Como usar

Admin → Temas do Catálog → **Importar tema** → escolher o `.json` → conferir
prévia → **Importar como rascunho** → revisar → **Ativar**.
Dica: `?variant=b` → nebula; mobile ou `hour<7` → cena desligada.

## Validação medida

`validateConfig()` **0 erros** · **`decode()` ok=true** · **harness 52 OK / 0
FALHAS** com `ScenePresetRegistry::matchingPreset()`,
`ConditionEngine::evaluate()` em 7 contextos e `ThemeSceneCss::compile()` ·
checksum idêntico em Python e Node · 25 fontes seguras e balanceadas ·
verificador 0 FALHAS · `verify-checksums` 142/0 · `repo-health`
**6 PASS / 0 FAIL** · `verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não instala o tema automaticamente, não cria arquivos `.twig`/`.js`, não
escreve JavaScript, não registra novas cenas, não faz upload em disco, não
altera `AssetManifest`, `TEMPLATE_KEYS`, schema, contrato, hash, manifesto ou
workflow, não decide D01–D10, não escreve no GitHub pelo agente e **não
significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health e aceites visuais
ainda pendentes.**

<!-- FIM: INDICE-BH.md -->


<!-- INICIO: INDICE-BI.md (3021 bytes) -->

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

<!-- FIM: INDICE-BI.md -->


<!-- INICIO: INDICE-BJ.md (3493 bytes) -->

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

<!-- FIM: INDICE-BJ.md -->


<!-- INICIO: INDICE-BK.md (3148 bytes) -->

# Índice — Frente BK (correção do vazamento do KAZEditor-Theme)

Pacote: `ReamurCMS-Frente-BK-Correcao-Stage-Editor.zip` · HEAD `ab8b765…`
**4 temas corrigidos + patch de runtime (proposta) + diagnóstico + prova**
· **nenhum arquivo do repositório escrito pelo agente** · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | **`DIAGNOSTICO-BK.md`** | Cadeia causal com arquivos/linhas, garantia quebrada, 2ª observação |
| 2 | **`GUIA-CORRECAO-BK.md`** | Os 2 passos: reimportar temas + `git apply` |
| 3 | `RELATORIO-FINAL-BK.md` | Investigação, correção, validação, limites |
| 4 | `INDICE-BK.md` | Este índice |
| 5 | **`docs/temas/David-Creator.reamurtheme.json`** | BJ corrigido · `f2e53d07…` |
| 6 | **`docs/temas/Atlas-Studio.reamurtheme.json`** | BI corrigido · `dced34ce…` |
| 7 | **`docs/temas/Reamur-Reference.reamurtheme.json`** | BG corrigido · `0197e140…` |
| 8 | **`docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json`** | BH corrigido · `1c6fc07a…` |
| 9 | **`docs/correcao/correcao-runtime.patch`** | Proposta: 4 linhas em 3 arquivos do Catálogo |
| 10 | `docs/correcao/patch.json` | Inventário: alvos, linhas, Admin intacto |
| 11 | `tools/verify-no-scene-leak.cjs` | Verificador anti-vazamento |
| 12 | **`evidencia/antes-depois.png`** | Sua captura real × prévia corrigida |
| 13 | `evidencia/layout-check.json` | 1 stage / 3 camadas → **0 / 0** |
| 14 | `evidencia/theme-export.txt` | 32 OK / 0 FALHAS com `export()`/`decode()` reais |

## O bug

`kaz_scene` → `ThemeSceneCss::compile()` → `scene_view.enabled=true` →
`startup/theme.php:155` publica `catalog_theme_scene_view` →
`home.php:369` injeta `theme_scene_view` → `home.twig:4` inclui
`theme-scene-block.twig` no topo do `<main>` → `<section class="rms-scene">`
**no site público**. O próprio editor documenta que a cena **não** é
renderização no site público — garantia violada pela Frente AB.

## A correção

| Camada | O que | Estado |
|---|---|---|
| **A — temas** | remove o include dos 4 pacotes (2/2/2/**12** pontos), mantém `kaz_scene`, CSS, ThreeJS | ✅ entregue, reimportar |
| **B — runtime** | remove 4 linhas em 3 arquivos do Catálogo; Admin intacto | ⚠️ **proposta, não aplicada** |

## Validação medida

`validateConfig()` **0 erros** · **`export()`/`decode()` reais 32 OK / 0
FALHAS** · checksums independentes **4/4** · **0** ocorrências de
`theme-scene-block.twig` · `theme-scene.twig` mantido nos 4 · Twig balanceado
· verificador **0 FALHAS** · antes/depois **1 stage + 3 camadas → 0 + 0**
com hero, CTA, footer e 10 seções preservados · CI **7/7**.

## O que NÃO faz

Não aplica o patch, não escreve no GitHub, não cria migration/schema,
não altera contrato, hash, manifesto, workflow, allowlist ou renderer,
não decide o Recorte B, não converte `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN`
em `PASS`, não decide D01–D10 e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Aceite visual da BJ/BK e
health no Admin real ainda pendentes.**

<!-- FIM: INDICE-BK.md -->


<!-- INICIO: INDICE-BL.md (3153 bytes) -->

# Índice — Frente BL (correção definitiva)

Pacote: `ReamurCMS-Frente-BL-Vazamento-Persistente-Editor.zip`
HEAD `3219ccae…` · CI 7/7 · **2 patches + diagnóstico SQL + prova com model real**
· **nenhuma escrita do agente no GitHub** · **sem migration/SQL de escrita**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | **`DIAGNOSTICO-BL.md`** | Causa única: repo ≠ banco; `save()` só rascunho; editor cai no disco |
| 2 | **`GUIA-BL.md`** | Ordem: diagnosticar → patches → cache → **Ativar** |
| 3 | `RELATORIO-FINAL-BL.md` | Investigação, prova, validação, limites |
| 4 | `INDICE-BL.md` | Este índice |
| 5 | **`docs/correcao/A-vazamento-failclosed.patch`** | **Obrigatório** — 6 edições / 5 arquivos |
| 6 | **`docs/correcao/B-editor-dados-reais.patch`** | Recomendado — 3 edições / 2 arquivos |
| 7 | `docs/correcao/patches.json` | Inventário dos patches |
| 8 | **`tools/diagnostico-tema.sql`** | Somente `SELECT` — qual tema está ativo e onde está o include |
| 9 | `tools/verify-bl.cjs` | Verificador |
| 10 | **`evidencia/model-harness.txt`** | 10 OK / 0 FALHAS com `design/theme.php` real |
| 11 | `evidencia/cadeia-bl.json` | Cadeia causal com arquivos e linhas do HEAD |
| 12–15 | `docs/temas/*.reamurtheme.json` | 4 temas corrigidos na BK (inalterados) |

## Causa em 3 linhas

1. `3219ccae…` commitou os **arquivos** do ZIP BK; **`dev/` não foi tocado** —
   as 3 linhas do patch continuam no HEAD (`home.twig:4`, `home.php:369`,
   `startup/theme.php:155`).
2. Temas são registros de `design_theme`. `save()`/`import()` gravam **só
   `draft_config`**; o Catálogo lê **`published_config`**, que só muda com
   **Ativar**. JSON em `docs/` não instala tema nenhum.
3. `edit()` completa `template_sources` com os `.twig` **do disco** quando o
   registro não tem `templates` → o editor mostra o tema nativo; e esses
   textareas têm `name="templates[…]"` → **salvar reintroduz o include**.

## A correção

| Patch | Mecanismo | Depende de Ativar? |
|---|---|---|
| **A** | gate `theme_scene_preview` no partial (só a prévia Admin injeta o flag) + 3 remoções + 2 flags | **Não** — fail-closed |
| **B** | herda `templates` do publicado, `template_saved_keys`, fontes nativos `disabled`/sem `name=` | — |

## Validação medida

Model real em PHP 8.4 WASM **10 OK / 0 FALHAS** · `git apply --check` **2/2**
sem conflito · PHP `TOKEN_PARSE` **4/4** · patches aplicáveis e inventariados ·
gate bloqueia no Catálogo e permite na prévia Admin ·
verificador **0 FALHAS** · nenhum patch toca `CHECKSUMS`/schema/`.github`/
allowlist/renderer · `sha256sum -c` 84/84 · CI 7/7 (lido, não disparado).

## O que NÃO faz

Não aplica os patches, não escreve no GitHub, não roda `UPDATE`/`ALTER`,
não altera `save()`/`activate()`/`buildDraftConfig()`, não decide o Recorte B,
não converte `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, não decide
D01–D10 e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Aceite visual e health no
Admin real ainda pendentes.**

<!-- FIM: INDICE-BL.md -->


<!-- INICIO: INDICE-BM.md (2891 bytes) -->

# Índice — Frente BM (Atlas Studio 3D)

Pacote: `ReamurCMS-Frente-BM-Atlas-Studio-3D.zip`
HEAD `ad7643de…` · **`referencia=atlas` / `threejs=completo`** · tema completo
com ThreeJS **declarativo** · **nenhuma escrita do agente no GitHub** ·
**sem migration, sem código executável**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | **`docs/temas/Atlas-Studio-3D.reamurtheme.json`** | **O tema** — 76.637 bytes, 25 fontes, checksum `59ca4cd1…` |
| 2 | `RELATORIO-FINAL-BM.md` | Construção, validação PHP real, capturas, limites |
| 3 | `GUIA-BM.md` | Instalação (**Ativar!**), cenas por rota, verificação |
| 4 | `INDICE-BM.md` | Este índice |
| 5 | `BM-Preview-Cenas.png` | Prancha visual: home / projetos / 404 / mobile |
| 6–8 | `preview/{home,projetos,not-found}.html` | Prévias estáticas fiéis |
| 9 | `evidencia/export-bm.txt` | **14 OK / 0 FALHAS** (`ThemePackage`, `ThemeSnapshot`, `ThemeSceneCss`) |
| 10 | `evidencia/export-report.json` | Nome, slug, checksum, contagens |
| 11 | `evidencia/layout-bm.json` | 4 capturas Chromium 141 |
| 12–15 | `evidencia/shot-{home,home-mobile,projetos,not-found}.png` | Capturas reais |
| 16 | `tools/verify-bm.cjs` | Verificador do pacote |
| 17–21 | `tools/bm_{theme_gen.py,preview.py,export.php,shot.cjs,montagem.py}` | Geradores reproduzíveis |

## Números

| Métrica | Valor |
|---|---|
| Fontes Twig | **25** (allowlist completa) |
| Idênticos ao Atlas aprovado | **13** |
| Templates com slot `[data-scene]` | **11** (1 por página) |
| `three.enabled` | **true** |
| Cenas por rota (`conditions`) | **4 cenas / 5 regras** |
| `kaz_scene` | **16 camadas**, 960×540, grid 8 |
| `validateConfig()` | **`[]`** |
| `decode()` | **`ok=true`** |
| Checksum (config canonizado) | `59ca4cd1ae312abd4c5a5771ed9981335d90f6fd35cc30e3aa8f233c1fb4adbf` |
| PHP WASM | **14 OK / 0 FALHAS** |
| Chromium 141 | 4 capturas · 0 overflow · 0 pageerrors · **0 scripts** · 1 slot/página |
| `verify-bm.cjs` | 0 FALHAS |

## Decisões de projeto

- **`parent` vazio**: child-theme dependeria de registro pai no banco; sem ele
  o editor cai nos `.twig` do disco (defeito BL). Tema autossuficiente.
- **1 slot por página**: o rodapé aprovado já emite o slot só em `footer_top`;
  duplicar geraria 2 containers WebGL na mesma rota.
- **`kaz_scene` ≠ runtime**: as 16 camadas são dados de edição para a prévia do
  Admin; o WebGL no front vem de `theme-scene.twig` + `scene-runner.js` nativo.
- **Recorte A intacto**: dados validados, sem `.js`/`.twig` soltos, `|raw`,
  handlers, fontes/CDN, upload ou contrato novo.

## Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Aceite visual da BM, health
no Admin real e aplicação dos patches BL ainda pendentes.** CI verde, PHP
WASM, Chromium e guardas **não significam release aprovada**.

<!-- FIM: INDICE-BM.md -->


<!-- INICIO: INDICE-BN.md (3163 bytes) -->

# Índice — Frente BN (David Universe 3D)

Pacote: `ReamurCMS-Frente-BN-David-Universe-3D.zip`
HEAD `ad7643de…` · amostra **medida** em `davidalmeida.xyz/threejs/?lang=pt` ·
tema completo ThreeJS **declarativo** · **nenhuma escrita do agente no GitHub**
· sem migration, sem código executável.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | **`docs/temas/David-Universe-3D.reamurtheme.json`** | **O tema** — 88.864 bytes, 25 fontes, checksum `e081f0d2…` |
| 2 | `RELATORIO-FINAL-BN.md` | Amostra medida, construção, validação, limites |
| 3 | `GUIA-BN.md` | Instalação (**Ativar!**), cenas, 3 diferenças conscientes |
| 4 | `INDICE-BN.md` | Este índice |
| 5 | **`BN-Amostra-vs-Tema.png`** | Hero da amostra online × tema entregue + 4 cenas + mobile |
| 6 | `evidencia/ref.json` | Medições Chromium da amostra (cores, fontes, canvas, seções) |
| 7–9 | `evidencia/ref-{hero,desktop,mobile}.png` | Capturas reais da amostra online |
| 10–14 | `evidencia/sec-0X.png` | 6 fases scroll-driven da amostra |
| 15 | `evidencia/export-bn.txt` | **14 OK / 0 FALHAS** com as classes PHP reais |
| 16 | `evidencia/export-report.json` | Nome, slug, checksum, contagens |
| 17 | `evidencia/layout-bn.json` | 5 capturas Chromium 141 do tema |
| 18–22 | `evidencia/shot-{home,home-mob,catalogo,ferramentas,not-found}.png` | Capturas do tema |
| 23–26 | `preview/{home,catalogo,ferramentas,not-found}.html` | Prévias estáticas fiéis |
| 27 | `tools/verify-bn.cjs` | Verificador do pacote |
| 28–33 | `tools/bn_{css.py,theme_gen.py,export.php,preview.py,shot.cjs,montagem.py}`, `bn_capture.cjs`, `bn_sections.cjs` | Geradores reproduzíveis |

## Números

| Métrica | Valor |
|---|---|
| Fontes Twig | **25** (allowlist completa) |
| Fases `.du-phase` | **10** · 37 cards · 9 níveis de catálogo |
| `three.enabled` | **true** |
| Cenas por rota | **4 cenas / 5 regras `conditions`** |
| `kaz_scene` | **20 camadas**, 960×540, grid 8 |
| `validateConfig()` | **`[]`** |
| `decode()` | **`ok=true`** |
| Checksum (config canonizado) | `e081f0d26759034b15f1e200e2248199bbee43dea30765fb8977704c3f29a1d4` |
| PHP WASM | **14 OK / 0 FALHAS** |
| Chromium 141 | 5 capturas · 0 overflow (390/360/320) · 0 pageerrors · **0 scripts** · 1 slot/página |
| `bodyBg` medido (amostra = tema) | `rgb(18,21,22)` |
| Botão primário (amostra = tema) | `rgb(92,198,208)` |

## Decisões de projeto

- **Sem Google Fonts / `.webp` remotas / JS**: substituídos por stack local,
  SVG inline e `data-scene` — Recorte A intacto.
- **`parent` vazio**: autossuficiente, não depende de registro pai no banco
  (evita o defeito BL).
- **1 slot por página**: o rodapé só emite `footer_top` no 404.
- **`kaz_scene` ≠ runtime**: 20 camadas são dados de edição para a prévia do
  Admin; o WebGL no front vem de `theme-scene.twig` + `scene-runner.js`.

## Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Aceite visual da BN, health
no Admin real e aplicação dos patches BL ainda pendentes.** CI verde, PHP
WASM, Chromium e guardas **não significam release aprovada**.

<!-- FIM: INDICE-BN.md -->


<!-- INICIO: INDICE-BO.md (2981 bytes) -->

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

<!-- FIM: INDICE-BO.md -->


<!-- INICIO: INDICE.md (954 bytes) -->

# Índice — Frente AG

| # | Arquivo | Papel |
| --- | --- | --- |
| 1 | `dev/system/library/theme/theme_preview_state.php` | **novo** — única tradutora do estado: `css()`, `globals()`, `catalogCss()` |
| 2 | `dev/system/library/template/twig.php` | 4º argumento `$themeState`, globais allowlistados no sandbox |
| 3 | `dev/admin/controller/design/theme.php` | `preview()` usa `ThemeSnapshot`; `compare()` repassa os dois estados; cache `-ag` |
| 4 | `dev/system/library/theme/theme_compare_view.php` | 6 de 6 partes de CSS + classes do estado por painel |
| 5 | `tools/kaz-theme-preview-state.php` | **novo** — PROBE 51/0, somente leitura |
| 6 | `tools/verify-kaz-preview-state.cjs` | **novo** — Chromium 24/0, mudança real medida |
| 23 | `RELATORIO.md` | este relatório |
| 24 | `INDICE.md` | este índice |

`.sha256` fica fora do ZIP, em `/home/user/entregas/`.
Frentes A–AF preservadas, aplicadas e confirmadas — não reentregues.

<!-- FIM: INDICE.md -->


<!-- INICIO: Inventario-Rename-Global.md (4128 bytes) -->

# Inventário do rename global (R2)

**HEAD:** `481b690209ec1a1caf4d9f334cef6012d4597653` · **CI:** 7/7 success · **Leitura apenas.**

Padrão considerado: `BDREditor`, `KAZTheme-Editor`, `KAZTheme`,
`kaz-theme-editor`, `kaz_theme_editor`, `blog-block-editor-kaz`, `kaz-bdre`.

## Totais

| Métrica | Valor |
|---|---|
| Arquivos afetados | **179** |
| Ocorrências de texto | **762** |
| Arquivos com o termo **no próprio path** | **57** |
| Manifestos `CHECKSUMS*.txt` a regenerar | **13 de 14** |
| Contratos congelados afetados | **0 de 3** |

## Por categoria

| Categoria | Arquivos | Ocorrências |
|---|---|---|
| Docs | 99 | 231 |
| CSS | 8 | 190 |
| Doc/contrato | 19 | 107 |
| Tools (probe/smoke/guard) | 14 | 68 |
| Traducao | 6 | 55 |
| JS runtime | 8 | 42 |
| PHP runtime | 9 | 34 |
| Template Twig | 5 | 14 |
| ADR/decisoes | 4 | 9 |
| Preview | 2 | 4 |
| Replica tree/ | 3 | 4 |
| Shell (guard) | 1 | 3 |
| Tests | 1 | 1 |

## Achados de governança

- `tools/decision-matrix.json` → `frozen_artifacts_sha256` protege 3 artefatos:
  `docs/F9-14-Contrato-Editor-MVP.json`, `docs/F9-15-Contrato-Renderer-Publico.json`,
  `docs/F9-25-Contrato-Editor-Editorial.json`. **Nenhum cita os nomes**, logo o
  rename não precisa alterá-los e `tools/verify-contract-freeze.py` segue coerente.
- Policy literal: *"Nenhuma decisao tomada por inferencia. Contratos congelados
  ate registro explicito de status=DECIDED."* — 10 `PENDING_DECISION` / 0 `DECIDED`.
- `docs/BDREditor-Table-Contract.json`, `-Verification.json`, `-Base-Hashes.json`
  **não** estão entre os 3 artefatos congelados, mas são contratos por nome e
  podem ser referenciados por outros checkers.
- **13 dos 14 manifestos** listam arquivos que mudariam de conteúdo (28 entradas
  cruzadas). Regenerar é obrigatório — foi exatamente esse tipo de divergência
  que deixou o CI vermelho na Frente AV.

## Arquivos sensíveis (amostra ordenada)

| Arquivo | Ocorrências | Nome no path |
|---|---|---|
| `agents-ia/Agent-BDREditor/README.md` | 3 | sim |
| `agents-ia/README.md` | 1 | — |
| `dev/admin/controller/blog/blog.php` | 8 | — |
| `dev/admin/controller/cms/page.php` | 8 | — |
| `dev/admin/language/en-us/blog/blog.php` | 14 | — |
| `dev/admin/language/en-us/cms/page.php` | 4 | — |
| `dev/admin/language/es-eu/blog/blog.php` | 14 | — |
| `dev/admin/language/es-eu/cms/page.php` | 4 | — |
| `dev/admin/language/pt-br/blog/blog.php` | 14 | — |
| `dev/admin/language/pt-br/cms/page.php` | 5 | — |
| `dev/admin/view/css/blog-block-editor-kaz-total.css` | 4 | sim |
| `dev/admin/view/css/blog-block-editor-kaz.css` | 104 | sim |
| `dev/admin/view/css/kaz-theme-editor-ajuste.css` | 9 | sim |
| `dev/admin/view/css/kaz-theme-editor-ergonomia.css` | 58 | sim |
| `dev/admin/view/css/kaz-theme-editor.css` | 2 | sim |
| `dev/admin/view/js/blog-block-editor-kaz.js` | 19 | sim |
| `dev/admin/view/js/kaz-theme-editor-ajuste.js` | 1 | sim |
| `dev/admin/view/js/kaz-theme-editor-ergonomia.js` | 3 | sim |
| `dev/admin/view/js/kaz-theme-editor.js` | 12 | sim |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | 9 | sim |
| `dev/system/library/theme/theme_preview_state.php` | 1 | — |
| `dev/system/library/theme/theme_release_probe.php` | 1 | — |
| `docs/BDREditor-Table-Base-Hashes.json` | 0 | sim |
| `docs/BDREditor-Table-Contract.json` | 2 | sim |
| `docs/BDREditor-Table-Verification.json` | 2 | sim |
| `docs/CHANGELOG.md` | 1 | — |
| `docs/README.md` | 6 | — |
| `tools/e2e/README.md` | 1 | — |
| `tools/kaz-bdre-total-probe.php` | 22 | sim |
| `tools/kaz-bdre-visual-probe.php` | 18 | sim |
| `tree/dev/admin/language/pt-br/cms/page.php` | 1 | — |

## Arquivos com o termo no próprio path (renomear = atualizar links)

Foram encontrados **57**. Renomeá-los exige atualizar `docs/README.md`, os
manifestos e todos os links relativos — sob pena de `docs-links` falhar no CI.

Lista completa em `evidencia/inventario-rename.json`.

## O que NÃO foi feito

Nenhuma substituição, nenhum rename de arquivo, nenhuma regeneração de
manifesto, nenhuma escrita no GitHub. Este é um levantamento.

<!-- FIM: Inventario-Rename-Global.md -->


<!-- INICIO: LEIA-ME.md (2981 bytes) -->

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

<!-- FIM: LEIA-ME.md -->


<!-- INICIO: Plano-Frente-AX.md (5426 bytes) -->

# Plano — Frente AX (Ferramentas internas + rename)

**Data:** 2026-11-06 · **HEAD:** `481b690209ec1a1caf4d9f334cef6012d4597653`
**CI:** 7/7 success (Frente AV aplicada) · **Status:** plano, **nada aplicado**.

## 1. Decisões do usuário

| Item | Decisão |
|---|---|
| Nomes finais | **`BDREditor-Content`** e **`KAZEditor-Theme`** (grafia corrigida de "Thene") |
| Alcance do rename | **R2 — global** |
| Versão / atualizações | **V2 — JSON de metadados versionado**, sem migration |
| Local | **P2 — seção "Ferramentas internas" somente no Admin** |

O escopo "editar somente o BDREditor" (AN/AP) foi **encerrado pelo veredito
APROVADO**; esta é uma frente nova e explicitamente autorizada.

## 2. Parte A — Seção "Ferramentas internas" (V2 + P2)

### Arquivos propostos

| Arquivo | Ação | Observação |
|---|---|---|
| `dev/admin/controller/tool/tool.php` | alterar | injetar `$data['internal_tools']` lido do JSON, com `try/catch` e fail-closed |
| `dev/admin/view/template/tool/tool_list.twig` | alterar | nova `<section>` após a lista de ferramentas conectadas |
| `dev/admin/language/{en-us,pt-br,es-eu}/tool/tool.php` | alterar | 6–8 rótulos novos por idioma |
| `tools/internal-tools.json` | **novo** | fonte única de versão, data e notas |
| `dev/admin/view/css/admin.css` | alterar | só adendo `.internal-tools-*`, prefixo anterior preservado |

**Nenhum** arquivo de `dev/install/sql/`, nenhuma migration, nenhuma tabela,
nenhum `|raw` novo, nenhuma fonte/CDN, nenhuma chamada de rede, nenhuma rota
nova, nenhuma alteração de ACL/CSRF, nenhuma mudança no catálogo público.

### Estrutura do JSON (`tools/internal-tools.json`)

```json
{
  "schema": 1,
  "tools": [
    {
      "id": "bdreditor-content",
      "name": "BDREditor-Content",
      "legacy_name": "BDREditor",
      "vendor": "ReamurCMS",
      "version": "PENDENTE_USUARIO",
      "updated_at": null,
      "notes": [],
      "href": null
    },
    {
      "id": "kazeditor-theme",
      "name": "KAZEditor-Theme",
      "legacy_name": "KAZTheme-Editor",
      "vendor": "ReamurCMS",
      "version": "PENDENTE_USUARIO",
      "updated_at": null,
      "notes": [],
      "href": null
    }
  ]
}
```

- `legacy_name` documenta a equivalência nome novo ↔ identificador antigo
  enquanto o R2 não é concluído.
- `version` e `updated_at` **não foram inventados**: ficam com `PENDENTE_USUARIO`
  / `null`, e a UI exibe "Versão não informada" de forma honesta (fail-closed).
- Controller valida `schema`, `id` único, `name` não vazio e tipos; JSON
  inválido/ausente → seção omitida com log, **sem quebrar a página**.
- `notes[]` = até N linhas de atualizações, renderizadas com escape automático
  do Twig (sem `|raw`).

### Comportamento visual

Seção separada, com o **tema ReamurCMS claro e acento azul** intactos (o
veredito APROVADO da AP continua vigente). Cards/badges reaproveitam classes
existentes; nada de identidade KAZ fora do BDREditor.

## 3. Parte B — Rename global (R2)

Inventário completo em `Inventario-Rename-Global.md`:
**179 arquivos / 762 ocorrências / 13 de 14 manifestos a regenerar / 0 de 3
contratos congelados afetados**.

### Fases propostas

| Fase | Conteúdo | Risco |
|---|---|---|
| **B1** | Rótulos de UI + traduções + textos de `docs/` | Baixo, mas exige regenerar 13 manifestos e conferir `docs-links` |
| **B2** | Renomear arquivos de `docs/` que têm o termo no path e atualizar todos os links (`docs/README.md`, índices, ADRs) | Médio — foi aqui que o CI quebrou na AV |
| **B3** | Identificadores de código: `blog-block-editor-kaz.js`, `kaz-theme-editor.js`, `kaz_theme_editor.twig`, classes, variáveis, rotas, probes e smoke tests | **Alto** — `<script src>`, asserções de string literal e 61 checkers e2e |
| **B4** | Regenerar os 14 `CHECKSUMS*.txt`, rodar os 6 guardas + `docs-links` localmente, conferir CI remoto | Obrigatório em todas as fases |

### Ponto que ainda depende de você

"R2 global" pode significar **B1+B2** (texto, rótulos, documentação e nomes de
arquivos de doc) ou **B1+B2+B3** (também identificadores internos de código).
A diferença é grande: B3 renomearia arquivos JS/PHP carregados por controllers
e exigiria atualizar `tools/kaz-bdre-visual-probe.php` (18 ocorrências),
`tools/kaz-bdre-total-probe.php` (22), os `.cjs` de verificação e os checkers
e2e — além de reauditoria completa do CI. **Não vou assumir B3 por inferência.**

## 4. O que falta para eu gerar o pacote de código

1. **Versões iniciais** (e datas/notas, se quiser) de `BDREditor-Content` e
   `KAZEditor-Theme`. Sem elas, o JSON fica com `PENDENTE_USUARIO`.
2. **Confirmação do alcance do R2**: B1+B2 ou B1+B2+B3.
3. Opcional: captura da tela onde as externas mostram versão/atualizações, se
   quiser que eu reproduza aquele padrão em vez do proposto aqui.

## 5. Limites

Nenhuma escrita no GitHub, nenhum rename aplicado, nenhuma migration, nenhuma
alteração de contrato congelado, manifesto, hash, `schema.sql`, DOM, JS de
~600 KB, renderer ou catálogo. Nenhuma conversão de `PENDING`/`NOT_RUN`/
`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão das 10 `PENDING_DECISION`, nenhuma
declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · CI 7/7 (lido, não disparado) · veredito **APROVADO**
da AP mantido. AR e AS seguem prontos e sem conflito.

<!-- FIM: Plano-Frente-AX.md -->


<!-- INICIO: Protocolo-Aceitacao-Visual-AP.md (4724 bytes) -->

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

<!-- FIM: Protocolo-Aceitacao-Visual-AP.md -->


<!-- INICIO: RELATORIO-BO.md (13952 bytes) -->

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

<!-- FIM: RELATORIO-BO.md -->


<!-- INICIO: RELATORIO-FINAL-AN.md (5031 bytes) -->

# RELATÓRIO FINAL — Frente AN
## Padrão visual BDREditor ↔ KAZTheme-Editor

**Data:** 2026-11-05 · **Base remota:** `b89790108e42347c4fe19128a9d241d90ee7d49e` · **Não é release**

### 1. Pedido e escopo

> "Vamos trabalhar agora novamente no BDREditor e deixar ele com a mesma aparencia do KAZTheme-Editor para manter o padrão entre as ferramentas"

Decisões confirmadas: `abrangencia=identidade` (visual completo, DOM mantido, sem clonar screenbar/3 colunas/divisórias), `onde=admin` (sem `dev/catalog/view/css/blog-blocks.css`), `foco=manter` (`#174ea6` intacto).

### 2. Diagnóstico

O KAZ tem 34.897 bytes / 315 regras / tokens escuros (`#18181b`, `#1f1f23`, `#e9e9ee`, accent `#31efb8`). O BDREditor tem **119.293 bytes / 1140 regras / 383 classes / 842 cores hex literais** com fundo `#fff` e accent `#215bd6`. A estrutura (canvas, outline, inspector, ribbon, abas, fullscreen) **já é equivalente** — divergia apenas a identidade visual. Como as cores são literais, só remapear `--rb-*` não bastaria.

**Composição adotada:** *chrome escuro, canvas claro* — idêntico ao KAZ, onde o board branco do tema publicado repousa sobre o workspace escuro.

### 3. O que foi feito

| Item | Resultado medido |
| --- | --- |
| Paleta, botões, abas, campos, painéis | 16 tokens `--kaz-bdre-*`, 8 variáveis `--rb-*` remapeadas, 30+ superfícies sobrescritas |
| Cobertura do chrome | **29 → 0** elementos com fundo claro fora do canvas (Chromium, 329/341 elementos varridos) |
| Canvas do conteúdo | permanece `rgb(255,255,255)` com texto `rgb(23,32,51)` |
| Barras de rolagem | `scrollbar-color` = `color(srgb .165 .627 .499) color(srgb .126 .178 .178)`; regras `::-webkit-scrollbar-thumb` carregadas (0 → 2) |
| Escala de todos os elementos | `--kaz-ui` .86 / 1 / 1.14 / 1.30; fonte do botão **13,12 px → 17,056 px** no nível "Amplo" |
| Expandir/recolher | 3 dobras + "Recolher tudo"/"Expandir tudo", `aria-pressed` true↔false; a barra de controles nunca é recolhida |
| Foco F9-26 | `rgb(23,78,166) solid 3px` + `outline-offset 2px` **inalterados**; apenas halo claro acrescentado |
| Contraste do masthead | título **13,57:1**, subtítulo **5,98:1** (WCAG ≥ 4,5) |
| Documento de blocos | **idêntico ao controle**, 243 bytes, zero alteração |
| Exceções JS | 0 no controle, 0 com AN |
| Responsividade | desktop overflow **0**; 390 px: excesso do host **824 px → 53 px** (página 841 → 70), sem agravar |

### 4. Arquitetura

**Camada aditiva**, marcador `-kaz-bdre-an`. `blog-block-editor.css` (119 KB) e `blog-block-editor.js` (600 KB) **não foram alterados** — nem `init.js`, `import.js`, `bdre-concurrency.js`, os 4 Twigs de host, `schema.sql`, traduções, `theme_form.twig`, os 14 manifestos, os 11 congelados, `decision-matrix.json`, F9-26, F9-27 ou F9-18.

O companion JS usa DOM puro, não monta/edita/serializa o documento, e reconstrói a barra via `MutationObserver` porque o `init.js` **substitui os filhos do host ao montar** (descoberto no A/B). `window.__kazBdre()` é diagnóstico opcional — **não é preciso abrir o console**.

Registro nos 2 controllers: `blog/blog.php` e `cms/page.php`, +3 linhas cada, com variável própria `$kazBdreAsset` para preservar as contagens de `$assetVersion` / `$version`.

### 5. Validação

| Verificação | Resultado |
| --- | --- |
| PROBE `kaz-bdre-visual-probe.php` | **57 OK / 0 FALHAS** |
| Chromium A/B `verify-kaz-bdre-visual.cjs` | **51 OK / 0 FALHAS** |
| 4 guards (`verify-checksums.sh`, `repo-health.sh`, `verify-ci-workflow.sh`, `verify-contract-freeze.py`) | **4/4 rc=0** |
| Réplica fiel do HEAD | 2601 arquivos → **2606** (2 alterados + 5 novos) |
| Árvore remota | 2601 blobs / 40.150.628 bytes baixados, **0 divergentes** |
| Sintaxe | 3 PHP + 2 JS/CJS, 0 erros |
| Contratos | `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` intactos |

### 6. Limites — isto NÃO é aprovação

A prévia usa o `preview/index.html` do repositório, que **não carrega o CSS do Admin real**; por isso o formulário ao redor aparece claro nas capturas. No Admin instalado, o entorno já recebe o tema das frentes AA–AM. Os **70 px** de overflow restantes a 390 px vêm do `.langtabs`/`.card` desse formulário, **fora de `[data-blog-block-editor]`** — corrigi-los exigiria reabrir o layout do formulário, fora do escopo `manter estrutura`.

Seguem pendentes as **2 capturas desktop + 390 px do Admin real**, `copy(__kazHealth())` em produção, o CI remoto (403 `Must have admin rights`) e o veredito visual do usuário. `image-7.png` e `image-8.png` continuam como critério histórico. **CI verde, PROBE, WASM e Chromium headless não significam release aprovada.**

`release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`; F9-18 `release_ready:false` com P1 aberto; backend, banco, migration, ciclo criar/editar/salvar/Publicar, P2/F9-18 e F9-103 **não foram reabertos**. Nenhuma escrita nossa foi feita no GitHub — a aplicação do pacote é feita pelo usuário.

<!-- FIM: RELATORIO-FINAL-AN.md -->


<!-- INICIO: RELATORIO-FINAL-AO.md (5875 bytes) -->

# RELATÓRIO FINAL — Frente AO
## Tema TOTAL do BDREditor ↔ KAZTheme-Editor

**Data:** 2026-11-06 · **Base remota:** `1b6ffd97e418272eebccfd573cccbe61c15ca386` · **Não é release**

### 1. Pedido e decisões

> "ReamurCMS-Frente-AN-Padrao-Visual-BDREditor aplicado com sucesso github atualizado. Podemos prosseguir com o próximo passo deixe todo tema do BDREditor semelhante ao KAZTheme-Editor"

Confirmação remota da AN: **14 adições** (5 arquivos da AN + 7 evidências + 2 docs de raiz), **2 modificações** (`blog/blog.php`, `cms/page.php`), **0 remoções**; os 7 arquivos da AN **byte a byte idênticos** ao pacote local. Árvore: 2615 blobs / 41.162.133 bytes.

Escopo respondido: `alcance=pagina_e_estados` (página Admin inteira + todos os estados) e `modo_tema=sempre_escuro` (sempre no escuro KAZ). Permanecem: somente Admin, sem clonar layout, sem alterar DOM/JS de 600 KB, foco `#174ea6` congelado.

### 2. Diagnóstico do que faltava

| Camada | Situação após a AN |
| --- | --- |
| Admin ao redor | `reamurcms.css` claro (`#f4f6f9`, `#fff`, `#f8fafc`): navbar, breadcrumb, `.page-titlebar`, `.card`, `.form-control`, `.nav-tabs`, `.input-group-text`, `.alert`, `.btn`, `.badge`, sidebar de publicação |
| Estados sob demanda | JS faz 3× `document.body.appendChild` (overlays **fora do host**); ~100 classes de command palette, padrões, inserter, prévias, tabelas, agrupamento, fullscreen, arraste/seleção; AN cobria 6 regras desses termos |
| `<html>` | superfície visível em fullscreen/workspace, não coberta pela AN |
| Preferência de tema | `data-bs-theme` / `theme_skin` / `theme_mode` continuavam claros |

A AN estava escopada em `[data-blog-block-editor]`, portanto só alcançava o chrome do editor.

### 3. Resultados medidos (Chromium A/B, Admin reconstruído com os CSS reais)

| Métrica | Controle (só AN) | Com AO |
| --- | --- | --- |
| `<html>` / `<body>` | `rgb(244,246,249)` | **`rgb(24,24,27)`** |
| `--bs-body-bg` | `#f4f6f9` | **`#18181b`** |
| `.card` | `rgb(255,255,255)` | **`rgb(31,31,35)`** |
| `.form-control` | `rgb(255,255,255)` | **`rgb(44,44,51)`** |
| `.alert` | `rgb(221,241,246)` | **`rgb(31,31,35)`** |
| `.btn-primary` | `rgb(30,145,207)` | **`rgb(49,239,184)`** |
| Título / labels | `rgb(33,37,41)` | **`rgb(233,233,238)` / `rgb(155,155,167)`** |
| Dialog / padrões / prévia / inserter | claros | **`rgb(38,38,43)`** |
| **Fundos claros fora do canvas** | **21** | **0** |
| Canvas de conteúdo | branco | **branco (inalterado)** |
| Regras / seletores AO no motor | 0 / 0 | **20 / 186** |
| `data-bs-theme` | `light` | **`light` (não alterado)** |
| Exceções JS | 0 | **0** |
| Overflow desktop / 390 px | 0 | **0 / 0** |
| Documento de blocos | — | **idêntico ao controle** |

**Não-regressão da AN:** escala "Amplo", 3 dobras, barra `ELEMENTOS` e `__kazBdre()` continuam operando; foco segue `rgb(23,78,166) solid 3px` com `outline-offset 2px`.

### 4. Arquitetura

Uma folha aditiva `blog-block-editor-kaz-total.css`, carregada **depois** da AN nos 2 controllers, marcador `-kaz-bdre-ao`. **Nenhum JS novo**, nenhuma alteração no CSS/JS da AN, nenhum Twig.

Escopo `:is(body:has([data-blog-block-editor]), html[data-kaz-bdre-tema="1"] body)` + `:is(html:has(...), html[data-kaz-bdre-tema="1"])`. O `:has()` é dinâmico (cobre overlays criados depois em `document.body`); o atributo é fallback para navegadores sem `:has()`, onde a **AN continua intacta**. "Sempre escuro" vem da sobrescrita dos tokens `--bs-*` e `--kaz-bdre-*` no `body` das telas com editor, mais regras explícitas com `!important` sobre as cores literais de `reamurcms.css`/Bootstrap — **sem mudar a preferência do usuário**.

**Inalterados:** `blog-block-editor.css` (119 KB), `blog-block-editor.js` (600 KB), init/import/concurrency, `blog-block-editor-kaz.css`, `blog-block-editor-kaz.js`, os 4 Twigs de host, `reamurcms.css`, `bootstrap.css`, skins, `admin.css`, `blog-blocks.css` do catálogo, `schema.sql`, traduções, `theme_form.twig`, os 14 manifestos, os 11 congelados, `decision-matrix.json` e os contratos F9-14/25/26/18/27.

### 5. Validação

| Verificação | Resultado |
| --- | --- |
| PROBE `kaz-bdre-total-probe.php` | **52 OK / 0 FALHAS** |
| Chromium A/B `verify-kaz-bdre-total.cjs` | **46 OK / 0 FALHAS** |
| 4 guards (`verify-checksums.sh`, `repo-health.sh`, `verify-ci-workflow.sh`, `verify-contract-freeze.py`) | 4/4 rc=0 |
| Checkers e2e (base × réplica) | 62 comparados, **0 divergências** |
| Réplica fiel do HEAD | 2615 blobs / 41.162.133 bytes / 0 divergentes → 2619 arquivos (2 alterados + 4 novos, normalizando `node_modules`) |
| Sintaxe | 392 PHP + 620+ JS/CJS, 0 erros |
| Contratos | `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` intactos |

### 6. Limites — isto NÃO é aprovação

A prévia reconstrói os formulários com as **classes reais** de `reamurcms.css`/Bootstrap e carrega `bootstrap.css`, `reamurcms.css` e a skin clara, mas **não é** uma sessão Admin autenticada: sem MySQL, deploy ou CI remoto. Seguem pendentes as **2 capturas desktop + 390 px do Admin real**, `copy(__kazHealth())` em produção, o CI remoto (403 `Must have admin rights`) e o veredito visual do usuário. `image-7.png` e `image-8.png` continuam como critério histórico.

`:has()` cobre Chrome 105+, Safari 15.4+, Firefox 121+; sem suporte, a página volta ao tema do Admin e a AN segue funcionando (degradação graciosa, sem perda funcional).

`release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`; F9-18 `release_ready:false` com P1 aberto. Backend, banco, migration, ciclo criar/editar/salvar/Publicar, P2/F9-18 e F9-103 **não foram reabertos**. **CI verde, PROBE, WASM e Chromium headless não significam release aprovada.** Nenhuma escrita nossa foi feita no GitHub — a aplicação do pacote é feita pelo usuário.

<!-- FIM: RELATORIO-FINAL-AO.md -->


<!-- INICIO: RELATORIO-FINAL-AP.md (7231 bytes) -->

# Relatório Final — Frente AP (correção de escopo da AO)

**Data:** 2026-11-06  
**Base reauditada:** `9c809050ff908b66b6f3bf97c1fc5a1aed9b3160` —
`feat: add total dark admin theme for BDREditor` (árvore 3.209 entradas,
2.626 blobs, 41.926.874 bytes, `truncated: false`), baixada byte a byte com
0 divergências.  
**Situação:** a AO foi aplicada pelo usuário e **reprovada visualmente**.
Este pacote é a **correção**, não uma continuação da AO.

---

## 1. A reprovação e a causa real

> *"A edição passou dos limites era para Editar somente o BDREditor mas acabou
> editando a barra de navegação lateral do ReamurCMS"*

`image-9.png` mostra o Admin real com sidebar, topbar, breadcrumb, cards e
campos escurecidos, item ativo e borda da sidebar **verde-menta**, e abas de
idioma com sublinhado verde. `image-10.png` mostra o KAZTheme-Editor com a
sidebar do Admin em **azul** e a identidade KAZ **apenas dentro do editor**.

A causa foi localizada no código, não inferida:

```css
/* dev/admin/view/css/reamurcms.css */
#menu > li.active > a { background-color: var(--bs-primary); }
/* dev/admin/view/css/themes/light.css */
--rms-accent: #0d6efd;
```

A AO sobrescrevia `--bs-primary: #31efb8`, `--bs-body-bg`,
`--bs-border-color` e dezenas de classes Bootstrap **no `body`/`html`**.
Como o item ativo da sidebar lê `var(--bs-primary)`, ele virou verde.

### A/B/C medido no Chromium (prévia com o markup real de `#menu`)

| Estado | item ativo da sidebar | `--bs-primary` | `.card` | `html`/`body` |
|---|---|---|---|---|
| Admin nativo + AN | `rgb(13, 110, 253)` azul | `#0d6efd` | `rgb(255,255,255)` | `rgb(244,246,249)` |
| **AO (reprovada)** | `rgb(49, 239, 184)` verde | `#31efb8` | `rgb(31,31,35)` | `rgb(24,24,27)` |
| **AP (corrigida)** | `rgb(13, 110, 253)` azul | `#0d6efd` | `rgb(255,255,255)` | `rgb(244,246,249)` |

A AO diverge do Admin nativo em **11 de 12 superfícies** medidas e em
**54 elementos** na varredura completa. A AP diverge em **zero**.

---

## 2. O que a AP entrega

**Recolhe a camada ao BDREditor**, substituindo o conteúdo da folha da AO no
mesmo caminho (nome mantido por compatibilidade; não é mais "total"):

- `[data-blog-block-editor]` e descendentes — host, chrome, biblioteca de
  padrões, inserter, prévias, tabelas, rail, inspector, outline, histórico,
  fullscreen, arraste, seleção;
- `body:has([data-blog-block-editor]) .rb-*` — **apenas** os overlays/dialogs
  que o editor cria em `document.body`; o `:has()` é só condição de presença,
  nunca alvo, e sempre exige um descendente `rb-*`;
- tokens **locais** `--kaz-ap-*` no host (nunca `:root`), scrollbars e foco
  dentro do host/overlays;
- canvas **branco** e foco F9-26 `#174ea6` / `3px` / `offset 2px`.

**Nenhuma regra** mira `html`, `body`, `:root`, `--bs-*`, `--rms-*`, `#menu`,
`.sidebar`, `.navbar`, `.page-titlebar`, `.breadcrumb`, `.container-fluid` ou
qualquer classe genérica do Admin. O PROBE varre **todos os seletores** e exige
que cada um contenha `[data-blog-block-editor]`; o verificador Chromium varre
**todos os elementos fora do editor** e exige zero divergências de
`background-color`, `color` e `border-color` contra o nativo.

O BDREditor continua com a identidade KAZ da AN: host `rgb(24,24,27)`,
chrome `rgb(31,31,35)`, accent `#31efb8`, canvas `rgb(255,255,255)` com texto
`rgb(23,32,51)`, documento de blocos idêntico (209 bytes), escala ampla e
dobras preservadas, zero overflow em desktop e 390 px.

### Arquivos (9)

| Arquivo | Ação |
|---|---|
| `dev/admin/controller/blog/blog.php` | marcador `-kaz-bdre-ao` → `-kaz-bdre-ap` |
| `dev/admin/controller/cms/page.php` | marcador `-kaz-bdre-ao` → `-kaz-bdre-ap` |
| `dev/admin/view/css/blog-block-editor-kaz-total.css` | **substituído**: escopo recolhido |
| `docs/KAZ-BDREditor-Tema-Total.md` | **substituído** pelo doc corrigido |
| `tools/kaz-bdre-total-probe.php` | **substituído** pelo PROBE de não-vazamento |
| `tools/verify-kaz-bdre-total.cjs` | **substituído** pelo A/B/C |
| `docs/Registro-Visual-AP.md` | **novo** — critério visual |
| `evidencia/ap_reprovacao_image-9.png` | **nova** — reprovação do usuário |
| `evidencia/ap_referencia_image-10.png` | **nova** — referência KAZ |

Substituir no mesmo caminho evita arquivo órfão e não exige remoção manual.
O marcador novo serve apenas para invalidar cache; a ordem AN → AP e os demais
assets não mudam.

---

## 3. O que permanece intocado

Sidebar, topbar, breadcrumb, titlebar, cards, campos, abas de idioma, botões,
alertas, badges e o acento azul do ReamurCMS; catálogo público, renderer,
documento de blocos, JS de ~600 KB, Twigs, `schema.sql`, traduções, manifestos
e os contratos F9-14 / F9-25 / F9-26 / F9-27 / F9-18. Sem JS novo, DOM novo,
`|raw`, migração, fonte externa ou CDN. Não se clona o layout do
KAZTheme-Editor (screenbar / 3 colunas).

`release_approved` segue `false`; F9-27 segue `BLOCKED_NOT_RUN`; F9-18 segue
`release_ready: false`; os 10 `PENDING_DECISION` não foram convertidos.

---

## 4. Validação

| Verificação | Resultado |
|---|---|
| Download fiel do HEAD `9c80905…` | 2.626 blobs / 41.926.874 bytes / **0 divergentes** |
| Réplica = base + pacote | 2.629 arquivos, 9/9 íntegros |
| Congelados (CSS/JS/Twigs/schema/manifestos/contratos) | **0 alterados** |
| PHP WASM `-l` | 392+ arquivos, **0 erros** |
| `node --check` JS/CJS | 620+ arquivos, **0 erros** |
| 4 guards (`checksums`, `repo-health`, `ci-workflow`, `freeze`) | **4 × rc=0** |
| 62 checkers e2e (base × réplica) | 30 rc=0 em cada, **0 divergências reais** |
| PROBE de não-vazamento | **53 OK / 0 FALHAS** |
| Chromium A/B/C | **41 OK / 0 FALHAS** |
| ZIP | CRC ok, `.sha256` conferido |

**Sobre os checkers e2e:** o único item instável é
`threejs_admin_preview_sandbox_isolated.cjs`, que alterna entre timeout e
`Target crashed` sob carga nos dois lados. Reexecutado isolado, **base e
réplica dão a mesma falha pré-existente `Target crashed`**; não há regressão
atribuída à AP. As 32 falhas restantes já existiam na base.

**Sobre os guards:** `repo-health.sh` instala um Playwright local em
`dev/system/storage/node_modules`; as duas árvores foram normalizadas antes da
comparação para que o `load-playwright.cjs` resolvesse a mesma versão
(1.56.1) nos dois lados.

---

## 5. Limites e aceitação

PHP 8.5 via WASM, Twig/jsdom e Chromium headless **não são produção**. A
prévia reconstrói as classes e os tokens do Admin; não reproduz o layout
completo da sidebar nem uma sessão autenticada. CI remoto segue não
verificado (`403 Must have admin rights`); `copy(__kazHealth())` de produção,
backend/DB/migração e o ciclo salvar–Publicar não foram executados.

Estes resultados locais **não significam release aprovada**. O veredito é
**visual, no Admin real, pelo usuário**: a AP só estará aceita quando a
sidebar/nav/topbar/formulário estiverem como antes da AO (azul, tema
ReamurCMS), o BDREditor continuar escuro e próximo de `image-10.png`, e
nenhum elemento fora do BDREditor tiver mudado. `image-9.png` permanece como
reprovação vigente até novo veredito.

Sem escrita no GitHub, sem deploy e sem declaração de release pelo agente.

<!-- FIM: RELATORIO-FINAL-AP.md -->


<!-- INICIO: RELATORIO-FINAL-AQ.md (2976 bytes) -->

# Relatório Final — Frente AQ (Roteiro dos próximos passos)

**Data:** 2026-11-06 · **HEAD:** `179269fea36584076f9ef41b95ae94638dd08f45`
**Tipo:** pacote de **planejamento/leitura**, não de código.

## Objetivo

Converter as sugestões pós-AP num roteiro verificável, ancorado nos contratos
**reais** do HEAD, sem executar runtime, sem alterar contrato, sem escrever no
GitHub e sem declarar release.

## Estado confirmado

- AP aplicada de forma fiel: +9 / −0 / ~6 vs AO; **6/6 arquivos substituídos
  byte a byte idênticos**; tree 3.218 entradas / 2.635 blobs / `truncated:false`.
- **Veredito visual ainda pendente** — `image-9.png` continua como reprovação
  vigente. Aplicar ≠ aprovar (AA e AO também foram aplicadas e reprovadas).
- `PENDING_DECISION: 10` / `DECIDED: 0`; F9-27 `BLOCKED_NOT_RUN`;
  F9-18 `release_ready:false` com 2 P1 + 2 P2 abertos;
  F9-25 `PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`;
  F9-26 `wcag_conformance_claimed:false`; F9-14 `not_integrated`.

## Correção de inconsistência

`copy(__kazHealth())` foi citado como pendência em 11 relatórios desde a AN,
mas **não existe no código**: varredura de 464 JS/PHP/Twig e 460
`.md`/`.json`/`.txt` do HEAD encontrou **0 ocorrências em código**. Era
formulação de relatório. Este pacote entrega `tools/health-admin-ap.js` —
health **observacional, somente leitura**, com `node --check` válido — no
lugar da instrução inexistente.

Outra ressalva descoberta: `ci_status: "7/7 success"` em
`decision-matrix.json` refere-se a runs **anteriores**
(`41483802295`/`41504039830`), não ao CI de `179269fe…`.

## Conteúdo do roteiro

1. **Passo 1 (bloqueante):** veredito visual, checklist de 4 pontos, critério
   de saída e desdobramentos aprovado/reprovado.
2. **Passo 2:** health observacional + CI remoto (6 jobs do `php.yml`;
   `repo-guards` chama `repo-health.sh`; `SKIP` ≠ `PASS`).
3. **Passo 3:** pauta das 10 `PENDING_DECISION` agrupadas por risco, política
   literal de "nenhuma decisão por inferência", e o estado real de
   F9-27 / F9-18 / F9-25 / F9-26 / F9-14.
4. Dependências, passos 4 e 5 (fora do roteiro imediato) e o que não fazer.

## Validação deste pacote

- Leitura de 13 arquivos de contrato/CI do remoto, sem cache.
- Varredura de 924 arquivos para localizar `kazHealth`.
- `node --check` do snippet de health: **OK**.
- Extrato de contratos transcrito, sem interpretação.
- ZIP com CRC verificado e `.sha256`; `sha256sum -c` conferido.

## Limites

PHP WASM, Chromium headless, CI verde, health local e este roteiro **não
significam release aprovada**. CI remoto não verificado (`403`), sem
`copy(__kazHealth())` de produção (função inexistente), sem sessão
autenticada, MySQL, staging, backend/DB/migration ou ciclo
salvar–Publicar. `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`,
F9-18 `release_ready:false`.

**O passo 1 depende do usuário.** Sem ele, nenhum dos passos seguintes
autoriza conclusão de release.

<!-- FIM: RELATORIO-FINAL-AQ.md -->


<!-- INICIO: RELATORIO-FINAL-AT.md (3677 bytes) -->

# Relatório Final — Frente AT (avanço do passo 2)

**Data:** 2026-11-06 · **HEAD:** `bfe9eec7aae82dc72c7054bcb46dbd901307d7c5`
**Tipo:** pacote de **registro de avanço**, não frente de código.

## Motivo

O usuário aplicou a Frente **AQ** no GitHub e pediu para prosseguir. O roteiro
define o passo 1 (veredito visual) como bloqueante e dependente dele; os
passos 2 e 3 vêm depois. O que era possível avançar **sem** sessão
autenticada, **sem** decidir por inferência e **sem** escrever no GitHub era
o **passo 2 — CI remoto**. Foi isso que esta frente fez.

## Resultados

1. **Aplicação da AQ confirmada:** HEAD `bfe9eec7…` (`docs(aq-acceptance):
   add full planning package and health tool`), **+6 / −0 / ~0** vs
   `179269fe…`, tree 3.224 entradas / 2.641 blobs / `truncated: false`.
   **10/10 arquivos do pacote AQ idênticos** no remoto (6 novos + 4
   evidências PNG já existentes da AP, com blob git idêntico).
2. **CI remoto destravado:** o `403 Must have admin rights` registrado antes
   aplicava-se a outro endpoint. `/actions/runs` e
   `/commits/<sha>/check-runs` responderam **sem token**.
   - HEAD `bfe9eec7…`: run **`42217429359`**, `completed / success`,
     **7/7 check-runs success**.
   - AP `179269fe…`: run `42212306218`, `completed / success`, 7/7.
   - Checks: `php-lint`, `node-deps (22)`, `static-analysis`, `repo-guards`,
     `node-deps (20)`, `docs-links`, `composer`.
3. **Ressalva preservada:** o `ci_status: "7/7 success (runs 41483802295 /
   41504039830)"` do `decision-matrix.json` continua sendo de runs antigos;
   o contrato **não** foi reescrito.

## Conteúdo do pacote

| Arquivo | Papel |
|---|---|
| `RELATORIO-FINAL-AT.md` / `INDICE-AT.md` | Este relatório e o índice |
| `Roteiro-Proximos-Passos-AP.md` | **Atualizado**: novo HEAD, CI verificado, passo 2 com critério de saída parcial |
| `Protocolo-Aceitacao-Visual-AP.md` | **Atualizado**: AQ aplicada, CI 7/7, health pendente |
| `docs/Registro-CI-Remoto-HEAD-bfe9eec7.md` | Evidência, aplicação da AQ, ressalvas |
| `evidencia/ci-remoto-bfe9eec7.json` | Dados estruturados coletados da API |
| `tools/verify-ci-evidencia.cjs` | Verificador Node puro da evidência |

Aplicar este pacote **substitui 2 documentos** (roteiro e protocolo) e
**acrescenta 5**; nenhum arquivo de código, contrato, manifesto ou workflow
é tocado.

## Validação

- 10/10 arquivos do AQ comparados byte a byte com `raw/<SHA>`.
- 4 blobs git das evidências PNG recalculados localmente e conferidos.
- 3 endpoints de CI consultados, respostas parseadas e consolidadas em JSON.
- `tools/verify-ci-evidencia.cjs` executado sobre o pacote.
- `node --check` em todos os `.cjs`/`.js`; ZIP com CRC e `.sha256`;
  `sha256sum -c` conferido.

## O que continua pendente

| Passo | Estado |
|---|---|
| **1 — Veredito visual da AP** | ⏳ **bloqueante, depende do usuário**; `image-9.png` segue vigente |
| **2 — Health do Admin real** | ⏳ `tools/health-admin-ap.js` já no GitHub; falta colar no console autenticado |
| **2 — CI remoto** | ✅ concluído nesta frente |
| **3 — 10 `PENDING_DECISION`** | ⏳ pacote **AR** pronto, não aplicado; decisões são do responsável |
| **4 — Backend / F918** | ⏳ pacote **AS** pronto, não aplicado; exige staging autorizado |

## Limites

CI verde, PROBE, Chromium headless e health local **não significam release
aprovada**. Sem veredito visual, sem `copy(__kazHealth())` (função
inexistente), sem CI de produção autenticada, sem backend/DB/migration,
sem staging e sem deploy. `release_approved:false` ·
F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready:false`. Nenhuma escrita do
agente no GitHub.

<!-- FIM: RELATORIO-FINAL-AT.md -->


<!-- INICIO: RELATORIO-FINAL-AU.md (3894 bytes) -->

# Relatório Final — Frente AU (registro do veredito APROVADO)

**Data:** 2026-11-06 · **HEAD do estado avaliado:**
`1b7724a10314cf5b5fee479a328651b21843d06a`
**Tipo:** pacote de **registro de aceitação visual**, não frente de código.

## O que aconteceu

O usuário respondeu explicitamente **`aprovado`** à pergunta estruturada de
próximo passo, após avaliar a Frente AP no Admin real autenticado. Era o
**único bloqueio real** do roteiro: desde a AO, `image-9.png` valia como
reprovação vigente.

## O que foi registrado

| Item | Antes | Agora |
|---|---|---|
| Veredito da AP | aguardando | ✅ **APROVADO** |
| `image-9.png` | reprovação vigente | registro histórico **superado** |
| `image-10.png` | referência | referência **atendida** |
| Escopo "editar somente o BDREditor" | em correção | **fechado e congelado** |
| Passo 1 do roteiro | bloqueante | ✅ **CONCLUÍDO** |

Os quatro critérios do checklist foram considerados satisfeitos: sidebar azul
`#0d6efd` sem verde; Admin como antes da AO; BDREditor escuro com canvas
branco e próximo do KAZTheme-Editor; nada alterado fora dele.

Apoio técnico (não substitui a decisão): A/B/C com AO 54 divergências vs AP
0 fora do editor; PROBE 53/0, Chromium 41/0, validação 117/0; instrumento
`veredito_visual_ap.js` 6/6 na AP e 3/6 na AO; CI remoto 7/7 success; 6/6
arquivos da AP idênticos no remoto.

## O que **NÃO** mudou — e é o ponto mais importante

| Item | Permanece |
|---|---|
| `release_approved` | **`false`** |
| F9-27 | **`BLOCKED_NOT_RUN`** / `runtime_tests_executed: false` |
| F9-18 | **`release_ready: false`** / F918-01…04 abertos |
| F9-26 | `wcag_conformance_claimed: false` |
| `decision-matrix.json` | **10 `PENDING_DECISION` / 0 `DECIDED`** |
| F9-25 | `patterns_persistence: PROPOSAL_PENDING` |

**Aprovação visual ≠ release.** Nenhum `PENDING`/`SKIP`/`NOT_RUN`/
`STATIC_ONLY`/`BLOCKED_NOT_RUN` foi convertido em `PASS`. Nenhum hash,
`base_head`, contrato, manifesto, workflow, `schema.sql`, JS de ~600 KB, DOM,
Twig, renderer ou catálogo foi alterado. **Nenhuma escrita do agente no
GitHub** e nenhum deploy.

## Conteúdo do pacote

| Arquivo | Papel |
|---|---|
| `RELATORIO-FINAL-AU.md` / `INDICE-AU.md` | Este relatório e o índice |
| `Registro-Veredito-Visual-AP.md` | **Novo** — decisão, decidido por, data, HEAD, efeitos, limites |
| `Protocolo-Aceitacao-Visual-AP.md` | **Substitui** — APROVADO, `image-9` superada, estado dos passos |
| `Roteiro-Proximos-Passos-AP.md` | **Substitui** — passo 1 concluído, dependências atualizadas |
| `Pauta-Decisoes-D01-D10.md` | **Nova** — pauta de 1 página para o passo 3 |
| `tools/verify-veredito.cjs` | Verificador: registro correto e **nenhuma** promoção indevida de status |

Aplicar: **3 substituições + 4 novos**. Zero arquivos de código ou contrato.

## Validação

- Leitura do `decision-matrix.json` do HEAD para gerar a pauta fiel.
- Verificador Node puro executado sobre o pacote.
- `node --check` em todos os `.cjs`/`.js`; ZIP com CRC e `.sha256`;
  `sha256sum -c` conferido.
- Conferência de que entre `179269fe…` (AP) e `1b7724a1…` (estado avaliado)
  **nenhum arquivo de `dev/` mudou** — o comportamento visual é o da AP.

## Próximos passos — todos dependentes do usuário

| Passo | Estado |
|---|---|
| 2 — CI remoto | ✅ 7/7 success |
| 2 — Health do Admin real | ⏳ colar `tools/health-admin-ap.js` no console |
| 3 — Decisões D01–D10 | ⏳ pacote **AR** + pauta prontos, sem conflito; decisões são do responsável |
| 4 — Verificação F918 / runtime | ⏳ pacote **AS** pronto, sem conflito; exige staging autorizado |
| 5 — Escopo extra | vetado até decisão explícita |

**Não há próxima frente de código a ser entregue pelo agente** sem uma dessas
ações. CI verde, health local, PROBE e Chromium headless **não significam
release aprovada**.

<!-- FIM: RELATORIO-FINAL-AU.md -->


<!-- INICIO: RELATORIO-FINAL-AV.md (3898 bytes) -->

# Relatório Final — Frente AV (diagnóstico e restauração do CI)

**Data:** 2026-11-06 · **HEAD diagnosticado:**
`accb625768424e2deae4b6054721602a64097323`
**Tipo:** pacote de **correção de integridade do repositório** (documentação
e manifestos), não frente de código.

## Por que esta frente, e não o passo 3

Ao reauditar o HEAD após a aplicação da Frente AU, encontrei que o mesmo
commit que registrou o veredito **também removeu 41 arquivos legados F11** e
deixou o **CI vermelho**: `docs-links` e `repo-guards` falharam. Isso é mais
urgente do que o dossiê de evidências D01–D10, que fica para depois.

## Achados

| Item | Resultado |
|---|---|
| Aplicação da AU | ✅ +6 arquivos (inclui `Pauta-Decisoes-D01-D10.md` e `Registro-Veredito-Visual-AP.md`) |
| Remoções no mesmo commit | **41**: 14 `CHECKSUMS*.txt`, 15 `F11-*-INDICE.md`, 12 relatórios/índices/`CONTRIBUTING.md` |
| CI | 5 checks ✅ · **`docs-links` ❌** · **`repo-guards` ❌** (run `42232750836`) |
| `repo-guards` | `verify-checksums.sh` exige ≥1 manifesto; com 0 → `FAIL nenhum CHECKSUMS*.txt encontrado` |
| `docs-links` | `docs/README.md` com **7 links mortos** para índices F11 removidos |
| Restauro parcial (29 arquivos) | ainda **2 FAIL** — `CONTRIBUTING.md` é citado por `CHECKSUMS.txt` e `CHECKSUMS-F11-04.txt` |

## Correção verificada (simulação local)

Réplica fiel do HEAD: **2.611 blobs / 42.833.718 bytes / 0 divergentes**.
Restaurando os **41 arquivos** do commit pai `aeafbb04…`:

- `tools/verify-checksums.sh` → **rc=0**, 14 manifestos, **142 arquivos
  conferidos, 0 divergentes**;
- `tools/repo-health.sh` → **rc=0**, **6 PASS / 0 FAIL / 0 SKIP**,
  `COERENTE (guardas estaticos)`;
- **0 links Markdown quebrados**.

## Conteúdo do pacote

| Grupo | Conteúdo |
|---|---|
| `RELATORIO-FINAL-AV.md` / `INDICE-AV.md` | Este relatório e o índice |
| `docs/Diagnostico-CI-Vermelho-accb6257.md` | Causa raiz, prova da correção, duas vias de decisão |
| `evidencia/lista-arquivos-removidos.txt` | Os 41 paths |
| `evidencia/sha256-restaurados.txt` | SHA-256 de cada arquivo restaurado |
| `evidencia/guardas-antes.txt` / `guardas-depois.txt` | Logs reais da simulação |
| `tools/verify-restauracao.cjs` | Verificador Node puro |
| **41 arquivos restaurados** | Cópias fiéis do pai `aeafbb04…`, nos paths originais |

**Via A (recomendada, entregue):** restaurar os 41 — não relaxa nenhum
guarda, não altera `docs/README.md`, workflow, hashes nem contratos.
**Via B (não recomendada):** manter a limpeza exigiria remover 7 links,
editar 2 manifestos e **fazer `verify-checksums.sh` tolerar zero manifestos**,
o que desliga uma verificação de integridade — só com autorização explícita.

## Validação

- Download fiel da tree do HEAD (0 divergentes) e dos 41 arquivos do pai.
- Guardas executados **antes** e **depois** do restauro, com logs anexados.
- `node --check` + execução de `tools/verify-restauracao.cjs`.
- ZIP com CRC verificado e `.sha256`; `sha256sum -c` conferido.

## Limites

**Simulação local ≠ CI remoto verde** — após aplicar, conferir novo run em
Actions. Nenhuma escrita do agente no GitHub, nenhum deploy, nenhuma
alteração de contrato congelado, nenhuma conversão de
`PENDING`/`SKIP`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão das
10 `PENDING_DECISION`.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
F9-18 `release_ready: false`. **Simulação local, guardas verdes e CI verde não
significam release aprovada.** O **veredito APROVADO da AP permanece válido**:
nenhum arquivo de `dev/` mudou entre a AP e este HEAD — a falha é de
documentação/manifestos, não de comportamento visual.

**Próximos passos adiados:** health do Admin real (2), dossiê/pauta D01–D10
(3, pacote AR pronto e sem conflito), verificação F918/staging (4, pacote AS
pronto e sem conflito).

<!-- FIM: RELATORIO-FINAL-AV.md -->


<!-- INICIO: RELATORIO-FINAL-AX.md (4220 bytes) -->

# Relatório Final — Frente AX (plano das Ferramentas internas)

**Data:** 2026-11-06 · **HEAD:** `481b690209ec1a1caf4d9f334cef6012d4597653`
**Tipo:** consolidação de decisões + inventário + plano. **Nenhum código
aplicado**, nenhuma escrita no GitHub.

## Contexto

Após a Frente AV, o **CI voltou ao verde: 7/7 success**. O usuário aplicou o
AV e propôs uma frente nova: cadastrar BDREditor e KAZTheme-Editor como
**ferramentas internas** na página Ferramentas, com versão/atualizações, no
mesmo padrão das externas Fluxa/Vertex/Solis/Quotia, além de renomeá-las.

## Levantamento de código

A página Ferramentas é o CRUD `tool/tool` (Admin) + grade pública no catálogo.
**Fluxa, Vertex, Solis e Quotia são linhas no banco**, não módulos — o doc
`F8-09-Ferramentas-Conectadas.md` diz explicitamente que a tarefa não
implementa essas ferramentas.

`dev/install/sql/schema.sql:225-243` — `rms_tool` tem `tool_id`, `image`,
`vendor`, `url`, `status`, `sort_order`, `date_added`; `rms_tool_description`
tem `name`, `description`. **Não há `version`, `date_modified` nem changelog** —
e nenhuma tela do repositório exibe versão das ferramentas externas.

## Decisões do usuário (via perguntas estruturadas)

| # | Escolha |
|---|---|
| 1 | **`KAZEditor-Theme`** (corrigido de "KAZEditor-Thene") |
| 2 | **R2 — rename global** |
| 3 | **V2 — JSON de metadados versionado**, sem migration |
| 4 | **P2 — seção "Ferramentas internas" só no Admin** |

Nomes finais: **`BDREditor-Content`** · **`KAZEditor-Theme`**.

## Inventário do R2

**179 arquivos / 762 ocorrências**, distribuídos em docs, CSS, traduções,
tools/probes, JS/PHP runtime, Twig, ADRs e réplica `tree/`.

- **13 de 14 manifestos** `CHECKSUMS*.txt` precisariam ser regenerados.
- Os **3 contratos congelados** de `frozen_artifacts_sha256` **não** citam os
  nomes → `verify-contract-freeze.py` pode permanecer verde. Isso **remove** o
  conflito de governança mais grave.
- Probes com strings literais: `kaz-bdre-visual-probe.php` (18),
  `kaz-bdre-total-probe.php` (22), além de `.cjs`/`.py`/`.sh` de verificação.
- Arquivos com o termo no próprio path → renomear exige atualizar links.

## Conteúdo do pacote

| Arquivo | Papel |
|---|---|
| `RELATORIO-FINAL-AX.md` / `INDICE-AX.md` | Este relatório e o índice |
| `Plano-Frente-AX.md` | Decisões, arquitetura V2/P2, JSON, fases B1–B4, o que falta |
| `Inventario-Rename-Global.md` | 179/762, categorias, sensíveis, manifestos, contratos |
| `Analise-Viabilidade-Ferramentas-Internas.md` | Levantamento atualizado com as decisões |
| `evidencia/inventario-rename.json` | Dados brutos por arquivo |
| `tools/verify-inventario.cjs` | Verificador Node puro |

## Validação

- Árvore do HEAD lida (`3243` entradas / `2660` blobs / `truncated: false`);
  HEAD pós-AV `481b6902…` com **7/7 check-runs success**.
- Réplica de leitura: 1.346 arquivos de texto + 432 scripts + 38 `.sql`.
- Contagens reproduzíveis a partir de `evidencia/inventario-rename.json`.
- `node --check` e execução do verificador; ZIP com CRC + `.sha256`.

## Por que ainda não há código aplicável

Duas informações são do usuário e **não podem ser inferidas**:

1. As **versões iniciais** das duas ferramentas (o JSON usa
   `PENDENTE_USUARIO` de forma honesta, sem inventar número).
2. Se **R2 inclui B3** (identificadores de código: nomes de arquivos JS/PHP,
   classes, rotas, probes) ou só B1+B2 (texto, rótulos e docs).

Sem isso, gerar o pacote de código produziria um rename incompleto ou um CI
vermelho — exatamente o problema que a Frente AV acabou de corrigir.

## Limites

Nenhuma escrita no GitHub, nenhum rename, nenhuma migration, nenhuma alteração
de contrato congelado/manifesto/hash/`schema.sql`/DOM/JS de ~600 KB/renderer/
catálogo. Nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`,
nenhuma decisão das 10 `PENDING_DECISION`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · veredito **APROVADO** da AP mantido. AR e AS prontos
e sem conflito. CI verde, health local e guardas estáticos **não significam
release aprovada**.

<!-- FIM: RELATORIO-FINAL-AX.md -->


<!-- INICIO: RELATORIO-FINAL-AY.md (5826 bytes) -->

# Relatório Final — Frente AY (Parte A: Ferramentas internas no Admin)

**Data:** 2026-11-06 · **HEAD:** `a73341affb73debe3979bb470ecb5f5aab82dfe9`
**CI remoto:** 7/7 success · **Tipo:** **código aplicável** (V2 + P2), não plano.

> Escopo: implementa **somente a Parte A** decidida em `Plano-Frente-AX.md`.
> A **Parte B (rename R2 global) NÃO foi aplicada** — continua aguardando as
> duas respostas do usuário (versões iniciais e alcance B3). Nenhuma escrita do
> agente no GitHub.

## 1. O que foi implementado

Uma **seção "Ferramentas internas"** na página Ferramentas do Admin, separada
da lista de ferramentas conectadas (Fluxa/Vertex/Solis/Quotia), exibindo as
duas ferramentas com **nome, versão, data de atualização e histórico de
atualizações**.

| Arquivo | Ação |
|---|---|
| `dev/admin/model/tool/internal-tools.json` | **Novo** — fonte única de metadados versionados |
| `dev/admin/controller/tool/tool.php` | Adendo: `internalTools()` + 1 linha de injeção no `index()` |
| `dev/admin/view/template/tool/tool_list.twig` | Adendo: `<section class="internal-tools-panel">` |
| `dev/admin/view/css/admin.css` | Adendo `.internal-tools-*`, prefixo anterior **intacto** |
| `dev/admin/language/{en-us,pt-br,es-eu}/tool/tool.php` | 8 rótulos novos por idioma |
| `tools/verify-internal-tools.cjs` | **Novo** — verificador Node puro |

### Ajuste de engenharia em relação ao plano AX

O plano previa `tools/internal-tools.json`. O JSON ficou em
**`dev/admin/model/tool/internal-tools.json`** porque `tools/` é diretório de
desenvolvimento/QA e pode não existir em produção; dentro do `admin/` o
caminho é derivado de `DIR_APPLICATION` (`rtrim(DIR_APPLICATION,'/\\')`),
estável e independente da estrutura do repositório.

### Versões **não foram inventadas**

```json
{"id":"bdreditor-content","name":"BDREditor-Content","legacy_name":"BDREditor",
 "version":"PENDENTE_USUARIO","updated_at":null,"notes":[]}
```

O controller converte `PENDENTE_USUARIO` em `''` e a UI exibe
**"Não informado"** / **"Nenhuma atualização registrada."** de forma honesta.
`legacy_name` documenta a equivalência nome novo ↔ identificador antigo
enquanto a Parte B não roda.

### Segurança e fail-closed

`internalTools()` lê **somente** o JSON local (sem banco, sem rede, sem
migration) e é **fail-closed**:

- arquivo ausente/ilegível, JSON inválido, `schema != 1`, `tools` ausente →
  **lista vazia** e a seção simplesmente não é renderizada — **a página nunca
  quebra**;
- `id` validado por regex, único; `name` obrigatório (≤128);
- `version` ≤32, `updated_at` somente `AAAA-MM-DD`, `notes` até 5 linhas
  de até 255 caracteres;
- **nenhum `|raw` novo** — todo valor passa pelo escape automático do Twig;
- sem rota nova, sem ACL/CSRF novo, sem `!important`, sem redefinir `--bs-*`,
  sem alvos globais no CSS (apenas `.internal-tools-*`).

Tema ReamurCMS claro e acento azul preservados — **nada** de identidade KAZ
fora do BDREditor. O veredito **APROVADO** da AP continua vigente.

## 2. Validação (toda local; não substitui o Admin real)

| Verificação | Resultado |
|---|---|
| Árvore fiel do HEAD | **2.667 blobs / 43.003.989 bytes / 0 divergentes** |
| `php -l` (PHP **8.5.12** WASM) nos 4 `.php` | **4/4 "No syntax errors"** |
| Harness funcional de `internalTools()` | **11/11 cenários** (real, ausente, JSON inválido, schema errado, sem `tools`, id duplicado, nome vazio, versão longa, data inválida, limite de notas, HTML preservado para escape) |
| `tools/verify-checksums.sh` | **rc=0** · 14 manifestos · 142 arquivos · 0 divergentes |
| `tools/verify-deps-layout.sh` | 8 PASS / 0 FAIL |
| `tools/verify-ci-workflow.sh` | 31 PASS / 0 FAIL |
| `tools/verify-f11-01.sh` | 17 PASS / 0 FAIL |
| `tools/verify-contract-freeze.py` | **COERENTE** — nenhum contrato alterado sem decisão |
| `tools/release-readiness-report.sh` | rc=0 |
| **`tools/repo-health.sh`** | **rc=0 · 6 PASS / 0 FAIL / 0 SKIP · COERENTE** |
| `tools/verify-internal-tools.cjs` | verificação completa do pacote |
| Twig | `if/endif` e `for/endfor` balanceados, 0 `\|raw` na seção nova |
| Links Markdown | inalterados |

**Nenhum dos 8 alvos aparece em qualquer `CHECKSUMS*.txt`** — foi por isso que
a implementação pôde seguir sem regenerar manifesto (lição da Frente AV).
**Nenhum arquivo de `dev/install/sql/`, contrato congelado, manifesto, hash,
`schema.sql`, DOM, JS de ~600 KB, renderer ou catálogo público foi alterado.**

## 3. O que NÃO foi feito

- **Parte B — rename R2 global:** nenhuma substituição, nenhum arquivo
  renomeado, nenhum manifesto regenerado. O inventário permanece
  **179 arquivos / 762 ocorrências**.
- Nenhuma migration, nenhuma coluna nova, nenhuma tabela, nenhuma rota,
  nenhuma execução de runtime/MySQL/staging/deploy.
- Nenhuma conversão de `PENDING`/`SKIP`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`,
  nenhuma decisão das **10 `PENDING_DECISION`**, nenhuma declaração de release.
- **Nenhuma escrita do agente no GitHub.**

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · veredito visual **APROVADO** da AP mantido.
CI verde, health estático, PHP WASM e harness **não significam release
aprovada** nem substituem a aceitação visual no Admin real.

## 4. Ainda depende de você

1. **Versões iniciais** de `BDREditor-Content` e `KAZEditor-Theme`
   (e `updated_at`/`notes`, se quiser). Basta me dizer que eu atualizo só o
   JSON — sem tocar em código.
2. **R2 inclui B3?** (identificadores de código: nomes de arquivos JS/PHP,
   classes, rotas, probes, checkers e2e) ou apenas B1+B2 (texto/rótulos/docs)?
3. **Aceite visual** da nova seção no Admin real após aplicar.

Pacotes **AR** e **AS** seguem prontos e sem conflito com o HEAD atual.

<!-- FIM: RELATORIO-FINAL-AY.md -->


<!-- INICIO: RELATORIO-FINAL-BA.md (3436 bytes) -->

# Relatório Final — Frente BA (versões iniciais 1.0.0)

**Data:** 2026-11-06 · **HEAD:** `997173803b3e70b114efe2f5d91a47e7423ba8e6`
**CI remoto:** 7/7 success · **Tipo:** atualização **somente de metadados**.

## Decisão do usuário

> *"Ambos em versões iniciais pois as versões vão contar a partir de agora
> então ambos em versão 1.0.0"*

| Ferramenta | `version` | `updated_at` | `notes` |
|---|---|---|---|
| `BDREditor-Content` | **`1.0.0`** | `2026-11-06` | `[]` |
| `KAZEditor-Theme` | **`1.0.0`** | `2026-11-06` | `[]` |

`updated_at` usa **a data de hoje** por "a partir de agora". `notes` ficou
**vazia** — não inventei histórico de atualizações; a UI continua exibindo
"Nenhuma atualização registrada." de forma honesta. Se quiser outra data ou
uma nota inicial, atualizo só o JSON.

## O que mudou — exatamente 2 arquivos

| Arquivo | Ação |
|---|---|
| `dev/admin/model/tool/internal-tools.json` | `PENDENTE_USUARIO` → `1.0.0`; `null` → `2026-11-06` |
| `tools/verify-internal-tools.cjs` | asserção atualizada: antes exigia `PENDENTE_USUARIO`, agora valida `1.0.0`, formato SemVer, data de início e ausência de pendência |

**Por que o verificador também muda:** a Frente AY o entregou com a checagem
"versões não inventadas". Se só o JSON fosse atualizado, esse verificador
passaria a **falhar** no repositório. Ele não roda em nenhum job do CI nem no
`repo-health`, mas foi atualizado por coerência.

**O controller NÃO muda.** `internalTools()` da AY já aceita `1.0.0`
(≤32 caracteres), valida `AAAA-MM-DD` e mantém o fallback para
`PENDENTE_USUARIO` — que continua útil se algum registro futuro ficar sem
versão. Nenhum PHP, Twig, CSS ou idioma foi tocado.

## Validação

| Verificação | Resultado |
|---|---|
| JSON válido, `schema: 1`, 2 ferramentas | ✅ |
| `php-wasm-cli` harness de `internalTools()` com o JSON novo | ✅ `version="1.0.0"`, `updated="2026-11-06"`, 2 itens |
| `node --check` + `tools/verify-internal-tools.cjs` na réplica | ✅ 0 FALHAS |
| `verify-checksums.sh` / `repo-health.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes · **6 PASS / 0 FAIL / COERENTE** |
| Nenhum dos 2 alvos em `CHECKSUMS*.txt` | ✅ nenhum manifesto a regenerar |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## O que NÃO mudou

- **Parte B — rename R2 global:** nenhuma substituição, nenhum arquivo
  renomeado, nenhum manifesto regenerado. Segue o inventário
  **179 arquivos / 762 ocorrências**.
- **Aceite visual** da seção "Ferramentas internas" no Admin real: ainda
  pendente.
- Nenhuma migration, contrato congelado, hash, `schema.sql`, workflow, DOM,
  JS de ~600 KB, renderer ou catálogo público alterado.
- Nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`,
  nenhuma decisão das 10 `PENDING_DECISION`, nenhuma declaração de release.
- **Nenhuma escrita do agente no GitHub.**

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · veredito visual **APROVADO** da AP mantido.
CI verde e guardas estáticos **não significam release aprovada**.

## Ainda depende de você

1. **Alcance do R2:** B1 (UI/rótulos/docs atuais), B1+B2 (também renomear
   arquivos de docs F9/F11 e contratos por nome) ou B1+B2+B3 (incluindo
   identificadores de código).
2. **Aceite visual** da seção no Admin real.

Pacotes **AR** e **AS** seguem prontos e sem conflito.

<!-- FIM: RELATORIO-FINAL-BA.md -->


<!-- INICIO: RELATORIO-FINAL-BB.md (4694 bytes) -->

# Relatório Final — Frente BB (rename R2 · Fase B1)

**Data:** 2026-11-06 · **HEAD:** `700d104cc6717e79c1677397ffb771491b456d1a`
**CI remoto:** 7/7 success · **Tipo:** **código aplicável** — nomes visíveis na UI.

## Por que avancei sem a resposta do formulário

Você aplicou a BA e pediu para prosseguir pela quarta vez consecutiva. A
pergunta sobre o alcance do R2 (B1 / B1+B2 / B1+B2+B3) estava travando o
trabalho. **B1 é subconjunto de todas as três opções** — executá-lo não decide
nada por inferência: apenas adianta o que é comum, deixando B2 e B3 para a sua
decisão explícita.

## O que foi feito

Substituição dos nomes **onde o usuário lê**, nos **9 arquivos do Admin**:

| De | Para |
|---|---|
| `BDREditor` | **`BDREditor-Content`** |
| `KAZTheme-Editor` | **`KAZEditor-Theme`** |
| `KAZTheme` (isolado, 2 rótulos) | **`KAZEditor-Theme`** |

**66 ocorrências → 0 remanescentes.**

| Arquivo | Ocorrências |
|---|---|
| `dev/admin/view/template/design/kaz_theme_editor.twig` | 8 |
| `dev/admin/view/template/cms/page_form.twig` | 1 |
| `dev/admin/view/template/cms/page_content_form.twig` | 2 |
| `dev/admin/language/{pt-br,es-eu,en-us}/cms/page.php` | 5 / 4 / 4 |
| `dev/admin/language/{pt-br,es-eu,en-us}/blog/blog.php` | 14 / 14 / 14 |

Foram atualizados: o nome na topbar do editor de temas, textos de painel,
`aria-label` de toolbar e canvas, mensagens de validação de documento e strings
de ajuda/intro nos três idiomas, além de comentários de código que citavam o
nome antigo.

### Invariantes estruturais (verificados arquivo a arquivo)

- **0** ocorrências em chaves `$_['...']` — nenhuma chave de idioma mudou;
- `class="..."`, `id="..."`, atributos `data-*` e contagem de `|raw`
  **idênticos** ao original;
- sem `BDREditor-Content-Content`, `KAZEditor-Theme-Editor` ou
  `KAZEditor-Thene` (ordem de substituição controlada);
- nenhum dos 9 arquivos aparece em qualquer `CHECKSUMS*.txt` → **nenhum
  manifesto a regenerar**.

## O que foi PRESERVADO (B2 e B3 não rodaram)

| Preservado | Motivo |
|---|---|
| Documentos históricos `docs/F9-*`, `docs/F11-*`, `RELATORIO-FINAL-*`, ADRs | reescrever história exigiria decisão explícita (B2) |
| Contratos por nome: `BDREditor-Table-Contract.json`, `-Verification.json`, `-Base-Hashes.json` | contratos congelados / `frozen_artifacts_sha256` |
| Nomes de arquivos e identificadores: `blog-block-editor-kaz.js`, `kaz-theme-editor.js`, `kaz_theme_editor.twig`, `kaz-theme-editor-ergonomia.js` | B3 — alto risco (`<script src>`, probes, 61 checkers e2e) |
| 14 manifestos `CHECKSUMS*.txt`, hashes, `tools/decision-matrix.json` | integridade do CI |
| `dev/catalog/view/template/partial/theme-scene-block.twig` | catálogo público; era só comentário Twig invisível |
| `dev/install/sql/**`, `schema.sql`, `.github/workflows/**`, DOM, renderer, JS de ~600 KB | fora de escopo |

Inventário original do R2: **179 arquivos / 762 ocorrências**. Após o B1
restam **113 ocorrências** em documentos/contratos/identificadores para B2+B3.

## Validação

| Verificação | Resultado |
|---|---|
| `php -l` (PHP 8.5.12 WASM) nos 6 `.php` de idioma | ✅ 6/6 |
| Twig: `if/endif`, `for/endfor`, `block/endblock` balanceados | ✅ 3/3 |
| Invariantes DOM/chaves/`|raw` | ✅ 9/9 arquivos |
| `tools/verify-rename-b1.cjs` | ✅ 0 FALHAS |
| `verify-checksums.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes |
| `repo-health.sh` na réplica fiel | ✅ **6 PASS / 0 FAIL / COERENTE** |
| `verify-contract-freeze.py` | ✅ COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## Limites

Nenhuma escrita do agente no GitHub, nenhum migration, contrato congelado,
hash, `schema.sql`, workflow, DOM, renderer ou catálogo alterado. Nenhuma
conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão
das **10 `PENDING_DECISION`**, nenhuma declaração de release.
**Aceite visual** da seção "Ferramentas internas" e do B1 no Admin real segue
**pendente** — não foi declarado aprovado.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · veredito **APROVADO** da AP mantido. CI verde,
guardas estáticos e PHP WASM **não significam release aprovada**.

## Próximos passos (dependem de você)

1. **B2** — renomear arquivos de `docs/` e contratos por nome (regenera 13
   manifestos e atualiza links) — sim ou não?
2. **B3** — renomear identificadores de código — sim ou não?
3. **Aceite visual** no Admin real: seção + nomes novos.
4. Passos do roteiro: health do Admin real (2), decisões D01–D10 com o pacote
   **AR** (3), verificação F918 com o **AS** e staging (4).

<!-- FIM: RELATORIO-FINAL-BB.md -->


<!-- INICIO: RELATORIO-FINAL-BC.md (5601 bytes) -->

# Relatório Final — Frente BC (KAZEditor-Theme · import por arquivo, recorte A)

**Data:** 2026-11-06 · **HEAD:** `df0d3d34e4903a948fcf5bdfa19823c072ef41a0`
**CI remoto:** ✅ 7/7 success · **Decisões do usuário:** recorte **A** + **dados
validados, sem código executável**.

## 1. Ponto de partida (levantamento)

O pedido era "importar temas e templates Twig e ThreeJS, com gestão estilo
WordPress". A auditoria mostrou que **o subsistema já é bem completo**:

- `design/theme` com lista, `edit`, `preview`, `restore` (revisões), `export`
  e `import` (sempre como **rascunho**, nunca publica);
- `rms_design_theme` + `rms_design_theme_revision`, no máximo **um tema ativo**;
- `ThemePackage` com `FORMAT=reamurcms-theme`, `VERSION=1`, **512 KiB**,
  **checksum SHA-256** com `hash_equals`, fail-closed, child-theme até 5 níveis;
- **25 fontes Twig allowlistadas** (`TEMPLATE_KEYS`) como strings no JSON;
- ThreeJS como **dados** (`three` + `kaz_scene` + `ScenePresetRegistry`);
- tokens, condições, seções, componentes, scheduler, mídia, sites, snapshot.

E `docs/GUIA-PACOTES-CHILD-THEME.md` proíbe textualmente **upload em disco,
`eval`, `call_user_func`, HTML, `|raw` novo e código executável**. O
`theme-package.js` **já lia um `.json` local** via `FileReader` — só faltava
UX e feedback.

## 2. O que foi implementado

**Recorte A**: seleção/arraste de arquivo com prévia e validação no cliente,
**sem alterar o backend**. O POST continua enviando `theme_package` (string)
para `design/theme.import`, e **toda** a validação de segurança permanece no
servidor (`ThemePackage::decode()`: tamanho, formato, versão, identidade,
`hash_equals` do checksum, allowlists, ACL `modify`, CSRF).

| Arquivo | Ação |
|---|---|
| `dev/admin/view/js/theme-package.js` | reescrito: drag-and-drop, prévia (nome/slug/formato/pai/arquivo/tamanho), validação fail-closed no client, mensagens de erro, limpeza ao editar manualmente |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | zona de drop + `<dl>` de prévia + `role="status" aria-live="polite"`; `accept` aceita `.reamurtheme.json` |
| `dev/admin/controller/design/theme.php` | **1 linha**: injeta as 10 novas chaves de idioma no `$data` |
| `dev/admin/language/{en-us,pt-br,es-eu}/design/theme.php` | 10 rótulos/mensagens por idioma |

### Validação no cliente (espelho do contrato, não substituto)

Recusa com mensagem traduzida: arquivo **> 512 KiB**, extensão/MIME inválidos,
JSON inválido, `format != reamurcms-theme`, `version != 1`, `name` vazio,
`slug` fora de `^[a-z0-9_-]{1,48}$`, `config` ausente ou `checksum` sem 64 hex
minúsculos. **O checksum não é calculado no cliente** — isso é do servidor.

### Superfície de segurança

Sem `fetch`, `XMLHttpRequest`, `eval`, `Function`, `innerHTML`, `outerHTML`,
`document.write`, `localStorage`, `sessionStorage`, `import()` ou `require()`.
A prévia usa **apenas `textContent`**. Nenhum arquivo é escrito no servidor:
`FileReader` lê no navegador e preenche a mesma `<textarea>`. **Sem JS, colar o
JSON continua funcionando.** Nenhum `|raw` novo, nenhum `<script>`, nenhum
iframe novo, nenhuma migração, nenhuma ACL nova, nenhuma rota nova.

**Não foi feito** (decisão "dados validados"): upload de ZIP com `.twig`/`.js`
reais, Twig arbitrário, JS ThreeJS do usuário, escrita em disco, sandbox Twig,
ampliação de `TEMPLATE_KEYS`, migration ou endpoint novo.

## 3. Validação

| Verificação | Resultado |
|---|---|
| `node --check` no JS | ✅ |
| `php -l` (PHP **8.5.12** WASM) no controller + 3 idiomas | ✅ **4/4** |
| **Harness jsdom funcional** | ✅ **31 OK / 0 FALHAS** — arquivo válido, prévia completa, pai ausente, >512 KiB, extensão inválida, JSON inválido, 5 pacotes malformados, drag-and-drop, edição manual, 10 checagens de superfície |
| Twig `if/endif` e `for/endfor` balanceados, 0 `\|raw` novo | ✅ |
| Controller: `import()`, ACL, CSRF e `decode()` **intactos** | ✅ |
| Idiomas: prefixo original byte a byte preservado | ✅ 3/3 |
| `tools/verify-theme-import-file.cjs` | ✅ 0 FALHAS |
| `verify-checksums.sh` / `repo-health.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes · **6 PASS / 0 FAIL / COERENTE** |
| `verify-contract-freeze.py` | ✅ COERENTE |
| **Nenhum dos 6 alvos em `CHECKSUMS*.txt`** | ✅ nenhum manifesto a regenerar |

## 4. Limites

Nenhuma escrita do agente no GitHub, nenhuma migration, contrato congelado,
hash, `schema.sql`, `TEMPLATE_KEYS`, `ThemePackage`, manifesto ou workflow
alterado. Nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`,
nenhuma decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING` (F9-25), nenhuma declaração de
release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, jsdom e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Passo 2 ainda aberto:** o guia de health foi aplicado, mas o JSON do Admin
real **não foi colado** — o health observacional segue pendente.

## 5. Próximos passos (dependem de você)

1. **Aceite** do import por arquivo no Admin real.
2. **Recorte B** — ampliar `TEMPLATE_KEYS` (diga quais templates).
3. **Recorte D** — gestão estilo WordPress (screenshot, duplicar, excluir).
4. **Recorte C** — ZIP com arquivos reais: exige decisão explícita de
   segurança, sandbox Twig, ACL dedicada e revisão de migration (hoje vetado).
5. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).

<!-- FIM: RELATORIO-FINAL-BC.md -->


<!-- INICIO: RELATORIO-FINAL-BE.md (6484 bytes) -->

# Relatório Final — Frente BE (CRUD de temas + Importar tema)

**Data:** 2026-11-06 · **HEAD auditado:** `7646c98405207cedf36a1fb86685046bd5e6f33c`
**CI remoto:** ✅ 7/7 success · **Gatilho:** `image-11.png` + pedido do usuário.

## 1. O que a imagem mostrou

A lista "Temas do Catálog" tinha apenas **"Novo tema"** e **"Editar"**. Faltavam
**ativar/desativar**, **excluir** e a opção **"Importar tema"** ao lado de
"Novo tema".

## 2. Levantamento: o que JÁ existia

| Item | Situação |
|---|---|
| `theme.activate` + `Model::activate()` | ✅ já existia — publica o rascunho e mantém **um único tema ativo** |
| Botão "Ativar" no Twig | ✅ já existia, mas **condicionado a `not theme.status`** — invisível na imagem porque `testetema` é o ativo |
| `theme.import` com `id=0` | ✅ já **cria um tema novo** (`save(0,…)`), mas só era alcançável de dentro do editor de um tema existente |
| `theme-package.js` (BC) | ✅ já lia `.json` com `FileReader`, prévia e validação no cliente |
| `deactivate` / `delete` | ❌ **não existiam** nem no controller nem no model |

**Fallback verificado:** `Catalog\Model\Common\Theme::getEffectiveTheme()` devolve
`['published'=>[],'draft'=>[],'slug'=>'']` quando nenhum tema está ativo — o
Catálogo passa a usar os padrões. **Desativar não quebra o catálogo.**

## 3. O que foi implementado

**6 arquivos** (nenhum em `CHECKSUMS*.txt`).

### Lista — `dev/admin/view/template/design/theme_list.twig`

- **"Importar tema"** ao lado de "Novo tema", ancorado no card de import;
- **card de import** na própria lista: textarea + escolha/arraste de `.json`,
  prévia (nome, slug, formato, pai, arquivo, tamanho), `role="status"
  aria-live="polite"`, CSRF, e `<details>` para recolher. Reaproveita o
  `theme-package.js` da BC **sem alterá-lo**;
- coluna **"Ações"** com, por linha:
  - **Editar** (inalterado);
  - tema **ativo** → **Desativar** + **Excluir desabilitado** com dica
    "Desative o tema antes de excluí-lo.";
  - tema **inativo** → **Ativar** + **Excluir** com **confirmação em duas
    etapas** via `<details>` (sem JS, sem `confirm()`);
- `notice`/`error` no topo para feedback de cada ação.

### Controller — `dev/admin/controller/design/theme.php`

- `index()`: injeta 43 chaves de idioma, `import_url`, `package_format`/
  `package_version`, URLs `deactivate`/`delete` por linha, feedback por query
  string com **allowlist** de códigos, e carrega `theme-package.js`;
- **`deactivate()`** e **`delete()`**: **POST**, ACL `modify`, CSRF, redirect
  para a lista com `ok=…`/`error=delete`;
- `import()` com **`id=0` passa a voltar para a lista** (`ok=import` /
  `error=import&code=…`). Para **`id>0` o comportamento é idêntico** ao
  anterior (`import_ok=1` / `import_error=…` no editor);
- `activate()` ganhou apenas o feedback `ok=activate`; a lógica de publicação
  não mudou.

### Model — `dev/admin/model/design/theme.php`

- **`deactivate(int $id, int $userId)`**: zera `status` e registra revisão.
  **Preserva `config`/`published_config`** — desativar não apaga conteúdo;
- **`delete(int $id)`**: remove o tema **e suas revisões**, mas **recusa
  excluir tema ativo** e tema inexistente (fail-closed). Sem `ALTER`/`CREATE`/
  `DROP` — **nenhuma migration, nenhuma coluna nova**.

### Idiomas — 3 arquivos

14 chaves novas por idioma (pt-br, en-us, es-eu). `button_activate` passou de
"Publicar"/"Publish"/"Publicar" para **"Ativar"/"Activate"/"Activar"**,
atendendo ao vocabulário do pedido; a dica `text_activate_help` deixa claro
que ativar **publica o rascunho**.

## 4. Decisões de segurança (explícitas no código)

| Decisão | Motivo |
|---|---|
| **Não excluir tema ativo** | impede deixar o Catálogo sem config publicada por acidente; exige desativar antes |
| Exclusão em **2 etapas** | evita clique único destrutivo, sem JS inline |
| Exclusão remove revisões | não deixa registros órfãos; a ação é **definitiva** e está escrita no aviso |
| POST + ACL `modify` + CSRF em **todas** as mutações | mesmo padrão de `activate()` |
| `import()` sempre como **rascunho** | nenhum pacote publica automaticamente |
| Sem `\|raw` novo, sem `<script>`, sem `onclick`, sem JS inline | só os 2 `\|raw` do chrome (`header`/`footer`) |
| Sem migration / `ALTER` / `schema.sql` | usa UPDATE/DELETE nas tabelas existentes |
| Backend de import intacto | `ThemePackage::decode()` com `hash_equals`, allowlists e 512 KiB continua a autoridade |

## 5. Validação

| Verificação | Resultado |
|---|---|
| `php -l` (**PHP 8.5.12** WASM): controller, model, 3 idiomas | ✅ **5/5** |
| **Harness PHP do model com DB falso** | ✅ **20 OK / 0 FALHAS** — desativar ativo/inativo/inexistente, excluir ativo **recusado**, excluir inativo (2 DELETE na ordem), payload preservado, sem ALTER, métodos originais intactos |
| Twig `if/for` balanceados · 2 `\|raw` · 4 forms POST · 0 `<script>`/JS inline | ✅ |
| `tools/verify-theme-crud.cjs` | ✅ 0 FALHAS |
| `verify-checksums.sh` / `repo-health.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes · **6 PASS / 0 FAIL / COERENTE** |
| `verify-contract-freeze.py` | ✅ COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 6. Limites

Nenhuma escrita do agente no GitHub, nenhuma migration, contrato congelado,
hash, manifesto, `schema.sql`, workflow, `TEMPLATE_KEYS` ou `ThemePackage`
alterado. Nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em
`PASS`, nenhuma decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING` (F9-25), nenhuma declaração de
release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, DB falso e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real (guia aplicado, não
colado), aceite visual da seção Ferramentas internas / nomes novos, e aceite
desta tela de temas.

## 7. Próximos passos (dependem de você)

1. **Aceite visual** desta lista no Admin real (`image-11.png` atualizada).
2. **Recorte B** — ampliar `TEMPLATE_KEYS` (diga quais templates).
3. **Recorte C** — ZIP com `.twig`/`.js` reais: exige decisão explícita de
   segurança, sandbox Twig, ACL dedicada e revisão de migration (hoje vetado).
4. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).

<!-- FIM: RELATORIO-FINAL-BE.md -->


<!-- INICIO: RELATORIO-FINAL-BF.md (6138 bytes) -->

# Relatório Final — Frente BF (correção do botão + Gerenciador de Temas)

**Data:** 2026-11-06 · **HEAD auditado:** `6ee263724af50008277d5af56d6d0f6fa4b7d551`
**CI remoto:** ✅ 7/7 success · **Gatilho:** *"Ao clicar no botão para importar nenhum evento ocorre"*.

## 1. O bug — causa raiz (meu erro na BE)

```twig
<a href="#theme-import" …>{{ button_import }}</a>       <!-- linha 7 -->
<details id="theme-import" {% if import_open %}open{% endif %}>  <!-- linha 15 -->
```

O botão era **apenas uma âncora** para um `<details>` **fechado** (só aberto
quando `?import=1`). Clicar rolava a página até um painel que continuava
recolhido — **nenhum evento visível**. Eu havia antecipado essa redundância e
escolhi mal. O usuário estava certo.

**Correção:** `data-theme-import-open` no botão + handler real no
`theme-package.js` (já carregado pela lista): `preventDefault()`,
`panel.open = true`, `scrollIntoView()` e `focus()` na textarea. O handler é
registrado **antes** do `early-return` do `FileReader`, então o botão funciona
mesmo sem suporte a `FileReader`. O `href` permanece como fallback sem JS.

## 2. Gerenciador de Temas (Twig + ThreeJS)

O pedido "importar os pacotes necessário tanto para twig quanto ThreeJS" foi
implementado na **interpretação segura** já decidida por você na frente BC
(**dados validados, sem código executável**): um **único pacote**
`reamurcms-theme` transporta **os dois tipos de conteúdo** como dados, e o
gerenciador agora **mostra e valida cada um**.

| Bloco do painel | O que exibe |
|---|---|
| **Pacotes Twig** | 25 fontes allowlistadas (`TEMPLATE_KEYS`), como strings validadas dentro do JSON |
| **Pacotes ThreeJS** | 5 cenas + 6 presets allowlistados, `three`/`kaz_scene` como dados versionados |
| **Importar** | textarea, escolha/arraste de `.json`, CSRF, mesmo POST `design/theme.import` |
| **Prévia · Conteúdo Twig** | nº de fontes, lista das chaves (até 25), status "Todas allowlistadas" / "Chave fora da allowlist" / "Ausente" |
| **Prévia · Conteúdo ThreeJS** | cena, slot, status "Cena allowlistada" / "Cena fora da allowlist" / "Ausente", nº de camadas e versão `kaz_scene`, tipos de camada |
| **Prévia · Demais blocos** | tokens, condições, seções, componentes, schedule, mídia, sites |

As allowlists são **injetadas pelo servidor** (`template_keys_list`,
`scene_list`, `template_count`, `scene_count`, `preset_count` via
`ThemePackage::TEMPLATE_KEYS` e `ScenePresetRegistry`) em atributos `data-*`.
O cliente **sinaliza** divergências, mas **não decide**: `decode()` com
`hash_equals` e `validateConfig()` continua a autoridade.

A tabela de temas com Ativar/Desativar/Excluir (BE) foi preservada.

## 3. Arquivos (6)

| Arquivo | Ação |
|---|---|
| `dev/admin/view/js/theme-package.js` | handler do botão + inspeção Twig/ThreeJS; mantém toda a BC |
| `dev/admin/view/template/design/theme_list.twig` | botão com `data-*`, cartões de capacidade, prévia enriquecida |
| `dev/admin/controller/design/theme.php` | `index()` injeta as allowlists/contagens + 25 chaves de idioma |
| `dev/admin/language/{en-us,pt-br,es-eu}/design/theme.php` | 25 rótulos/status por idioma |

**Nenhuma rota, model, `ThemePackage`, `TEMPLATE_KEYS`, migration, schema,
contrato, hash, manifesto ou workflow foi alterado.** Nenhum dos 6 alvos está
em `CHECKSUMS*.txt`.

### Segurança

Sem `fetch`, `XMLHttpRequest`, `eval`, `Function`, `innerHTML`, `outerHTML`,
`document.write`, storage, `import()` ou `require()`. Listas são construídas
com `createElement` + `textContent`. Sem `|raw` novo (só os 2 do chrome), sem
`<script>`, sem JS inline, sem upload em disco, sem `ALTER`/`CREATE`/`DROP`.
Import continua **sempre como rascunho**, nunca publica.

## 4. Validação

| Verificação | Resultado |
|---|---|
| `node --check` no JS | ✅ |
| `php -l` (**PHP 8.5.12** WASM): controller + 3 idiomas | ✅ **4/4** |
| **Harness jsdom** | ✅ **47 OK / 0 FALHAS** — **reprodução do bug** (painel fechado) → **clique abre**, foco, funciona sem `FileReader`; pacote completo Twig+ThreeJS; chave/cena fora da allowlist; pacote sem nenhum dos dois; >512 KiB; extensão/JSON/checksum inválidos; drag-and-drop; 10 checagens de superfície; 7 checagens estruturais do Twig |
| `tools/verify-theme-manager.cjs` | ✅ 0 FALHAS |
| `verify-checksums.sh` / `repo-health.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes · **6 PASS / 0 FAIL / COERENTE** |
| `verify-contract-freeze.py` | ✅ COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 5. O que NÃO foi feito — decisão sua

**Não** foram criados pacotes separados contendo **arquivos `.twig` ou `.js`
ThreeJS executáveis**, nem upload de ZIP, nem escrita em disco, nem sandbox
Twig. Isso **reverteria** a decisão "dados validados, sem código executável" e
exigiria ACL dedicada (F9-25), revisão de migration e análise de SSTI/XSS.
Hoje está **vetado**; se quiser abrir essa frente, diga explicitamente.

## 6. Limites

Nenhuma escrita do agente no GitHub, nenhuma migration, contrato congelado,
hash, `schema.sql`, workflow ou biblioteca alterado. Nenhuma conversão de
`PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão das
**10 `PENDING_DECISION`**, nenhuma mudança em `PROPOSAL_PENDING` /
`DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, jsdom e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real (guia aplicado, não
colado) e aceites visuais (Ferramentas internas, nomes, lista de temas,
gerenciador).

## 7. Próximos passos

1. **Aceite visual** no Admin real: clicar em "Importar tema" e confirmar que
   o painel abre, e carregar um pacote para ver a prévia Twig/ThreeJS.
2. Decisão sobre **arquivos executáveis** (ver §5), se desejar.
3. **Recorte B** — ampliar `TEMPLATE_KEYS` (diga quais templates).
4. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).

<!-- FIM: RELATORIO-FINAL-BF.md -->


<!-- INICIO: RELATORIO-FINAL-BG.md (5675 bytes) -->

# Relatório Final — Frente BG (tema completo em Twig de referência)

**Data:** 2026-11-06 · **HEAD auditado:** `11b98aace6c4478747d8782c86afc38f9f06f930`
**CI remoto:** ✅ 7/7 success · **Pedido:** *"criar um tema completo em twig para ser uma opção de tema de referencia"*.

## 1. O que foi entregue

**`docs/temas/Reamur-Reference.reamurtheme.json`** — um pacote
`reamurcms-theme` v1 **importável pelo botão "Importar tema"** (corrigido na
BF), com **as 25 fontes Twig allowlistadas preenchidas**, ThreeJS, tokens,
condições e cena de editor. **26.226 bytes**, checksum
`07b6b2d294681e01ccec14cc0a02e3e50feeb72d8a2b0b0e4453e90853a444d4`.

Acompanham: `docs/temas/GUIA-TEMA-REFERENCIA.md`,
`tools/verify-reference-theme.cjs` e `evidencia/theme-package-decode.txt`.

| Bloco | Valor |
|---|---|
| `name` / `slug` / `parent` | `Reamur Reference` / `reamur_reference` / vazio (tema base) |
| `primary` / `surface` | `#1d4ed8` / `#ffffff` |
| `scene` / `three` | `hero-particles`; `enabled`, `slot=hero`, `count=400`, `color=#1d4ed8`, `speed=0.05` |
| **`templates`** | **25/25 chaves `TEMPLATE_KEYS`**, ~19.823 bytes de Twig |
| `tokens` | 22 tokens do `TokenRegistry` (contraste AA validado) |
| `conditions` | `version=1`, `rules=[]` |
| `kaz_scene` | `kaztheme-scene/v1`, canvas 960×540, 5 camadas |

## 2. Como foi construído (e por que é confiável)

Não é um JSON "parecido" com o formato: foi gerado pelo **próprio
`ThemePackage::export()`** e validado pelo **`ThemePackage::decode()` real**,
executado em PHP **8.5.12 WASM** com as **classes reais** do ReamurCMS
(`ConditionEngine`, `TokenRegistry`, `SectionRegistry`, `ComponentRegistry`,
`ThemeSchedule`, `ThemeMedia`, `ThemeSites`, `PageLayout`).

| Verificação com as classes reais | Resultado |
|---|---|
| `validateConfig($config)` | **`[]` — zero erros** |
| `decode($json)` | **`ok = true`, `errors = []`** |
| `export()` → `checksum` | SHA-256 do config canônico |
| `decode()` → 25 templates, cena, nome/slug | conferidos |
| `resolveChain()` sem pai | `#1d4ed8` |
| **Checksum recalculado de forma independente em Python** | **bate exatamente** |

O checksum também é recalculado de forma independente em **Node** pelo
verificador (ordenação recursiva de chaves + `JSON.stringify`), garantindo que
o pacote não foi adulterado.

## 3. Regras que o tema obedece

As fontes são **strings Twig dentro do JSON** — o modelo de **dados
validados, sem código executável** que você confirmou na frente BF. Cada uma
passa na regex de `validateConfig()` e no adaptador `ArrayLoader`: sem
`<script|iframe|object|embed`, `javascript:`, `on…=`, `|raw`,
`{% autoescape`, `srcdoc=`, `formaction=`. Sem CDN, fontes externas,
`@import`, `!important`, `--bs-*` ou `--rms-*`.

**Um detalhe encontrado e corrigido durante a construção:** meu comentário
inicial de cabeçalho dizia *"Sem scripts, |raw, eventos ou autoescape"* — a
regex varre a string inteira, **inclusive comentários**, e recusaria 11
fontes por causa da palavra `|raw` no comentário. O texto foi trocado e a
varredura passou a **0 fontes proibidas**.

Templates de rota usam `{{ header }}`, `common/footer` fecha o documento, e
os includes de `partial/theme-scene*` preservam a cena ThreeJS. Todas as
variáveis usam `|default(...)` para não quebrar em rotas com contexto
diferente.

## 4. Onde fica e o que NÃO mexe

`docs/temas/` é um diretório novo. Testei na réplica fiel: o arquivo novo
**não** entra em nenhum `CHECKSUMS*.txt` e **não** altera os guardas —
`verify-checksums` 142 arquivos / 0 divergentes, `repo-health`
**6 PASS / 0 FAIL**, `verify-contract-freeze` **COERENTE**.

**Nenhuma** migration, `schema.sql`, contrato congelado, hash, manifesto,
workflow, `ThemePackage`, `TEMPLATE_KEYS`, controller, model, idioma ou
template de arquivo foi alterado. **Nenhum tema foi instalado
automaticamente**: para aparecer na lista é preciso importar o pacote — e o
import continua **sempre como rascunho**, nunca publicando.

## 5. Validação

| Verificação | Resultado |
|---|---|
| `validateConfig()` / `decode()` reais (PHP 8.5.12 WASM) | ✅ **0 erros / ok=true** |
| Checksum independente (Python + Node) | ✅ idêntico |
| 25 fontes: allowlist, tamanho, conteúdo proibido, `\|raw`, `<script>`, CDN, `!important` | ✅ 0 violações |
| Twig `if/endif` e `for/endfor` balanceados por fonte | ✅ 25/25 |
| `kaz_scene`: canvas, kinds/roles, ids, cores, textos | ✅ |
| `tools/verify-reference-theme.cjs` | ✅ 0 FALHAS |
| `verify-checksums` / `repo-health` / `verify-contract-freeze` na réplica | ✅ 142/0 · 6 PASS/0 FAIL · COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 6. Limites

Nenhuma escrita do agente no GitHub, nenhum runtime/MySQL/staging/deploy,
nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma
decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real e os aceites visuais
(Ferramentas internas, nomes, lista de temas, gerenciador e agora o tema de
referência importado).

## 7. Próximos passos

1. **Importar** o pacote no Admin real, conferir a prévia (25 Twig +
   ThreeJS) e dar o aceite visual.
2. **Recorte B** — ampliar `TEMPLATE_KEYS` se quiser mais fontes (diga quais).
3. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).

<!-- FIM: RELATORIO-FINAL-BG.md -->


<!-- INICIO: RELATORIO-FINAL-BH.md (5911 bytes) -->

# Relatório Final — Frente BH (tema completo em ThreeJS de referência)

**Data:** 2026-11-06 · **HEAD auditado:** `dc7d2a969297bfd523f4a5c7b158385d3c2b3d8c`
**CI remoto:** ✅ 7/7 success · **Pedido:** *"fazer um tema completo em ThreeJS que servirá como referência para criar outros temas"*.

## 1. O que foi entregue

**`docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json`** — pacote
`reamurcms-theme` v1 importável pelo botão **Importar tema**. **42.665 bytes**,
checksum `16461680c8a1e5a42bf0c53bb1906bdeca794e99d49e3d9e3a4e1b3d94611742`.

Acompanham: `docs/temas/GUIA-TEMA-THREEJS-REFERENCIA.md`,
`tools/verify-threejs-reference.cjs` e `evidencia/threejs-decode-harness.txt`.

| Bloco | Conteúdo |
|---|---|
| `name` / `slug` / `parent` | `Reamur ThreeJS Reference` / `reamur_threejs_reference` / vazio |
| `primary` / `surface` / `scene` | `#8a4fff` / `#101827` / `hero-particles` |
| **`three`** | preset **aurora** completo (`enabled`, `scene`, `slot`, `count=400`, `color`, `speed`) |
| **`conditions`** | **6 regras**, uma para **cada um dos 6 presets reais** |
| **`kaz_scene`** | `v1`, canvas 960×540, **24 camadas** com os **5 kinds** e **5 roles**, 7 `bindId` |
| `templates` | **25/25 fontes** adaptadas ao 3D (~19,7 KB), classes `thr-*` |
| `tokens` | 22 tokens com contraste AA validado |

## 2. Os dois mecanismos 3D cobertos

| Mecanismo | Origem | Consumo |
|---|---|---|
| **Runtime declarativo** `theme_scene` | `three` + regras | `partial/theme-scene.twig` emite `data-scene/count/color/speed`; `scene-runner.js` faz `boot()` |
| **Composição** `theme_scene_view` | `kaz_scene` | `ThemeSceneCss::compile()` → CSS `--rms-scene-*` + `partial/theme-scene-block.twig` |

As 4 cenas allowlistadas (`hero-particles`, `portfolio-3d`, `tools-3d`,
`not-found`) e os **3 slots** (`hero`, `home_top`, `footer_top`) aparecem.
O tema **não escreve JavaScript** — modelo de dados validados confirmado nas
frentes BC/BF.

### Os 6 presets em regras condicionais

| Regra | Gatilho | Preset |
|---|---|---|
| `home-aurora` | `route = common/home` | aurora |
| `portfolio-geometric` | `path` contém `project` | geometric |
| `tools-workshop` | `path` contém `tool` | workshop |
| `notfound-lost` | `route = error/not_found` | lost |
| `variant-nebula` | `query.variant = b` | nebula |
| `mobile-off` | `device=mobile` **ou** `hour<7` | off (desliga a cena) |

## 3. Validação com as classes REAIS (PHP 8.5.12 WASM)

Carreguei `ThemePackage`, `ConditionEngine`, `TokenRegistry`,
`SectionRegistry`, `ComponentRegistry`, `ThemeSchedule`, `ThemeMedia`,
`ThemeSites`, `PageLayout`, **`ScenePresetRegistry`** e **`ThemeSceneCss`**.

**52 OK / 0 FALHAS**, incluindo:

- `validateConfig()` = `[]` e **`decode()` ok=true** com checksum conferido;
- `ScenePresetRegistry::matchingPreset()` retornando **o preset correto para o
  `three` de base e para cada uma das 6 regras**; `validate()` = `[]` em todos;
- `ConditionEngine::validate()` = `[]` e **`evaluate()` em 7 contextos reais**
  (home, portfolio, tools, 404, `?variant=b`, mobile, hora<7) casando as regras
  e produzindo o preset esperado;
- `ThemePackage::validateScene()` = `[]`, `ThemeSceneCss::compile()` com
  `enabled=true`, **24 camadas** e CSS iniciando em `--rms-scene-w:960`;
- **5 kinds e 5 roles** cobertos, 7 `bindId`, `export()` reproduzindo o
  checksum e **1 byte alterado sendo recusado**.

O checksum também foi recalculado de forma independente em Python/Node e bate
exatamente.

## 4. Segurança e escopo

Fontes Twig sem `<script|iframe|object|embed`, `javascript:`, `on…=`, `|raw`,
`{% autoescape`, `srcdoc=`, `formaction=`, CDN, `@import`, `!important`,
`--bs-*` ou `--rms-*`. Sem arquivos `.twig`/`.js` no servidor, upload em disco,
`eval` ou código executável.

`docs/temas/` já existe desde a BG; testei na réplica fiel: o novo arquivo
**não** entra em `CHECKSUMS*.txt` e **não** altera os guardas —
`verify-checksums` 142 arquivos / 0 divergentes, `repo-health`
**6 PASS / 0 FAIL**, `verify-contract-freeze` **COERENTE**.

**Nenhum código foi alterado**: nenhuma migration, `schema.sql`, contrato
congelado, hash, manifesto, workflow, `AssetManifest`, `ScenePresetRegistry`,
`ThemePackage`, `TEMPLATE_KEYS`, controller, model, idioma ou arquivo de
runtime. **Nada é instalado automaticamente** — import continua **sempre como
rascunho**, nunca publicando.

## 5. Validação consolidada

| Verificação | Resultado |
|---|---|
| `validateConfig()` / `decode()` reais | ✅ 0 erros / ok=true |
| **Harness PHP com presets, condições e CSS** | ✅ **52 OK / 0 FALHAS** |
| Checksum independente (Python + Node) | ✅ idêntico |
| 25 fontes: allowlist, segurança, Twig balanceado | ✅ 25/25, 0 violações |
| `tools/verify-threejs-reference.cjs` | ✅ 0 FALHAS |
| `verify-checksums` / `repo-health` / `verify-contract-freeze` | ✅ 142/0 · 6 PASS/0 FAIL · COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 6. Limites

Nenhuma escrita do agente no GitHub, nenhum runtime/MySQL/staging/deploy,
nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma
decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real e os aceites visuais
(Ferramentas internas, nomes, lista, gerenciador, tema Twig e agora o tema
ThreeJS).

## 7. Próximos passos

1. **Importar** o pacote, conferir a prévia e dar o aceite visual; testar
   `?variant=b` e mobile.
2. **Recorte B** — ampliar `TEMPLATE_KEYS` (diga quais templates).
3. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).

<!-- FIM: RELATORIO-FINAL-BH.md -->


<!-- INICIO: RELATORIO-FINAL-BI.md (6156 bytes) -->

# Relatório Final — Frente BI (tema de site real em Twig)

**Data:** 2026-11-06 · **HEAD auditado:** `a704d2ae969c3ca89e89c352d4c84260508c123d`
**CI remoto:** ✅ 7/7 success · **Pedido:** *"fazer um tema real para twig como um site completo… não tem nada parecido com um site ou com um tema profissional de uso real"*.

## 1. A correção

A crítica foi aceita: BG/BH entregaram **25 esqueletos** com `|default('')`,
divs genéricas, sem CSS, sem identidade e sem conteúdo. Eram exemplos de
estrutura, não um site. Esta frente entrega **Atlas Studio**, um site
institucional completo.

| Antes (BG/BH) | Agora (BI) |
|---|---|
| `|default('')` e texto genérico | Textos editoriais reais em todas as fontes |
| Nenhum CSS | **CSS próprio de ~14 KB** embutido |
| `div` soltas | `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<footer>` |
| Sem conteúdo | 9 seções, 16 cards, 11 páginas internas, 15 SVG inline |
| Sem prévia | **`preview-desktop.png`**, **`preview-mobile.png`**, `preview.html` |

## 2. O que foi entregue

**`docs/temas/Atlas-Studio.reamurtheme.json`** — pacote `reamurcms-theme` v1
importável, ~62 KB, checksum
`4241cd9b430c66c72929ab0bee41c2a22cb9bbbe71141b28a5fe713baff72534`.

| Bloco | Conteúdo |
|---|---|
| `name` / `slug` / `parent` | `Atlas Studio` / `atlas_studio` / vazio |
| `primary` / `surface` | `#4338ca` / `#ffffff` |
| **`templates`** | **25/25 fontes**, ~55 KB de Twig com conteúdo real |
| `tokens` | 22 tokens (contraste AA validado) |
| `conditions` | `version=1`, `rules=[]` |
| `three` | **desligado** — site editorial, sem 3D |
| `kaz_scene` | `v1`, 960×540, 3 camadas de composição |

Acompanham: guia, verificador, `preview.html`, dois PNGs e evidências de layout.

## 3. Como o tema vira site de verdade

A leitura do `common/header.twig` e do `home.php` mostrou que:

- o `header.twig` do sistema **já inclui `partial/site_header.twig`** (com `only`);
- o `<body>` já recebe `--theme-primary` / `--theme-surface` e `theme_token_css`;
- as rotas usam `{{ header }}` no início e `{{ footer }}` no fim.

Então `partial/site_header.twig` = `<style>` (CSS do tema) + cabeçalho real;
`common/footer.twig` = rodapé em 4 colunas + fechamento do documento; e as rotas
compõem `<main>` com home completa (hero, clientes, serviços, sobre, método,
portfólio, depoimentos, blog, contato, CTA) e páginas internas com trilha,
conteúdo e barra lateral. O CSS usa `var(--theme-primary, …)` — **trocar a cor
no editor recoloriza o site inteiro**.

**Limite honesto:** o formato `reamurcms-theme` só aceita as 25 `templates` como
strings; não existe chave `css` nem assets. Por isso o CSS fica embutido em
`<style>` no `site_header`, uma vez por página. Funciona e é seguro, mas não é
um arquivo `.css` separado — isso exigiria mudança de allowlist/renderer
(**Recorte B**, sem autorização).

## 4. Prévia renderizada — e dois bugs que ela revelou

Gerei `preview.html` extraindo **o próprio CSS do pacote** e resolvendo as
diretivas simples, e capturei com Playwright em viewport real:

| | desktop 1440 | mobile 390 |
|---|---|---|
| `scrollWidth` | 1440 | 390 |
| overflow | **nenhum** | **nenhum** |
| seções / cards | 9 / 16 | 9 / 16 |
| botão primário | `rgb(255,255,255)` sobre `rgb(67,56,202)` | idem |

Dois achados corrigidos durante a construção:

1. **Bug real de CSS:** `.atlas a` (especificidade 0,1,1) vencia
   `.atlas-btn-primary` (0,1,0) — o texto do CTA ficava roxo sobre fundo roxo,
   **invisível**. Corrigido com `.atlas a.atlas-btn-primary`.
2. **Falso alarme de overflow no mobile:** o Chromium headless com
   `--window-size=390` reportava `innerWidth=500` (largura mínima da janela),
   cortando o texto na captura. Refeito com `page.setViewportSize(390, 844)`:
   `scrollWidth=390`, zero overflow.

Também troquei o rodapé de `<div>` para `<footer>` por semântica.

## 5. Validação

| Verificação | Resultado |
|---|---|
| `ThemePackage::validateConfig()` / `decode()` reais (PHP 8.5.12 WASM) | ✅ **0 erros / ok=true** |
| **Harness PHP do site** | ✅ **36 OK / 0 FALHAS** |
| Checksum independente (Python + Node) | ✅ idêntico |
| 25 fontes: allowlist, segurança, Twig balanceado | ✅ 25/25, 0 violações |
| CSS: tokens, responsivo, foco, sem `!important`/CDN/`--bs-*` | ✅ |
| Prévia: 9 seções, 16 cards, header/footer/CTA, sem overflow | ✅ desktop + mobile |
| `tools/verify-site-theme.cjs` | ✅ 0 FALHAS |
| `verify-checksums` / `repo-health` / `verify-contract-freeze` | ✅ 142/0 · 6 PASS/0 FAIL · COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 6. O que NÃO foi mexido

Nenhuma migration, `schema.sql`, contrato congelado, hash, manifesto,
workflow, `ThemePackage`, `TEMPLATE_KEYS`, `header.twig`, controller, model,
idioma, renderer ou arquivo `.twig`/`.css`/`.js` no repositório. Os arquivos
novos ficam em `docs/temas/` e não entram em `CHECKSUMS*.txt`. **Nada é
instalado automaticamente** — import continua sempre como rascunho.

Sem código executável, `<script>`, `|raw`, `javascript:`, `on…=`, CDN, fontes
externas, `@import`, `!important`, `--bs-*` ou `--rms-*`.

## 7. Limites e pendências

Nenhuma escrita do agente no GitHub, nenhum runtime/MySQL/staging/deploy,
nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma
decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, Chromium e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real e os aceites visuais.

## 8. Próximos passos

1. **Importar** o Atlas Studio no Admin real, ativar e comparar com as prévias.
2. Se quiser **CSS/assets como arquivos reais**, autorize o **Recorte B**
   (muda `CONFIG_KEYS`/allowlist/renderer).
3. Trocar segmento, paleta ou conteúdo conforme a sua preferência.
4. Roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).

<!-- FIM: RELATORIO-FINAL-BI.md -->


<!-- INICIO: RELATORIO-FINAL-BJ.md (6679 bytes) -->

# Relatório Final — Frente BJ (tema completo baseado em davidalmeida.xyz)

**Data:** 2026-11-06 · **HEAD auditado:** `7745d731fde119f8d1bc4a7687655fcbad757783`
**CI remoto:** ✅ 7/7 success · **Pedido:** *"Agora sim ficou perfeito e como esperado, seguindo os mesmos critérios poderia fazer um tema completo baseado no meu site davidalmeida.xyz"*.

**Aceite visual:** a frente BI (Atlas Studio) foi **aprovada visualmente** pelo
usuário nesta data. Não é release, não é health e não decide D01–D10.

## 1. Fonte consultada

- `https://davidalmeida.xyz/catalog/` (conteúdo integral em 2 blocos)
- `assets/css/style.css`, `skeleton.css`, `layout.css` (paleta/tipografia)
- Capturas reais do site em 1440 px (hero, meio, portfólio, rodapé)

**Identidade extraída:** fundo escuro texturizado/cósmico, acento ciano
`#5CC6D0`, tipografia fina e geométrica em caixa alta com tracking largo,
header "DAVID CREATOR" com nav em inglês, seções em one-page, ThreeJS no hero.

## 2. O que foi entregue

**`docs/temas/David-Creator.reamurtheme.json`** — pacote `reamurcms-theme` v1
importável, ~63 KB, checksum
`c9fb44b0a82849b19991db53c415474ed71fcaf83d4f4b987838cd7bb3ab0201`.

| Bloco | Valor |
|---|---|
| `name` / `slug` / `parent` | `David Creator` / `david_creator` / vazio |
| `primary` / `surface` | **`#5cc6d0`** / **`#1b1f24`** |
| `scene` / `three` | `hero-particles` ligado, slot `hero`, 400, `#5cc6d0`, 0.05 |
| `templates` | **25/25 fontes**, ~55 KB de Twig com conteúdo real |
| `tokens` | 22 campos AA válidos |
| `conditions` | `version=1`, `rules=[]` |
| `kaz_scene` | `v1`, 960×540, 3 camadas |

Acompanham: guia, verificador, `preview.html`, dois PNGs e evidências.

**11 seções** na home · **34 cards** · **11 páginas internas** · **12 SVG inline**
· header com nav de 9 itens · rodapé em 4 colunas · responsivo 980/640 px ·
`:focus-visible` e `prefers-reduced-motion`.

### Conteúdo transcrito

Hero "I'M / DAVID / L. ALMEIDA / CREATIVE DEVELOPER"; WHO I AM? e os dois
blocos institucionais; UNITY / CREDIBILITY / ATTRACTION; as **6 etapas** do
processo (Knowing the Client → Delivery); MY SKILLS com TECH / PLATFORMS /
PROGRAMS; WHAT DO I DO? com Graphic Design / Web Design / SEO; os 3
depoimentos (Silvio Robson, Pedrinho, Bruno); WHAT IT LOOKS LIKE? com 9
projetos reais; CONNECTED TOOLS (Fluxa, Quotia, Vertex, Solis); BELOVED
CLIENTS; LATEST NEWS; LET'S HAVE A CHAT? com endereço, e-mail e WhatsApp;
redes sociais e © 2025.

**Não copiado:** fotos `.webp` e fontes externas (Lato/Open Sans/Poiret One) —
o formato não aceita assets nem CDN. Os visuais são gradientes + SVG
determinísticos e a tipografia usa a stack de sistema. Conteúdo textual fiel,
imagem não é clone pixel a pixel.

## 3. Mesmos critérios da BI

| Critério BI | BJ |
|---|---|
| Pacote `reamurcms-theme` v1 importável | ✅ `export()`/`decode()` reais |
| 25 fontes `TEMPLATE_KEYS` com conteúdo real | ✅ 25/25 |
| CSS próprio embutido em `partial/site_header.twig` | ✅ ~16 KB |
| Header/footer semânticos, `{{ header }}`/`{{ footer }}` | ✅ 11 + 11 |
| `--theme-primary`/`--theme-surface` no CSS | ✅ |
| Responsivo, foco, reduced-motion, sem CDN/`!important`/`--bs-*` | ✅ |
| Prévia HTML + PNG desktop/mobile com viewport real | ✅ |
| `validateConfig()`/`decode()` + harness + verificador + guardas | ✅ |
| ZIP + `.sha256` + REL/ÍNDICE/guia | ✅ |

## 4. Dois ajustes técnicos encontrados

1. **`tokens_invalid`:** o `TokenRegistry` exige contraste AA da primária sobre
   a superfície **e** do branco sobre o botão; com `color_surface` escuro isso
   é matematicamente impossível. Resolvido mantendo a identidade escura em
   `config.surface` + CSS e usando um conjunto AA válido nos tokens
   (`color_primary #0e6d7a`, `heading_weight 700`). O `decode()` passou.
2. **Chromium ausente:** o snapshot não persiste `.cache`, então o browser da
   BI sumiu entre mensagens. Reinstalei em `/home/user/.pw-browsers` (o `/tmp`
   é tmpfs de 1 GB e deu `ENOSPC`; redirecionei `TMPDIR` e
   `PLAYWRIGHT_BROWSERS_PATH` para `/home/user`).

## 5. Prévia renderizada

| | desktop 1440 | mobile 390 |
|---|---|---|
| `scrollWidth` | 1440 | 390 |
| overflow | **nenhum** | **nenhum** |
| seções / cards | 11 / 34 | 11 / 34 |
| fundo | `rgb(27,31,36)` | idem |
| acento | `rgb(92,198,208)` | idem |
| hero | `DAVID` / `L. ALMEIDA` | idem |

`preview.html` usa **o próprio CSS extraído do pacote**; sem diretivas Twig
remanescentes.

## 6. Validação

| Verificação | Resultado |
|---|---|
| `ThemePackage::validateConfig()` / `decode()` reais (PHP 8.5.12 WASM) | ✅ **0 erros / ok=true** |
| **Harness PHP do tema** | ✅ **46 OK / 0 FALHAS** |
| Checksum independente (Python + Node) | ✅ idêntico |
| 25 fontes: allowlist, segurança, Twig balanceado | ✅ 25/25, 0 violações |
| CSS: tokens, cósmico, responsivo, foco, sem `!important`/CDN | ✅ |
| Prévia: 11 seções, 34 cards, sem overflow | ✅ desktop + mobile |
| `tools/verify-david-theme.cjs` | ✅ 0 FALHAS |
| `verify-checksums` / `repo-health` / `verify-contract-freeze` | ✅ 142/0 · 6 PASS/0 FAIL · COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 7. O que NÃO foi mexido

Nenhuma migration, `schema.sql`, contrato congelado, hash, manifesto,
workflow, `ThemePackage`, `TEMPLATE_KEYS`, `header.twig`, controller, model,
idioma, renderer ou arquivo `.twig`/`.css`/`.js` no repositório. Arquivos novos
em `docs/temas/`, fora de `CHECKSUMS*.txt`. **Nada é instalado
automaticamente** — import continua sempre como rascunho.

## 8. Limites e pendências

Nenhuma escrita do agente no GitHub, nenhum runtime/MySQL/staging/deploy,
nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma
decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, Chromium e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real, aceite visual **deste
tema** e os demais aceites visuais.

## 9. Próximos passos

1. **Importar** o David Creator no Admin real, ativar e comparar com o site.
2. Se quiser **as fotos reais e um `.css` separado**, autorize o **Recorte B**
   (muda allowlist/renderer) — com assets, dá para chegar a um clone visual.
3. Ajustar textos/idioma (o site original é em inglês) ou criar variações via
   child-theme.
4. Roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).

<!-- FIM: RELATORIO-FINAL-BJ.md -->


<!-- INICIO: RELATORIO-FINAL-BK.md (5559 bytes) -->

# Relatório Final — Frente BK (correção do vazamento do KAZEditor-Theme)

**Data:** 2026-11-06 · **HEAD:** `ab8b76588010a5349930c6326e947e5af36fcb49`
**Gatilho:** `image-12.png` + HTML `<section class="rms-scene">` enviado pelo usuário.
**Classificação:** falha de produto confirmada (não é erro de CSS do tema).

## 1. Investigação

Cadeia causal completa em `DIAGNOSTICO-BK.md`. Resumo:
`ThemeSnapshot` → `ThemeSceneCss::compile($config['kaz_scene'])` →
`scene_view.enabled = (layers !== [])` →
`startup/theme.php:155` publica `catalog_theme_scene_view` →
`home.php:369` injeta `theme_scene_view` →
`home.twig:4` inclui `theme-scene-block.twig` no topo do `<main>` →
`<section class="rms-scene">` no site público.

O próprio editor (`kaz_theme_editor.twig:566`) documenta que a cena é
*"dado de edição e versionamento (`kaz_scene`), não renderização no site
público"*. A Frente AB contradiz essa garantia; a Frente AF (prévia sandbox
no Admin) é legítima.

**Abrangência:** os 4 pacotes entregues continham o include — BJ/BI/BG com
2 pontos cada e BH com **12**. O `common/home.twig` nativo do Catálogo
também o contém, então o bug atinge qualquer tema com `kaz_scene` visível.

## 2. Correção entregue

### A — 4 temas reexportados (aplicáveis já)

| Tema | Includes removidos | Camadas `kaz_scene` | Checksum novo |
|---|---|---|---|
| David Creator (BJ) | 2 | 3 | `f2e53d070b252e71b07e64128191f51a02c908642e5d3c8c8f16a051894354ff` |
| Atlas Studio (BI) | 2 | 3 | `dced34ce27247a9295f5185b4b5299e86bcb007c27b7748722f3a4fb3d972dd4` |
| Reamur Reference (BG) | 2 | 5 | `0197e1400ccadf51155d58371c49350448b72446cfdc7ddd51f921a18e15a9e5` |
| ThreeJS Reference (BH) | **12** | 24 | `1c6fc07ac9fb9995fead752dd642ea0c8b0cb831b2ed24320fc29a31dbd8c053` |

Mantidos: cores, conteúdo, CSS, `kaz_scene`, ThreeJS e
`partial/theme-scene.twig`. Removida **apenas** a linha do include.

### B — patch de runtime (PROPOSTA, não aplicado)

`docs/correcao/correcao-runtime.patch`: **4 linhas removidas em 3 arquivos**
do Catálogo (`common/home.twig`, `common/home.php`, `startup/theme.php`).
Admin intacto (4 pontos inventariados). Os 3 arquivos não constam em
`CHECKSUMS.txt`. Aplicação: `git apply …`. **Nenhuma escrita do agente no GitHub.**

## 3. Sobre "o KAZEditor-Theme não está alterando coisas dos temas inseridos"

Confirmado e explicado: a edição grava `kaz_scene` (`theme.php:651-667`),
que não altera `templates`, tokens nem layout renderizado; o único efeito no
site era o bloco avulso agora removido. Fazer a ferramenta editar o tema de
fato exige contrato novo (`kaz_scene` → `templates`/tokens), com mudança de
allowlist/validação/renderer e autorização do **Recorte B**. **Não decidido,
não executado, nenhuma decisão por inferência.**

## 4. Validação

| Verificação | Resultado |
|---|---|
| `ThemePackage::validateConfig()` / `export()` / `decode()` reais (PHP 8.4 WASM) | ✅ **32 OK / 0 FALHAS** nos 4 temas |
| Checksum canônico independente (Node, `ksort` recursivo) | ✅ 4/4 idênticos |
| 25 fontes por tema, `kaz_scene` preservado, Twig balanceado | ✅ 4×25 fontes |
| Nenhum `theme-scene-block.twig` restante | ✅ 0 ocorrências |
| `theme-scene.twig` declarativo mantido | ✅ 4/4 |
| Patch: 3 arquivos / 4 linhas / Admin intacto / sem contrato | ✅ |
| `tools/verify-no-scene-leak.cjs` | ✅ 0 FALHAS |
| Prova estrutural antes/depois | ✅ 1 stage+3 camadas → **0/0**; hero/CTA/footer/10 seções preservados |
| `evidencia/antes-depois.png` | ✅ captura real do usuário × prévia corrigida |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

**Ambiente:** nesta sessão `php-wasm-cli` e `/tmp/acbrowser` não
persistiram; reinstalamos `php-wasm@0.2.0` (PHP 8.4.1) em
`/home/user/phptest` com wrapper `php-cli.cjs`, e `playwright@1.56.0` em
`/home/user/browser`. O Chromium build 1194 foi reinstalado, mas o sandbox
desta sessão não dispõe de `libnspr4.so`/`libnss3` — por isso a prova visual
usa **a captura real enviada por você** e a prévia estática validada na BJ,
com medição estrutural determinística em vez de reexecução de browser. Isso
não altera a conclusão: o HTML colado e a cadeia de código são inequívocos.

## 5. O que NÃO foi mexido

Nenhum arquivo do repositório foi escrito pelo agente; nenhuma migration,
`schema.sql`, contrato congelado, `CHECKSUMS.txt`, workflow, `ThemePackage`,
`TEMPLATE_KEYS`, allowlist, renderer, DOM ou tradução. O patch é **proposta
em arquivo**, não commit. Nada é instalado automaticamente.

## 6. Limites e pendências

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Sem runtime/MySQL/staging/
deploy, sem converter `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, sem
decidir D01–D10 nem o Recorte B. CI verde, PHP WASM, PIL e guardas estáticos
**não significam release aprovada**.

**Aceite visual:** a frente BJ **não** foi aprovada — você reportou esta
falha. Segue pendente, junto do health no Admin real e dos demais aceites.

## 7. Próximos passos

1. Reimportar os 4 temas corrigidos e aplicar o patch de runtime.
2. Conferir a home em modo anônimo (sem `<section class="rms-scene">`).
3. **Decidir** o contrato do KAZEditor-Theme: manter `kaz_scene` como dado de
   edição (estado atual, documentado) ou autorizar o **Recorte B** para que a
   ferramenta passe a gerar `templates`/tokens do tema inserido.
4. Roteiro pendente: health (2), D01–D10 com AR (3), F918 com AS (4).

<!-- FIM: RELATORIO-FINAL-BK.md -->


<!-- INICIO: RELATORIO-FINAL-BL.md (5276 bytes) -->

# Relatório Final — Frente BL (vazamento persistente + editor sem dados reais)

**Data:** 2026-11-06 · **HEAD:** `3219ccae80550f81510068c311a8afcc1de8d71a`
**CI:** 7/7 success · **Pedido:** *"Ainda está ocorrendo vazamento indesejado…
mesmo atualizando as informações… e o KAZEditor-Theme quando coloco para
editar temas prontos ele não carrega as informações reais dos temas"*.

## 1. Causa raiz única

O commit `3219ccae…` ("fix…") adicionou **os arquivos do ZIP BK**, mas o diff
mostra que **`dev/` não foi tocado**. No HEAD atual:

- `common/home.twig:4` ainda inclui `theme-scene-block.twig`;
- `home.php:369` ainda injeta `theme_scene_view`;
- `startup/theme.php:155` ainda publica `catalog_theme_scene_view`.

E, sobretudo, **temas vivem na tabela `design_theme`, não em `docs/temas/`**:

| Coluna | Gravada por | Lida por |
|---|---|---|
| `draft_config` | `save($id,…)`, `import($id,…)` | editor / prévia de rascunho |
| `published_config` | **somente `activate()`** | **Catálogo** (`startup:79-80`, `getThemeMap('published')`) |
| `config` | criação / `activate()` | fallback |

`import()` com `id=0` cria registro **inativo**; com `id≠0` e `save()` gravam
**só o rascunho**. Sem **Ativar**, o site segue no payload antigo.

**Editor:** `theme.php:369` completa `template_sources` com os `.twig` do
disco quando o registro não tem `templates` — o usuário vê o tema nativo,
cores default e *"No saved source is available"* na prévia. Como esses
textareas têm `name="templates[…]"`, **salvar grava o layout nativo** (que
contém o include) de volta no tema. Cada salvamento reinjeta o vazamento.

## 2. Prova com o model real

`evidencia/model-harness.txt` — `dev/admin/model/design/theme.php` executado em
PHP 8.4 WASM com DB em memória: **10 OK / 0 FALHAS**.

| Afirmação | Resultado |
|---|---|
| publicado inicial contém o include | ✅ |
| após `import()`: rascunho limpo | ✅ |
| após `import()`: **publicado ainda com include** | ✅ |
| `import()` não altera `config` | ✅ |
| salvar no editor **reintroduz** o include no rascunho | ✅ |
| `activate()` copia rascunho → publicado/config | ✅ |
| com **Ativar**, publicado fica limpo | ✅ |
| gate fail-closed bloqueia no Catálogo / permite na prévia Admin | ✅ |

## 3. Entrega

| Arquivo | O que é |
|---|---|
| `DIAGNOSTICO-BL.md` | Causa unificada com arquivos/linhas e os 3 payloads |
| `GUIA-BL.md` | Ordem operacional: diagnosticar → patches → cache → **Ativar** |
| **`A-vazamento-failclosed.patch`** | **6 edições / 5 arquivos** — gate `theme_scene_preview` + as 3 remoções da BK + 2 flags no `twig.php` |
| **`B-editor-dados-reais.patch`** | **3 edições / 2 arquivos** — herda `templates` do publicado, `template_saved_keys`, fontes nativos `disabled` sem `name=` |
| `diagnostico-tema.sql` | **Somente `SELECT`** — mostra o tema ativo e onde está o include |
| `tools/verify-bl.cjs` | Verificador dos patches, do gate e da governança |
| 4 `.json` corrigidos | Reexportados na BK, **inalterados** aqui |

**Por que o patch A é diferente da BK:** ele **não depende** de reimportar, de
Ativar, nem do conteúdo do banco. O gate no `theme-scene-block.twig` exige um
flag que **só** `renderSandboxPreview()` (Admin) injeta; o Catálogo nunca o
injeta → o stage não renderiza no front mesmo com `published_config` antigo e
`kaz_scene` cheio. A prévia do Admin e o `ThemeCompareView` continuam intactos.

## 4. Validação

| Verificação | Resultado |
|---|---|
| HEAD/CI lidos do GitHub | ✅ `3219ccae…`, 7/7 |
| 3 linhas da BK confirmadas **ainda presentes** | ✅ |
| `save()`/`import()`/`activate()` com model real | ✅ 10 OK / 0 FALHAS |
| `git apply --check` dos 2 patches sobre o HEAD | ✅ ambos aplicáveis, sem conflito |
| PHP `TOKEN_PARSE` nos 4 arquivos PHP já patchados | ✅ 4/4 |
| Patch A: gate fail-closed + 3 remoções + 2 flags | ✅ |
| Patch B: herança do publicado + `disabled`/sem `name=` | ✅ |
| Patch A **não** remove o partial do Admin | ✅ |
| Nenhum patch toca em `CHECKSUMS`/schema/`.github`/allowlist | ✅ |
| `tools/verify-bl.cjs` | ✅ 0 FALHAS |
| `sha256sum -c` | ✅ 84/84 |

## 5. O que NÃO foi mexido

Nenhuma escrita do agente no GitHub, nenhum SQL de escrita, nenhuma migration,
contrato, hash, manifesto, workflow, `ThemePackage`, `TEMPLATE_KEYS`,
`buildDraftConfig()`, `save()`, `activate()` ou renderer alterado. Os patches
são **propostas em arquivo**; o ZIP não se autoaplica. Nenhum runtime/MySQL/
deploy executado pelo agente.

## 6. Limites e pendências

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Sem converter `PENDING`/
`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, sem decidir D01–D10 nem o Recorte B.
CI verde, PHP WASM e guardas **não significam release aprovada**.

**Aceite visual da BJ/BK/BL e health no Admin real continuam pendentes.**

## 7. Próximos passos

1. Rodar o SQL e aplicar **A** (fecha o vazamento imediatamente).
2. Aplicar **B**, limpar cache, importar no **tema ativo** e **Ativar**.
3. Conferir em aba anônima e me mandar a saída do SQL + uma captura.
4. Depois, se quiser, decidir o contrato do KAZEditor-Theme (Recorte B) e
   retomar health / D01–D10.

<!-- FIM: RELATORIO-FINAL-BL.md -->


<!-- INICIO: RELATORIO-FINAL-BM.md (6178 bytes) -->

# Relatório Final — Frente BM (Atlas Studio 3D)

**Data:** 2026-11-07 · **HEAD:** `ad7643de6ca2` · **Pedido:** *"fazer um tema
completo utilizando ThreeJS seguindo os mesmos critérios aplicados no Astra"*.
`ask_user` → **`referencia=atlas`** (o Atlas Studio aprovado na BI) e
**`threejs=completo`** (site completo + cena declarativa em várias seções).

## 1. O que foi construído

`Atlas Studio 3D` (`atlas_studio_3d`) — **tema completo de site real**, não
esqueleto. Parte dos **25 fontes Twig aprovados** na BI (*"Agora sim ficou
perfeito e como esperado"*) e acrescenta apenas **dados validados**:

| Aspecto | Atlas Studio (BI, aprovado) | Atlas Studio 3D (BM) |
|---|---|---|
| Fontes Twig | 25, site completo | **25**, site completo (13 partials/rodapé idênticos) |
| `three.enabled` | `false` | **`true`** |
| `scene` | `''` | `hero-particles` |
| Cenas por rota | nenhuma | **4 cenas via 5 regras `conditions`** |
| Slot `[data-scene]` | 1 (inerte, no rodapé) | **11 templates**, 1 por página |
| `kaz_scene` | 3 camadas | **16 camadas** declarativas |
| Tokens/cores/tipografia | `#4338ca`, system, 1140px | **idênticos** |

**13 templates ficaram byte-a-byte iguais** ao tema aprovado (todos os
partials de seção e o rodapé). As mudanças ficaram concentradas no CSS do
cabeçalho, nos templates de página e nos metadados `three`/`conditions`/
`kaz_scene`.

## 2. Como o ThreeJS entra (Recorte A — sem código executável)

O tema **não contém JavaScript**. O slot é o partial nativo
`partial/theme-scene.twig`, que emite apenas atributos `data-*` allowlistados:

```twig
{% include 'partial/theme-scene.twig' %}
```

O `scene-runner.js` **self-hosted do próprio Catálogo** faz `boot()` nos
`[data-scene]` e respeita `prefers-reduced-motion` / `IntersectionObserver`.
O `common/header.twig` (shell confiável, fora da allowlist de 25 fontes)
carrega o importmap e `three/main.js` quando `has_3d` é verdadeiro. O tema
escolhe **cena, slot, contagem, cor e velocidade** — nunca escreve código.

`conditions` allowlistadas trocam a cena por rota, validadas pelo
`ConditionEngine` real:

| Rota | Cena | Slot | Partículas | Cor | Velocidade |
|---|---|---|---|---|---|
| `common/home` | `hero-particles` | `hero` | 520 | `#4338ca` | 0.06 |
| `catalog/project_*` | `portfolio-3d` | `home_top` | 500 | `#22d3ee` | 0.06 |
| `tool/tool_*` | `tools-3d` | `home_top` | 350 | `#f59e0b` | 0.08 |
| `error/not_found` | `not-found` | `footer_top` | 200 | `#94a3b8` | 0.04 |
| `mobile = true` | `hero-particles` + classe `atlas-scene-lite` | `hero` | 180 | `#4338ca` | 0.04 |

O rodapé aprovado já condicionava o slot a `footer_top`, então **não há dois
containers `[data-scene]` na mesma rota** — medido: `slots = 1` nas 4 capturas.

`kaz_scene` tem **16 camadas** (`rect`/`text`/`button`/`card`/`section`,
960×540, grid 8) que desenham a composição 3D no editor. São **dados de edição
e versionamento**: `ThemeSceneCss` compila 16 camadas em `--rms-scene-*`, e a
cena só aparece na **prévia do Admin** (gate fail-closed da frente BL) — nunca
no front público.

**`parent` ficou vazio de propósito.** Um child-theme dependeria de o Atlas
original estar importado/publicado no banco; sem ele, o editor cairia nos
`.twig` do disco — exatamente o defeito diagnosticado na BL. O tema é
autossuficiente.

## 3. Validação com as classes reais (PHP 8.4 WASM)

`evidencia/export-bm.txt` — **`ThemePackage`, `ThemeSnapshot`,
`ThemeSceneCss` e `ConditionEngine` do HEAD `ad7643de…`**:

| Verificação | Resultado |
|---|---|
| `validateConfig()` | **`[]`** |
| `normalizeScene()` | OK |
| `ThemeSceneCss::compile()` | **16 camadas**, CSS 1322 bytes allowlistado |
| `ThemeSnapshot::resolve()` nas 4 rotas | cenas corretas por rota |
| `export()` / `encode()` | 76.637 bytes (< 512 KiB) |
| `decode()` | **`ok=true`, `errors=[]`** |
| `checksum` (SHA-256 do config canonizado) | `59ca4cd1ae312abd4c5a5771ed9981335d90f6fd35cc30e3aa8f233c1fb4adbf` |
| **Total** | **14 OK / 0 FALHAS** |

## 4. Prévia visual real (Chromium 141)

As libs de sistema ausentes nas sessões anteriores foram extraídas dos `.deb`
(`LD_LIBRARY_PATH`), então foi possível rodar **Chromium headless de verdade**
com viewport real via Playwright — mesma técnica da BI/BJ.

`evidencia/layout-bm.json` e 4 capturas:

| Captura | Cena | Altura do slot | Overflow | `<script>` | Pageerrors |
|---|---|---|---|---|---|
| Home 1440px | `hero-particles` | 340px | 0 | **0** | 0 |
| Home 390px | `hero-particles` | 220px | 0 | **0** | 0 |
| Projetos 1440px | `portfolio-3d` | 240px | 0 | **0** | 0 |
| 404 1440px | `not-found` | 180px | 0 | **0** | 0 |

Home: 8 seções `.atlas-section`, 16 cards, header/footer/CTA presentes, botão
primário `rgb(67,56,202)` com texto branco, **zero handlers inline** e **zero
scripts**. Prancha: `BM-Preview-Cenas.png`.

> As capturas mostram o **fallback declarativo** (pontilhado/gradiente CSS).
> O WebGL animado é desenhado em runtime pelo `scene-runner.js` do Catálogo;
> a prévia estática prova o layout, os slots e os parâmetros, não a animação.

## 5. O que NÃO foi mexido

Nenhuma escrita do agente no GitHub; nenhum arquivo `dev/`, contrato,
`TEMPLATE_KEYS`, `CONFIG_KEYS`, `CHECKSUMS`, manifesto, schema, `ALTER`,
migration, runtime, MySQL ou deploy. Nenhum `.js`/`.twig` solto, `|raw`,
`<script>`, handler `on*=`, `innerHTML`, `eval`, `fetch`, fonte/CDN, upload em
disco ou contrato novo `kaz_scene`→`templates`. O Recorte A permanece
**dados validados, sem código executável**. Nenhuma decisão D01–D10.

## 6. Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. CI verde, PHP WASM,
Chromium e guardas **não significam release aprovada**. Aceite visual da BM,
health no Admin real e aplicação dos patches BL **ainda pendentes**.

## 7. Como usar

Ver `GUIA-BM.md`: importar o `.json` no **tema ativo** e **Ativar** (sem
Ativar, `save()` grava só `draft_config` — causa raiz da BL). Os patches
`A`/`B` da BL continuam **propostas não aplicadas**: o HEAD `ad7643de…`
commitou apenas `docs/`, com **`dev/` intacto**.

<!-- FIM: RELATORIO-FINAL-BM.md -->


<!-- INICIO: RELATORIO-FINAL-BN.md (7285 bytes) -->

# Relatório Final — Frente BN (David Universe 3D)

**Data:** 2026-11-07 · **HEAD:** `ad7643de6ca2` · **Pedido:** *"um projeto que
estava trabalhando o novo tema oficial do meu site
https://davidalmeida.xyz/threejs/?lang=pt … fazer um tema completo seguindo
este que está online como amostra"*.

**Contexto:** aceite visual explícito da BM (*"ficou muito bom"*). Seguem os
mesmos critérios de BI/BJ/BM: site real completo, 25 fontes Twig, Recorte A
(**dados validados, sem código executável**), ThreeJS declarativo e prévia
Chromium real.

## 1. A amostra online (medida, não presumida)

`fetch_page` nos 2 chunks + HTML bruto (953.239 bytes, SPA Vite/React) +
**Chromium 141 headless real** com WebGL:

| Propriedade | Valor medido |
|---|---|
| Título | `David L. Almeida — Desenvolvedor Criativo` |
| `<html lang>` | `pt-BR` |
| `body` background | `rgb(18, 21, 22)` = **`#121516`** |
| `body` color | `rgb(223, 223, 223)` = **`#dfdfdf`** |
| Fonte corpo | `"Open Sans", system-ui, sans-serif` |
| Fonte títulos | `"Poiret One", cursive` |
| `theme-color` | `#121516` |
| Canvas WebGL | **1** (planeta wireframe, nave, estrelas, orbes) |
| Seções / altura | 10 / 13.548px desktop · 20.611px mobile |
| Imagens | 21–22 · 0 pageerrors |
| Paleta | `#5cc6d0` (25×), `#121516`, `#a7b3b5`, `#323a3d`, `#eb767e`, `#ffdf00` |

**Conteúdo transcrito:** 9 fases — 00 Decolagem, 01 Sobre, 02 Processo (6
níveis), 03 Por que investir (3 power-ups), 04 Habilidades (classe +
inventário com 3×5 slots), 05 Serviços (3 blocos ×5 itens), 06 Catálogo (9
projetos: Juvenile World Academy, Steel of Death, Farmer Life Simulation,
Chameleon Digital Art, Rogério Consultoria, Pitbul Color, Cat Fe, Classic Car,
Selva Dev), 07 Depoimentos + Instagram, 08 Contato. HUD de jogo:
*"FASE 00 — DECOLAGEM"*, *"DISTÂNCIA 0 KM"*, *"ORBES 00/10"*,
*"Mova o mouse para pilotar · Role para voar · Colete os orbes"*,
*"Controles"*, *"Jogador 1 LVL 01"*.

Capturas de referência em `evidencia/ref-*.png` e `evidencia/ref.json`.

## 2. O tema entregue

`David Universe 3D` (`david_universe_3d`) — **88.864 bytes**, 25 fontes Twig,
checksum do config canonizado
`e081f0d26759034b15f1e200e2248199bbee43dea30765fb8977704c3f29a1d4`.

| Elemento | Como ficou |
|---|---|
| HUD fixo | marca `DA / DAVID L. ALMEIDA · Creative Developer`, 6 links, `ORBES 00/10` com 5 orbes coloridos, faixa `FASE 00 / DISTÂNCIA 0 KM` |
| Hero | `EU SOU` + `DAVID L. ALMEIDA` + `Desenvolvido` + tagline real + `Iniciar missão →` / `Ver catálogo` + painel **Controles** + redes |
| Fases | 10 `.du-phase` numeradas com números fantasma, tags `FASE 0X`, `Nível 0X · OK`, `Power-up 0X · +1` |
| Cartões | `.du-card` translúcidos com cantos HUD (mesmo desenho da amostra) |
| Catálogo | **9 níveis** com selos **SVG inline** (sem `.webp` remotas) |
| Inventário / serviços | 3×5 slots e 3 blocos ×5 itens, conteúdo real |
| Contato | ficha do jogador + formulário (nome/e-mail/mensagem) |
| Rodapé | `DAVID L. ALMEIDA` fantasma, © 2026, São Paulo, `contact@davidalmeida.xyz` |
| Cores | `#5cc6d0` / `#121516` / `#dfdfdf` / `#a7b3b5` / `#eb767e` / `#ffdf00` |

**Sem fontes remotas:** a amostra carrega Google Fonts (Poiret One / Open
Sans / Lato), o que é **proibido** pelas restrições do projeto. O CSS usa
`--du-display` com stack local (`"Poiret One", "Century Gothic", "Trebuchet
MS", system-ui`) — se a fonte existir no sistema ela é usada, senão cai na
stack sem rede. As 9 miniaturas de projeto são SVG inline determinísticos.

### ThreeJS declarativo (Recorte A)

O tema **não contém JavaScript**. O slot é o partial nativo
`partial/theme-scene.twig` (atributos `data-*`), e o `scene-runner.js`
self-hosted do Catálogo monta o WebGL. `conditions` allowlistadas trocam a
cena por rota — confirmado pelo `ThemeSnapshot` real:

| Rota | Cena | Slot | Partículas | Cor | Vel. |
|---|---|---|---|---|---|
| `common/home` | `hero-particles` | `hero` | 520 | `#5cc6d0` | 0.06 |
| `catalog/project_*` | `portfolio-3d` | `home_top` | 500 | `#eb767e` | 0.07 |
| `tool/tool_*`, `blog/blog_list` | `tools-3d` | `home_top` | 350 | `#ffdf00` | 0.08 |
| `error/not_found` | `not-found` | `footer_top` | 200 | `#a7b3b5` | 0.04 |
| mobile | `hero-particles` + `du-scene-lite` | `hero` | 180 | `#5cc6d0` | 0.04 |

`kaz_scene`: **20 camadas** (960×540, grid 8) desenhando HUD, planeta, cartões
de fase e CTA — dados de edição/versionamento para a prévia do Admin, não
renderização no front. `parent` vazio (autossuficiente, evita o defeito BL).

## 3. Validação com as classes reais (PHP 8.4 WASM)

`evidencia/export-bn.txt` — `ThemePackage`, `ThemeSnapshot`, `ThemeSceneCss`,
`TokenRegistry` e `ConditionEngine` do HEAD `ad7643de…`:

| Verificação | Resultado |
|---|---|
| `validateConfig()` | **`[]`** |
| tokens modo escuro (AA) | OK |
| `ThemeSceneCss::compile()` | **20 camadas**, CSS 1629 bytes allowlistado |
| `ThemeSnapshot` nas 4 rotas | cenas corretas |
| `export()`/`encode()` | 88.864 bytes (< 512 KiB) |
| `decode()` | **`ok=true`, `errors=[]`** |
| checksum canonizado | OK |
| **Total** | **14 OK / 0 FALHAS** |

## 4. Prévia visual real (Chromium 141)

`evidencia/layout-bn.json` + 5 capturas. Correção aplicada: o HUD (orbes +
nav de idiomas) estourava 33px em 390px; compactado e revalidado em
**390 / 360 / 320px** com 0 overflow.

| Captura | Cena | Altura | Fases | Cards | Overflow | `<script>` | Pageerrors |
|---|---|---|---|---|---|---|---|
| Home 1440 | `hero-particles` | 320px | 10 | 37 | 0 | **0** | 0 |
| Home 390 | `hero-particles` | 210px | 10 | 37 | 0 | **0** | 0 |
| Catálogo | `portfolio-3d` | 230px | 1 | 6 | 0 | **0** | 0 |
| Ferramentas | `tools-3d` | 230px | 1 | 3 | 0 | **0** | 0 |
| 404 | `not-found` | 170px | 0 | 0 | 0 | **0** | 0 |

`bodyBg = rgb(18,21,22)` e botão `rgb(92,198,208)` medidos — idênticos à
amostra. **`BN-Amostra-vs-Tema.png`** traz a comparação lado a lado do hero
com a captura real de `davidalmeida.xyz/threejs/?lang=pt`.

> As capturas mostram o **fallback declarativo** (pontilhado + planeta em
> CSS). A animação WebGL/scroll-driven da amostra é feita em runtime pelo
> `scene-runner.js` do Catálogo; a prévia prova layout, HUD, slots,
> parâmetros e conteúdo, não o movimento da espaçonave.

## 5. O que NÃO foi mexido

Nenhuma escrita do agente no GitHub; nenhum arquivo `dev/`, contrato,
`TEMPLATE_KEYS`, `CONFIG_KEYS`, `CHECKSUMS`, manifesto, schema, `ALTER`,
migration, runtime, MySQL ou deploy. Nenhum `.js`/`.twig` solto, `|raw`,
`<script>`, handler `on*=`, `innerHTML`, `eval`, `fetch`, `@import`, `url()`,
`<link>`, fonte/CDN, upload em disco ou contrato novo. Nenhuma decisão
D01–D10; Recorte A intacto.

## 6. Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. CI verde, PHP WASM,
Chromium e guardas **não significam release aprovada**. **Aceite visual da
BN**, health no Admin real e aplicação dos patches BL **ainda pendentes**.

## 7. Como usar

Ver `GUIA-BN.md`: importar no **tema ativo** e **Ativar** (sem Ativar,
`save()` grava só `draft_config` — causa raiz da BL). Os patches A/B da BL
continuam **propostas não aplicadas**: o HEAD `ad7643de…` tem **`dev/`
intacto**.

<!-- FIM: RELATORIO-FINAL-BN.md -->


<!-- INICIO: RELATORIO.md (6091 bytes) -->

# ReamurCMS — Frente AG · O estado efetivo do tema na prévia

> **A AF fechou a cena; a AG fecha todo o resto.** `ThemeSnapshot::resolve()`
> já era a única fonte de verdade do que o visitante vê. Faltava fazer a
> **prévia** e os **painéis de comparação** consumirem esse estado por inteiro
> — e não apenas `token_css` + `scene_css`. **Sem migration, sem coluna, sem
> rota nova, sem `|raw` novo.**

| | |
| --- | --- |
| Base | `main` @ `2494669eda6e` (AF aplicada), `pushed_at=2026-11-05T11:10:07Z` |
| Árvore real | 3.209 entradas / 2.626 blobs / `truncated:false` |
| **Aderência** | **18/18 arquivos da AF byte a byte idênticos ao `main`**, 0 divergentes |
| Arquivos | **19** completos (15 substituições + 4 novos) + 3 evidências PNG + `RELATORIO.md` + `INDICE.md` |
| Réplica | 2213 → 2217 arquivos (15 alterados + 4 novos + 2198 intactos) |
| **Efeito visível** | PROBE 51/0 · Chromium 24/0 — **8 campos medidos mudam entre dois rascunhos**: radius, fonte, cor primária, superfície, cor computada e o próprio `theme.primary` no Twig |
| Harnesses | AG 51 + 24 · AF 43 + 22 · health 26 · boot 22 · Chromium 251 + 251 · layout 90 · DOM 226 · PROBE publicação 93 · alcance 5 = **1104/0** |
| Validação final | 177 OK / 0 FALHAS |
| Guards | 4/4 verdes e byte-idênticos |
| Sintaxe | 391 PHP 0 erros · 615 JS/CJS 0 falhas |
| Build | idempotente 5/5 · CRC 24/24 · `sha256sum -c` 57/57 |

---

## 1. O problema medido

`ThemeSnapshot::resolve()` devolve 39 chaves, entre elas seis blocos de CSS:
`token_css`, `component_css`, `schedule_css`, `media_css`, `site_css` e
`scene_css`. `startup/theme.php` publica todas e `common/header.php` as
concatena **nesta ordem** no `style` do body.

No `main` `2494669eda6e`:

- `preview()` (prévia isolada de um template) injetava **zero** dessas partes
  e nenhum CSS que as consumisse;
- `Twig::renderSandboxPreview()` fixava
  `theme=['primary'=>'#2563eb','surface'=>'#ffffff','vars'=>[],...]`;
- `ThemeCompareView::panel()` injetava apenas **duas** das seis partes
  (`token_css` + `scene_css`, vindas da AF).

Consequência: depois da AF a **cena** aparecia, mas alterar cor primária,
tipografia, densidade, radius, componentes, agenda ou sites no editor
**continuava sem nenhum efeito antes de Publicar**. Sem erro e sem console —
a mesma perda silenciosa da AF, um nível acima. Causa estrutural: **dois
consumidores do mesmo estado, cada um com sua própria lista de partes**.

## 2. A correção (5 arquivos de código)

| Arquivo | Mudança |
| --- | --- |
| `theme_preview_state.php` (**novo**) | `ThemePreviewState`: `css()` (6 partes na ordem do `header.php`, fail-closed por parte), `colorVars()`, `bodyClasses()`, `globals()` (só `theme`/`theme_*`, planos, ≤256 bytes) e `catalogCss()` (o `theme.css` real, leitura limitada) |
| `twig.php` | 4º argumento **opcional** `$themeState`; aplica os globais ao contexto; a cena sanitizada da AF continua prevalecendo |
| `design/theme.php` | `preview()` resolve o rascunho com o **mesmo** `ThemeSnapshot` que `compare()` já usava; injeta `catalogCss()+previewCss()`, `css()+colorVars()` no body e `bodyClasses()` na classe; `compare()` repassa `$pubState`/`$draftState`; cache `-af` → `-ag` |
| `theme_compare_view.php` | `panel()` usa `ThemePreviewState::css()` + `colorVars()`, corrige os nomes das variáveis para os emitidos pelo `TokenRegistry`, faz `border-radius` consumir `--rms-radius` e aplica `bodyClasses()` |

Um detalhe que só a medição revelou: **injetar variáveis não basta**. Sem o
`theme.css` real, as declarações `--rms-*` existiriam, mas nenhuma regra as
usaria — a prévia continuaria idêntica. `catalogCss()` fecha esse ponto.

`css()` descarta (nunca relaxa) qualquer parte com `<`, `>`, `{`, `}`,
barra invertida, `javascript:`, `@import`, `expression(` ou `behavior:`, e
limita cada parte a 8.192 bytes e o total a 65.536. `globals()` não pode
sobrescrever `header`/`footer` do sandbox. CSP, iframe sandbox, autoescape e
proibições de tag script / `srcdoc` / `iframe` / `form` permanecem intactos.

## 3. Prova ponta a ponta

`tools/kaz-theme-preview-state.php` (**51/0**, somente leitura) constrói
dois rascunhos A/B com `ThemeSnapshot::resolve()` real: radius 12→32px,
fonte 16→20px, cor primária `#2563eb`→`#dc2626`, texto/superfície
diferentes e um componente `card` habilitado. Verifica que `css(A) !==
css(B)`, que `css($state)` é **idêntico à concatenação que `header.php`
publica**, que o fail-closed descarta uma parte maliciosa mantendo as demais,
que `globals()` não vaza `header`/`footer`, e que o Twig sandbox renderiza o
`theme.primary` **do rascunho** (não o `#2563eb` fixo).

`tools/verify-kaz-preview-state.cjs` (**24/0**) abre os dois HTMLs em
Chromium real (desktop 1280×800 e mobile 390×844), mede `getComputedStyle` e
confirma **8 divergências**: `--rms-radius`, `--rms-font-size`,
`--theme-primary`, superfície, `border-radius` e `font-size` computados do
card, cor computada e os atributos renderizados pelo Twig. Salva

**Rota de aceitação no servidor:** alterar cor primária / densidade / radius
/ um componente → Salvar → Comparar (os dois painéis devem diferir) →
Pré-visualizar → Publicar → home do catálogo (o mesmo resultado).

## 4. O que não foi feito

- **Nenhuma migration, coluna, tabela ou `schema.sql` alterado**; o hash
  congelado foi conferido contra o `main` real. `kaz_scene` continua em
  `draft_config`/`published_config`.
- Nenhum manifesto, hash congelado, tradução, `PENDING_DECISION` ou
  `release_approved` tocado. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor/F9-103
  pausado; P2/F9-18 não reaberto.
- Nenhuma escrita nossa no GitHub: o pacote é para **você** instalar.
- **Harnesses, PROBE e Chromium headless não são o seu Admin autenticado.**
  Ainda não há nova avaliação visual do usuário desde `image-7.png`, nem
  veredito `copy(__kazHealth())` colado do servidor de produção. Esta frente
  **não** alega aprovação de release; CI verde ou health local não significam
  release aprovada.

<!-- FIM: RELATORIO.md -->


<!-- INICIO: Registro-Veredito-Visual-AP.md (4058 bytes) -->

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

<!-- FIM: Registro-Veredito-Visual-AP.md -->


<!-- INICIO: Roteiro-Proximos-Passos-AP.md (14167 bytes) -->

# Roteiro dos próximos passos — pós-AP

**Data:** 2026-11-06 (atualizado pela Frente AT) · **HEAD atual:**
`bfe9eec7aae82dc72c7054bcb46dbd901307d7c5`
(`docs(aq-acceptance): add full planning package and health tool`, árvore
3.224 entradas / 2.641 blobs, `truncated: false`). A Frente **AQ** foi
aplicada sobre `179269fe…` (`fix(bdre-editor): limit dark theme to editor`):
+6 / −0 / ~0, com **10/10 arquivos do pacote idênticos** no remoto.

**Natureza deste documento:** planejamento e leitura de contratos. **Não** é
uma frente de código, **não** executa runtime, **não** altera contrato,
**não** escreve no GitHub e **não** declara release. Aplicação da AP: +9 / −0 /
~6 em relação à AO, com os **6 arquivos substituídos byte a byte idênticos**
ao pacote entregue.

---

## 0. Ponto de partida (confirmado, não inferido)

| Item | Estado no HEAD |
|---|---|
| Aplicação técnica da AP | ✅ fiel (6/6 idênticos, 9 adições, 0 remoções) |
| **Veredito visual da AP** | ✅ **APROVADO pelo usuário em 2026-11-06** — `image-9.png` é registro histórico superado; `image-10.png` referência atendida |
| `decision-matrix.json` | `PENDING_DECISION: 10`, `DECIDED: 0` |
| F9-27 | `BLOCKED_NOT_RUN`, `release_approved: false`, `runtime_tests_executed: false` |
| F9-18 | `release_ready: false`, 4 achados abertos (2 × P1, 2 × P2) |
| F9-25 | `patterns_persistence: PROPOSAL_PENDING`, `catalog_context_preview: DESIGN_GATE_PENDING` |
| F9-26 | `wcag_conformance_claimed: false`, runtime/axe/performance `false`; foco `#174ea6`/3 px/2 px intacto |
| F9-14 | `standalone_in_memory_component_prepared_not_integrated` |
| CI remoto | ✅ **verificado** (Frente AT): run `42217429359` `completed/success`, **7/7 check-runs success** no HEAD `bfe9eec7…`; leitura pública da API, sem token |
| Health do Admin real | ⏳ pendente: `tools/health-admin-ap.js` já está no GitHub, falta colar no console do Admin autenticado |
| Aplicação da AQ | ✅ +6 / −0 / ~0; 10/10 arquivos idênticos (6 novos + 4 evidências já existentes da AP) |

> Regra que segue valendo: **aplicar ≠ aprovar**. AA e AO também foram
> aplicadas e depois reprovadas.

---

## 1. Passo 1 — Veredito visual ✅ **CONCLUÍDO: APROVADO (2026-11-06)**

> **Atualização AU.** O usuário respondeu explicitamente **`aprovado`** após
> avaliar o Admin real. Os quatro critérios abaixo foram considerados
> satisfeitos. `image-9.png` deixa de ser a reprovação vigente; o escopo
> "editar somente o BDREditor" fica **fechado e congelado**. Registro
> completo em `Registro-Veredito-Visual-AP.md`. **Isto não é release**:
> `release_approved: false` e todos os contratos permanecem como estavam.

Critérios avaliados (mantidos para rastreabilidade):

Abrir **Pages › Edit** e **Blog › Edit post** no Admin autenticado e conferir:

1. Item ativo da sidebar **azul** (`#0d6efd`), sem borda/linha verde.
2. Topbar, breadcrumb, título, cards, campos, abas de idioma, botões, alertas
   e badges **como antes da AO**.
3. BDREditor escuro, accent verde, **canvas branco**, documento íntegro.
4. **Nada mais** alterado fora do BDREditor (overlays do próprio editor podem
   e devem ser escuros).

**Como foi registrado:** resposta explícita **`aprovado`** do usuário em
2026-11-06, após avaliação no Admin real. `Protocolo-Aceitacao-Visual-AP.md`
e `Registro-Veredito-Visual-AP.md` foram atualizados; `image-9.png` passou a
registro histórico superado.

| Desfecho | Ação |
|---|---|
| ✅ **Aprovado (ocorrido)** | Veredito registrado; escopo congelado; passos 2–4 liberados para as ações do usuário |
| Reprovado (não ocorreu) | Frente de ajuste **pontual**, sem reabrir o escopo da página |

**Critério de saída: SATISFEITO** pelo veredito explícito. PROBE 53/0,
Chromium 41/0, 117 asserções finais e CI 7/7 foram apoio técnico — **não**
substituíram a decisão e **não significam release aprovada**.

---

## 2. Passo 2 — Saúde do Admin real e CI remoto

### 2.1 Correção necessária: `__kazHealth()` não existe

Varrendo **464 JS/PHP/Twig** e **460 `.md`/`.json`/`.txt`** do HEAD:
`kazHealth` aparece em **11 documentos** (relatórios meus) e em **nenhum
arquivo de código**. Ou seja, a recomendação repetida de *"rodar
`copy(__kazHealth())` em produção"* era uma formulação de relatório, não uma
função instalada. **Não tente executá-la.** O equivalente concreto é um
health **observacional, somente leitura**, pelo console do DevTools.

### 2.2 Health observacional (copiar e colar no console do Admin real)

```js
copy(JSON.stringify((() => {
  const q = s => document.querySelector(s);
  const cs = e => e ? getComputedStyle(e) : null;
  const host = q('[data-blog-block-editor]');
  const menu = q('#menu > li.active > a');
  const styles = [...document.querySelectorAll('link[rel="stylesheet"]')]
    .map(l => l.getAttribute('href') || '')
    .filter(h => h.includes('blog-block-editor-kaz'));
  return {
    path: location.pathname,
    marker_ap: styles.some(h => h.includes('-kaz-bdre-ap')),
    styles,
    bs_primary: getComputedStyle(document.body).getPropertyValue('--bs-primary').trim(),
    rms_accent: getComputedStyle(document.documentElement).getPropertyValue('--rms-accent').trim(),
    menu_active_bg: menu ? cs(menu).backgroundColor : null,
    page_bg: getComputedStyle(document.body).backgroundColor,
    host_present: !!host,
    host_bg: host ? cs(host).backgroundColor : null,
    editor_mounted: !!q('.rb-editor'),
    canvas_bg: q('.rb-canvas') ? cs(q('.rb-canvas')).backgroundColor : null
  };
})(), null, 2))
```

Somente leitura; não expõe sessão, token nem conteúdo de post. **Esperado**
(conforme o A/B/C medido): `marker_ap: true`, `bs_primary: "#0d6efd"`,
`rms_accent: "#0d6efd"`, `menu_active_bg: "rgb(13, 110, 253)"`,
`page_bg: "rgb(244, 246, 249)"`, `host_bg: "rgb(24, 24, 27)"`,
`editor_mounted: true`, `canvas_bg: "rgb(255, 255, 255)"`.
Qualquer divergência é **achado a investigar**, não erro automático — o
Admin real pode ter skin/modo diferente da prévia.

Complemento local (sem rede, sem PHP, sem banco): `bash tools/repo-health.sh`
— orquestrador *read-only* de 6 guardas; `SKIP` não conta como `PASS`.

### 2.3 CI remoto

O workflow `.github/workflows/php.yml` tem **6 jobs**: `composer`,
`php-lint`, `docs-links`, `static-analysis`, `node-deps`, `repo-guards`
(este último chama `tools/repo-health.sh`).

**Resultado verificado em 2026-11-06 (Frente AT).** A leitura pública da
API funcionou sem token — o `403 Must have admin rights` registrado antes
aplicava-se a outro endpoint, não a `/actions/runs` nem a
`/commits/<sha>/check-runs`:

| Commit | Run | Status | Check-runs |
|---|---|---|---|
| **`bfe9eec7…` (AQ, HEAD atual)** | `42217429359` | `completed / success` | **7/7 success** |
| `179269fe…` (AP) | `42212306218` | `completed / success` | **7/7 success** |

Os 7 checks: `php-lint`, `node-deps (22)`, `static-analysis`, `repo-guards`,
`node-deps (20)`, `docs-links`, `composer`. Atenção: o
`ci_status: "7/7 success (runs 41483802295 / 41504039830)"` gravado em
`decision-matrix.json` continua sendo de **runs antigos**; a evidência
acima é a do commit atual, registrada em `evidencia/ci-remoto-bfe9eec7.json`.

**Critério de saída do passo 2:** ✅ CI do commit atual verde (concluído) +
⏳ health colado do Admin real (pendente, depende do usuário). Isso **não**
converte `NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS` e **não** autoriza release.

---

## 3. Passo 3 — Governança dos contratos

### 3.1 Regra de ouro (literal do repositório)

> `policy`: *"Nenhuma decisao tomada por inferencia. Contratos congelados ate
> registro explicito de status=DECIDED."*

Ou seja: as 10 decisões são **do responsável**, não do agente. Minha parte é
preparar a pauta, não decidir.

### 3.2 Pauta das 10 `PENDING_DECISION`

| ID | Natureza | Checker | Recomendação registrada | Decisão que falta |
|---|---|---|---|---|
| **D03** | POSSÍVEL_MUDANÇA_ACL | `check_audio_catalog.cjs` | **B** — segurança/ACL: registrar achado, não relaxar checker | A ou B (B recomendado) |
| **D07** | SEGURANÇA_OU_REGRESSÃO | `e2e_f915.cjs` | **B** — se não for `Twig\Markup`, é regressão funcional P1 | investigar + A ou B |
| **D08** | DIVERGÊNCIA_REAL_CONTRATO | `e2e_f925_static.cjs` | **B** — exige confirmação de aprovação das features antes de mexer no contrato | aprovar features? depois A ou B |
| **D10** | DRIFT_METADADOS | `e2e_f927_static.cjs` | **B** — **não** atualizar o SHA sem autorização: equivaleria a declarar prontidão do gate | A ou B (B recomendado) |
| D05 | CONTRATO_HISTÓRICO | `e2e_bdre_unsaved_changes_static.cjs` | **A** — reconhecer histórico de revisões, mantendo a proibição de autosave | A ou B |
| D06 | CONTRATO_HISTÓRICO | `e2e_f914.cjs` | **A** — atualizar F9-14 pela evolução, **sem** alterar o checker de segurança | A ou B |
| D09 | ACHADO_REAL_GOVERNANÇA | `e2e_f905.cjs` | **B** — `schema.sql` é legítimo de outra fase? aposentar o checker histórico? | A ou B |
| D01 | CADEIA_MARCADORES | `e2e_bdre_hierarchical_reordering_static.cjs` | **A** — revisar marcadores um a um com evidência de equivalência | A ou B |
| D02 | EQUIVALENTE_NÃO_COMPROVADO | `e2e_bdre_spacer_static.cjs` | **INVESTIGAR** — pode ser regressão real do inspector; ler o código antes | investigar → A ou B |
| D04 | EQUIVALENTE_NÃO_COMPROVADO | `check_threejs_native_admin.cjs` | **INVESTIGAR** — confirmar equivalência da assinatura antes de atualizar marcador | investigar → A ou B |

**Ordem sugerida de pauta:** (1) segurança/ACL D03, D07, D08, D10 →
(2) contratos históricos D05, D06, D09 → (3) marcadores/equivalência D01,
D02, D04. Para cada uma registrar `status: DECIDED`, `decided_by` e
`decision_date` — **nunca** por inferência nem reescrevendo hashes.

### 3.3 Contratos bloqueantes e o que realmente falta

| Contrato | Estado | Pré-requisito real |
|---|---|---|
| **F9-27** | `BLOCKED_NOT_RUN`; matriz com 12 casos (10 `NOT_RUN` + 2 `BLOCKED_F9_12_UNKNOWN`); `f9_12_status: UNKNOWN_NOT_CONFIRMED` | 7 itens em `required_before_execution`: autorização escrita, staging descartável identificado, prova de isolamento, **confirmação de F9-12 pelo responsável**, rollback/dados sintéticos, escopo explícito das famílias runtime, contas de teste para pattern library |
| **F9-18** | `release_ready: false` | **F918-01 P1** (`dev/catalog/model/blog/blog.php:110-124`): exceção de lookup cacheada como "tabela ausente" → fallback legado `description|raw` *fail-open*; exige detecção tri-state e *fail closed*. **F918-02 P1** (`block_renderer.php:62-66`): URLs relativas em rotas SEO aninhadas. P2: F918-03 (idioma do sidecar), F918-04 (atomicidade do delete) |
| **F9-25** | `patterns_persistence: PROPOSAL_PENDING`; `catalog_context_preview: DESIGN_GATE_PENDING` | ACL dedicada (não inferir do ACL de Blog), revisão do install-agent e aprovação de schema/migration antes de qualquer persistência; nenhum endpoint de preview público |
| **F9-26** | `wcag_conformance_claimed: false`; `php_sql_browser_app_e2e_runtime: false`; `screen_reader_or_axe: false`; `measured_browser_performance: false` | runtime e medição em navegador real; **não** declarar conformidade WCAG a partir do headless |
| **F9-14** | `standalone_in_memory_component_prepared_not_integrated` | contrato histórico do MVP; tratado por D06/D09 — atualizar descrição, não reimplementar |

### 3.4 Sequência e critérios de saída

1. **Decidir** as 10 `PENDING_DECISION` (responsável) → `DECIDED` explícito.
2. **Fechar F918-01 e F918-02** com correção + evidência (dependente de
   ambiente com PHP/DB, portanto do passo 4).
3. **Só então** abrir F9-27 com as 7 condições do Release Gate satisfeitas.
4. **Nunca:** marcar `release_ready`/`release_approved` por inferência,
   reescrever `base_head`/hashes, aposentar checker sem decisão, ou converter
   `PENDING`/`SKIP`/`NOT_RUN`/`STATIC_ONLY`/`BLOCKED_NOT_RUN` em `PASS`.

---

## 4. Dependências em uma linha

`✅ 1 veredito visual (APROVADO)` → `2 health + ✅ CI` → `3 decisões + P1` →
`4 backend/ciclo salvar–Publicar` → `5 (opcional) identidade extra`.

**Após o veredito, todos os passos restantes dependem de uma ação do
usuário**: colar o health no console (2), decidir D01–D10 (3), autorizar
staging/runtime (4) ou revisar o escopo (5). Não há próxima frente de código
a ser entregue pelo agente sem uma dessas ações.

O passo 4 é o maior risco funcional remanescente e **exige** sessão
autenticada, MySQL e staging — tudo fora deste ambiente. Deve ser uma frente
separada e explicitamente autorizada, não uma continuação visual.

O passo 5 (screenbar / 3 colunas / grid do canvas do KAZTheme-Editor, ou
levar a identidade ao catálogo público) segue **vetado** pelas decisões
`onde=admin` e "não clonar layout KAZ"; só com nova decisão explícita.

---

## 5. O que não fazer agora

Declarar release; rodar migration ou SQL; alterar DOM, o JS de ~600 KB,
Twigs, renderer, `schema.sql`, traduções ou manifestos; reentregar A–AO;
tratar CI verde, health local, PHP WASM ou Chromium headless como aceitação;
escrever no GitHub pelo agente.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
F9-18 `release_ready: false`.

---

## 6. O que este roteiro fez e não fez

**Fez:** reauditou o HEAD pós-AP, confirmou 6/6 arquivos idênticos, leu 13
arquivos de contrato/CI do remoto, varreu 924 arquivos em busca de
`kazHealth`, corrigiu a inconsistência e montou a pauta de decisões.
**Atualização AT:** confirmou a aplicação da AQ (10/10 idênticos), verificou
o CI remoto do HEAD atual (7/7 success) e registrou a evidência em JSON.

**Não fez:** executar runtime, acessar banco/staging, aplicar correção,
alterar qualquer contrato, escrever no GitHub, deploy ou declaração de
release.

Se quiser, posso — ainda sem tocar no GitHub — transformar isto num pacote
em `entregas/`, preparar um modelo de registro para D01–D10, ou detalhar o
plano de correção de F918-01/F918-02 para o passo 4.

<!-- FIM: Roteiro-Proximos-Passos-AP.md -->
