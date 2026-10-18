# ADR 0016 — CRUD de Webhooks + Log de Entregas (`setting/webhook`, F2 Tarefa 16)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Nono e **último módulo do backlog do schema** (F2 CRUDs).
  Par `rms_webhook` + `rms_webhook_log` do Dicionário §10, já instalado
  pelo wizard — zero DDL pelo 9º módulo consecutivo. Escolha do grupo
  `setting/`: o Dicionário §2 oferecia `marketing/` ou `setting/` — webhook
  é configuração técnica de integração, não peça de marketing.

## Decisões

1. **Gramática plena + 2 rotas de leitura do log** (mesmo controller, mesma
   rota-base de ACL): `/setting/webhook` · `.add` · `.edit|N` · `.delete|N`
   (POST) · **`.logs|N`** (N=0 ⇒ todas as entregas, teto 200) · **`.log|N`**
   (payload read-only, JSON pretty-print quando decodificável).
2. **Régua do Dicionário §10 implementada à risca**:
   - `trigger` em sintaxe wildcard (`order_add`, `order_*`, `*_add`) —
     regex `^\*{0,1}[a-z0-9_]{1,63}(\.\*)?$`, lowercase normalizado; form
     tem `<datalist>` com os 9 eventos conhecidos (engine in-memory + ponte
     `system/webhook/` da Fase 5);
   - **URL somente `https://`** (payload HMAC sensível em trânsito) — mesmo
     no edit adulterado, `http://` é rejeitado sem mutação;
   - `secret` 16–64 chars, com botão "Gerar" (32 hex via
     `crypto.getRandomValues`, JS puro) e **mascaramento na grade**
     (`••••••••` + últimos 4 — o secret integral jamais aparece no HTML);
   - `UNIQUE(trigger,url)` do schema checado ANTES do SQL em add e edit
     (erro amigável, nunca 1062 cru);
   - `status` coercido a 0/1 no payload (POST adulterado com `status=9` vira
     0 ou 1 — testado), inativo pausa entrega mantendo histórico.
3. **Delete em cascata manual**: endpoint + TODAS as entregas dele (logs de
   outros endpoints intactos — testado). GET jamais muta.
4. **11ª rota nativa no seed do wizard**; migração
   `upgrade_f2_16_webhook_grant.sql` idempotente (DIV 15 chars); 9º card do
   dashboard.

## Aprendizado permanente (incidente desta sessão — 2 lições)

5. **Avisos é exceção no nosso bootstrap**: `set_error_handler` do
   framework converte **QUALQUER warning em `\ErrorException`**
   (`framework.php` §5), e o `Router::dispatch` cai para `error/not_found`
   (404) sem render de erro específico. Um `Undefined array key` escondido
   em ternário de repovo (`(int)$post['status']` sem `?? 0` quando
   `$post` array não-vazio) derrubou TODOS os POSTs do módulo com 404 —
   marcador silencioso era o próprio texto do warning no `error.log`.
   **Disciplina nova (checklist F3+)**: todo acesso a índice de
   `$this->request->post`/`get` dentro de ternários/fallbacks passa por
   `?? null|0|''` — sem exceção; a bateria deve incluir POST sem o campo
   (o caso "checkbox ausente" que pegou isso aqui).
6. **Instrumentação de debug**: escrever em `/tmp/...` falha (mont/uid do
   sandbox); debug de fluxo usa **somente** `$this->log->write('MARK ...')`
   — o serviço grava onde o PHP tem garantia. Extra: `die('MARK')` no topo
   do método prova em 1 request se a rota chega. Baterias com grep em texto
   renderizado lembram: **Twig escapa aspas** — grep por literal JSON é
   `&quot;`, não `"` (foi o check 12 daqui).

## Deltas não-objetivos

- **Dispatcher HTTP real dos eventos** (delivery + HMAC + retry) — a ponte
  `system/webhook/` + engine in-memory já existem e são tratadas na Fase 5
  (hardening robustez); aqui entra apenas o admin que as alimenta.
- **Retry/fila com backoff** e idempotência por `delivery` — decisão da
  Fase 5 junto do dispatcher.
- **Rotação de secret** com janela de coexistência — operação futura.
- **Filtro por `http_code`/data no log** — quando houver volume; hoje teto
  200 mais-recentes.
- **Limpeza automática do log por idade** (LGPD/retenção) — política a
  definir na publicação.

## Validação

- `dev/tests/e2e/e2e_f216.sh` — **17 checks, 0 falha**: seed-grant 11 rotas ·
  login · lista vazia · card dashboard · form (datalist + gerador secret +
  hint https) · validações §10 como bloco (trigger inválido, http://,
  secret curto — nada persiste) · create + **secret mascarado na grade**
  (integral ausente do HTML) · edit com checkbox ausente (status→0) ·
  **duplicata trigger+url rejeitada** · fixtures 3 entregas · filtros
  logs|1/2/0 (2/1/3 linhas) + badge HTTP 500 · detalhe payload
  (JSON pretty + campo escapado Twig) · delete cascata (só as entregas
  dele) · GET não-muta em delete · **tamper: status=9 coercido + http://
  rejeitado sem mutação** · pt/es + reset en · ACL deny → migração ×2 → 200.
- Regressão + f212..f215 (mesmo sandbox): **10+14+14+15+15** — total
  **85 asserts verdes** (regressão: 11 grants, 9 cards).
- PHPStan: suíte oficial `[OK] No errors` (nível 2, escopo engine/library/
  helper — padrão vigente das tarefas F2).

## Entrega

- `reamurcms-fase2-tarefa16.zip` — módulo completo (model/controller/
  langs×3/views×4/guards) + dashboard ×4 + seed install.php + migração +
  `dev/tests/e2e/e2e_f216.sh` e `e2e_regressao.sh` atualizada.

## Marco

**Backlog de CRUDs do schema esgotado** (9 módulos, zero DDL novo em todos).
Próximos passos do roadmap: publicação do site (catálogo/front) →
hardening → Fase 3. O dispatcher de webhooks entra na Fase 5 (robustez).
