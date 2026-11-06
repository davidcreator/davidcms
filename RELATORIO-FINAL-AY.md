# Relatório Final — Frente AY (Parte A: Ferramentas internas no Admin)

**Data:** 2026-11-06 · **HEAD:** `a73341affb73debe3979bb470ecb5f5aab82dfe9`
**CI remoto:** 7/7 success · **Tipo:** **código aplicável** (V2 + P2), não plano.

> Escopo: implementa **somente a Parte A** decidida em `Plano-Frente-AX.md`.
> A **Parte B (rename R2 global) NÃO foi aplicada** — continua aguardando as
> duas respostas do usuário (versões iniciais e alcance B3). Nenhuma escrita do
> agente no GitHub.

## 1. O que foi implementado

Uma **seção "Ferramentas internas"** na página Ferramentas do Admin, separada
da lista de ferramentas conectadas (Fluxa/Vertex/Solis/Quotia), exibindo as
duas ferramentas com **nome, versão, data de atualização e histórico de
atualizações**.

| Arquivo | Ação |
|---|---|
| `dev/admin/model/tool/internal-tools.json` | **Novo** — fonte única de metadados versionados |
| `dev/admin/controller/tool/tool.php` | Adendo: `internalTools()` + 1 linha de injeção no `index()` |
| `dev/admin/view/template/tool/tool_list.twig` | Adendo: `<section class="internal-tools-panel">` |
| `dev/admin/view/css/admin.css` | Adendo `.internal-tools-*`, prefixo anterior **intacto** |
| `dev/admin/language/{en-us,pt-br,es-eu}/tool/tool.php` | 8 rótulos novos por idioma |
| `tools/verify-internal-tools.cjs` | **Novo** — verificador Node puro |

### Ajuste de engenharia em relação ao plano AX

O plano previa `tools/internal-tools.json`. O JSON ficou em
**`dev/admin/model/tool/internal-tools.json`** porque `tools/` é diretório de
desenvolvimento/QA e pode não existir em produção; dentro do `admin/` o
caminho é derivado de `DIR_APPLICATION` (`rtrim(DIR_APPLICATION,'/\\')`),
estável e independente da estrutura do repositório.

### Versões **não foram inventadas**

```json
{"id":"bdreditor-content","name":"BDREditor-Content","legacy_name":"BDREditor",
 "version":"PENDENTE_USUARIO","updated_at":null,"notes":[]}
```

O controller converte `PENDENTE_USUARIO` em `''` e a UI exibe
**"Não informado"** / **"Nenhuma atualização registrada."** de forma honesta.
`legacy_name` documenta a equivalência nome novo ↔ identificador antigo
enquanto a Parte B não roda.

### Segurança e fail-closed

`internalTools()` lê **somente** o JSON local (sem banco, sem rede, sem
migration) e é **fail-closed**:

- arquivo ausente/ilegível, JSON inválido, `schema != 1`, `tools` ausente →
  **lista vazia** e a seção simplesmente não é renderizada — **a página nunca
  quebra**;
- `id` validado por regex, único; `name` obrigatório (≤128);
- `version` ≤32, `updated_at` somente `AAAA-MM-DD`, `notes` até 5 linhas
  de até 255 caracteres;
- **nenhum `|raw` novo** — todo valor passa pelo escape automático do Twig;
- sem rota nova, sem ACL/CSRF novo, sem `!important`, sem redefinir `--bs-*`,
  sem alvos globais no CSS (apenas `.internal-tools-*`).

Tema ReamurCMS claro e acento azul preservados — **nada** de identidade KAZ
fora do BDREditor. O veredito **APROVADO** da AP continua vigente.

## 2. Validação (toda local; não substitui o Admin real)

| Verificação | Resultado |
|---|---|
| Árvore fiel do HEAD | **2.667 blobs / 43.003.989 bytes / 0 divergentes** |
| `php -l` (PHP **8.5.12** WASM) nos 4 `.php` | **4/4 "No syntax errors"** |
| Harness funcional de `internalTools()` | **11/11 cenários** (real, ausente, JSON inválido, schema errado, sem `tools`, id duplicado, nome vazio, versão longa, data inválida, limite de notas, HTML preservado para escape) |
| `tools/verify-checksums.sh` | **rc=0** · 14 manifestos · 142 arquivos · 0 divergentes |
| `tools/verify-deps-layout.sh` | 8 PASS / 0 FAIL |
| `tools/verify-ci-workflow.sh` | 31 PASS / 0 FAIL |
| `tools/verify-f11-01.sh` | 17 PASS / 0 FAIL |
| `tools/verify-contract-freeze.py` | **COERENTE** — nenhum contrato alterado sem decisão |
| `tools/release-readiness-report.sh` | rc=0 |
| **`tools/repo-health.sh`** | **rc=0 · 6 PASS / 0 FAIL / 0 SKIP · COERENTE** |
| `tools/verify-internal-tools.cjs` | verificação completa do pacote |
| Twig | `if/endif` e `for/endfor` balanceados, 0 `\|raw` na seção nova |
| Links Markdown | inalterados |

**Nenhum dos 8 alvos aparece em qualquer `CHECKSUMS*.txt`** — foi por isso que
a implementação pôde seguir sem regenerar manifesto (lição da Frente AV).
**Nenhum arquivo de `dev/install/sql/`, contrato congelado, manifesto, hash,
`schema.sql`, DOM, JS de ~600 KB, renderer ou catálogo público foi alterado.**

## 3. O que NÃO foi feito

- **Parte B — rename R2 global:** nenhuma substituição, nenhum arquivo
  renomeado, nenhum manifesto regenerado. O inventário permanece
  **179 arquivos / 762 ocorrências**.
- Nenhuma migration, nenhuma coluna nova, nenhuma tabela, nenhuma rota,
  nenhuma execução de runtime/MySQL/staging/deploy.
- Nenhuma conversão de `PENDING`/`SKIP`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`,
  nenhuma decisão das **10 `PENDING_DECISION`**, nenhuma declaração de release.
- **Nenhuma escrita do agente no GitHub.**

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · veredito visual **APROVADO** da AP mantido.
CI verde, health estático, PHP WASM e harness **não significam release
aprovada** nem substituem a aceitação visual no Admin real.

## 4. Ainda depende de você

1. **Versões iniciais** de `BDREditor-Content` e `KAZEditor-Theme`
   (e `updated_at`/`notes`, se quiser). Basta me dizer que eu atualizo só o
   JSON — sem tocar em código.
2. **R2 inclui B3?** (identificadores de código: nomes de arquivos JS/PHP,
   classes, rotas, probes, checkers e2e) ou apenas B1+B2 (texto/rótulos/docs)?
3. **Aceite visual** da nova seção no Admin real após aplicar.

Pacotes **AR** e **AS** seguem prontos e sem conflito com o HEAD atual.
