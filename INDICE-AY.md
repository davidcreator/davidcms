# Índice — Frente AY (Parte A: Ferramentas internas no Admin)

Pacote: `ReamurCMS-Frente-AY-Ferramentas-Internas.zip` · HEAD `a73341af…` · CI 7/7 ✅
**Código aplicável** · **8 alvos**, nenhum em manifesto `CHECKSUMS`.

| # | Entrada | Ação | Conteúdo |
|---|---|---|---|
| 1 | `RELATORIO-FINAL-AY.md` | novo | Implementação, validação, limites, pendências |
| 2 | `INDICE-AY.md` | novo | Este índice |
| 3 | `dev/admin/model/tool/internal-tools.json` | **novo** | Metadados das 2 ferramentas (`PENDENTE_USUARIO`) |
| 4 | `dev/admin/controller/tool/tool.php` | substitui | `internalTools()` fail-closed + 1 linha no `index()` |
| 5 | `dev/admin/view/template/tool/tool_list.twig` | substitui | `<section class="internal-tools-panel">` |
| 6 | `dev/admin/view/css/admin.css` | substitui | Adendo `.internal-tools-*`, prefixo intacto |
| 7 | `dev/admin/language/en-us/tool/tool.php` | substitui | 8 rótulos EN |
| 8 | `dev/admin/language/pt-br/tool/tool.php` | substitui | 8 rótulos PT-BR |
| 9 | `dev/admin/language/es-eu/tool/tool.php` | substitui | 8 rótulos ES-EU |
| 10 | `tools/verify-internal-tools.cjs` | **novo** | Verificador Node puro |
| 11 | `evidencia/php-lint.txt` | novo | `php -l` PHP 8.5.12 WASM, 4/4 |
| 12 | `evidencia/wasm-harness.txt` | novo | 11/11 cenários fail-closed |
| 13 | `evidencia/guardas.txt` | novo | `repo-health` 6 PASS / 0 FAIL |

## Como aplicar

**3 novos + 4 substituições de código + 3 de idioma.** Nenhum manifesto,
contrato, hash, `schema.sql`, workflow ou arquivo de `dev/install/` precisa
ser tocado.

```bash
node tools/verify-internal-tools.cjs <raiz>
bash tools/repo-health.sh
```

Depois conferir o novo run em **Actions** e a seção no Admin real.

## Resultado medido (réplica fiel, 2.667 blobs)

`php -l` **4/4** · harness WASM **11/11** · `verify-checksums` **142 arquivos /
0 divergentes** · `verify-contract-freeze` **COERENTE** · `repo-health`
**6 PASS / 0 FAIL / COERENTE** · Twig balanceado · **0 `|raw`** na seção nova.

## O que este pacote NÃO faz

Não aplica o **rename R2** (Parte B: 179 arquivos / 762 ocorrências), não faz
migration, não altera contrato congelado, não decide D01–D10, não escreve no
GitHub pelo agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION` · veredito **APROVADO** da AP
mantido.

## Pendente do usuário

Versões iniciais das duas ferramentas · R2 inclui B3? · aceite visual no Admin
real.
