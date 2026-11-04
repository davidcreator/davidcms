# Frente V — KAZTheme-Editor: cena persistente no tema

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base:** `main` @ `aced5daa678c5e7c744c6f5fc9b2c8c3fb2394c5` (pai `018cafb…`, frente U aplicada **7/7** no raw).  
**Estado:** pacote instalável entregue para aplicação. **Não foi aplicado no GitHub por nós**; não é release aprovada.

## 1. O que mudou de verdade

Na frente U a cena do editor era **browser-local**: sobrevivia apenas por exportar/importar JSON. Nesta frente a cena passa a ser **parte do tema** e é gravada pelo botão **Salvar** do próprio gerenciador de temas, na mesma transação de `config` que já existia.

| Antes (HEAD V) | Depois (pacote V) |
|---|---|
| `CONFIG_KEYS` com 13 chaves | 14 chaves, a nova `kaz_scene` na allowlist |
| textarea do editor sem `name` | `name="kaz_scene_json"`, preenchido com `JSON_HEX_*` |
| reabrir o tema recriava 5 camadas derivadas | reabre as camadas **salvas** (posições, texto, cores, visibilidade, travamento) |
| POST ignorava o campo | `buildDraftConfig()` valida e persiste; JSON inválido → `error_validation` e **zero gravações** |
| child-theme herdava tudo | `MAP_KEYS` **não** inclui `kaz_scene`: um tema filho substitui a cena integralmente |

**Migração zero:** temas A–U não têm a chave; `validateConfig()` a aceita ausente e o controller usa `kazScene($config)` como fallback, gerando as mesmas 5 camadas derivadas da U. Nenhuma coluna, tabela, `schema.sql`, migration, sessão nova ou chave de tradução nova.

## 2. Arquivos (7 completos, sem patches)

| Arquivo | Papel |
|---|---|
| `dev/system/library/theme/theme_package.php` | `SCENE_*`, `validateScene()`, `normalizeScene()`, `validateConfig()` aceita a 14ª chave, `sanitizeConfig()` descarta tipo errado, `decode()` revalida a cena pelo checksum, `spec()` publica os limites |
| `dev/admin/controller/design/theme.php` | lê a cena salva, publica `kaz_scene_saved`/`kaz_scene_json`, reflete o POST, `buildDraftConfig()` fail-closed, assets `-kaz-theme-editor-v` |
| `dev/admin/view/js/kaz-theme-editor.js` | `syncField()` a cada mutação + listener `submit`; status "cena salva no tema" / "cena derivada do config" |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | campo real `name="kaz_scene_json"`, `data-kaz-saved`, nota de procedência |
| `docs/KAZTheme-Editor.md` | referência atualizada (contrato, 14 chaves, migração zero) |
| `docs/KAZTheme-Editor-Guia-Rapido.md` | "Salvar a cena" e "Cena derivada do config" |
| `docs/KAZTheme-Editor-Cena-Persistente.md` | **novo**: decisão, contrato, round-trip, child-theme, limites |

O ZIP tem **9 entradas** (7 do pacote + este `RELATORIO.md` e o `INDICE.md`). O `.sha256` fica **fora** do ZIP. Nenhum dos 14 manifestos cobre os 7 arquivos (**7/7 livres**); `theme_package.php` não está entre os 11 artefatos congelados por hash de `tools/decision-matrix.json`.

## 3. Contrato `kaztheme-scene/v1` no servidor

`version=1`, canvas `960×540` com `grid=8`, 5 `kind` e 5 `role`, máximo **48 camadas** e **65.536 bytes**. `normalizeScene()` reconstrói cada camada com exatamente **14 chaves** e descarta extras, casando com o editor. `validateScene()` rejeita tipo, versão, canvas, tamanho, número de camadas, `id` duplicado, enums, números, cor hexadecimal, texto com marcação/`on*`/`srcdoc`/`formaction`, flags e `bind`. `validateConfig()` devolve `kaz_scene_invalid` para cena inválida e aceita a chave ausente. `export()`/`decode()` fazem round-trip e o `decode()` adulterado falha pelo checksum. `MAP_KEYS` sem `kaz_scene` ⇒ `resolveChain()` substitui a cena do tema filho, sem mesclar camadas.

## 4. Segurança

Sem `|raw` novo, `custom_js`, `eval`, `call_user_func` novo, `fetch`, `innerHTML`, tag `script` inline, atributo `on*`, `srcdoc`, WebGL, Three.js, upload, migration, coluna ou escrita remota. Texto do usuário vai para `textContent`; o JSON entra por `|e('html_attr')` com `JSON_HEX_TAG|HEX_AMP|HEX_APOS|HEX_QUOT`. O POST usa a mensagem `error_validation` **já existente** nos 3 idiomas (418 chaves ×3 intactas, nenhuma chave nova). Import malformado ou fora do contrato é rejeitado integralmente, sem alterar o estado. `validateConfig()` é fail-closed: erro de validação → nenhum `save()`.

## 5. Validação executada (números reais)

A/B **real** com bootstraps PHP separados (`FV_MODE=head|pkg`), bibliotecas reais do HEAD V em ambos, `ThemePackage`/controller/template do HEAD vs do pacote; Twig 3.14 renderizando o formulário nos dois lados; editor executado no jsdom 29.1.1; PHP 8.5.12 WASM.

| Verificação | Resultado |
|---|---|
| Probe A/B `edit()` — 12 cenários × HEAD e pacote | **12 + 12**, `twig_error=0` |
| H1 contratos, `kazScene()`, `CONFIG_KEYS` 14, `validateScene/normalizeScene`, autoloader | **170/0** |
| H2 pipeline real do pacote, 12 cenários, valores literais | **530/0** |
| H3 A/B HEAD × pacote, 12 cenários, congelados e derivações | **767/0** |
| H4a Autoloader real carregando controller + `ThemePackage` do pacote | **25/0** |
| H4b regressão A–U, manifestos, 418×3, gates, BDREditor, U 7/7 | **230/0** |
| H5 jsdom: DOM real, editor executado, `syncField`/`submit`, XSS, 12 cenários, contrato | **413/0** |
| `php -l` (2 PHP do pacote) | **2/0** |
| `node --check` (471 JS do HEAD V, com o do pacote) | **471/0** |
| **Total de asserções + sintaxe** | **2608/0** |

Probe: HEAD `com_nome=0`, `gravados=8`, `asset_v=u,u`, contrato `nao`; pacote `com_nome=10`, `gravados=2`, `asset_v=v,v`, contrato `sim` (os 2 POSTs válidos redirecionam, por isso 10 formulários com `name`). `abre_com_cena`: pacote `data-kaz-saved=1`, 2 camadas, `#111111`, nota "Cena salva neste tema"; HEAD 5 camadas derivadas `#2563eb`. `post_json_ruim`: pacote **0 gravações** + `error_validation`; HEAD 1 gravação.

Réplica A/B: **1313 paths**, **6 alterados**, **1 novo**, **1307 congelados idênticos**; guards **4/4 verdes e byte-idênticos** (`verify-checksums`, `repo-health`, `verify-ci-workflow`, `verify-contract-freeze`); 14 manifestos idênticos A/B; 418 chaves ×3; 25 `TEMPLATE_KEYS`; **40 arquivos de A–U intactos**; U **7/7** no raw; BDREditor **77 paths**, nenhum no pacote e todos congelados; `schema.sql` presente; 10 `PENDING_DECISION` e 11 hashes congelados intocados; build idempotente **7/7**; CRC **9/9**; `sha256sum -c` **46/46**.

## 6. Limitações e estado do repositório

- **Não disponível neste ambiente:** PHP nativo, Composer, PHPStan, rsync, navegador real, MySQL, sessão autenticada, migration, staging, deploy, E2E e CI remoto verificado. PHP WASM, Twig e jsdom com mocks **não são um servidor**. Nenhuma escrita no GitHub foi feita por nós; **CI verde/health coerente não é release aprovada**.
- F9-27 continua `BLOCKED_NOT_RUN` com `release_approved:false`; `docs/F9-27-Base-Hashes.json`, `docs/F9-18-Risk-Register.json`, `tools/decision-matrix.json` e `schema.sql` intocados. Nenhum `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` foi convertido em `PASS`; nenhum guard foi relaxado.
- **Escopo decidido, não autorizado em detalhe:** persistir a cena no config foi uma decisão de implementação nossa; **não há renderização da cena no Catalog**, nem ADR0080, `ThemeSnapshot`, `ConditionEngine`, `header.php`, hashes de release ou manifestos alterados. BDREditor/F9-103 pausado; P2/F9-18 não reaberto; a limitação de hreflang da frente **N permanece**. As frentes A–U não foram reentregues.
- O relatório/índice da raiz do HEAD ainda são os da frente U; as **entradas finais** neste ZIP são as desta frente (padrão A–U).

## Instalação

Extrair os 7 arquivos completos sobre a raiz do repositório, preservando a estrutura de diretórios (1 é novo, 6 substituem). `RELATORIO.md` e `INDICE.md` são informativos e podem substituir os da raiz. Não há patches, migração nem manifestos a atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`. Sem aplicação, o comportamento do Admin permanece idêntico ao HEAD V.
