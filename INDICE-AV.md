# Índice — Frente AV (diagnóstico e restauração do CI)

Pacote: `ReamurCMS-Frente-AV-Restauracao-CI.zip` · HEAD `accb6257…`

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AV.md` | Por que esta frente, achados, correção verificada, limites |
| 2 | `INDICE-AV.md` | Este índice |
| 3 | `docs/Diagnostico-CI-Vermelho-accb6257.md` | Causa raiz dos 2 checks vermelhos, prova, Via A / Via B |
| 4 | `evidencia/lista-arquivos-removidos.txt` | 41 paths removidos no commit `accb6257…` |
| 5 | `evidencia/sha256-restaurados.txt` | SHA-256 de cada arquivo restaurado |
| 6 | `evidencia/guardas-antes.txt` | `repo-health` antes: 5 PASS / 1 FAIL / REPROVADO |
| 7 | `evidencia/guardas-depois.txt` | `repo-health` depois: 6 PASS / 0 FAIL / COERENTE |
| 8 | `tools/verify-restauracao.cjs` | Verificador Node puro |
| 9–49 | **41 arquivos restaurados** | 14 `CHECKSUMS*.txt`, 15 `F11-*-INDICE.md`, 12 relatórios/índices/`CONTRIBUTING.md` |

## Como aplicar (Via A — recomendada)

Copiar os **41 arquivos** de volta aos paths originais. Não é necessário
alterar `docs/README.md`, `.github/workflows/php.yml`, nenhum guarda, nenhum
contrato e nenhum hash: os manifestos restaurados conferem com os arquivos
restaurados.

```bash
node tools/verify-restauracao.cjs <raiz>
bash tools/verify-checksums.sh
bash tools/repo-health.sh
```

Depois conferir o novo run em **Actions** — a validação aqui é local.

## Resultado esperado (medido)

```
verify-checksums.sh : rc=0 · 14 manifestos · 142 arquivos · 0 divergentes
repo-health.sh      : rc=0 · 6 PASS / 0 FAIL / 0 SKIP · COERENTE
links Markdown      : 0 quebrados
```

## O que NÃO fazer sem decisão explícita

Manter a limpeza exigiria fazer `verify-checksums.sh` **tolerar zero
manifestos**, o que desliga uma verificação de integridade. Essa via **não**
foi preparada por este pacote.

## Limites

Simulação local ≠ CI remoto verde. Não escreve no GitHub pelo agente, não
altera contrato, não converte status em `PASS`, não decide D01–D10 e **não
significa release aprovada**. `release_approved: false` · F9-27
`BLOCKED_NOT_RUN` · F9-18 `release_ready: false`. O veredito **APROVADO** da
AP permanece válido.

**Pacotes prontos e sem conflito:** `AR` (modelo D01–D10) e `AS`
(verificação F918-01/02).
