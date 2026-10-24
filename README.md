# F9-13 — Parser, serializer e conversor do Blog

Entrega incremental do parser/conversor legado, sem tela, integração de persistência ou conversão de posts.

- HEAD base do GitHub `main`: `9cb6e196a3d8b77008961dd1736ec77f3bc01ea7` (F9-12 publicado; artefatos F9-12 conferidos por SHA-256).
- Biblioteca isolada: `dev/system/library/blog/block_document.php`.
- Fixtures: `tests/fixtures/legacy-conversion.json`.
- Runner funcional PHP: `tests/run_f913_fixtures.php` — incluído, **não executado**.
- Validação estática: `bash tools/e2e/e2e_f913.sh --check`.

A conversão fail-closed conserva a fonte original e não retorna blocos parciais ao encontrar conteúdo desconhecido/perigoso. Nenhum SQL/PHP/browser/app test foi executado; nenhum banco foi acessado.
