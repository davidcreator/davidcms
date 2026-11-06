# Índice — Frente AT (avanço do passo 2)

Pacote: `ReamurCMS-Frente-AT-Avanco-Passo-2-CI.zip` · HEAD `bfe9eec7…`

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AT.md` | Motivo, resultados, pendências, limites |
| 2 | `INDICE-AT.md` | Este índice |
| 3 | `Roteiro-Proximos-Passos-AP.md` | **Substitui** a versão da AQ: novo HEAD, CI 7/7, passo 2 parcialmente concluído |
| 4 | `Protocolo-Aceitacao-Visual-AP.md` | **Substitui** a versão da AQ: AQ aplicada, CI verificado, health pendente |
| 5 | `docs/Registro-CI-Remoto-HEAD-bfe9eec7.md` | Evidência de CI, confirmação da aplicação da AQ, ressalva do contrato |
| 6 | `evidencia/ci-remoto-bfe9eec7.json` | Dados estruturados: runs, 7+7 check-runs, endpoints, limites |
| 7 | `tools/verify-ci-evidencia.cjs` | Verificador Node puro da evidência |

## Como aplicar

Copiar os 7 arquivos sobre os caminhos correspondentes: **2 substituições**
(roteiro e protocolo) + **5 novos**. Nenhum arquivo de código, contrato,
manifesto, workflow ou evidência PNG é alterado.

```bash
node tools/verify-ci-evidencia.cjs <raiz>
```

## Estado dos passos após a AT

| Passo | Estado |
|---|---|
| 1 — Veredito visual da AP | ⏳ depende do usuário |
| 2 — CI remoto | ✅ `42217429359` · 7/7 success |
| 2 — Health do Admin real | ⏳ colar `tools/health-admin-ap.js` no console |
| 3 — Decisões D01–D10 | ⏳ pacote AR pronto, não aplicado |
| 4 — F918 / backend | ⏳ pacote AS pronto, não aplicado |

## Limites

Registro de avanço. CI verde **não significa release aprovada**, não
substitui o veredito visual e não converte `NOT_RUN`/`PENDING_DECISION` em
`PASS`. Sem escrita do agente no GitHub.
