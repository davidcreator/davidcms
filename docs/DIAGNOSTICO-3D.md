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
