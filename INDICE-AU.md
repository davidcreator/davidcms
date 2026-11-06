# Índice — Frente AU (veredito APROVADO)

Pacote: `ReamurCMS-Frente-AU-Registro-Veredito-Aprovado.zip` · HEAD `1b7724a1…`

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AU.md` | O que aconteceu, o que mudou, **o que não mudou**, validação, próximos passos |
| 2 | `INDICE-AU.md` | Este índice |
| 3 | `Registro-Veredito-Visual-AP.md` | **Novo** — decisão `APROVADO`, decidido por, data, HEAD, efeitos, limites |
| 4 | `Protocolo-Aceitacao-Visual-AP.md` | **Substitui** — AP aprovada, `image-9` superada, estado dos passos |
| 5 | `Roteiro-Proximos-Passos-AP.md` | **Substitui** — passo 1 concluído, dependências atualizadas |
| 6 | `Pauta-Decisoes-D01-D10.md` | **Nova** — pauta de 1 página para o passo 3, ordem de votação |
| 7 | `tools/verify-veredito.cjs` | Verificador Node puro do registro |

## Como aplicar

**3 substituições** (protocolo, roteiro e — se já existir — registro) +
**4 novos**. Nenhum arquivo de código, contrato, manifesto, workflow ou
evidência PNG é alterado.

```bash
node tools/verify-veredito.cjs <raiz>
```

## O que este pacote NÃO faz

Não promove `release_approved` (segue `false`), não converte `PENDING`/`NOT_RUN`/
`BLOCKED_NOT_RUN` em `PASS`, não decide D01–D10, não altera F9-27/F9-18,
não escreve no GitHub pelo agente e não declara release.

**Aprovação visual ≠ release.** `release_approved: false` ·
F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready: false` ·
10 `PENDING_DECISION`.

## Próximos passos (dependem do usuário)

Colar o health no console (2) · aplicar **AR** e decidir D01–D10 (3) ·
aplicar **AS** e autorizar staging (4). AR e AS estão prontos em
`entregas/`, sem conflito com o HEAD atual.
