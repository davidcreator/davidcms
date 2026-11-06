# Índice — Frente AQ (Roteiro dos próximos passos)

Pacote: `ReamurCMS-Frente-AQ-Roteiro-Proximos-Passos.zip` · HEAD `179269fe…`

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-AQ.md` | Objetivo, estado confirmado, correção do `kazHealth`, validação, limites |
| 2 | `INDICE-AQ.md` | Este índice |
| 3 | `Roteiro-Proximos-Passos-AP.md` | Roteiro principal: passos 1–3, dependências, o que não fazer |
| 4 | `Protocolo-Aceitacao-Visual-AP.md` | Critério vigente; AP aplicada tecnicamente, veredito pendente |
| 5 | `docs/Extrato-Contratos-HEAD-179269fe.md` | Transcrição dos contratos e do CI lidos do remoto |
| 6 | `tools/health-admin-ap.js` | Health observacional somente leitura (substitui `__kazHealth()`) |
| 7 | `evidencia/ap_admin_nativo.png` | A/B — Admin nativo + AN (sidebar azul) |
| 8 | `evidencia/ap_admin_ao.png` | A/B — AO reprovada (sidebar verde, página escura) |
| 9 | `evidencia/ap_admin_ap.png` | A/B — AP corrigida (Admin azul, BDREditor escuro) |
| 10 | `evidencia/ap_mobile.png` | AP a 390 px, sem overflow |

## Como usar

1. Ler `Roteiro-Proximos-Passos-AP.md`.
2. Executar o **passo 1** no Admin real e registrar o veredito.
3. No passo 2, colar `tools/health-admin-ap.js` no console do DevTools e
   conferir o CI do commit `179269fe…` em Actions.
4. Para a pauta de decisões, usar o pacote **AR**; para os achados P1, o
   pacote **AS**.

## Limites

Planejamento. Não executa runtime, não altera contrato, não escreve no
GitHub e **não significa release aprovada**. O veredito visual do usuário
continua sendo o critério vigente.
