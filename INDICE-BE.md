# Índice — Frente BE (CRUD de temas + Importar tema)

Pacote: `ReamurCMS-Frente-BE-CRUD-Temas-Importar.zip` · HEAD `7646c984…` · CI 7/7 ✅
**6 arquivos alterados** · **0** em `CHECKSUMS*.txt` · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BE.md` | `image-11.png`, levantamento, implementação, segurança, validação |
| 2 | `INDICE-BE.md` | Este índice |
| 3 | `dev/admin/view/template/design/theme_list.twig` | Botão Importar tema, card de import, coluna Ações, Ativar/Desativar/Excluir |
| 4 | `dev/admin/controller/design/theme.php` | `index()` + novas rotas `deactivate()`/`delete()` + redirect do `import()` |
| 5 | `dev/admin/model/design/theme.php` | `deactivate()` e `delete()` fail-closed |
| 6 | `dev/admin/language/en-us/design/theme.php` | 14 chaves EN |
| 7 | `dev/admin/language/pt-br/design/theme.php` | 14 chaves PT-BR |
| 8 | `dev/admin/language/es-eu/design/theme.php` | 14 chaves ES-EU |
| 9 | `tools/verify-theme-crud.cjs` | Verificador Node puro |
| 10 | `evidencia/model-harness.txt` | 20 OK / 0 FALHAS (PHP WASM + DB falso) |
| 11 | `evidencia/validacao.txt` | `php -l` 5/5 + guardas |

## O que muda na tela

| Antes (`image-11.png`) | Depois |
|---|---|
| `Novo tema` | `Novo tema` + **`Importar tema`** |
| só `Editar` | **Editar · Ativar/Desativar · Excluir** |
| header vazio | coluna **Ações** + notices de sucesso/erro |

## Decisões de segurança

- **Não exclui tema ativo** — precisa desativar antes (fail-closed);
- **Exclusão em 2 etapas** via `<details>`, sem JS inline;
- **POST + ACL `modify` + CSRF** em todas as mutações;
- **desativar preserva** `config`/`published_config`, só zera `status`;
- import continua **sempre como rascunho**; `ThemePackage::decode()` intacto;
- sem `\|raw` novo, `<script>`, `onclick`, `ALTER`, `CREATE`, `DROP`.

## Como aplicar

**6 substituições.** Nenhum manifesto, contrato, hash, schema, workflow ou
biblioteca precisa ser tocado. O `theme-package.js` **já está no HEAD** (BC) e
não é reenviado.

```bash
node tools/verify-theme-crud.cjs <raiz>
bash tools/repo-health.sh
```

## Validação medida

`php -l` **5/5** · **model harness 20 OK / 0 FALHAS** · Twig balanceado ·
verificador 0 FALHAS · `verify-checksums` 142 arquivos / 0 divergentes ·
`repo-health` **6 PASS / 0 FAIL / COERENTE** · `verify-contract-freeze`
**COERENTE**.

## O que NÃO faz

Não aceita ZIP com `.twig`/`.js`, não amplia allowlist, não faz migration, não
altera contrato congelado, não decide D01–D10, não escreve no GitHub pelo
agente e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health do Admin real e
aceites visuais ainda pendentes.**
