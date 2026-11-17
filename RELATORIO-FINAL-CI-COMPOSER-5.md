# RELATORIO-FINAL-CI-COMPOSER-5 — Última falha do PHPUnit (1 failure restante)

**Data:** 2026-11-17 · **Base:** HEAD `f47ed29` · Log: KazInlineTextTest.php:100 "Failed asserting that 3 is identical to 2"
**Pacote:** `ReamurCMS-Frente-CI-Composer-Hotfix-5.zip` · Não é release

## Causa
`KazInlineTextTest::testOnlyTwoPaintProperties...` contava `.style.setProperty(` = 2 e `removeProperty(` = 1 no bloco.
O KZ-24 adicionou a devolução da pintura ao virar para caixa própria no meio da edição → 3 e 2.

## Correção
`tests/Unit/Library/KazInlineTextTest.php`: contagens atualizadas (3 set / 2 remove), com comentário KZ-24.

## Validação
- Varredura de padrões antigos em tests/Unit: sem mais hits
- Checkers JS: todos verdes · checksums intocados (3 PASS / 0 FAIL)
