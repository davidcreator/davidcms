# RELATORIO-FINAL-CI-COMPOSER-3 — Terceiro hotfix do job composer (testes PHP desatualizados pelo KZ-24)

**Data:** 2026-11-17 · **Base:** HEAD `998a929` · CI: só `composer` (PHPUnit) vermelho
**Pacote:** `ReamurCMS-Frente-CI-Composer-Hotfix-3.zip` · Não é release

## Causa raiz
Os testes PHP de contrato varrem o `kaz-canvas.js` e o controller e ainda esperavam o comportamento pré-KZ-24:
- `KazInlineTextTest.php`: esperava `if (ev.key === 'Enter') { ev.preventDefault(); inlineStop(true, true); }` — o KZ-24 tornou o Enter condicional (em texto do tema, Enter quebra linha; Ctrl/Cmd+Enter conclui).
- `KazThemeTextTest.php`: esperava `THEME_REF` sem `nl2br`.

## Correções
- `tests/Unit/Library/KazInlineTextTest.php` → assert do novo trecho do Enter (KZ-24)
- `tests/Unit/Library/KazThemeTextTest.php` → THEME_REF com `(upper|lower|nl2br)`

## Validação local
- Todos os `tests/check_*.js` passam (varredura completa)
- Padrões antigos varridos em tests/Unit: nenhum outro hit
- `verify-checksums.sh` → 3 PASS / 0 FAIL (manifesto intocado)

Se o composer seguir vermelho após este pacote, o próximo passo é ler o log do job no GitHub (o log exige admin no repo) para ver o teste exato que falha.
