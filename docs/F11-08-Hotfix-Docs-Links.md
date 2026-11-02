# F11-08 — Hotfix de performance do `docs-links` (lychee)

**Data:** 2026-11-02 · **HEAD verificado:** `a65d06800` (F11-07 aplicado) · **Tipo:** hotfix de CI. **Nenhuma escrita no GitHub.**

---

## 1. Sintoma medido

| Run | HEAD | Action | `docs-links` |
| --- | --- | --- | --- |
| `41448213202` | `31b15e06` | `@v1` | ✅ 360 s |
| `41456647410` | `b3268b7ac` | `@v1` | ✅ 369 s |
| `41457917494` / `41458059613` | `9debd9ed8` / `510640c68` | `@v1` | ✅ 363 s / 358 s |
| `41462336587` | `706205389` | `@v2` | ❌ **14,0 min → `cancelled`** pelo push de `fb7b56055` |
| `41464495804` | `fb7b56055` | `@v2` | ❌ **15,5 min → `cancelled`** pelo push de `a65d06800` (step 3 interrompido) |
| `41466922434` | `a65d06800` | `@v2` | ⏳ **≥8,3 min**, ainda em andamento na última consulta |

Ou seja: **~6 min estáveis em quatro runs** com `@v1`; nos **três** runs com `@v2`, **14,0 e 15,5 min** (cancelados por novos pushes, não por timeout) e **≥8,3 min sem conclusão**. Nenhum run `@v2` chegou a `success` até a última consulta. A lentidão é **observada**, mas pode incluir fator transitório de rede/rate limiting — daí a ressalva da seção 2. O `timeout-minutes: 20` do F11-04 ainda não estourou; o que interrompeu os dois primeiros foi o `concurrency`, não o limite.

## 2. Diagnóstico (com o que é e o que **não** é comprovado)

Diff do job `docs-links` entre `510640c68` e `a65d06800`: a única mudança funcional é **`lycheeverse/lychee-action@v1 → @v2`** (além de `runs-on: ubuntu-24.04`, `actions/checkout@v7`, `timeout-minutes: 20` e `env GITHUB_TOKEN` → input `token`). Os `args` — `--verbose`, `--no-progress`, as 5 exclusões e `'./**/*.md'` — **não mudaram**.

| Evidência | Valor |
| --- | --- |
| Arquivos `.md` | 297 → **304** (+7, +37.704 bytes) — volume não explica 2,5× |
| `lycheeVersion` default `@v1` | **`0.15.0`** |
| `lycheeVersion` default `@v2.9.0` | **`v0.24.2`** (9 minors à frente) |
| `--timeout` (0.15.0 / 0.21.0) | 20 s / 20 s — **igual** |
| `--max-retries` | 3 / 3 — **igual** |
| `--max-concurrency` | 128 / 128 — **igual** |
| `--retry-wait-time` | 1 s / 1 s — **igual** |
| Outros deltas da v2 | `fail: true` e `failIfEmpty: true` por default (v1: `fail: false`); `entrypoint.sh` adiciona `--mode task`, `--format markdown`, `--output` |

> ⚠️ **Ressalva de rigor:** a hipótese inicial de "defaults de rede mudaram" foi **descartada** pela leitura dos READMEs oficiais. A causa interna do binário `0.24.2` (mais verificações, tratamento de 429/âncoras, I/O de `--verbose`, etc.) **não pode ser comprovada**: os logs do job exigem permissão de administrador (`403 Must have admin rights to Repository`). O que há é uma **correlação forte e reproduzível** entre a troca do binário e a lentidão — não uma causa raiz demonstrada. Esta correção é, portanto, **determinística por A/B empírico**, não uma "causa corrigida".

## 3. Correção aplicada

```yaml
- name: Link check (docs, agents-ia e README)
  uses: lycheeverse/lychee-action@v2
  with:
    token: ${{ secrets.GITHUB_TOKEN }}
    lycheeVersion: v0.15.0   # F11-08
    args: >- ...
```

Escolha justificada:

- **Mantém a action `@v2`** — Node 24, zero avisos de depreciação, input `token` correto. Não há downgrade da action.
- **Pina o binário `v0.15.0`**, exatamente o que produziu os 4 runs verdes em ~6 min. A v2 aceita `lycheeVersion`; seu `entrypoint.sh` detecta versões < 0.18.1, emite um aviso e **continua sem `--mode task`**, e o `action.yml` usa o nome de artefato `lychee-v0.15.0-…` — compatível.
- **Nenhuma proteção foi relaxada**: `fail: true` e `failIfEmpty: true` permanecem (são **mais estritos** que a v1, que tinha `fail: false`); as 5 exclusões originais permanecem; **nenhum `--accept`** foi adicionado; o job não foi desligado; `timeout-minutes: 20` mantido.
- **Não** foram adicionadas flags de performance (`--timeout 10`, `--max-retries 0`), pois reduzir timeout geraria **falsos positivos** de link quebrado e os defaults já eram os mesmos nas duas versões.

### Efeito colateral tratado
`php.yml`, `tools/verify-ci-workflow.sh` e `docs/CHANGELOG.md` constam em manifestos de integridade. Por isso `CHECKSUMS.txt` (33), `CHECKSUMS-F11-04.txt` (11) e `CHECKSUMS-F11-05.txt` (8) foram **reemitidos com os hashes do estado final do repo** (lição do F11-05). `CHECKSUMS-F11-07.txt` (4 entradas) permanece **intacto** — nenhum de seus arquivos foi tocado.

## 4. Auditor reforçado

`tools/verify-ci-workflow.sh` ganhou a seção 7, com 4 checks: `lycheeVersion` fixado em `v0.15.0`; ausência de `latest`/`nightly`/expressão; ausência de `--accept`; e manutenção das 5 exclusões. Resultado: **26 PASS / 0 FAIL** no workflow novo e **25 PASS / 1 FAIL** no workflow antigo sem o pin (A/B confirma que o guard detecta a brecha).

## 5. Validações executadas

| Verificação | Resultado |
| --- | --- |
| `yaml.safe_load` do `php.yml` | 6 jobs, `docs-links.timeout-minutes: 20`, `lycheeVersion: v0.15.0` |
| `bash -n` em `verify-ci-workflow.sh` e `verify-checksums.sh` | OK |
| `bash tools/verify-ci-workflow.sh` | **26 PASS / 0 FAIL** |
| A/B com workflow publicado sem o pin | **25 PASS / 1 FAIL** (`exit 1`) |
| `bash tools/verify-checksums.sh` na réplica final | **64 arquivos conferidos / 0 divergentes**, 3 guardas presentes |
| `unzip -t` / CRC / `sha256sum -c` | OK |
| `sha256sum -c` dos 7 ZIPs anteriores | OK |

### ⚠️ Limitações
- **A correção ainda não rodou no CI**: o run `41466922434` usa o YAML de `a65d06800` (sem o pin) e seu `docs-links` já acumulava **8,3 min sem conclusão** na última consulta. O efeito de ~6 min é uma **expectativa baseada em 4 runs medidos com `@v1`**, não uma medição deste commit.
- Não foi possível ler os logs do job (`403` sem admin) nem executar o `lychee` localmente (binário ausente no sandbox).
- PHP, Composer, PHPStan, browser, banco, staging, F9-12, migrations e deploy **não** foram executados.
- **Nenhuma escrita no GitHub**: sem commit, push, PR ou aplicação remota.

## 6. Plano de reavaliação

1. Deixar **um run terminar sem novo push** para medir o `docs-links` com o pin (meta: voltar a <10 min).
2. Quando houver acesso de admin aos logs, medir `v0.24.2` com `--timeout`/`--max-retries`/`--max-concurrency` **explícitos** e comparar; só então migrar o pin, com run verde.
3. Revisar `ubuntu-24.04` → `ubuntu-26.04` **após 19/11/2026** (ADR 0082), também com run verde.

## 7. Arquivos do pacote

Arquivos completos: `.github/workflows/php.yml`, `tools/verify-ci-workflow.sh`, `docs/CHANGELOG.md`, os 3 manifestos reemitidos, `docs/F11-08-Hotfix-Docs-Links.md`, `F11-08-INDICE.md` e `CHECKSUMS-F11-08.txt`.

```bash
bash tools/verify-ci-workflow.sh     # 26 PASS / 0 FAIL
bash tools/verify-checksums.sh       # 0 divergentes
unzip -t ReamurCMS-F11-08-Hotfix-Docs-Links.zip
```
