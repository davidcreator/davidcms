# RELATORIO-FINAL-CI-COMPOSER-4 — Hotfix do KazThemeTextTest (5 falhas no PHPUnit)

**Data:** 2026-11-17 · **Base:** HEAD `4c72efe` · Log do CI: `KazThemeTextTest.php:292`, 5 failures em 1734 testes
**Pacote:** `ReamurCMS-Frente-CI-Composer-Hotfix-4.zip` · Não é release

## Causa raiz
O `KazThemeTextTest` (contrato do KZ-21) varre o `kaz-canvas.js` com strings fixas que o KZ-23/KZ-24 mudaram:
- linha 255: `substr_count($script, 'themeCommit(')` esperava 3 — o KZ-23 adicionou a chamada do atributo → agora 4
- linha 292: `st.mode = st.theme && st.theme.filter ? 'box' : ...` — o KZ-24 incluiu também quebra de linha nessa condição

## Correção
`tests/Unit/Library/KazThemeTextTest.php` atualizado nos dois pontos (comentários KZ-23/KZ-24).

## Validação
- Varredura de padrões pré-KZ-23/24 em todos os tests/Unit: nenhum outro hit
- Checkers JS: varredura completa verde · `verify-checksums.sh` 3 PASS / 0 FAIL (manifesto intocado)

Se o composer continuar vermelho depois deste pacote, mande o bloco completo de falhas do log (Tests: ..., Failures: N + as linhas) para eu pegar as restantes de uma vez.
