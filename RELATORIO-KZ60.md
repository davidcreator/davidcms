# RELATÓRIO — KZ-60: F918-05 (D03) — ACL do catálogo de áudio

## Decisão (D03, pelo responsável)
ACL pretendida do catálogo de áudio = **`can_use_audio_catalog`**.

## Correção
- `dev/admin/view/template/cms/page_form.twig`: `data-audio-picker` agora exige **`can_modify and can_use_audio_catalog`** (antes só `can_modify` — era o relaxamento de ACL apontado no F918-05).
- `dev/admin/controller/cms/page.php`: calcula e passa `can_use_audio_catalog` = `hasPermission('access','catalog/audio') || hasPermission('modify','catalog/audio')` (form e edit).

## Limites respeitados
- `tools/e2e/check_audio_catalog.cjs` **não foi alterado nem relaxado** — mesmo resultado do main limpo (8/11; as 3 falhas são pré-existentes e fora deste escopo).

## Governança
- `docs/F9-18-Risk-Register.json`: F918-05 `open` → **`resolved`** (nota KZ-60 + D03).
- decision-matrix: KZ-60 DECIDED (opção A) + hash congelado do register atualizado.
- CHANGELOG + CHECKSUMS recalculado (3 PASS / 0 FAIL).
