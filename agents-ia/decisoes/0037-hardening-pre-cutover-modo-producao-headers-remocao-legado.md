# ADR 0037 — Hardening pré-cutover: modo produção de erros, headers extras e remoção do legado morto

- **Status:** Aceita — 2026-10-21 (F6 Tarefa 01)
- **Contexto:** Plano da Análise §Fase 6: *hardening (HTTPS, headers,
  `error_display=false`), composer validate verde no CI, redirects e
  cutover*. O item CI já estava sanado (`working-directory` do
  `.github/workflows/php.yml` + jobs validate/lint/phpstan/link-check —
  item do plano original coberto; hoje confirmado com
  `composer validate --strict` ✓ e `phpstan` ✓ **0 errors** sobre o nível
  vigente). Esta tarefa cobre o hardening de runtime + housekeeping das
  dívidas declaradas de legado (ADRs 0033 e 0036).
- **Leitura real (prova, HEAD `de4f755`):**
  `$_['error_display'] = true` no config default com comentário "*change
  to false on a live site*" armado para explodir dados sensíveis em
  produção se esquecido; headers já tinham a base F2-18 (nosniff,
  X-Frame-Options, Referrer-Policy) mas faltavam Permissions-Policy e
  CSP; e o legado morto seguia no repositório: `system/webhook/`
  (superseded ADR 0033), `system/library/openbay*`, **mais** dois blocos
  descobertos na varredura desta tarefa — `system/library/googleshopping*`
  e `system/library/squareup*` — todos com **zero consumidores** fora das
  próprias pastas (grep confirmado) e 132 entradas anestesiantes na
  baseline do phpstan escondendo isso.

- **Decisões:**

  1. **Default de produção é OFF; o override é explícito no config.php.**
     `$_['error_display'] = defined('REAMUR_ERROR_DISPLAY') ? (bool)REAMUR_ERROR_DISPLAY : false;`
     — instalações novas: o wizard semeia
     `define('REAMUR_ERROR_DISPLAY', true)` com comentário orientando a
     virar `false` em produção (ou REMOVER a constante, caindo no default
     seguro). Sites já existentes: sem a constante = comportamento seguro
     **por omissão**. O log em `storage/logs/error.log` é independente
     dessa flag e continua sempre ativo. *(Inversão do risco: antes,
     esquecer a linha em produção = vazamento; agora, esquecer =
     silêncio seguro.)*

  2. **Headers novos SEM quebrar jornadas** (block em
     `system/config/default.php`, apps podem divergir nos próprios configs):
     - `Permissions-Policy: geolocation=(), microphone=(), camera=(),
        payment=(), usb=()` — o CMS não usa nenhum desses sensores; payment
        nesta bateria **não é** a API Payments (é Payment Request API).
     - `Content-Security-Policy-Report-Only` com política generosa de
        transição (`https:` amplo, `'unsafe-inline'/'unsafe-eval'`) —
        observar por um ciclo ANTES de endurecer/virar enforce: hero 3D,
        fontes externas e scripts inline do tema atual precisariam de
        exceções curadas. Enforce = dívida F6 futura (registrada).

  3. **Legado morto removido de verdade (não só declarado):**
     - `dev/system/webhook/` — dispatcher Fase--0 de tabelas-fantasma
       (superseded ADR 0033; o transporte real é
       `Library\Webhook\Dispatcher`, intacto).
     - `dev/system/library/openbay.php` + `openbay/` (superseded ADR 0036).
     - `dev/system/library/googleshopping.php` + `googleshopping/` —
       **correção honesta**: o ADR 0036 deveria tê-los detectado; eram
       biblioteca órfã sem controller/rota, na MESMA situação do openbay
       (a conclusão do ADR 0036 se mantém — a decisão não era sobre
       implementação-binária, e sim sobre domínio; este addendum registra).
     - `dev/system/library/squareup.php` + `squareup/` — mesma família
       órfã (gateway legado da Fase 0; o trilho de pagamento real é o
       Mercado Pago dos ADRs 0034/0035).
     - `phpstan-baseline.neon`: removidas as **132 entradas** de ignore
       que mascaravam esses pacotes inteiros (par de path/docs).
     - Comentário histórico-órfão no controller admin de webhooks
       ajustado (apontava "ponte system/webhook").

  4. **Provas de não-regressão:** `composer validate --strict` ✓;
     `phpstan analyse` = **No errors**; bateria `tools/e2e/e2e_f601.sh`
     10/10; suíte completa = **24 baterias, 281 asserts, 0 fail em DUAS
     corridas completas** após correções de bateria descritas em §6.

  5. **Fora de escopo (F6-02/F6-03):** redirects 301 das URLs antigas
     (mapa Análise §9), CSP enforce, runbook de cutover/backup e os
     testes manuais de jornada do usuário — tarefas seguintes.

- **Ambiente (registro operacional):** a reconstrução periódica do
  sandbox tirou Apache/MariaDB/php do ar **duas** vezes nesta sessão.
  Criado `tools/e2e/rebuild_lab.sh` — receita idempotente que sobe o lab
  (apt, Apache :8090 com `AcceptPathInfo`+`a2enconf`, MariaDB + usuário
  reamur, composer install do storage, sync_live → live real). Também
  documenta a armadilha encontrada: rotas admin "bonitas" dependem do
  `.htaccess` (AllowOverride/reescrita) — sem `a2enconf` elas 404am.
- **Correções de bateria/ambiente de teste (não de produto):**
  1. `e2e_f305.sh` — o check "sem side-effects" varria `*feed*` no
     storage e colidia com nomes de **sessão** cujo hash aleatório pode
     conter "feed" (caso real: `sess_…feed38ea72f`). Filtro agora exclui
     `session/sess_`; a feature permanece integralmente coberta.
  2. **Flake f502→f503 diagnosticado à fundo (não era regressão):** as
     duas baterias trocam o MESMO path `/var/www/html/mpmock.php` por
     conteúdos diferentes; o **OPcache** do Apache do lab
     (`validate_timestamps=On`, `revalidate_freq=2`) servia o bytecode
     **velho** na janela ~2s — o mock antigo (só OAuth) respondia 400 ao
     `checkout/preferences`, o controller flashava `error_preference`
     (corretamente!) e não gravava payment. Assinatura que deduziu a
     causa: `mpmock.jsonl` logava as preferências, mas `prefs.jsonl`/
     `pref_*.json` (só escritos pelo mock NOVO) não existiam. Fix:
     OPcache desligado no lab (`99-lab-noopcache.ini`, receita
     incorporada ao `rebuild_lab.sh`) — lab e2e não precisa de cache de
     opcode e troca arquivos quentes o tempo todo. f502→f503 3× 10/10 e
     suíte 2× verde após a mudança.

- **Consequências:** nenhum segredo é declarado nesta tarefa; a
  instalação de PRODUÇÃO nova do wizard nasce com display_errors **ON**
  (escolha dev-friendly declarada — instrução de virar OFF está na linha
  ao lado do define e no runbook F6-03). CSP enforce pendente. Zips
  anteriores seguem aplicáveis (delta cirúrgico).

- **Bateria e evidências:** `tools/e2e/e2e_f601.sh` — 10 checks
  (wizard; ausência binária + referencial do legado; define semeado;
  default OFF sem define; headers novos + preservados; exceção genérica
  sem stack; jornadas contato/orçamento íntegras; admin intacto; webhook
  F5-01 entregue pós-remoção). Suíte: 24 baterias, 281 asserts, 0 fail
  (2 corridas completas).
