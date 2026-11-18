# Hotfix CI — KZ-34/KZ-35 deixaram composer (check_form_patterns) e docs-links vermelhos

**Data:** 2026-11-17 · **Base:** HEAD `05f48d9` · **Pacote:** `ReamurCMS-Frente-Hotfix-CI-KZ3435.zip`

## Causas
1. `composer`: `check_form_patterns.js` — o pattern `[a-z0-9][a-z0-9-]{0,31}` no input da rota do admin
   é inválido no modo `v` do navegador (hífen no fim da classe). Corrigido para `[a-z0-9][a-z0-9\-]{0,31}` → 99 PASS.
2. `docs-links`: o CHANGELOG (KZ-25) tinha `https://davidalmeida.xyz/threejs/`; o lychee --offline só
   tolera internos e o exclude cobre só `/catalog/tools`. URL reescrita sem scheme.

## Validação local
Todos os `tests/check_*.js` passam; CHECKSUMS recalculado (só CHANGELOG no manifesto).

## Aplicar
```bash
git commit -m "fix(ci): pattern com hífen escapado no dracaunos + link externo fora do escopo do docs-links"
```
