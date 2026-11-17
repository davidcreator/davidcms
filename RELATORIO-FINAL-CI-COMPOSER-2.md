# RELATORIO-FINAL-CI-COMPOSER-2 — Segundo hotfix do job composer

**Data:** 2026-11-17 · **Base:** HEAD `e8d8e10` (CI-Composer-Hotfix aplicado) · CI: só `composer` vermelho
**Pacote:** `ReamurCMS-Frente-CI-Composer-Hotfix-2.zip` · Não é release

## Causa raiz (2 pontos restantes)
1. `tests/check_kaz_inline_text.js` (KZ-18) desatualizado pelo KZ-24: contagem de `setProperty`/`removeProperty`
   (o KZ-24 adicionou a devolução da pintura ao virar para caixa própria) e a regra do Enter
   (em texto do tema, Enter quebra linha; Ctrl+Enter conclui).
   - Corrigido → **182 PASS**
2. `tests/Unit/Security/BlogBlockHtmlMarkupTest.php` (novo no F918-P1) tinha `ROOT` com 4 níveis
   (`/../../../..`); de `tests/Unit/Security` o correto é 3 níveis → `assertFileExists` falhava no PHPUnit.
   - Corrigido para `/../../..`

## Validação
- Todos os `tests/check_*.js` passam (varredura completa, nenhum FAIL)
- `verify-checksums.sh` → 3 PASS / 0 FAIL (nenhum arquivo do manifesto tocado)

## Arquivos
- `tests/check_kaz_inline_text.js` (182 PASS)
- `tests/Unit/Security/BlogBlockHtmlMarkupTest.php` (ROOT 3 níveis)
