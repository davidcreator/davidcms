# RELATÓRIO — KZ-40: Dracaunos-Security Fase C (checker estático + decision-matrix)

## Entrega
1. **`tests/check_dracaunos_security.js`** (novo) — 52 verificações estáticas, PASS local; `node --check` passa (é o que o job `node-deps` exige dos scripts de `tests/`). Confere:
   - `startup/dracaunos` no pre-action do admin, antes de `startup/login`, com `startup/extension` último (restrição do `ShippedAndWiringTest`);
   - pre-ação aplicando IP em todas as rotas (escopo admin) com 403, auditoria e expiração de senha;
   - login do admin com **exatamente 1** `$this->request->ip()` (restrição do `ProxyWiringTest`, que regrediu no run 44299525501);
   - SQL da Fase B portátil (sem `ADD COLUMN IF NOT EXISTS` no código — erro MySQL #1064 reportado) e idempotente via `information_schema`;
   - Dracaunos/JOW fora de `rms_tool` (nunca públicos) e Dracaunos-Security 1.2.0 no `internal-tools.json`;
   - conta do cliente (KZ-38): controllers POST com CSRF, views existem, paridade de chaves pt-br/en-us/es-eu;
   - `password_changed_at` gravado na troca de senha (perfil e admin);
   - templates novos sem `<script>` inline nem atributo `on*` (regra CSP).
2. **`tools/decision-matrix.json`** — entrada **KZ-39** (status DECIDED, decided_by David L. Almeida, 2026-11-18, decision A) congelando 19 artefatos do Dracaunos (Fase A catálogo + Fase B admin) com SHA-256 em `frozen_artifacts_sha256`. `status_counts.DECIDED` 10 → 11; `head` → `8421bc70`.

## Validações
- `node tests/check_dracaunos_security.js` → PASS (52 verificações).
- Suite completa `tests/check_*.js`: 21 PASS; só falham `check_elementor_workbench.js` e `check_reopen_and_immersive.js` — **pré-existentes**, falham igual no main sem esta frente.
- `bash tools/verify-checksums.sh` → 3 PASS / 0 FAIL (CHANGELOG recalculado; é o único arquivo novo coberto pelo manifesto).
- CI no main @ `8421bc70`: run 44313021370 **7/7 success**.

## Observação (não é регрессия desta frente)
`tools/verify-contract-freeze.py` mostra DIVERGENTE em artefatos antigos de F9/F11 (ex.: `docs/F9-18-Risk-Register.json`) **já no main limpo**, sem esta frente — o script não roda no CI (não está no `php.yml`). Os 19 hashes novos que registrei conferem com o HEAD atual. Não alterei os antigos: contrato congelado exige decisão explícita do responsável.
