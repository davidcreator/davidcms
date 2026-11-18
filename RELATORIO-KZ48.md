# RELATÓRIO — KZ-48: F918-04 (P2) — atomicidade total do deletePost

## Achado
`deletePost()` no model admin do blog já envolvia post/description/SEO/content em transação, mas o **delete de `bdre_revision` ficava depois do COMMIT** — se falhasse, deixava revisões órfãs (exatamente o cenário do F918-04).

## Correção
O delete de `bdre_revision` foi movido para **dentro da mesma transação**, antes do COMMIT. Agora um rollback cobre post + content + descriptions + seo_url + revisions — exclusão parcial não é mais possível nesse caminho.

## Arquivos
- `dev/admin/model/blog/blog.php` — deletePost totalmente transacional
- `tests/check_blog_delete_atomicity.js` — **novo checker** (6 verificações): garante START TRANSACTION, ROLLBACK no catch e delete de revisions ANTES do COMMIT
- `docs/F9-18-Risk-Register.json` — F918-04 `open` → `mitigated` (nota KZ-48)
- `tools/decision-matrix.json` — KZ-48 DECIDED + hash congelado do register atualizado
- `docs/CHANGELOG.md` + `CHECKSUMS.txt` recalculado

## Validações locais (@ `db777963` + esta frente)
- `node tests/check_blog_delete_atomicity.js` → PASS (6); dracaunos checker PASS (105); checksums 3 PASS / 0 FAIL.
- Suite: só os 2 pré-existentes de UI falham. CI @ `db777963`: run 44343819246 success.
