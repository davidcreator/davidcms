# Índice — Frente AX (plano das Ferramentas internas)

Pacote: `ReamurCMS-Frente-AX-Plano-Ferramentas-Internas.zip` · HEAD `481b6902…` · CI 7/7 ✅

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AX.md` | Contexto, levantamento, decisões, inventário, por que não há código ainda |
| 2 | `INDICE-AX.md` | Este índice |
| 3 | `Plano-Frente-AX.md` | **Principal** — arquitetura V2/P2, JSON de metadados, fases B1–B4, pendências |
| 4 | `Inventario-Rename-Global.md` | 179 arquivos / 762 ocorrências, categorias e pontos sensíveis |
| 5 | `Analise-Viabilidade-Ferramentas-Internas.md` | Como a página Ferramentas funciona, schema real, 3 opções de versão |
| 6 | `evidencia/inventario-rename.json` | Dados brutos por arquivo |
| 7 | `tools/verify-inventario.cjs` | Verificador Node puro |

## Decisões registradas

| Item | Escolha |
|---|---|
| Nomes | `BDREditor-Content` · **`KAZEditor-Theme`** |
| Rename | **R2 — global** |
| Versão | **V2 — JSON versionado**, sem migration |
| Local | **P2 — seção "Ferramentas internas" só no Admin** |

## O que falta (do usuário)

1. Versões iniciais das duas ferramentas.
2. R2 = B1+B2 (texto/docs) **ou** B1+B2+B3 (também identificadores de código).

## O que este pacote NÃO é

Não é código aplicável, não renomeia nada, não regenera manifesto, não faz
migration, não altera contrato congelado, não escreve no GitHub e **não
significa release aprovada**. `release_approved: false` · F9-27
`BLOCKED_NOT_RUN` · F9-18 `release_ready: false` · 10 `PENDING_DECISION`.
