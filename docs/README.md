# docs/ — Documentação consolidada do ReamurCMS

**Data:** 2026-11-01 · **Abrangência:** frentes iniciais → **BO-r4**
**Isto é documentação, não é aprovação, release, deploy nem escrita no GitHub.**

---

## Comece aqui

| Se você quer… | Abra |
|---|---|
| **O relatório geral de tudo que foi feito** | **`RELATORIO-GERAL.md`** ⭐ |
| Saber o que está aprovado / pendente / proibido | `ESTADO-GOVERNANCA.md` |
| Saber o que pode ser removido e como | `PLANO-LIMPEZA.md` |
| **Limpar a raiz do GitHub (compilar + remover)** | **`ANALISE-RAIZ-GITHUB.md`** |
| Encontrar qualquer documento da raiz | `INDICE-DOCUMENTACAO.md` |
| Ver os 33 itens classificados com bytes | `inventario-limpeza.csv` |
| Fazer a limpeza local sem apagar nada | `limpar-workspace.py` |

---

## Conteúdo desta pasta

| Arquivo | O que é |
|---|---|
| `README.md` | Este mapa |
| `RELATORIO-GERAL.md` | Linha do tempo A→BO-r4, decisões `ask_user`, entregas, validações, lições, limites |
| `ESTADO-GOVERNANCA.md` | Cartão de consulta: `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, D01–D10, aceites, restrições |
| `PLANO-LIMPEZA.md` | Nível 2 do GitHub (46 arquivos), correção doc dentro do repo, quarentena do workspace, o que **não** remover |
| `INDICE-DOCUMENTACAO.md` | Catálogo dos 73 `.md` da raiz por categoria e situação |
| `inventario-limpeza.csv` | 33 itens: categoria, caminho, arquivos, bytes, motivo, ação |
| `limpar-workspace.py` | Quarentena reversível — **dry-run por padrão**, `--apply` move, `--restore` desfaz |
| **`ANALISE-RAIZ-GITHUB.md`** | Auditoria fresca da **raiz do GitHub** (`687fba34…`): 109 arquivos, o que compilar, o que remover, o que é intocável |
| `FRENTES-CONSOLIDADO.md` | União enxuta de **63 documentos de frente** (tabela-mestra + resumos) |
| `FRENTES-INTEGRAL.md` | Texto **byte a byte** dos mesmos 63, sem perda de conteúdo |
| `inventario-raiz.csv` | Os 109 arquivos da raiz classificados com bytes e motivo |
| **`CORRIGIR-4-ERROS.md`** | ⚠️ **Leia antes de aplicar**: o `FAIL` do `CHECKSUMS-F11-10.txt`, como restaurar `docs/README.md` e as alternativas sem Python/lychee |
| `RESTAURAR-docs-README.md` | Conteúdo original exato (3.213 bytes / `6f0783e2…`) para desfazer a colisão de nomes |
| `verificar-congelados.sh` | Equivalente read-only do `verify-contract-freeze.py`, **sem Python** |
| `verificar-links.sh` | Triagem de links Markdown relativos, **sem lychee/Python/Node** |
| `aplicar-limpeza-raiz.sh` | Aplicador da limpeza: acha `listas/`, **dry-run por padrão**, nunca faz push |
| **`VERIFICAR.ps1`** ⭐ | **PowerShell puro** — faz os 4 guards + limpeza sem Bash/Python/`sha256sum`/`lychee` |
| `COMANDOS-POWERSHELL.md` | Equivalentes de `sha256sum`/`wc`/`cp` e a sequência no `PS E:\wamp64\www\davidcms>` |

---

## Regra de organização

Os documentos antigos **permanecem na raiz**, indexados e não movidos.
Mover quebraria links relativos em `INDICE.md`, `RELATORIO.md` e ADRs `agents-ia/`,
e exigiria reemitir os 14 `CHECKSUMS*.txt` — o que os contratos congelados proíbem.
**Organizar = indexar sem duplicar, sem mover e sem alterar hashes.**

---

## Estado em uma linha

Entrega corrente: `entregas/ReamurCMS-Frente-BO-r4-Windows.zip`
(3.203.580 bytes / 80 entradas / SHA `e69ed9f306b5f2c6c91533d79c03f4eb0bd2332af42d93d27d5f69287f01f76c`),
**90/90** `.sha256` OK, verificador **142 OK / 0 FALHAS**.
Aceites visuais: **AP, BI, BM**. Reprovadas: **AO, BK**. Contestado: **BN**.
**Sem aceite visual do 3D no servidor e sem release aprovada.**

---

## Limites

Nenhuma escrita do agente foi feita no GitHub. Não houve migration, deploy, CI remoto autenticado,
health real, sessão Admin, MySQL nem decisão das D01–D10. Toda aplicação em repositório é sua.
**CI verde, PHP WASM, jsdom, Chromium headless e guardas não substituem veredito nem release.**
