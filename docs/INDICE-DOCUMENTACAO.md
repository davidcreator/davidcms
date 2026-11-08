# Índice da Documentação

**Data:** 2026-11-01 · **Escopo:** documentos `.md` na raiz de `/home/user` + esta pasta `docs/`.

Organização por **referência**, não por movimentação: os arquivos **não foram movidos nem duplicados**.
Mover os `.md` da raiz quebraria os links relativos já citados por `INDICE.md`, `RELATORIO.md` e
pelos ADRs `agents-ia/`, além de exigir reemissão dos 14 manifestos — o que os contratos congelados proíbem.
O índice abaixo aponta para os originais, com a situação de cada um.

## Comece por aqui (`docs/`)

| Documento | Para que serve |
|---|---|
| `README.md` | Mapa geral e ordem de leitura |
| `RELATORIO-GERAL.md` | **O relatório da jornada** (frentes → BO-r4) |
| `ESTADO-GOVERNANCA.md` | Release, F9, D01–D10, aceites, o que não fazer |
| `PLANO-LIMPEZA.md` | O que pode ser removido do repo e do workspace |
| `INDICE-DOCUMENTACAO.md` | Este catálogo |
| `inventario-limpeza.csv` | 33 itens classificados com bytes e motivo |
| `limpar-workspace.py` | Quarentena reversível (dry-run por padrão) |

## Situação dos documentos

| Situação | Significado |
|---|---|
| **CANÔNICO** | Fonte de verdade atual; consultar antes de qualquer decisão |
| **HISTÓRICO** | Registro imutável de uma frente; manter, mas não usar como estado atual |
| **OBSOLETO** | Texto superado por revisão posterior; corrigir ou arquivar |
| **REPROVADO / CONTESTADO** | Veredito negativo registrado; não reutilizar como base |

## Documentos de governança (CANÔNICOS)

| Arquivo | Bytes |
|---|---|
| `Protocolo-Aceitacao-Visual-AP.md` | 4.724 |
| `Registro-Veredito-Visual-AP.md` | 4.058 |
| `Pauta-Decisoes-D01-D10.md` | 5.143 |
| `Estado-Consolidado-2026-11-06.md` | 5.094 |
| `Health-Admin-Real-Instrucoes.md` | 4.371 |
| `Analise-KAZEditor-Theme-Importacao.md` | 8.534 |
| `Analise-Viabilidade-Ferramentas-Internas.md` | 8.041 |
| `Auditoria-Limpeza-GitHub-2026-11-05.md` | 9.068 |
| `Analise-Pos-Limpeza-GitHub-2026-11-05.md` | 6.469 |

> `Auditoria-Limpeza-GitHub-2026-11-05.md` (9.068 bytes) é a **versão corrigida**, com `tests/` mantido.
> A versão **dentro do repositório** é anterior e está desatualizada em 3 trechos — ver `PLANO-LIMPEZA.md`.

## Catálogo por categoria

### Análise — 3 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Analise-KAZEditor-Theme-Importacao.md` | 8.534 | HISTÓRICO |
| `Analise-Pos-Limpeza-GitHub-2026-11-05.md` | 6.469 | CANÔNICO (limpeza) |
| `Analise-Viabilidade-Ferramentas-Internas.md` | 8.041 | HISTÓRICO |

### Auditoria — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Auditoria-Limpeza-GitHub-2026-11-05.md` | 9.068 | CANÔNICO (limpeza) |

### Checklist — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Checklist-Veredito-Visual-AP.md` | 3.393 | HISTÓRICO |

### Diagnóstico — 2 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `DIAGNOSTICO-BK.md` | 7.230 | HISTÓRICO |
| `DIAGNOSTICO-BL.md` | 7.044 | HISTÓRICO |

### Documento base — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `analise-davidcms-2026-11-01.md` | 22.575 | HISTÓRICO |

### Estado consolidado — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Estado-Consolidado-2026-11-06.md` | 5.094 | CANÔNICO |

### Guia de aplicação — 8 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `GUIA-BL.md` | 3.151 | HISTÓRICO |
| `GUIA-BM.md` | 3.110 | HISTÓRICO |
| `GUIA-BN.md` | 3.350 | CONTESTADO (BN) |
| `GUIA-CORRECAO-BK.md` | 3.132 | HISTÓRICO |
| `GUIA-TEMA-ATLAS.md` | 5.102 | HISTÓRICO |
| `GUIA-TEMA-DAVID.md` | 5.146 | HISTÓRICO |
| `GUIA-TEMA-REFERENCIA.md` | 4.190 | HISTÓRICO |
| `GUIA-TEMA-THREEJS-REFERENCIA.md` | 5.275 | HISTÓRICO |

### Health — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Health-Admin-Real-Instrucoes.md` | 4.371 | CANÔNICO |

### Inventário — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Inventario-Rename-Global.md` | 4.128 | HISTÓRICO |

### Pauta de decisões — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Pauta-Decisoes-D01-D10.md` | 5.143 | CANÔNICO |

### Plano — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Plano-Frente-AX.md` | 5.426 | HISTÓRICO |

### Protocolo — 2 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Protocolo-Aceitacao-Visual-AM.md` | 4.064 | HISTÓRICO |
| `Protocolo-Aceitacao-Visual-AP.md` | 4.724 | CANÔNICO |

### Registro — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Registro-Veredito-Visual-AP.md` | 4.058 | CANÔNICO |

### Relatório de frente — 24 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `RELATORIO-FINAL-AN.md` | 5.031 | HISTÓRICO |
| `RELATORIO-FINAL-AO.md` | 5.875 | REPROVADO (AO) |
| `RELATORIO-FINAL-AP.md` | 7.231 | HISTÓRICO |
| `RELATORIO-FINAL-AQ.md` | 2.976 | HISTÓRICO |
| `RELATORIO-FINAL-AR.md` | 2.719 | HISTÓRICO |
| `RELATORIO-FINAL-AS.md` | 3.617 | HISTÓRICO |
| `RELATORIO-FINAL-AT.md` | 3.677 | HISTÓRICO |
| `RELATORIO-FINAL-AU.md` | 3.894 | HISTÓRICO |
| `RELATORIO-FINAL-AV.md` | 3.898 | HISTÓRICO |
| `RELATORIO-FINAL-AX.md` | 4.220 | HISTÓRICO |
| `RELATORIO-FINAL-AY.md` | 5.826 | HISTÓRICO |
| `RELATORIO-FINAL-BA.md` | 3.436 | HISTÓRICO |
| `RELATORIO-FINAL-BB.md` | 4.694 | HISTÓRICO |
| `RELATORIO-FINAL-BC.md` | 5.601 | HISTÓRICO |
| `RELATORIO-FINAL-BE.md` | 6.484 | HISTÓRICO |
| `RELATORIO-FINAL-BF.md` | 6.138 | HISTÓRICO |
| `RELATORIO-FINAL-BG.md` | 5.675 | HISTÓRICO |
| `RELATORIO-FINAL-BH.md` | 5.911 | HISTÓRICO |
| `RELATORIO-FINAL-BI.md` | 6.156 | HISTÓRICO |
| `RELATORIO-FINAL-BJ.md` | 6.679 | HISTÓRICO |
| `RELATORIO-FINAL-BK.md` | 5.559 | REPROVADO (BK) |
| `RELATORIO-FINAL-BL.md` | 5.276 | HISTÓRICO (sem prova no servidor) |
| `RELATORIO-FINAL-BM.md` | 6.178 | HISTÓRICO |
| `RELATORIO-FINAL-BN.md` | 7.285 | CONTESTADO (BN) |

### Roteiro — 1 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `Roteiro-Proximos-Passos-AP.md` | 14.167 | HISTÓRICO |

### Índice de pacote — 24 arquivo(s)

| Arquivo | Bytes | Situação |
|---|---|---|
| `INDICE-AN.md` | 3.070 | HISTÓRICO |
| `INDICE-AO.md` | 3.500 | REPROVADO (AO) |
| `INDICE-AP.md` | 2.627 | HISTÓRICO |
| `INDICE-AQ.md` | 1.612 | HISTÓRICO |
| `INDICE-AR.md` | 1.463 | HISTÓRICO |
| `INDICE-AS.md` | 1.735 | HISTÓRICO |
| `INDICE-AT.md` | 1.699 | HISTÓRICO |
| `INDICE-AU.md` | 1.721 | HISTÓRICO |
| `INDICE-AV.md` | 2.311 | HISTÓRICO |
| `INDICE-AX.md` | 1.578 | HISTÓRICO |
| `INDICE-AY.md` | 2.531 | HISTÓRICO |
| `INDICE-BA.md` | 1.827 | HISTÓRICO |
| `INDICE-BB.md` | 2.096 | HISTÓRICO |
| `INDICE-BC.md` | 2.494 | HISTÓRICO |
| `INDICE-BE.md` | 2.788 | HISTÓRICO |
| `INDICE-BF.md` | 2.851 | HISTÓRICO |
| `INDICE-BG.md` | 2.426 | HISTÓRICO |
| `INDICE-BH.md` | 2.799 | HISTÓRICO |
| `INDICE-BI.md` | 3.021 | HISTÓRICO |
| `INDICE-BJ.md` | 3.493 | HISTÓRICO |
| `INDICE-BK.md` | 3.148 | HISTÓRICO |
| `INDICE-BL.md` | 3.153 | HISTÓRICO |
| `INDICE-BM.md` | 2.891 | HISTÓRICO |
| `INDICE-BN.md` | 3.163 | CONTESTADO (BN) |

## Documentação dentro dos pacotes

Cada ZIP em `entregas/` carrega sua própria pasta `docs/` e um `INDICE-*.md`.
O pacote corrente (`BO-r4`) contém, na raiz: `DIAGNOSTICO-3D.md`, `GUIA-BO.md`,
`INDICE-BO.md`, `LEIA-ME.md`, `RELATORIO-BO.md`, `COPIAR-MANUALMENTE.md`,
`diagnostico/`, `evidencia/`, `patch/`, `previa/`, `tema/`, `tools/`.

## O que NÃO foi feito nesta organização

- Não movi, renomeei ou apaguei nenhum `.md` da raiz.
- Não dupliquei documentos para dentro de `docs/` (evita divergência de versões).
- Não reemiti `CHECKSUMS*.txt`, não recalculei hashes e não toquei em contratos congelados.
- Não editei `INDICE.md`, `RELATORIO.md` nem `docs/RELATORIO-ADERENCIA-main.md` do repositório.
- Não escrevi no GitHub.

---
*Companheiro: `PLANO-LIMPEZA.md` para a lista de remoção segura.*
