# Relatório Final — Frente AV (diagnóstico e restauração do CI)

**Data:** 2026-11-06 · **HEAD diagnosticado:**
`accb625768424e2deae4b6054721602a64097323`
**Tipo:** pacote de **correção de integridade do repositório** (documentação
e manifestos), não frente de código.

## Por que esta frente, e não o passo 3

Ao reauditar o HEAD após a aplicação da Frente AU, encontrei que o mesmo
commit que registrou o veredito **também removeu 41 arquivos legados F11** e
deixou o **CI vermelho**: `docs-links` e `repo-guards` falharam. Isso é mais
urgente do que o dossiê de evidências D01–D10, que fica para depois.

## Achados

| Item | Resultado |
|---|---|
| Aplicação da AU | ✅ +6 arquivos (inclui `Pauta-Decisoes-D01-D10.md` e `Registro-Veredito-Visual-AP.md`) |
| Remoções no mesmo commit | **41**: 14 `CHECKSUMS*.txt`, 15 `F11-*-INDICE.md`, 12 relatórios/índices/`CONTRIBUTING.md` |
| CI | 5 checks ✅ · **`docs-links` ❌** · **`repo-guards` ❌** (run `42232750836`) |
| `repo-guards` | `verify-checksums.sh` exige ≥1 manifesto; com 0 → `FAIL nenhum CHECKSUMS*.txt encontrado` |
| `docs-links` | `docs/README.md` com **7 links mortos** para índices F11 removidos |
| Restauro parcial (29 arquivos) | ainda **2 FAIL** — `CONTRIBUTING.md` é citado por `CHECKSUMS.txt` e `CHECKSUMS-F11-04.txt` |

## Correção verificada (simulação local)

Réplica fiel do HEAD: **2.611 blobs / 42.833.718 bytes / 0 divergentes**.
Restaurando os **41 arquivos** do commit pai `aeafbb04…`:

- `tools/verify-checksums.sh` → **rc=0**, 14 manifestos, **142 arquivos
  conferidos, 0 divergentes**;
- `tools/repo-health.sh` → **rc=0**, **6 PASS / 0 FAIL / 0 SKIP**,
  `COERENTE (guardas estaticos)`;
- **0 links Markdown quebrados**.

## Conteúdo do pacote

| Grupo | Conteúdo |
|---|---|
| `RELATORIO-FINAL-AV.md` / `INDICE-AV.md` | Este relatório e o índice |
| `docs/Diagnostico-CI-Vermelho-accb6257.md` | Causa raiz, prova da correção, duas vias de decisão |
| `evidencia/lista-arquivos-removidos.txt` | Os 41 paths |
| `evidencia/sha256-restaurados.txt` | SHA-256 de cada arquivo restaurado |
| `evidencia/guardas-antes.txt` / `guardas-depois.txt` | Logs reais da simulação |
| `tools/verify-restauracao.cjs` | Verificador Node puro |
| **41 arquivos restaurados** | Cópias fiéis do pai `aeafbb04…`, nos paths originais |

**Via A (recomendada, entregue):** restaurar os 41 — não relaxa nenhum
guarda, não altera `docs/README.md`, workflow, hashes nem contratos.
**Via B (não recomendada):** manter a limpeza exigiria remover 7 links,
editar 2 manifestos e **fazer `verify-checksums.sh` tolerar zero manifestos**,
o que desliga uma verificação de integridade — só com autorização explícita.

## Validação

- Download fiel da tree do HEAD (0 divergentes) e dos 41 arquivos do pai.
- Guardas executados **antes** e **depois** do restauro, com logs anexados.
- `node --check` + execução de `tools/verify-restauracao.cjs`.
- ZIP com CRC verificado e `.sha256`; `sha256sum -c` conferido.

## Limites

**Simulação local ≠ CI remoto verde** — após aplicar, conferir novo run em
Actions. Nenhuma escrita do agente no GitHub, nenhum deploy, nenhuma
alteração de contrato congelado, nenhuma conversão de
`PENDING`/`SKIP`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão das
10 `PENDING_DECISION`.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
F9-18 `release_ready: false`. **Simulação local, guardas verdes e CI verde não
significam release aprovada.** O **veredito APROVADO da AP permanece válido**:
nenhum arquivo de `dev/` mudou entre a AP e este HEAD — a falha é de
documentação/manifestos, não de comportamento visual.

**Próximos passos adiados:** health do Admin real (2), dossiê/pauta D01–D10
(3, pacote AR pronto e sem conflito), verificação F918/staging (4, pacote AS
pronto e sem conflito).
