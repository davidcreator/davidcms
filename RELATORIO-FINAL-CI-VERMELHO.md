# RELATORIO-FINAL-CI-VERMELHO — Hotfix do CI (jobs composer + repo-guards)

**Data:** 2026-11-17 · **Base:** HEAD `a9e8ab7` (D01–D10 aplicados) · CI vermelho desde `235271c` (KZ-23)
**Pacote:** `ReamurCMS-Frente-CI-Vermelho-Hotfix.zip` · **Não é release**

## Causa raiz (2 problemas, ambos Regime KZ-23/KZ-24)

1. **repo-guards / verify-checksums.sh** — `CHECKSUMS.txt` ficou desatualizado: `docs/CHANGELOG.md` mudou nas entradas KZ-23 e KZ-24 (tamanho 135.227 → 138.583 bytes) e o manifesto não foi regenerado.
   - Correção: entrada de `docs/CHANGELOG.md` recalculada (CRC32 + SHA-256 + tamanho). Revalidado: **3 PASS / 0 FAIL**.
2. **composer / PHPUnit** — os testes novos `KazThemeAttrTest.php` e `KazThemeLinesTest.php` usavam `ROOT = __DIR__ . '/../../../..'` (4 níveis, fora do repositório); o correto é 3 níveis (`'/../../..'`). Com 4 níveis, `assertFileExists` falhava e o job `composer` (PHPUnit) ficava vermelho.
   - Correção: ROOT acertado nos dois testes.

## Validação local (HEAD a9e8ab7 + hotfix)
- `verify-checksums.sh` → 3 PASS / 0 FAIL · `verify-deps-layout.sh` → 17 PASS / 0 FAIL · `verify-ci-workflow.sh` → 35 PASS / 0 FAIL
- `check_kaz_theme_attr.js` → 47 OK · `check_kaz_theme_lines.js` → 40 OK
- `ThemeStrings::isText` conferido: público e estático (teste do KZ-24 é válido)

## Arquivos neste pacote
- `CHECKSUMS.txt` (entrada do CHANGELOG recalculada)
- `tests/Unit/Library/KazThemeAttrTest.php` (ROOT 3 níveis)
- `tests/Unit/Library/KazThemeLinesTest.php` (ROOT 3 níveis)

## Como aplicar
Descompactar sobre a raiz do projeto e commitar. O próximo push deve voltar o CI ao verde (7/7).
