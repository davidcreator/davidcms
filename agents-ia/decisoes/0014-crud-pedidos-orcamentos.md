# ADR 0014 — CRUD de Pedidos/Orçamentos (`sale/order`, F2 Tarefa 14)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Sétimo CRUD no molde-ouro, primeiro do grupo `sale/` e o
  mais complexo até aqui: 4 tabelas relacionais (order + order_product +
  order_history + order_status, esta última semeada pelo wizard com 6
  status em en-US — Dicionário-de-Dados §Pedidos/Orçamentos). Zero DDL novo,
  7º módulo consecutivo.

## Decisões

1. **Rota `sale/order`**, gramática-ouro (.add/.edit|N/.delete|N POST,
   ACL base-route). **Sem `.upload`, sem SEO, sem multi-idioma no form** —
   dados de ordem são operacionais (cliente/e-mail/itens), não editoriais.
2. **Semânticas do Dicionário implementadas à risca**:
   - `customer_id = 0` fixo (orçamento de visitante — admin não edita
     customers nesta fase; suposição declarada);
   - **cada mudança de status vira registro**: create grava linha inicial
     em `rms_order_history`; edit grava nova linha **somente quando o
     status muda** (detectado comparando com a linha existente); edição
     sem mudança de status não inflama o histórico;
   - `total = 0.0000` é estado legítimo (orçamento sem preço);
   - `date_modified = NOW()` em toda edição.
3. **Contabilidade anti-adulteração (servidor manda)**:
   - linha: `total = qty × price` (qty ≥ 1, price ≥ 0 — decimal saneado de
     "1.234,56"/texto/negativo → 0.0000 sem warnings);
   - pedido: `total = Σ` das linhas (DECIMAL(15,4) formatado server-side);
   - linha totalmente vazia descartada; linha com preço/qtd mas SEM nome
     gera erro de validação; POST que tente mandar `total` é ignorado.
4. **Status**: validado contra `rms_order_status` existente (id "99"
   adulterado rejeitado); opções com coalesce idioma ativo → en-US
   (traduções pt/es dos status virão com a ativação dos idiomas — seeds
   hoje são en-only, decisão do schema v1.0).
5. **Itens dinâmicos no form** (linhas nome/qtd/preço + add/remove, JS puro
   no padrão da galeria do project): sem componente novo — HTML montado no
   cliente; servidor repovoa a partir do POST em erro.
6. **Delete** POST+confirm: cascata manual nas 3 tabelas (product →
   history → order); GET jamais muta; não há arquivo físico.
7. **Seed/migração padrão**: seedRoutes do wizard += `sale/order` (9 rotas
   nativas); `upgrade_f2_14_sale_order_grant.sql` idempotente.
8. **Nova auditoria permanente**: "keys usadas pelo controller" — grep
   estático de `language->get('...')` no controller × keys presentes em
   cada lang file (a aprendi com o bug desta tarefa: as 3 keys
   `text_success_*` ficaram fora do en file e o flash renderizou a key
   crua; erro visual que só a bateria flagram). Entra na checklist dos
   próximos módulos.
9. **Lição de harness**: `?language=` persiste na sessão do admin — checks
   com asserts de texto precisam forçar `&language=en-us` após os probes
   de idioma (corrigido no f214).

## Deltas não-objetivos

- Paginação/filtros da grade e tela "view" de pedido — quando houver
  volume; listagem atual é integral como nos demais CRUDs.
- `customer`/`customer_group` admin e vínculo real `customer_id>0` —
  módulo próprio (Fase futura; hoje o campo fica 0).
- Envio de e-mail ao mudar status (webhooks/notificações — roadmap
  separado; `rms_webhook` ainda sem CRUD).
- Edição do catálogo de **status** (CRUD de order_status) — hoje é seed
  fixo; tornar admin editável será decisão própria.
- Histórico é read-only no form (sem comentário por transição — o campo
  comment em history fica vazio; UI de comentário fica para a tela de
  operação quando existir).

## Validação

- `dev/tests/e2e/e2e_f214.sh` — **15 checks, 0 falha**: seed-grant +
  6 status semeados · login · lista vazia · card dashboard · form
  operacional (6 opções de status, dinâmica de itens, sem upload/SEO) ·
  validações (nome/e-mail/item-sem-nome) · **create: 2 itens + linha vazia
  descartada, totais server-side (Σ=3031.0000, linha=3001.0000),
  histórico inicial** · lista com flash e total formatado · **edição com
  troca de status (+1 histórico, recálculo)** · edição sem troca (histórico
  intacto, lastname salvo) · delete cascata 3 tabelas (GET-nega) · pt/es ·
  ACL deny → migração ×2 → 200 · status 99 rejeitado · preço adulterado
  saneado (0.0000, qtd preservada).
- Regressão + f212 + f213 (mesmo sandbox): **10 + 14 + 14** — total
  **53 asserts verdes** (regressão agora valida 9 grants e 7 cards).
- PHPStan level-5 limpo; clone+diff contra `@70d5cae` antes de escrever
  (repo ≡ espelho); sandbox resetou 1× nesta sessão — rebuild via
  `dev/tests/e2e/setup_sandbox.sh` em ~30s.

## Entrega

- `reamurcms-fase2-tarefa14.zip` — módulo completo (model/controller/
  langs×3/views×2) + dashboard/seed/migração + guards sale/ + bateria
  `e2e_f214.sh` e `e2e_regressao.sh` atualizada em `dev/tests/e2e/`.
