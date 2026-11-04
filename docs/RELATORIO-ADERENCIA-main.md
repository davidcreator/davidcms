# Relatório de Aderência — `main` real (Frente AE)

Auditoria executada em 2026-11-04 contra o conteúdo cru do commit
`56fd0c5d40fb…` (não contra réplica local).

## Commits encontrados

A API `commits/main` devolvia `13b0f7b8475b…` em todas as consultas das
frentes AC e AD. Uma consulta a `commits?sha=main&per_page=5` com `nocache`
novo e `User-Agent` revelou a cadeia completa:

| SHA | Data (UTC) | Mensagem | Frente |
| --- | --- | --- | --- |
| `56fd0c5d40fb…` | 20:06:31 | `fix(admin/theme-editor): fix silent theme editor boot failure` | AD |
| `50d4844f66d9…` | 19:46:39 | `fix(admin/theme-editor): correct browser layout issues from AB release` | AC |
| `13b0f7b8475b…` | 19:21:59 | `feat: add published theme scene and fix admin UI` | AB |
| `a408f928b380…` | 18:38:02 | `feat(kaz-theme-editor): add AA front features` | AA |
| `08eca35d39c7…` | 17:44:28 | `feat(kaz-theme-editor): make editor page owner` | anterior |

`pushed_at=2026-11-04T20:06:35Z`, `default_branch=main`.
Árvore de `56fd0c5d40fb`: **3.194 entradas / 2.611 blobs / truncated:false**.
Os 14 manifestos `CHECKSUMS-*.txt` permanecem.

## Comparação byte a byte

Baixamos de `raw.githubusercontent.com/davidcreator/davidcms/56fd0c5d40fb/`
os paths entregues pelas frentes AC e AD e comparamos com o conteúdo dos ZIPs
`ReamurCMS-Frente-AC-…zip` e `ReamurCMS-Frente-AD-…zip`.

**Resultado: 19/19 IGUAL, 0 divergências, 0 ausentes.**

| Path | Bytes |
| --- | --- |
| `dev/admin/controller/design/theme.php` | 70.109 |
| `dev/admin/view/js/kaz-theme-editor.js` | 47.669 |
| `dev/admin/view/css/kaz-theme-editor.css` | 34.897 |
| `dev/admin/view/template/common/header.twig` | 6.089 |
| `dev/admin/controller/common/header.php` | 13.287 |
| `dev/system/library/document.php` | 2.476 |
| `tools/verify-kaz-layout.cjs` | 4.616 |
| `tools/verify-kaz-boot.cjs` | 3.673 |
| `tools/diagnostico-kaztheme-somente-leitura.sql` | 1.540 |
| 10 × `docs/KAZTheme-Editor*.md` | 8.370 … 30.473 |

Marcadores confirmados no conteúdo cru: `-kaz-theme-editor-ad`,
`addScriptDeferred`, `scripts_deferred`, `bootDone`.

## Paths presentes no `main` e ausentes no recorte local

405 paths: **227 `index.html`** + **178 binários** (fontes `.woff/.woff2/.ttf/
.otf/.eot`, imagens `.png/.gif`, sourcemaps `.map`). São artefatos que o
script de download filtra por não serem texto; não representam divergência
de código. **Nenhum** é `.php`, `.js`, `.twig`, `.sql` de migration ou
manifesto.

O único `.sql` novo em relação ao recorte é
`tools/diagnostico-kaztheme-somente-leitura.sql`, entregue pela AD e rotulado
como não-migration. `dev/install/sql/schema.sql` segue com o hash congelado.

## Conclusão

As frentes AC e AD estão **integralmente aplicadas no `main`**. A ressalva das
entregas anteriores — "a API ainda devolvia o HEAD antigo, trabalhamos sobre
réplica" — está encerrada. Permanece a limitação de que repositório correto
não prova execução correta no servidor de produção: para isso existe
`tools/kaz-theme-editor-health.js` e
`docs/KAZTheme-Editor-Diagnostico-em-Producao.md`.

Nenhuma escrita no GitHub foi feita por esta auditoria. `release_approved`
segue `false`; F9-27 segue `BLOCKED_NOT_RUN`.
