# Registro de CI remoto — HEAD `bfe9eec7…`

**Data:** 2026-11-06 · **Frente:** AT (registro de avanço do passo 2)
**Repositório:** `davidcreator/davidcms` · leitura pública, sem token.

## O que mudou

Até a Frente AQ, o roteiro registrava *"CI remoto não verificado
(`403 Must have admin rights`)"*. Nova consulta mostrou que o `403` aplicava-se
a **outro endpoint**: `/actions/runs` e `/commits/<sha>/check-runs`
responderam normalmente sem autenticação. O CI do commit atual pôde ser
verificado de fato.

## Evidência

| Commit | Mensagem | Run | Status | Check-runs |
|---|---|---|---|---|
| **`bfe9eec7aae82dc72c7054bcb46dbd901307d7c5`** | `docs(aq-acceptance): add full planning package and health tool` | `42217429359` · `PHP Composer` · 2026-11-06T12:24:32Z | `completed / success` | **7/7 success** |
| `179269fea36584076f9ef41b95ae94638dd08f45` | `fix(bdre-editor): limit dark theme to editor` | `42212306218` · 2026-11-06T11:43:07Z | `completed / success` | **7/7 success** |
| `9c809050ff908b66b6f3bf97c1fc5a1aed9b3160` | `feat: add total dark admin theme for BDREditor` | `42128396459` | `completed / success` | — |

Os 7 check-runs do HEAD: `php-lint`, `node-deps (22)`, `static-analysis`,
`repo-guards`, `node-deps (20)`, `docs-links`, `composer`. São os 6 jobs de
`.github/workflows/php.yml` com `node-deps` em duas versões de Node.

URL do run: `https://github.com/davidcreator/davidcms/actions/runs/42217429359`

## Ressalva sobre o contrato

`tools/decision-matrix.json` continua gravando
`ci_status: "7/7 success (runs 41483802295 / 41504039830)"`. Esses IDs são
**runs antigos**, não os do HEAD. Este registro **não** reescreve o contrato:
a evidência nova fica em `evidencia/ci-remoto-bfe9eec7.json` e não altera
`status_counts`, hashes, `base_head` nem qualquer campo congelado.

## Aplicação da AQ (confirmada no mesmo HEAD)

| Item | Valor |
|---|---|
| diff vs `179269fe…` | **+6 / −0 / ~0** |
| tree | 3.224 entradas / 2.641 blobs / `truncated: false` |
| arquivos do pacote AQ no remoto | **10/10 byte a byte idênticos** |
| novos | `INDICE-AQ.md`, `RELATORIO-FINAL-AQ.md`, `Roteiro-Proximos-Passos-AP.md`, `Protocolo-Aceitacao-Visual-AP.md`, `docs/Extrato-Contratos-HEAD-179269fe.md`, `tools/health-admin-ap.js` |
| já existentes da AP | 4 PNGs em `evidencia/` — blob git idêntico (`196e27db…`, `c82c393a…`, `0f37e475…`, `f1579351…`) |

## O que o passo 2 ainda exige

O health do **Admin real autenticado**: colar `tools/health-admin-ap.js` no
console do DevTools em Pages/Posts e devolver o JSON. Sem sessão autenticada
não há como fazê-lo daqui. `__kazHealth()` não existe no código; o health é
observacional e somente leitura.

## Limites

CI verde **não** substitui o veredito visual do usuário (passo 1), **não**
converte `NOT_RUN`/`BLOCKED_NOT_RUN`/`PENDING_DECISION` em `PASS`, **não**
altera `release_approved`/`release_ready` e **não significa release
aprovada**. `release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
F9-18 `release_ready: false`.

Nenhuma escrita no GitHub, nenhum deploy, nenhuma execução de runtime, banco
ou staging pelo agente.
