# Índice — Frente BC (KAZEditor-Theme · import por arquivo, recorte A)

Pacote: `ReamurCMS-Frente-BC-Import-Tema-Arquivo.zip` · HEAD `df0d3d34…` · CI 7/7 ✅
**6 arquivos alterados** · **0** em manifesto `CHECKSUMS` · **backend intacto**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BC.md` | Levantamento, implementação, validação, limites |
| 2 | `INDICE-BC.md` | Este índice |
| 3 | `dev/admin/view/js/theme-package.js` | Drag-and-drop, prévia, validação fail-closed no client |
| 4 | `dev/admin/view/template/design/kaz_theme_editor.twig` | Zona de drop + `<dl>` de prévia + status ARIA |
| 5 | `dev/admin/controller/design/theme.php` | **1 linha**: injeta as 10 chaves de idioma |
| 6 | `dev/admin/language/en-us/design/theme.php` | 10 rótulos EN |
| 7 | `dev/admin/language/pt-br/design/theme.php` | 10 rótulos PT-BR |
| 8 | `dev/admin/language/es-eu/design/theme.php` | 10 rótulos ES-EU |
| 9 | `tools/verify-theme-import-file.cjs` | Verificador Node puro |
| 10 | `evidencia/jsdom-harness.txt` | 31 OK / 0 FALHAS |
| 11 | `evidencia/validacao.txt` | `php -l` 4/4 + guardas |

## Decisões

- **Recorte A** — import por arquivo, modelo seguro.
- **Twig/ThreeJS continuam DADOS validados** (allowlist + checksum), sem
  código executável.

## Contrato preservado

O POST segue `theme_package` (string) → `design/theme.import`. ACL `modify`,
CSRF e `ThemePackage::decode()` (checksum `hash_equals`, allowlists) **não
mudaram**. Import continua **sempre como rascunho**, nunca publica.

## Como aplicar

**6 substituições.** Nenhum manifesto, contrato, hash, schema, workflow,
`TEMPLATE_KEYS` ou biblioteca precisa ser tocado.

```bash
node tools/verify-theme-import-file.cjs <raiz>
bash tools/repo-health.sh
```

## Validação medida

`node --check` ✅ · `php -l` **4/4** · **jsdom 31 OK / 0 FALHAS** ·
Twig balanceado · verificador 0 FALHAS · `verify-checksums` 142 arquivos /
0 divergentes · `repo-health` **6 PASS / 0 FAIL / COERENTE** ·
`verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não aceita ZIP com `.twig`/`.js`, não amplia allowlist, não escreve arquivo no
servidor, não faz migration, não altera contrato congelado, não decide
D01–D10, não escreve no GitHub pelo agente e **não significa release
aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health do Admin real ainda
pendente** (guia aplicado, JSON não colado).
