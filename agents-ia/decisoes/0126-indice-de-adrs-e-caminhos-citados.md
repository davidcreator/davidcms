# ADR 0126 — O índice de ADRs completo, e o documento que não manda rodar o que não existe

| | |
| --- | --- |
| **Status** | ✅ executada (2026-11-20) |
| **Data** | 2026-11-20 |
| **Agente proponente** | system |
| **Contexto** | Fui procurar, fora do escopo de pagamento, o mesmo tipo de defeito que apareceu cinco vezes seguidas nas últimas entregas: **documento que afirma coisa que o código já não faz**. A varredura de caminhos citados em documentos operacionais encontrou **9 instruções que não funcionam** e, ao conferir o índice de ADRs, um problema maior: **65 das 125 ADRs não estavam no índice** — inclusive a 0033, a 0034 e a 0037, que as ADRs de pagamento citam como base. Quem procurasse "webhooks" ou "marketplace" no índice não achava nada. |
| **Relacionadas** | ADR 0004 (registro de exceção de pasta), ADR 0037 (remoção do legado morto — a origem de metade dos caminhos quebrados), ADR 0033/0034 (as ADRs que estavam fora do índice), ADR 0082 (guardas do CI) |

## O que estava quebrado, com nome

### 1. O índice cobria metade do registro

| | |
| --- | --- |
| ADRs no disco | **125** |
| No índice | **60** |
| Fora do índice | **65** (0005–0071, quase todas, com 0048/0049 e 0072+ intercaladas) |

O padrão do buraco diz o que aconteceu: o índice foi mantido nas primeiras entregas, cada linha escrita à
mão; a partir da ADR 0005 as decisões passaram a ser registradas só pelo arquivo. **A regra do repositório
diz "ADR numerada + linha no índice no mesmo commit"** — a regra existia e não tinha quem a verificasse.

### 2. Nove caminhos citados que não existem

| Documento | Citava | Realidade |
| --- | --- | --- |
| `LEIA-ME.md`, `KZ-64-Como-Aplicar.md` | `dev/system/config/config.php` | o arquivo é `catalog/config.php`; `dev/system/config/` é o **diretório** dos configs por aplicação |
| `CONTRIBUTING.md` | `dev/tests/` como exceção aprovada | a pasta não existe; os testes vivem em `tests/` |
| `docs/Estrutura-de-Pastas.md` §7 inteiro | `dev/system/webhook/` | **removida na ADR 0037** — seção descrevia pasta, controllers e models inexistentes |
| `docs/arquitetura/Arquitetura-MVCL.md` | `dev/system/webhook/` como aplicação | idem |
| `docs/integracoes/Webhooks.md` §§2–4, 6 | `system/engine/webhook.php`, `dev/system/webhook/…` | a classe `Webhook` foi substituída pelo `Event`; a "criação de webhook" descrita em 40 linhas de PHP não existe |
| `docs/arquitetura/Dicionario-de-Dados.md` | `upgrade_f9_02_custom_fields_schema.sql` | as tabelas estão no `schema-complete.sql` |
| `docs/guias/Guia-CMS.md` | `upgrade_f10_01_cms_catalog_routes.sql` | a coluna está no `schema-complete.sql` |
| `docs/integracoes/MercadoPago-Marketplace.md` §5 | `tools/e2e/e2e_f502.sh` | não existe; a bateria saiu no cutover |

O `Webhooks.md` era o pior: **quatro seções** ensinavam a criar um webhook escrevendo um controller
`ControllerWebhookSaleOrder`, registrando evento em `admin/controller/startup/` e chamando
`$this->load->modelWebhook('advanced/webhook')`. Nada disso existe desde a ADR 0033/0037. Quem seguisse o
documento escreveria código para uma arquitetura que não está no repositório.

## Decisão

**1. O índice foi completado — 125 de 125.** Título, status e data lidos do cabeçalho de cada arquivo,
tabela ordenada por número. As ADRs antigas (0007–0071) não têm bloco de status padronizado; foram
registradas como `✅ aceita`, que é o que o próprio arquivo diz em prosa ("Aceito", "Aceita — 2026-10-20").

**2. Os documentos operacionais foram corrigidos para o código de hoje.** `Webhooks.md` foi reescrito nas
seções erradas: a "engine" agora é `Event`; não existe controller de webhook; o disparo é
`dispatchWebhookEvent()` no ponto do fluxo, e a criação de um webhook novo é **duas coisas** (disparar o
evento e cadastrar o endpoint na tela), não um arquivo PHP novo.

**3. `tools/verify-docs-index.php` fecha as duas portas.** Verifica:

- **toda ADR no disco tem linha no índice** (sempre erro);
- **nenhuma linha do índice aponta para arquivo ausente** (sempre erro);
- **caminho citado entre crases em documento operacional que não existe** — aviso por padrão, **erro com
  `--strict`**, que é como o CI roda.

O terceiro item olha só a lista de documentos em que um caminho é **instrução** (`LEIA-ME`, `README`,
`CONTRIBUTING`, `docs/arquitetura/`, `docs/integracoes/`, `docs/guias/`, `tools/README.md`). Histórico,
fase e relatório ficam de fora: ali um arquivo que existiu e foi removido é **registro**, não defeito —
punir isso faria o guarda ser desligado na primeira semana.

**4. Está no CI e no guarda de integridade.** Passo novo no job `repo-guards` (`--strict`), e
`tools/verify-checksums.sh` passa a exigir a existência do verificador — a mesma proteção que a F11-05
criou quando o CI chamou um script ausente e o workflow aprovou porque só conferia a referência no YAML.

## O que esta ADR **não** faz

| Fora | Por quê |
| --- | --- |
| **Não** reescreve as 65 ADRs antigas para o formato de cabeçalho atual | são registro histórico; padronizá-las agora é churn com risco de perder informação. O índice diz o essencial |
| **Não** verifica caminho em documento histórico | é registro, não instrução (ver acima) |
| **Não** valida se o **conteúdo** do documento ainda é verdade | isso não se automatiza: é leitura. Esta ADR fez essa leitura nos documentos operacionais de pagamento e de infraestrutura, não nos 314 arquivos `.md` |
| **Não** mexe em código de produto | nenhuma linha de `dev/` mudou |

## Riscos residuais

| Risco | Situação |
| --- | --- |
| Documento novo citar caminho inexistente | o CI pega (`--strict`) |
| ADR nova sem linha no índice | o CI pega |
| Documento **certo no caminho, errado no conteúdo** | **não é coberto** — foi o caso do `Webhooks.md`, e só a leitura pegou. Fica registrado como limite do guarda |
| `docs/api/` citado no `Guia-PHP8.md` | está no `.gitignore` e é gerado; o texto agora diz isso |

## Prova

| O que | Como |
| --- | --- |
| índice completo | `verify-docs-index`: **125 ADRs, 0 fora do índice, 0 entrada quebrada** |
| caminhos citados | **0** inexistentes, inclusive com `--strict` |
| o guarda funciona | `DocsIndexTest` (5 casos) roda o verificador e lê o JSON; `--strict` devolve 0 hoje |
| nada regrediu | suíte **1975 testes / 46.458 asserções** verde; PHPStan sem erro; `verify-checksums` **4 PASS / 0 FAIL**; `verify-deps-layout` 18 PASS / 0 WARN; `verify-ci-workflow` 35 PASS; checkers **ESTÁVEL** |
