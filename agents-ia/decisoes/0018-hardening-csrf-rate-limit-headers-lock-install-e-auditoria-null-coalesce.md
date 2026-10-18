# ADR 0018: Hardening — CSRF, rate limit, headers de segurança, selo do install e auditoria `??`

- **Status:** Aceita
- **Data:** 2026-10-18
- **Autor:** Cascade (Coevolve AI)
- **Tarefa:** Fase 2 — Tarefa 18 (F2-18)
- **Regras relacionadas:** agents-ia/regras/00-AGENT-PROTOCOL.md

## Contexto

Passo intermediário antes da Fase 3 (tema Twig+Three.js): endurecer a
superfície pública exposta desde a F2-17 (contato/orçamento) e o login admin
(único ponto de credencial). Escopo travado com o mantenedor (ask_user):

- **A) Formulários:** CSRF por sessão + rate limit por IP no contato, orçamento e login.
- **B)-headers + trava do install/ pós-instalação:** `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`; `install/` selado após o wizard.
- **C) Auditoria global:** zero acessos `request->post|get|cookie[...]` sem `??`
  nos controllers (consolida a lição do ADR 0016 — warning vira ErrorException → 404 silencioso).
- **Captcha:** descartado nesta iteração (honeypot + rate limit bastam).

## Decisão

### 1 — CSRF (libraries novas, sem captcha)
- `system/library/security/csrf.php` — classe final `Csrf`:
  - `token($session)`: token estável por sessão (hex 32B), criado lazily em
    `$_SESSION['csrf_token']`.
  - `check($session, $token)`: comparação em tempo constante (`hash_equals`);
    token vazio/ausente nunca passa.
- Controllers afetados recebem `csrf_token` na view; POST valida ANTES de
  qualquer lógica de negócio (ordem CSRF → rate limit → validação de domínio).
- Views: contato, orçamento e login ganham `<input type="hidden" name="csrf_token">`.

### 2 — Rate limit por IP (tabela + lib)
- `system/library/security/rate_limit.php` — janela deslizante simples por
  `(scope, ip)` em `rms_rate_limit` (`INSERT ... ON DUPLICATE KEY UPDATE`
  atômico; janela expira sem UPDATE separado). Limpeza oportunista (1%) das
  janelas mortas, sem cron.
- Política de janelas:
  - `contact` 5/10 min (600s)
  - `quote`   5/10 min (600s)
  - `login`  6/15 min (900s)
- `rms_rate_limit` entra na seção 9 do `install/sql/schema.sql` e gain
  `install/sql/upgrade_f2_18_rate_limit_table.sql` para instalações existentes
  (idempotente, registra a convenção de prefixo `rms_`).

### 3 — Contato e orçamento (catalog)
- POST valida: **honeypot `'company'` primeiro** (bots seguem fingindo sucesso
  e REDIRECIONAM sem gastar janela), depois CSRF e rate limit, depois a
  validação de domínio. Erros → re-render repovoado (`error_csrf` /
  `error_rate_limit` como primeira mensagem).
- Limites do schema mantidos como última linha de defesa.

### 4 — Login admin
- POST valida CSRF (já existente o `user_token` de URL, que NÃO é CSRF).
- Rate limit por IP no POST: > 6 tentativas/15 min → erro genérico e interrompe
  antes de consultar credenciais (senha CERTA também fica de fora — evita
  enumerator e senha spray).
- **Session fixation:** após login válido, `session->regenerateId()` antes do
  redirect (novo id; o token CSRF muda junto — isso é intencional).
- Langs `error_csrf`/`error_rate_limit` × 3 idiomas.

### 5 — Headers de segurança (global)
- `system/config/default.php` → `response_header` agora inclui
  `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy: strict-origin-when-cross-origin` (aplicados pelo
  framework a todas as apps — catalog, admin, install, api).
- Apps podem sobrescrever no próprio config quando precisarem divergir.

### 6 — Selo do installer pós-instalação
- `install/index.php`: se `system/storage/install.lock` existe → **403**
  (página explicativa) antes de qualquer bootstrap.
- `install/step_4` grava `install.lock` ao renderizar a conclusão (marca
  timestamp ISO). Remover o lock devolve o wizard (mecanismo simples e
  auditável — nada em banco/escondido).
- As baterias E2E passaram a remover o lock no setup junto aos `config.php`.

### 7 — Auditoria `??` (lição ADR 0016 consolidada)
- Script de auditoria passa a correr como check 16 da bateria F2-18:
  `grep -E "request->(post|get|cookie)\[..."` sem `??` nos controllers deve ser
  **zero**.
- Os 6 acessos remanescentes (em blocos já validados) receberam `?? ''`
  defensivo — nenhum comportamento alterado.

## Consequências

### Positivas
- Toda superfície de escrita pública/autenticada tem CSRF + rate limit.
- Credentials não driblam a janela (login bloqueia antes de `password_verify`).
- `install/` deixa de ser superfície de reconfiguração pós-wizard.
- Headers padrão neutralizam sniff, clickjacking e referrer leak.
- Suite: **16 checks F2-18 + 101 da regressão = 117/117 verde**; PHPStan
  `[OK] No errors` (baseline intacta); baterias F2-12..F2-17 foram adaptadas
  para enviar o token CSRF (o comportamento do produto continua idêntico).

### Negativas / limitações assumidas
- `install.lock` fica em `system/storage/` (path simples e previsível; quem
  controla o filesystem já controla tudo — sem ilusão de defesa adicional).
- Rate limit é por IP, não por credencial de conta — uma rede atrás de NAT
  compartilha a cota (documentado como trade-off aceito; política futura pode
  ponderar `scope=login:<ip>:<username>`).
- Limpeza de rate limits mortos é oportunista (1% dos hits); um CRON dedicado
  pode vir junto com a Fase 5.

## Validação executada
- `php -l` 8 arquivos novos/alterados; PHPStan oficial `[OK]` (baseline F2-0).
- Bateria `tools/e2e/e2e_f218.sh` — **16/16**:
  lock do install (gravado, 403, unlock), headers ×2 apps, contato sem csrf,
  contato csrf errado, honeypot intacto, contato válido com csrf, rate limit
  (6º contato barrado, janela registrada), orçamento sem/com csrf, login sem
  csrf, login com csrf + dashboard liberado, rate limit login (7ª barrada,
  credencial certa também barrada), auditoria `??` = 0.
- Regressão completa após adaptar baterias para CSRF:
  `e2e_regressao.sh` 10/10 · F2-12 14/14 · F2-13 14/14 · F2-14 15/15 ·
  F2-15 15/15 · F2-16 17/17 · F2-17 17/17 (contato/orçamento/login ajustados
  para incluir o token — sem mudar o que o produto faz).
- Total: **117 asserts, 0 falha**.
