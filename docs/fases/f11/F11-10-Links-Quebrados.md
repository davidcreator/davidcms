# F11-10 — Correção dos links quebrados no `docs-links`

**Data:** 2026-11-02 · **HEAD verificado:** `709d7fa08` · **Tipo:** hotfix de CI/documentação. **Nenhuma escrita no GitHub.**

---

## 1. Sintoma

Após o F11-08, o `docs-links` passou a **concluir** (6,5 min em vez de 12,8/15,5 min) e, ao concluir, passou a **falhar**: `exit code 2` nos runs `41468925481` (`440419b12`) e `41471699316` (`709d7fa08`), com 2 annotations cada. Todos os outros 6 jobs seguem verdes.

Causa estrutural: a **`lychee-action@v1` tinha `fail: false`** por default; a **`@v2` tem `fail: true`** (breaking change documentado em v2.0.0). Os links quebrados **já existiam** — só não derrubavam o CI. Como os runs `@v2` anteriores foram cancelados por novos pushes, a falha nunca havia aparecido.

## 2. Metodologia — e uma retificação

Baixei o **binário oficial `lychee 0.15.0`** (o mesmo que o workflow fixa em `lycheeVersion`) do release `v0.15.0` e executei-o numa réplica de **2.476 arquivos** do `main`, com os `args` **exatos** do workflow. Resultado: **16 erros**, `exit 2`.

> ⚠️ **Retificação necessária:** na resposta anterior eu havia estimado "**41 links relativos ausentes**" por uma varredura com regex. Isso estava **errado**: a réplica daquele momento não continha todos os arquivos, e os "ausentes" eram artefatos de download. Com a réplica completa, `lychee --offline` reporta apenas **2** links locais quebrados, ambos para `docs/README.md`. Registro a correção aqui para não propagar o número errado.

## 3. Os 16 erros reais

| Qtd | Erro | Origem | Natureza |
| --- | --- | --- | --- |
| 2 | `docs/README.md` → `Cannot find file` | `README.md`, `docs/Estrutura-de-Pastas.md` | Link relativo **realmente quebrado** |
| 6 | `429 Too Many Requests` em `gnu.org/licenses/{gpl,lgpl}.html` | `ckeditor/LICENSE.md`, `plugins/scayt/*` | Rate limiting transitório |
| 1 | `429` em `instagram.com/davidalmeidades` | `README.md` | Rate limiting / anti-bot |
| 1 | `403 Forbidden` em `redbubble.com/people/DavidCreator` | `README.md` | Anti-bot |
| 2 | `404` em `watchersnet.de/…/CKEditorSourceView.png`, `SourceDialog.png` | `plugins/codemirror/README.md` | Domínio de vendor **morto** |
| 2 | `500` em `code.google.com/p/{flashcanvas,exprolercanvas}` | `jquery/flot/README.md` | Google Code descontinuado |
| 2 | `Too many redirects` em `awio.iljmp.com/5/drpdemogh` | `jquery/datetimepicker/README.md` | Link de demo de vendor morto |

Todos os arquivos de origem dos 14 erros externos, exceto `README.md`, são **READMEs/LICENSEs de bibliotecas de terceiros vendored** — não foram editados (integridade de licenças).

## 4. Correção aplicada (sem desligar `fail`)

1. **Criado `docs/README.md`** — índice real de documentação (handoff, CHANGELOG, guias, frentes F11, F9/release, ferramentas). Resolve os 2 links locais. É arquivo **novo**: nenhum manifesto existente o cobria.
2. **3 exclusões de domínio** adicionadas ao `args`: `watchersnet\.de`, `code\.google\.com`, `awio\.iljmp\.com` — links congelados e mortos em vendor, fora do controle do projeto. Segue o padrão já existente (`reamurcms\.com`, `wa\.me`, `davidalmeida\.xyz/catalog/tools`).
3. **`--accept '100..=103,200..=299,403,429'`** — aceita explicitamente 403/429. São códigos de **recusa à verificação automatizada**, não de link inexistente; o próprio `--help` do lychee documenta `--accept '200..=204, 429, 500'`. Resolve os 8 erros de rate limiting/anti-bot **sem remover nenhum link da varredura**.
4. **`fail: true` mantido** (implícito na v2), `failIfEmpty: true` mantido, `timeout-minutes: 20` e `lycheeVersion: v0.15.0` mantidos. Nenhuma proteção foi desligada.

> **Trade-off explícito:** aceitar `403`/`429` significa que um link que passe a responder apenas com esses códigos não derrubará o CI. É uma decisão consciente e documentada, diferente de `fail: false`, que desligaria **todos** os erros. Se o responsável preferir, a alternativa é excluir os 3 domínios de redes sociais/GNU individualmente.

### Não funcionou (registrado para não repetir)
`--exclude-path` com globs (`dev/admin/view/js/ckeditor/**` etc.) **não** pulou os arquivos de entrada: reduziu 16 → 14 erros e alterou a contagem de excluídos de 127 para 7. Abandonado em favor de `--exclude` por URL + `--accept`.

## 5. Validação com o binário do CI

| Comando | Resultado |
| --- | --- |
| `lychee` com os `args` **atuais** (publicados) | `exit 2`, **16 erros** |
| `lychee --offline` na réplica completa | `exit 2`, **2 erros** (`docs/README.md`) |
| `lychee` com a **correção** | **`exit 0`, 0 erros, 522 OK, 13 excluídos** |
| Controle: correção **sem** `--accept` | `exit 2`, 8 erros (confirma o que o `--accept` resolve) |
| `yaml.safe_load` do workflow | 6 jobs, `docs-links.timeout-minutes: 20`, pin intacto |
| `bash tools/verify-ci-workflow.sh` | **28 PASS / 0 FAIL** |
| `bash tools/verify-checksums.sh` | **0 divergentes** em 7 manifestos |
| `bash -n` / `unzip -t` / CRC / `sha256sum -c` | OK |

### ⚠️ Limitações
- A varredura usa a **rede do sandbox**: os `429`/`403` podem não se reproduzir no runner do GitHub. De todo modo, o `--accept` os torna inócuos; os `404`/`500`/redirects de vendor são definitivos.
- **A correção ainda não rodou no CI** — depende da aplicação deste pacote. `exit 0` acima é medição local com o binário idêntico, não um run do GitHub.
- PHP, Composer, PHPStan, browser, banco, staging, F9-12, migrations e deploy **não** foram executados.
- **Nenhuma escrita no GitHub**: sem commit, push, PR ou aplicação remota.

## 6. Efeito nos manifestos

`php.yml`, `verify-ci-workflow.sh` e `CHANGELOG.md` foram alterados e constam em manifestos. Por isso `CHECKSUMS.txt` (33), `CHECKSUMS-F11-04.txt` (11), `CHECKSUMS-F11-05.txt` (8) e `CHECKSUMS-F11-08.txt` (8) foram **reemitidos com hashes do estado final**. `CHECKSUMS-F11-07.txt` e `CHECKSUMS-F11-09.txt` permanecem intactos. Novo `CHECKSUMS-F11-10.txt`.

## 7. Próximo passo

Aplicar o pacote e **deixar um run terminar sem novo push** — o `docs-links` deve concluir em ~6,5 min com `success`, fechando o CI 7/7. Depois, a frente pendente é a decisão do responsável sobre os **10 `MARKER_OR_FINDING`** do F11-09; o F11-06 (radar) continua opcional.
