# Análise de viabilidade — Ferramentas internas na página Ferramentas

**Data:** 2026-11-06 · **HEAD:** `481b690209ec1a1caf4d9f334cef6012d4597653`
**CI:** ✅ **7/7 success** (após a Frente AV — `repo-guards` e `docs-links` voltaram ao verde)
**Tipo:** levantamento de código, **sem alteração aplicada**, sem escrita no GitHub.

> **Nota de escopo:** a restrição "editar somente o BDREditor" pertencia à
> correção visual AN/AP e foi **encerrada com o veredito APROVADO**. Esta é uma
> frente nova, explicitamente proposta pelo usuário, e portanto pode tocar a
> página Ferramentas. Ainda assim, nada será executado sem as decisões abaixo.

---

## 1. Como a página Ferramentas funciona hoje

| Camada | Arquivo | Papel |
|---|---|---|
| Admin (CRUD) | `dev/admin/controller/tool/tool.php`, `dev/admin/model/tool/tool.php` | cadastro, filtros, paginação, CSRF |
| Admin (view) | `dev/admin/view/template/tool/tool_list.twig`, `tool_form.twig` | tabela e formulário |
| Admin (JS/CSS) | `dev/admin/view/js/connected-tools.js`, `dev/admin/view/css/admin.css` | upload de ícone, filtros |
| Admin (idiomas) | `dev/admin/language/{en-us,pt-br,es-eu}/tool/tool.php` | rótulos |
| Catálogo (grade pública) | `dev/catalog/controller/tool/tool.php`, `dev/catalog/model/tool/tool.php`, `dev/catalog/view/template/tool/tool_list.twig` | páginas públicas `/fluxa`, `/quotia`… |
| Documentação | `docs/F8-09-Ferramentas-Conectadas.md`, ADR `0053` | contrato da tarefa F8-09 |

**Descoberta central:** `docs/F8-09-Ferramentas-Conectadas.md` diz literalmente
que a tarefa *"trata cartões/links de ferramentas conectadas existentes, **não
implementação de Fluxa, Quotia, Vertex, Solis, APIs, execução de ferramenta**"*.
Ou seja, **Fluxa, Vertex, Solis e Quotia são linhas no banco**, não módulos de
código. Isso torna o cadastro das internas algo potencialmente **sem código
nenhum**.

### Schema real (`dev/install/sql/schema.sql:225-243`, espelhado em `schema-complete.sql:304`)

```sql
CREATE TABLE `rms_tool` (
  tool_id, image, vendor, url, status, sort_order, date_added
);
CREATE TABLE `rms_tool_description` (
  tool_id, language_id, name, description
);
```

**Não existe `version`, `date_modified`, `changelog` nem tabela de updates.**
A lista do Admin exibe: imagem, nome, fornecedor, URL, status, ordem e
`date_added`. O catálogo público exibe nome, fornecedor, descrição e link.
`dev/admin/controller/common/dashboard.php:162` mostra `REAMUR_VERSION` —
versão **do CMS**, não das ferramentas.

➡️ **Não encontrei, no repositório, nenhuma tela que mostre versão ou
atualizações das ferramentas externas.** Se você está vendo isso em outra tela
(painel, documentação ou produto externo), preciso de uma captura para
reproduzir o mesmo padrão.

---

## 2. Dimensionamento do rename

| Termo | Arquivos que citam (amostra de texto do HEAD) |
|---|---|
| `BDREditor` | **147** |
| `KAZTheme-Editor` / `KAZTheme` | **30** |
| `BDREditor-Content` | 0 |
| `KAZEditor-Thene` | 0 |

Exemplos de pontos sensíveis:

- **Contratos congelados:** `docs/BDREditor-Table-Contract.json`,
  `docs/BDREditor-Table-Verification.json`, dezenas de `docs/BDREditor-*.md`.
- **Probes com asserções de string literal:** `tools/kaz-bdre-visual-probe.php`,
  `tools/kaz-bdre-total-probe.php`, `tools/f9-89-single-ribbon-brand-smoke.js`.
- **Identificadores de código:** `dev/admin/view/js/blog-block-editor-kaz.js`,
  `kaz-theme-editor.js`, `kaz-theme-editor-ergonomia.js`,
  `dev/admin/view/template/design/kaz_theme_editor.twig`,
  `dev/system/library/theme/theme_preview_state.php`.
- **Integridade:** 14 `CHECKSUMS*.txt` (142 arquivos conferidos) e
  `tools/verify-contract-freeze.py`. Qualquer alteração nesses arquivos exige
  regenerar manifesto **e** decisão sobre contrato congelado — foi exatamente
  isso que deixou o CI vermelho na Frente AV.
- Há ainda um diretório `tree/` com réplica de código que duplica várias
  ocorrências.

### Consequência

| Alcance | Impacto |
|---|---|
| **R1 — só exibição** (rótulos/registros/novo JSON) | Seguro. Código, contratos, hashes e probes permanecem intactos; documenta-se a equivalência nome novo ↔ identificador antigo |
| **R2 — global** (renomear arquivos, identificadores, docs, probes) | **Quebra** `verify-contract-freeze`, os 14 manifestos, `docs-links`, probes e contratos F9. Exige reauditoria completa e decisão explícita sobre os 10 `PENDING_DECISION` |

**Recomendação técnica: R1.** O nome de produto exibido ao usuário não precisa
ser igual ao identificador interno.

---

## 3. De onde viriam "versão" e "atualizações"

| Opção | Migration? | O que mexe | Viabilidade aqui |
|---|---|---|---|
| **V1 — reaproveitar campos** (`description` = changelog curto, `date_added` = "desde") | ❌ | só dados no CRUD | Imediato, mas `description` tem 255 chars e `date_added` é data de criação, não de atualização — **não é uma versão real** |
| **V2 — arquivo de metadados versionado** (`tools/internal-tools.json` com `version`, `updated_at`, `notes[]`), lido pelo controller e renderizado numa seção "Ferramentas internas" | ❌ | 2–3 arquivos de código + 3 idiomas + 1 JSON | **Recomendado**: versão auditável, sem tocar em `schema.sql`, sem migration |
| **V3 — colunas novas** (`version`, `date_modified`) + tabela de updates | ✅ | `schema.sql`, `schema-complete.sql`, upgrade SQL, model, form, list, ACL | **Vetado** pelas restrições vigentes (sem migration, sem runtime/staging) e sem MySQL neste ambiente. Só com autorização explícita |

As versões iniciais teriam de ser **fornecidas por você** (ou lidas de uma
referência existente). Não vou inventar números de versão.

---

## 4. Onde as internas apareceriam

- **P1 — como registros na mesma lista `tool/tool`**, junto de Fluxa etc.,
  com `vendor` = "ReamurCMS" e `url` apontando para a rota interna. Zero código
  se feito pela UI do Admin; mas herda as mesmas colunas (sem versão).
- **P2 — seção separada "Ferramentas internas"** na página Ferramentas,
  alimentada pelo JSON da V2. Mantém externas e internas visualmente
  distintas e permite versão/atualizações. Exige pacote de código.
- **P3 — também na grade pública do catálogo.** Atenção: `tool/tool` público
  é uma **vitrine de ferramentas externas** com SEO/keyword; expor ferramentas
  internas do Admin ali pode não fazer sentido e amplia o escopo.

---

## 5. O que NÃO foi feito

Nenhum pacote de código, nenhuma escrita no GitHub, nenhuma migration, nenhum
rename aplicado, nenhuma alteração de contrato/manifesto/hash, nenhuma decisão
das 10 `PENDING_DECISION`, nenhuma declaração de release. O veredito
**APROVADO** da AP permanece válido.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · CI 7/7 success (lido, não disparado).

---

## 6. Decisões do usuário (registradas em 2026-11-06)

| # | Decisão | Escolha |
|---|---|---|
| 1 | Grafia do nome | ✅ **`KAZEditor-Theme`** (não `KAZEditor-Thene` — grafia corrigida) |
| 2 | Alcance do rename | ✅ **R2 — global** |
| 3 | Origem da versão | ✅ **V2 — JSON de metadados versionado**, sem migration |
| 4 | Onde exibir | ✅ **P2 — seção "Ferramentas internas" só no Admin** |

Nomes finais: **`BDREditor-Content`** e **`KAZEditor-Theme`**.

**Levantamento posterior ao R2:** 179 arquivos / 762 ocorrências;
13 dos 14 manifestos `CHECKSUMS*.txt` teriam de ser regenerados; os **3
contratos congelados** de `frozen_artifacts_sha256` **não** citam os nomes e
portanto **não** precisam ser alterados — `verify-contract-freeze.py` pode
continuar verde. Detalhe e fases em `Plano-Frente-AX.md` e
`Inventario-Rename-Global.md`.

**Ainda pendente (não decidido por inferência):** as versões iniciais das duas
ferramentas e se o R2 inclui também **identificadores de código** (nomes de
arquivos JS/PHP, classes, rotas) ou só texto/rótulos/docs. Sem isso, nenhum
rename foi aplicado.
