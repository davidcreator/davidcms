# RELATORIO-FINAL-F918-P1 — Hotfix de segurança P1 do blog público (F918-06)

**Data:** 2026-11-17 · **Base:** HEAD pós-hotfix-CI · **Pacote:** `ReamurCMS-Frente-F918-P1-Blog-Markup.zip`

## O achado (F918-06, decidido B em D07)
`dev/catalog/view/template/blog/blog_info.twig` imprime `{{ post.block_html }}` **sem `|raw`,
mas o controller entregava `(string)$rendered['html']` — o Twig escapava o HTML do BlockRenderer
e o conteúdo do post saía como texto puro na página pública (regressão funcional P1).

## Correção
`dev/catalog/controller/blog/blog.php` agora entrega `new \Twig\Markup($blockHtml, 'UTF-8')`
— o HTML vem do renderer allowlistado/sanitizado (BlockRenderer), e o template continua sem `|raw`,
seguindo o padrão do projeto (`twig.php` usa `Twig\Markup` para HTML confiável).

## Risk register
- F918-06 → **resolved** (este hotfix)
- F918-01 / F918-02 → **mitigated** (já cobertos no HEAD por `tests/fixtures/f918_regressions.json` + `tools/e2e/e2e_f919_static.cjs`: fail-closed na detecção do sidecar e resolução de imagem contra a base do catálogo)

## Arquivos
- `dev/catalog/controller/blog/blog.php` (Markup)
- `tests/Unit/Security/BlogBlockHtmlMarkupTest.php` (novo: controller entrega Markup; template sem |raw; fixture F918 presente)
- `docs/F9-18-Risk-Register.json` (status atualizados)
- `docs/CHANGELOG.md` (entrada) + `CHECKSUMS.txt` (recalculado — lição do CI vermelho)

## Validação
- `verify-checksums.sh` → 3 PASS / 0 FAIL após recálculo do CHANGELOG
