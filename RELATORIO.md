# Frente W — Correção: "Mapa de sites inválido" ao personalizar um tema

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base:** `main` @ `004a99cd88ef5e28a9b8dcc8a2a68f2755ef6d09` (pai `aced5daa…`, frente V aplicada **7/7** no raw).  
**Motivo:** erro relatado ao personalizar um tema: *"Mapa de sites inválido: revise host, slug e idioma."*  
**Estado:** pacote instalável entregue para aplicação. **Não foi aplicado no GitHub por nós**; não é release aprovada.

## 1. Causa raiz (reproduzida, não inferida)

`theme_form.twig` sempre renderiza **8 blocos** `sites[<id>][host|slug|language|enabled]`.
`theme-sites.js` desabilita os campos das linhas desmarcadas no boot; se o script não
carrega, ou se o formulário é enviado sem essa desabilitação, os **8 blocos chegam ao
POST com `host=''`**. `buildDraftConfig()` montava as 8 entradas e chamava
`ThemeSites::normalize()`, que exige host válido em **todas** — `host=''` devolvia
`null`, e o controller respondia `error_sites` **antes** de qualquer `save()`.

Probe A/B sobre o `edit()` real do HEAD W: **9 de 12** POSTs falhavam, incluindo
`oito_vazios`, `um_valido_sete_vazios` e `cena_v_com_vazios` — a **cena persistente
da frente V também ficava bloqueada**. `twig_error=0` nos 24 casos A/B. Esse cenário
não estava coberto pelos probes A–V, que montavam POSTs **sem** a chave `sites`:
foi um **ponto cego dos testes anteriores**, não uma regressão introduzida pela V.

## 2. O que foi corrigido

| Antes (HEAD W) | Depois (pacote W) |
|---|---|
| 8 linhas vazias → `error_sites`, **0 gravações** | linha sem host, sem slug e desmarcada é descartada; o tema **salva** |
| mensagem genérica sem indicar o bloco | mensagem existente + **ID allowlistado** do bloco inválido (`(shop)`) |
| sites desabilitados com host preenchido eram apagados ao salvar | `submit` libera os campos dessas linhas, **preservando** a política |
| cache key `-theme-sites-t` | `-theme-sites-w` (evita browser servir o JS antigo) |

**Nada foi relaxado.** Continuam inválidos com **zero gravações**: host com scheme,
porta (`:8080`), underline, caminho, `..`, `.-`, `-.`, >253 caracteres; slug fora de
`^[a-z0-9_-]{1,48}$`; idioma desconhecido; >8 sites; ID fora da allowlist; checkbox
marcado sem host; chave extra ou valor não escalar. `ThemeSites::normalize()`,
`hostKey()`, `match()`, `byId()`, `ThemePackage::validateConfig()` e `MAP_KEYS`
**não foram alterados** (byte-idênticos A/B). Sem chave de tradução nova (418×3),
sem `|raw` novo, migration, coluna, `schema.sql`, manifesto ou hash de release.

**Servidor soberano:** com JS antigo em cache ou JavaScript desligado, a correção do
controller já resolve o bloqueio. O cliente é uma melhoria independente.

## 3. Arquivos (4 completos, sem patches)

| Arquivo | Papel |
|---|---|
| `dev/admin/controller/design/theme.php` | descarta linha vazia/desmarcada antes de `normalize()`; diagnóstico com ID allowlistado; cache key `-theme-sites-w` |
| `dev/admin/view/js/theme-sites.js` | no `submit`, libera campos de linhas **com host preenchido** (preserva sites desligados); linhas vazias continuam `disabled` |
| `docs/GUIA-MULTISITE.md` | regra de linhas em branco, preservação e limites que permanecem |
| `docs/CORRECAO-MAPA-DE-SITES.md` | **novo**: causa raiz, evidência A/B, correção, instalação e limitações |

O ZIP tem **6 entradas** (4 do pacote + este `RELATORIO.md` e o `INDICE.md`). O
`.sha256` fica **fora**. Nenhum dos 14 manifestos cobre os 4 paths (**4/4 livres**);
`theme_sites.php` e `theme_package.php` não foram tocados. 3 arquivos substituem,
1 é novo.

## 4. Validação executada (números reais)

A/B **real** com bootstraps PHP separados (`FW_MODE=head|pkg`), bibliotecas reais do
HEAD W em ambos, controller do HEAD vs do pacote, Twig 3.14 renderizando o formulário
nos dois lados, editor da V e `theme-sites.js` executados no jsdom 29.1.1, PHP 8.5.12 WASM.

| Verificação | Resultado |
|---|---|
| Probe A/B `edit()` — 12 cenários × HEAD e pacote | **12 + 12**, `twig_error=0` |
| H1 contrato, `ThemeSites` intocado, V 7/7, gates, manifestos | **199/0** |
| H2 pipeline real do pacote, 12 cenários, valores literais | **236/0** |
| H3 A/B HEAD × pacote, 12 cenários, corrigidos e fail-closed | **332/0** |
| H4a Autoloader real carregando controller + bibliotecas | **31/0** |
| H4b regressão A–V, 418×3, BDREditor, manifestos, docs | **219/0** |
| H5 jsdom: `sync()`, `submit`, sonda, editor da V, 12 cenários | **314/0** |
| `php -l` (1 PHP do pacote) | **1/0** |
| `node --check` (471 JS do HEAD W, com o do pacote) | **471/0** |
| **Total de asserções + sintaxe** | **1803/0** |

Probe: HEAD **9 erros / 2 gravações**; pacote **6 erros / 5 gravações**. Os 3 casos
corrigidos (`oito_vazios`, `um_valido_sete_vazios`, `cena_v_com_vazios`) passam a salvar
— o último **com a `kaz_scene` da V preservada**. Os 6 casos realmente inválidos
(`host_com_porta`, `host_com_scheme`, `host_com_underline`, `enabled_sem_host`,
`slug_invalido`, `idioma_invalido`) continuam com **0 gravações** nos dois lados e
ganham o diagnóstico `(shop)` no pacote. `sem_sites`, `id_desconhecido` e o GET do
formulário mantêm comportamento idêntico.

Réplica A/B: **1314 paths**, **3 alterados**, **1 novo**,
**1311 congelados idênticos**; guards **4/4 verdes e byte-idênticos**
(`verify-checksums`, `repo-health`, `verify-ci-workflow`, `verify-contract-freeze`);
14 manifestos idênticos A/B; 418 chaves ×3; 25 `TEMPLATE_KEYS`;
**44 arquivos de A–V intactos**; V **7/7** no raw; `ThemeSites` e
`ThemePackage` byte-idênticos A/B; BDREditor **77 paths** intocado; `schema.sql`
presente; 10 `PENDING_DECISION` e 11 hashes congelados; build idempotente **4/4**;
CRC **6/6**; `sha256sum -c` **47/47**.

## 5. Limitações e estado do repositório

- **Não disponível neste ambiente:** PHP nativo, Composer, PHPStan, rsync, navegador real,
  MySQL, sessão Admin autenticada, DNS/vhost, E2E, staging, deploy e CI remoto
  verificado. PHP WASM, Twig e jsdom com mocks **não são um servidor**. Nenhuma escrita
  no GitHub foi feita por nós; **CI verde/health ≠ release aprovada**.
- F9-27 continua `BLOCKED_NOT_RUN` com `release_approved:false`;
  `docs/F9-27-Base-Hashes.json`, `docs/F9-18-Risk-Register.json`,
  `tools/decision-matrix.json` e `schema.sql` intocados. Nenhum `PENDING_DECISION`,
  `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` virou `PASS`; nenhum guard
  foi relaxado. BDREditor/F9-103 pausado; P2/F9-18 não reabertos; a limitação de
  hreflang da frente **N permanece**. As frentes A–V não foram reentregues.
- **Escopo:** esta frente é a **correção do erro relatado**, decidida por nós a partir
  da reprodução. Não há nova funcionalidade de produto, renderização da cena no
  Catalog, ADR, `ThemeSnapshot`, `ConditionEngine`, `header.php` ou hashes de release
  alterados. Após confirmação, o próximo passo de funcionalidade pode ser retomado.
- O relatório/índice da raiz do HEAD ainda são os da frente V; as **entradas finais**
  neste ZIP são as desta frente (padrão A–V).

## Instalação

Extrair os 4 arquivos completos sobre a raiz do repositório, preservando a estrutura
de diretórios (3 substituem, 1 é novo). `RELATORIO.md` e `INDICE.md` são informativos.
Não há patches, migração nem manifestos a atualizar. Depois:
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`. Sem aplicação, o
comportamento do Admin permanece idêntico ao HEAD W.
