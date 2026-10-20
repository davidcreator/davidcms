# ADR 0033 — Webhooks de pedido: HMAC, retries síncronos e isolamento do fluxo

- **Status:** Aceita — 2026-10-20 (F5 Tarefa 01)
- **Contexto:** Fase 5 prevê "Webhooks de pedido (`order_add`, `order_edit`)
  com assinatura HMAC + retries" (Análise §Fase 5). A infra de cadastro já
  era real desde F2-16/ADR 0016: `rms_webhook` (endpoint + secret + trigger
  wildcard) e `rms_webhook_log` (delivery/payload/http_code/duration/
  attempt) + CRUD admin `setting/webhook` com logs read-only — mas **nada
  disparava**: o único "dispatcher" era o esqueleto da Fase 0 em
  `system/webhook/`, que consulta tabelas `webhook_client` e
  `webhook_request_history` **inexistentes no schema** (código morto que
  nunca rodou).
- **Leitura real (prova, HEAD `2d79602`):** `grep webhook` em
  `catalog/checkout/quote.php` e `admin/sale/order.php` = zero ocorrências;
  `system/engine/webhook.php` é um registry in-memory sem registrantes;
  `docs/Webhooks.md` descrevia o formato antigo das tabelas-fantasma.

- **Decisões:**

  1. **Transporte puro na Library, SQL só nos Models.** NOVO
     `system/library/webhook/dispatcher.php` — monta envelope JSON
     canônico, assina e faz HTTP; não toca o banco e **nunca lança
     exceção**. Novos métodos `getHooksForEvent`/`addLog` em
     `catalog/model/setting/webhook.php` (novo) e
     `admin/model/setting/webhook.php` (editado, espelhado). Match de
     trigger wildcard (`*`→`%`, `?`→`_`) resolvido em SQL com `LIKE`
     invertido — sem varrer endpoints em PHP.

  2. **Envelope + assinatura HMAC por endpoint:**
     `{event, delivery_id, timestamp, data{...}}`; headers
     `X-Reamur-Event`, `X-Reamur-Delivery` (uuid v4 estável pelos 3
     retries do mesmo evento×endpoint — o receptor deduplica),
     `X-Reamur-Signature: sha256=<hex hash_hmac(corpo, secret)>`.
     Verificação no receptor = recompute do HMAC do corpo cru com o
     secret cadastrado. Payload carrega PII (nome/email/telefone/itens) —
     coberto pelo CRUD que só aceita **https://** (ADR 0016).

  3. **Retries SÍNCRONOS com backoff 0s/2s/4s (3 tentativas máx.),
     sucesso = 2xx.** Sem camada cron na casa, uma fila ficaria sem
     processador — retry inline com teto total de ~21s/endpoint (3×(3s
     connect+5s) só no pior caso de sucessivas falhas de rede) é o ponto
     honesto entre simplicidade e robustez. Cada tentativa gera 1 linha
     em `rms_webhook_log` (`attempt` crescente, mesmo `delivery`) — os
     logs read-only do F2-16 passam a contar a história completa.
     **Alternativa avaliada e adiada:** `rms_webhook_queue` + processador
     (registrada como melhoria futura se o volume de endpoints crescer).

  4. **Isolamento do fluxo de negócio (guarda-chuva).** Os pontos de
     disparo (`catalog/checkout/quote` após `addQuote`; `admin/sale/order`
     após `editOrder`) envolvem o despacho em `try/catch (\Throwable)`:
     endpoint fora do ar, tabela ausente ou timeout jamais impedem o
     orçamento/pedido de existir — falha vira linha no `error_log`.

  5. **Eventos:** `order_add` (quote público criado), `order_edit`
     (edição admin) e `order_history_edit` (só quando o STATUS muda na
     edição — o F2-14 já grava histórico nessa hora; disparar em toda
     edição geraria ruído). Mesmo schema de payload nos dois lados.

  6. **Esqueleto `system/webhook/` declarado SUPERSEDED, não removido.**
     Código upstream da Fase 0; não executa (tabelas inexistentes). Não
     removo trabalho existente — o ADR marca a substituição e um
     REMOVAL fica para tarefa de higiene futura, com janela de busca
     por consumidores.

  7. **SSRF-safety mínima no dispatcher:** `FOLLOWLOCATION=false` (3xx não
     é sucesso nem é seguido), TLS verificado, timeouts curtos fixos e
     sem config por endpoint (mistério zero). O alvo continua sendo
     escolha do admin (CRUD), não do POST público.

- **DoD verificado (bateria `e2e_f501.sh`, 10 checks):** orçamento público
  dispara `order_add` → log `http_code=200, attempt=1`; mock local
  recebeu headers do protocolo e a bateria **recomputa o HMAC** do corpo
  com o secret e confirma igualdade; `delivery_id` igual no body e no
  header; edição admin dispara `order_edit` (+`order_history_edit` quando
  o status muda); endpoint apontado para porta fechada → `attempt=3`,
  `http_code=0` e **o orçamento ainda é criado normalmente** (isolamento);
  trigger wildcard `order_*` casa ambos os eventos.
- **Bateria e mock HTTP:** o CRUD exige HTTPS (correto em produção); a
  bateria insere os endpoints de teste **direto por SQL** no banco de
  testes e aponta para um mock `http://127.0.0.1:8090/hookmock.php`
  gravado pela própria bateria FORA do repo (não é código entregue).
- **Registro de suposições (anti-alucinação):** o schema de payload com
  PII pressupõe receptor sob controle do lojista (CRM próprio); secret é
  obrigatório por design do CRUD (16–64) então toda entrega sai assinada.
