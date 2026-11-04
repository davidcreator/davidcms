# ReamurCMS — Frente X: Biblioteca de Blocos + Alterações Não Salvas

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base inspecionada:** `main` @ `cd92573c507b6a5a4189d7b1b574cb73ec17ad59` — pai `004a99cd88ef5e28a9b8dcc8a2a68f2755ef6d09`, `2026-11-04T15:04:47Z`, mensagem `fix(theme-sites): resolve invalid site map error for empty rows`.  
**Árvore:** 3.173 entradas, 2.591 blobs, 14 manifestos `CHECKSUMS*.txt`, `truncated:false`.  
**Estado:** 1 ZIP instalável entregue para aplicação. **Não foi aplicado no GitHub por nós.** Não é release aprovada.

## 1. O que esta frente entrega

Mudança **funcional e visível no Admin**, sem reentregar as frentes A–W:

1. **Biblioteca de blocos no KAZTheme-Editor** — 6 presets declarativos
   (`hero`, `card`, `banner`, `titulo`, `galeria`, `cta`) que inserem, por clique,
   conjuntos de **2–3 camadas reais** no canvas. As camadas entram na árvore, no
   SVG, no histórico (desfazer/refazer) e no campo `kaz_scene_json`, portanto
   **são persistidas na `kaz_scene` ao salvar o tema**. Cada preset gera apenas
   camadas válidas do contrato `kaztheme-scene/v1`: `kind` entre os 5
   permitidos, `role='none'`, `bindId=''`, cores hex, snap na grade de 8 px,
   IDs únicos e todos os limites numéricos respeitados.
2. **Selo "Alterações não salvas"** — `data-kaz-dirty` + badge no cabeçalho.
   Compara a cena atual com o `baseline` carregado do servidor: **não aparece ao
   abrir a tela**, aparece em qualquer mutação (inclusive inserir bloco),
   desaparece no `submit` e também ao desfazer até o estado inicial.

O cache key dos assets passou de `-kaz-theme-editor-v` para
`-kaz-theme-editor-x` para impedir que o navegador sirva o JS antigo.

## 2. Causa e decisão de escopo

A frente W (mapa de sites) foi confirmada aplicada e está **4/4 byte-idêntica**
no HEAD X; a V está **6/7** porque a W sobrepôs legitimamente o mesmo
`theme.php`. Para o próximo passo, a evolução natural do editor entregue em U/V
era sair de "criar camadas uma a uma" para **compor blocos prontos** e dar ao
usuário um **sinal claro de estado não gravado**. Escolhemos fazer isso
**inteiramente dentro dos arquivos já livres do KAZTheme-Editor**: presets são
constantes no JavaScript, não vêm de request/sessão/arquivo, e não exigem
runtime, Catalog, `ThemeSnapshot`, `ConditionEngine`, schema, migration, coluna,
`custom_js`, `|raw` novo, chave de tradução nova nem manifesto.

**Fail-closed:** ID de bloco desconhecido é rejeitado sem alterar nada; se
`camadas atuais + camadas do bloco > 48` (`MAX_LAYERS`), a inserção é recusada
integralmente. Inserir um bloco **não** aplica bindings ao formulário — o
usuário continua precisando de **Aplicar ao tema**, como em U/V.

## 3. Arquivos do pacote (7 entradas, todos completos)

| Arquivo | Bytes | Tipo | SHA-256 |
| --- | ---: | --- | --- |
| `docs/KAZTheme-Editor-Biblioteca-de-Blocos.md` | 4488 | NOVO | `a93a052915942fd1…` |
| `docs/KAZTheme-Editor-Guia-Rapido.md` | 4827 | substitui | `5934da5a78129b20…` |
| `docs/KAZTheme-Editor.md` | 9605 | substitui | `f9fbcc16dabdc379…` |
| `dev/admin/view/css/kaz-theme-editor.css` | 4502 | substitui | `1b8b33c6ab72f9ab…` |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | 8031 | substitui | `7e02888a1a925d4e…` |
| `dev/admin/view/js/kaz-theme-editor.js` | 24328 | substitui | `21617c066c82b840…` |
| `dev/admin/controller/design/theme.php` | 69616 | substitui | `16ef309aaddc61ed…` |

`RELATORIO.md` e `INDICE.md` são as duas últimas entradas do ZIP e **não** são
arquivos do repositório (os dois paths já existem na raiz do `main` e não foram
tocados). Os 7 paths estão **LIVRES** nos 14 manifestos (142 entradas, 90 paths
cobertos). Não há patches, diff ou arquivo parcial.

## 4. O que NÃO mudou (congelado)

- `ThemePackage` (`CONFIG_KEYS` com 14 chaves, `MAP_KEYS` sem `kaz_scene`,
  `validateScene()`, `normalizeScene()`, `validateConfig()`, `decode()`,
  `export()`, 25 `TEMPLATE_KEYS`) e `ThemeSites`: **byte-idênticos** A/B.
- `theme_form.twig`, `theme-sites.js`, `ThemeSnapshot`, `ConditionEngine`,
  `startup/*`, runtime do Catalog, as 418 chaves de tradução × 3 idiomas, os
  14 manifestos, os 11 artefatos congelados por hash, `schema.sql`,
  `F9-27-Base-Hashes.json`, `tools/decision-matrix.json`, `release_approved`,
  10 `PENDING_DECISION` e os 77 paths do BDREditor/F9-103.
- Sem migration, coluna, `custom_js`, `|raw` novo, WebGL, Three.js real,
  upload, `localStorage`, tag `script` inline, atributo `on*`, `fetch`,
  `eval`, `innerHTML`, escrita remota, deploy ou release aprovada.
- **A cena continua sem renderização no site público**: é dado de edição e
  versionamento. Child-themes continuam substituindo-a integralmente.
- A correção da W (linhas vazias do mapa de sites) e a persistência da V
  continuam funcionando: o probe A/B salvou `post_sites_vazios`,
  `post_valido`, `post_bloco_hero` e `post_sem_campo` nos **dois** lados, e
  rejeitou `post_invalido` e `post_49_camadas` nos **dois** lados com
  `error_validation`.

## 5. Validação executada (tudo real, sem inferência)

| Verificação | Resultado |
| --- | --- |
| H1 — contrato, bibliotecas, HEAD X = W 4/4 / V 6/7, gates | **214/0** |
| H2 — pipeline do pacote (12 cenários reais `edit()`/`compare()`) | **614/0** |
| H3 — A/B real HEAD X × pacote X (12 cenários + congelados) | **884/0** |
| H4a — Autoloader real carregando controller do pacote | **34/0** |
| H4b — regressão A–W, manifestos, traduções, BDREditor | **218/0** |
| H5 — jsdom 29.1.1: 6 blocos, dirty state, V/W, 12 cenários | **946/0** |
| `php -l` (PHP WASM 8.5.12) em todos os `.php` do recorte | **136/0** |
| `node --check` em todos os `.js` do recorte | **471/0** |
| **Total de asserções** | **3517/0** |

Bootstraps separados HEAD/pacote, PHP 8.5.12 WASM + Twig 3.14 + mocks de
serviços: **12 + 12 cenários**, `twig_error=0` nos 24. No pacote, os 7
cenários que renderizam formulário exibem **6 botões `data-kaz-block`**, o
badge `data-kaz-dirty-badge`, `data-kaz-dirty="0"` e o asset
`-kaz-theme-editor-x`; no HEAD, 0 botões, 0 badge e asset `-v`. Os 21 campos
de servidor (gravações, `error_validation`, redirects, `kaz_scene`, camadas
salvas, `db_calls`, `name_field`, `sites_field`, `config_keys_count`,
`raw_markers`, XSS) são **idênticos A/B**.

**H5 executou os 6 blocos**: cada um gerou a contagem esperada (3/2/2/2/3/2
camadas), passou em `validScene()`, sincronizou o textarea, marcou
`dirty=true`/badge visível, produziu IDs únicos e respeitou as 14 chaves,
enums, hex, snap e limites. Também verificou `insertBlock('evil') == false`
(sem alteração), recusa no limite de 48 camadas, `undo()` até o baseline
limpando o selo, `submit` atualizando o baseline, clique real no botão, e a
coexistência com `theme-sites.js` da W (8 linhas, submit libera host válido).

**Réplica A/B:** 1149 paths / 17955466 bytes / 0 vazios;
6 alterados + 1 novo + 1142 congelados;
14 manifestos idênticos; 7/7 paths livres.

**Nota sobre os guards:** `verify-f11-01.sh` (invocado por `repo-health.sh`)
executa `npm ci --prefix dev/system/storage` e cria 178 arquivos locais
que o Git ignora — o HEAD X versiona **apenas**
`dev/system/storage/node_modules/index.html`. A réplica A/B foi medida **antes**
dessa instalação, os artefatos foram removidos depois e os 14 manifestos e os
1142 paths congelados foram **revalidados idênticos**. A comparação
das saídas normaliza o caminho absoluto do ROOT impresso no cabeçalho dos
guards (única diferença entre HEAD e réplica).

**Guards existentes, HEAD × réplica (4/4 verdes e saídas IGUAIS):**
`verify-checksums.sh` (3 PASS / 0 FAIL, 14 manifestos / 142 arquivos),
`repo-health.sh` (6 PASS / 0 FAIL / 0 SKIP), `verify-ci-workflow.sh`
(31 PASS / 0 FAIL) e `verify-contract-freeze.py` (11/11 hashes coerentes,
10 pendentes de decisão). **Nenhum checker novo foi criado.**

Build idempotente 7/7; CRC 9/9 do ZIP; `sha256sum -c` global 48/48.

## 6. Limitações declaradas

Este ambiente não tem PHP nativo, Composer, PHPStan, rsync, navegador real,
MySQL, sessão Admin autenticada, migration, staging, deploy, E2E nem **CI remoto
verificado**. PHP WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não são um
servidor**: a validação é de contrato, sintaxe, render Twig e DOM, não de
requisição HTTP real. Nenhuma escrita no GitHub foi feita por nós; o HEAD X é
uma escrita do usuário. Nenhum status `PENDING_DECISION`, `STATIC_ONLY`,
`NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` foi convertido em `PASS`. F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`; BDREditor/F9-103 pausado;
P2/F9-18 não reaberto; a limitação de hreflang da frente N permanece.
**CI verde / health coerente ≠ release aprovada.**

## 7. Instalação

1. Extrair os 7 arquivos completos sobre a raiz do repositório (6 substituem,
   1 é novo). Nenhum manifesto precisa ser atualizado.
2. `bash tools/verify-checksums.sh && PYTHON_CMD=python3 bash tools/repo-health.sh`.
3. Admin → Design → Temas → editar um tema: o card KAZTheme-Editor mostra a
   **Biblioteca de blocos** e o selo **Alterações não salvas** após editar.
   Limpar o cache do navegador não é necessário (cache key `-x`).
4. Se o selo não aparecer ao inserir um bloco, confirme que o JS servido é o
   `-kaz-theme-editor-x` e relate o comportamento.

---
**Próximo passo:** aguardar confirmação de aplicação antes de qualquer nova frente.
