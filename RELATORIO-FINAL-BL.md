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
