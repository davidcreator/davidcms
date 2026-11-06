# Relatório Final — Frente AX (plano das Ferramentas internas)

**Data:** 2026-11-06 · **HEAD:** `481b690209ec1a1caf4d9f334cef6012d4597653`
**Tipo:** consolidação de decisões + inventário + plano. **Nenhum código
aplicado**, nenhuma escrita no GitHub.

## Contexto

Após a Frente AV, o **CI voltou ao verde: 7/7 success**. O usuário aplicou o
AV e propôs uma frente nova: cadastrar BDREditor e KAZTheme-Editor como
**ferramentas internas** na página Ferramentas, com versão/atualizações, no
mesmo padrão das externas Fluxa/Vertex/Solis/Quotia, além de renomeá-las.

## Levantamento de código

A página Ferramentas é o CRUD `tool/tool` (Admin) + grade pública no catálogo.
**Fluxa, Vertex, Solis e Quotia são linhas no banco**, não módulos — o doc
`F8-09-Ferramentas-Conectadas.md` diz explicitamente que a tarefa não
implementa essas ferramentas.

`dev/install/sql/schema.sql:225-243` — `rms_tool` tem `tool_id`, `image`,
`vendor`, `url`, `status`, `sort_order`, `date_added`; `rms_tool_description`
tem `name`, `description`. **Não há `version`, `date_modified` nem changelog** —
e nenhuma tela do repositório exibe versão das ferramentas externas.

## Decisões do usuário (via perguntas estruturadas)

| # | Escolha |
|---|---|
| 1 | **`KAZEditor-Theme`** (corrigido de "KAZEditor-Thene") |
| 2 | **R2 — rename global** |
| 3 | **V2 — JSON de metadados versionado**, sem migration |
| 4 | **P2 — seção "Ferramentas internas" só no Admin** |

Nomes finais: **`BDREditor-Content`** · **`KAZEditor-Theme`**.

## Inventário do R2

**179 arquivos / 762 ocorrências**, distribuídos em docs, CSS, traduções,
tools/probes, JS/PHP runtime, Twig, ADRs e réplica `tree/`.

- **13 de 14 manifestos** `CHECKSUMS*.txt` precisariam ser regenerados.
- Os **3 contratos congelados** de `frozen_artifacts_sha256` **não** citam os
  nomes → `verify-contract-freeze.py` pode permanecer verde. Isso **remove** o
  conflito de governança mais grave.
- Probes com strings literais: `kaz-bdre-visual-probe.php` (18),
  `kaz-bdre-total-probe.php` (22), além de `.cjs`/`.py`/`.sh` de verificação.
- Arquivos com o termo no próprio path → renomear exige atualizar links.

## Conteúdo do pacote

| Arquivo | Papel |
|---|---|
| `RELATORIO-FINAL-AX.md` / `INDICE-AX.md` | Este relatório e o índice |
| `Plano-Frente-AX.md` | Decisões, arquitetura V2/P2, JSON, fases B1–B4, o que falta |
| `Inventario-Rename-Global.md` | 179/762, categorias, sensíveis, manifestos, contratos |
| `Analise-Viabilidade-Ferramentas-Internas.md` | Levantamento atualizado com as decisões |
| `evidencia/inventario-rename.json` | Dados brutos por arquivo |
| `tools/verify-inventario.cjs` | Verificador Node puro |

## Validação

- Árvore do HEAD lida (`3243` entradas / `2660` blobs / `truncated: false`);
  HEAD pós-AV `481b6902…` com **7/7 check-runs success**.
- Réplica de leitura: 1.346 arquivos de texto + 432 scripts + 38 `.sql`.
- Contagens reproduzíveis a partir de `evidencia/inventario-rename.json`.
- `node --check` e execução do verificador; ZIP com CRC + `.sha256`.

## Por que ainda não há código aplicável

Duas informações são do usuário e **não podem ser inferidas**:

1. As **versões iniciais** das duas ferramentas (o JSON usa
   `PENDENTE_USUARIO` de forma honesta, sem inventar número).
2. Se **R2 inclui B3** (identificadores de código: nomes de arquivos JS/PHP,
   classes, rotas, probes) ou só B1+B2 (texto, rótulos e docs).

Sem isso, gerar o pacote de código produziria um rename incompleto ou um CI
vermelho — exatamente o problema que a Frente AV acabou de corrigir.

## Limites

Nenhuma escrita no GitHub, nenhum rename, nenhuma migration, nenhuma alteração
de contrato congelado/manifesto/hash/`schema.sql`/DOM/JS de ~600 KB/renderer/
catálogo. Nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`,
nenhuma decisão das 10 `PENDING_DECISION`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · veredito **APROVADO** da AP mantido. AR e AS prontos
e sem conflito. CI verde, health local e guardas estáticos **não significam
release aprovada**.
