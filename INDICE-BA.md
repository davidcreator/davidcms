# Índice — Frente BA (versões iniciais 1.0.0)

Pacote: `ReamurCMS-Frente-BA-Versoes-Iniciais-1.0.0.zip` · HEAD `99717380…` · CI 7/7 ✅
**2 arquivos alterados**, nenhum em manifesto `CHECKSUMS`.

| # | Entrada | Ação | Conteúdo |
|---|---|---|---|
| 1 | `RELATORIO-FINAL-BA.md` | novo | Decisão, validação, limites, pendências |
| 2 | `INDICE-BA.md` | novo | Este índice |
| 3 | `dev/admin/model/tool/internal-tools.json` | **substitui** | `1.0.0` + `2026-11-06` para ambas |
| 4 | `tools/verify-internal-tools.cjs` | **substitui** | Asserção de versão atualizada |
| 5 | `evidencia/validacao.txt` | novo | Harness WASM + verificador + guardas |

## Decisão

| Ferramenta | Versão | Atualizado em |
|---|---|---|
| `BDREditor-Content` | **1.0.0** | 2026-11-06 |
| `KAZEditor-Theme` | **1.0.0** | 2026-11-06 |

`notes: []` — nenhum histórico inventado; a UI mostra "Nenhuma atualização
registrada.".

## Como aplicar

Copiar os **2 arquivos** substituindo os da Frente AY. Nenhum PHP, Twig, CSS,
idioma, manifesto, contrato ou workflow precisa ser tocado.

```bash
node tools/verify-internal-tools.cjs <raiz>
bash tools/repo-health.sh
```

## Validação medida

Harness WASM: 2 itens com `version="1.0.0"` / `updated="2026-11-06"` ·
verificador 0 FALHAS · `verify-checksums` 142 arquivos / 0 divergentes ·
`repo-health` **6 PASS / 0 FAIL / COERENTE**.

## O que NÃO faz

Não aplica o **rename R2** (179 arquivos / 762 ocorrências), não faz
migration, não altera contrato/hash, não decide D01–D10, não escreve no
GitHub pelo agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`.

## Pendente do usuário

Alcance do R2 (B1 / B1+B2 / B1+B2+B3) · aceite visual da seção no Admin real.
