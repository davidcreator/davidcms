# RELATORIO-FINAL-CI-COMPOSER — Hotfix do job composer (PHPUnit via KazThemeTextTest)

**Data:** 2026-11-17 · **Base:** HEAD pós-hotfix-CI (`e23f328`) · CI: repo-guards verde, composer vermelho
**Pacote:** `ReamurCMS-Frente-CI-Composer-Hotfix.zip` · **Não é release**

## Causa raiz
O job `composer` roda o PHPUnit, e `KazThemeTextTest` invoca `node tests/check_kaz_theme_text.js`. As etapas **KZ-23 e KZ-24** mudaram o comportamento do editor, mas o checker do KZ-21 ficou desatualizado:
- `themeWhy` não devolve mais `theme_lines` (KZ-24) e o regex do `THEME_REF` ganhou `nl2br`
- `themeClean` preserva `\n` (CRLF→LF, tab→espaço, controle sai)
- `themeCommit` passou de 2 para 3 chamadas (KZ-23: atributo)
- modo `box` agora também por quebra de linha (KZ-24)

Além disso, o próprio checker **achou um bug real do KZ-23/KZ-24**: as chaves novas `theme_attr`, `theme_attr_help` e `theme_lines_help` não estavam na lista `$canvasLabels` do controller — o painel mostraria a chave crua no lugar do texto traduzido.

## Correções
- `tests/check_kaz_theme_text.js` atualizado para o comportamento KZ-23/KZ-24 → **342 PASS**
- `dev/admin/controller/design/theme.php`: 3 labels adicionados à lista `$canvasLabels` (com `theme_attr` em WITH_LANG por causa do `%s`)

## Validação
- `check_kaz_theme_text.js` → 342 PASS · `check_kaz_theme_lines.js` → 40 OK · `check_kaz_theme_attr.js` → 47 OK
- `verify-checksums.sh` → 3 PASS / 0 FAIL (nenhum arquivo do manifesto foi tocado neste pacote)
