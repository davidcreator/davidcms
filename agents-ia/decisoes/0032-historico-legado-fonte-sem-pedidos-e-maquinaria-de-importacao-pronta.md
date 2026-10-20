# ADR 0032 — Histórico legado: fonte sem pedidos, spam não migra, maquinaria pronta

- **Status:** Aceita — 2026-10-20 (F4 Tarefa 08, última da Fase 4)
- **Contexto:** O documento de fases prevê na Fase 4 "Gestão de
  pedidos/orçamentos + **histórico migrado**" (Análise §Fase 4 e §202-203) e
  em §93 marca "Pedidos/orçamentos de clientes | histórico a preservar". A
  gestão em si já existe desde F2-14 (`admin/sale/order` + formulário
  público de orçamento F2-17/F2-18). Faltava migrar o histórico do CMS
  legado (davidalmeida.xyz, banco `u163008030_almeida`).
- **Leitura real (dump phpMyAdmin de 20/10/2026, analisado linha a linha):**
  - **Não existe tabela de pedidos/orçamentos** no banco legado. As 18
    tabelas exportadas são: contato (`contact_messages` + eventos +
    templates), ferramentas (`external_resources` — já migradas na F3 como
    `rms_tool`), cliques-analytics, plataforma de aulas/alunos
    (`course_*`, `class_*`, `student_*` — outro produto, fora de escopo),
    `portfolio_items` (vazia) e tabelas internas de auditoria/settings.
  - A única candidata a "histórico de cliente" é `contact_messages`:
    **9 linhas, 100% spam** — bots de formulário (nomes gerados, links
    maliciosos, 3 duplicadas imediatas). Nenhum contato comercial real.
  - Constatação apresentada ao usuário, que **confirmou: não há histórico
    a preservar**.

- **Decisões:**

  1. **Spam não vira pedido.** Migrar as 9 linhas poluiria `rms_order`
     com lixo sem valor comercial e falsificaria métricas de demanda.
     Renúncia registrada — se algum dia se quiser auditoria de spam,
     ela vive na staging, não no cadastro de pedidos.

  2. **Maquinaria de importação entregue pronta** (não adiada "para quando
     houver"): quando o cliente tiver um dump real de pedidos/orçamentos
     (ou quiser migrar contatos legítimos antigos), roda-se
     `install/sql/upgrade_f4_08_import_historico_contatos.sql`:
     - staging `rms_import_contact` (espelho mínimo da `contact_messages`
       legada, com bloco demarcado para colar os INSERTs do dump);
     - `INSERT ... SELECT` mapeado para `rms_order` **idempotente**
       (guarda `NOT EXISTS` por email + data + comentário; staging com
       `INSERT IGNORE` por legacy_id) — executável N vezes;
     - 1 linha de auditoria por pedido em `rms_order_history`
       ("Migrado do CMS legado (F4-08/ADR 0032)"), igualmente guardada.

  3. **Mapeamento semântico do status legado** (`contact_messages.status`
     é varchar livre): `new` → 1 Received; `replied` → 3 Quote Sent;
     `archived`/`closed` → 5 Completed; **qualquer outro valor não migra**
     (filtro `IN`, não CASE-else) — decisão explícita contra "case else =
     spam→Canceled", pois status inesperado deve ser investigado, não
     silenciosamente classificado.

  4. **Campos derivados, honestos:** nome partido em firstname (1ª palavra)
     + lastname (restante; `'-'` quando há uma palavra só); assunto+texto
     em `comment` prefixado "Assunto: "; `total=0.0000` (orçamento sem
     preço, Dicionário §6); `customer_id=0` (visitante); **sem linhas em
     `rms_order_product`** — a fonte não tem itens; datas herdadas
     (`created_at`→date_added, `updated_at`→date_modified), IP preservado
     (truncado a 40).

  5. **PII não entra no repositório.** O SQL entregue contém apenas
     estrutura + regras; os INSERTs de dados reais são colados pelo
     usuário localmente (XAMPP) no bloco demarcado e jamais comitados.
     A bateria e2e valida a máquina com **dados sintéticos**.

- **Alternativas consideradas:**
  1. **Migrar as 9 mensagens spam como pedidos "Cancelados"** — recusada
     (§1); o usuário escolheu explicitamente não preservar;
  2. **Importador genérico via painel (upload CSV)** — recusada: escopo
     desproporcional para um evento único; SQL idempotente é suficiente e
     auditável;
  3. **Fechar F4-08 só com o ADR, sem script** — recusada: deixaria a
     promessa "script de importação" (Análise §203) sem artefato.

- **DoD verificado:** bateria `e2e_f408.sh` 10 checks — wizard + upgrades;
  staging existe; 2 contatos sintéticos legítimos migram (firstname/
  lastname, "Assunto: "+corpo, status mapeado, IP, datas); 1 linha com
  status `spam` **não migra**; re-execução não duplica (staging nem
  pedidos); pedidos migrados aparecem na lista `admin/sale/order`;
  histórico de migração criado uma única vez.
- **Registro de suposições (anti-alucinação):** assumi que um eventual
  dump real futuro terá formato compatível com `contact_messages` (é o
  único formato de histórico observado no legado); formatos novos exigirão
  ajuste do mapeamento no próprio script. IDs de status (1/3/5) vêm da
  seed do wizard (schema.sql §383-389, en-US) — `getInitialStatusId` do
  catálogo não é usado aqui porque o mapeamento é semântico, não
  posicional.
