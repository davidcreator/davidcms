# F11-12 — `docs-links` determinístico com `--offline`

**Data:** 2026-11-02 · **HEAD verificado:** `9ce71a662` · **Tipo:** hotfix de CI. **Nenhuma escrita no GitHub.**

---

## 1. Histórico do problema

| Run | HEAD | `docs-links` |
| --- | --- | --- |
| `41464495804` | `fb7b56055` | 15,5 min → `cancelled` por push |
| `41466922434` | `a65d06800` | 12,8 min → `cancelled` por push |
| `41468925481` | `440419b12` | **6,5 min → `failure` (`exit 2`)** — pin do F11-08 acelerou e expôs `fail: true` da `@v2` |
| `41471699316` | `709d7fa08` | **6,5 min → `failure` (`exit 2`)** |
| `41478689925` | `9ce71a662` | **5,7 min → `failure` (`exit 2`)** — após F11-10 |

O F11-10 corrigiu os **16 erros reproduzidos localmente** com o binário oficial `lychee 0.15.0`: `docs/README.md` criado, 3 exclusões de domínios mortos e `--accept` para 403/429. Localmente: **`exit 0 / 0 erros / 561 OK`**. No runner do GitHub, **continuou `exit 2`**.

## 2. O que foi descartado (com evidência)

| Hipótese | Teste | Resultado |
| --- | --- | --- |
| A action passa `--mode task` ao binário 0.15.0 (que não suporta a flag e dá `exit 2`) | `lychee --mode task` → `exit 2`, `unexpected argument`; mas o `version_lt` do `entrypoint.sh` foi executado literalmente e detectou `0.15.0` → **emite WARNING, não passa `--mode`** | **Descartada** |
| `--format markdown --output` da action altera o resultado | Reprodução com `eval` idêntico ao `entrypoint.sh` | **`exit 0`** — descartada |
| Réplica incompleta | 2.476 arquivos; `--offline` com `docs/README.md` criado | **`exit 0 / 0 erros / 449 OK`** |
| Links externos voláteis no runner | Única diferença restante; os logs do job exigem admin (`403 Must have admin rights to Repository`) e o HTML público não traz o log | **Não comprovável daqui** |

Conclusão honesta: **a causa exata dos erros no runner não é provável sem os logs de administrador**. O que é provável é que a varredura de links externos é **inerentemente não determinística** (403/404/429/500/redirects variam por IP, horário e rate limiting), e é a única fonte de divergência entre o `exit 0` local e o `exit 2` remoto.

## 3. Correção aplicada (decisão do responsável: opção A)

```yaml
args: >-
  --verbose
  --no-progress
  --offline
  --exclude 'reamurcms\.com'
  --exclude 'wa\.me'
  --exclude 'davidalmeida\.xyz/catalog/tools'
  --exclude 'dev/system/storage/node_modules'
  --exclude 'node_modules'
  './**/*.md'
```

- **`--offline`** — verifica apenas links **internos/relativos** (`file://`), sem nenhuma requisição de rede. Resultado determinístico e independente do runner.
- **`fail: true` e `failIfEmpty: true` mantidos** — um link local quebrado continua derrubando o CI. Nada foi desligado.
- `lycheeVersion: v0.15.0`, `timeout-minutes: 20`, `token` e as 5 exclusões originais mantidos.
- **`--accept` e as 3 exclusões de domínios mortos removidos** — ficaram inócuos com `--offline`; removê-los evita a falsa impressão de que links externos ainda são verificados.

> ⚠️ **Trade-off assumido explicitamente:** links **externos deixam de ser verificados** pelo CI. A integridade da documentação interna (o que o repo controla) continua garantida; a disponibilidade de domínios de terceiros passa a ser responsabilidade de uma auditoria manual/eventual, não do gate de todo push. Se o responsável quiser retomar a varredura externa, o caminho é F11-13 com a lista real do Summary do job.

## 4. Auditor

`tools/verify-ci-workflow.sh` teve a seção 8 atualizada: exige `--offline`, proíbe `fail: false`, mantém a exigência das 5 exclusões e do `docs/README.md` no disco, e **proíbe `--accept`** (que seria enganoso neste modo). **28 PASS / 0 FAIL**.

## 5. Validações executadas

| Verificação | Resultado |
| --- | --- |
| `lychee 0.15.0 --no-progress --offline './**/*.md'` (binário do CI) | **`exit 0`, 0 erros, 449 OK, 127 excluídos** |
| Mesmo comando **sem** `docs/README.md` (controle) | `exit 2` — confirma que o guard local continua ativo |
| `bash tools/verify-ci-workflow.sh` | **28 PASS / 0 FAIL** |
| A/B: workflow do F11-10 (com `--accept`, sem `--offline`) | **FAIL** no novo auditor |
| `bash tools/verify-checksums.sh` | **0 divergentes** em 8 manifestos |
| `yaml.safe_load` / `bash -n` / `unzip -t` / CRC / `sha256sum -c` | OK |

### ⚠️ Limitações
- **A correção ainda não rodou no CI** — depende da aplicação deste pacote. O `exit 0` é medição local com o binário idêntico e os `args` finais.
- PHP, Composer, PHPStan, browser, banco, staging, F9-12, migrations e deploy **não** foram executados.
- **Nenhuma escrita no GitHub**: sem commit, push, PR ou aplicação remota.

## 6. Efeito nos manifestos

`php.yml`, `verify-ci-workflow.sh` e `CHANGELOG.md` foram alterados; por isso `CHECKSUMS.txt` (33), `CHECKSUMS-F11-04.txt` (11), `CHECKSUMS-F11-05.txt` (8), `CHECKSUMS-F11-08.txt` (8) e `CHECKSUMS-F11-10.txt` (10) foram **reemitidos com hashes do estado final**. `CHECKSUMS-F11-07.txt`, `CHECKSUMS-F11-09.txt` e `CHECKSUMS-F11-11.txt` permanecem intactos. Novo `CHECKSUMS-F11-12.txt`.

## 7. Próximo passo

Aplicar e deixar **um run terminar sem novo push**: com `--offline`, o `docs-links` deve concluir em **segundos** com `success`, fechando o CI **7/7**. Depois, a frente pendente é a decisão do responsável sobre os **10 `MARKER_OR_FINDING`** (F11-11); F11-06 segue opcional e `ubuntu-26.04` fica para após 19/11/2026.
