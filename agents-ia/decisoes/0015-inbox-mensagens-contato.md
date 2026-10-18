# ADR 0015 — Inbox de Mensagens de Contato (`communication/contact_message`, F2 Tarefa 15)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Oitavo CRUD no molde-ouro — e o **primeiro assimétrico**:
  `rms_contact_message` é preenchida pelo formulário "Let's have a chat?"
  do front (Fase 3); o admin só opera o que chegou. Tabela 1:1 com o
  Dicionário-de-Dados §9 já instalada pelo wizard — zero DDL novo,
  8º módulo consecutivo.

## Decisões

1. **Rotas assimétricas**: `/communication/contact_message` (grade com
   abas Todas/Novo/Lido/Respondido + contadores) · `.read|N` (detalhe
   read-only) · `.status|N` (POST) · `.delete|N` (POST). **Sem `.add` e
   sem `.edit`** — o admin não cria nem reescreve mensagem de visitante.
   A bateria tem check negativo explícito: a rota `.add` não pode existir
   em lugar nenhum da grade.
2. **Semântica exata do Dicionário §9** (status `0 novo / 1 lido / 2 respondido`):
   - abrir uma mensagem nova no `.read` **marca-a como lida automaticamente**
     (uma única vez; reload não repete efeito — suposição declarada, é o
     comportamento natural de inbox);
   - **respondido é manual**: a resposta real sai por e-mail via botão
     `mailto:` pré-preenchido (assunto `Re: ...` + corpo citado) — não há
     engine de envio no escopo desta fase;
   - voltar a "novo" é permitido (caso de uso: fila de atendimento).
3. **Whitelist única no model** (`Contact_message::STATUSES` = [0,1,2]):
   controller e model compartilham da mesma constante — POST adulterado
   (`status=9`, vazio, texto) não muta nada e gera flash de erro
   (defesa em profundidade: controller rejeita E o model rejeitaria).
4. **Filtro de grade por status** com abas + contadores (`GROUP BY status`);
   parâmetro fora da whitelist cai em "todas" em vez de quebrar a SQL.
5. **IP exibido no detalhe** (auditoria/anti-spam — dado coletado pelo
   front; não editável).
6. **GET jamais muta** em `.status`/`.delete` (redirect permission) —
   invariante do molde-ouro. **10ª rota nativa no seed do wizard**;
   `upgrade_f2_15_contact_message_grant.sql` idempotente (DIV 27 chars).
7. **Convenção de classe**: `communication/contact_message` →
   `Contact_message` (o `Action::getClass()` usa `ucfirst` preservando o
   underscore, mesmo padrão do `Step_1` do wizard). Registrado aqui porque
   é novidade de router explorada nesta tarefa.
8. **Auditoria permanente herdada da F2-14**: keys usadas pelo controller
   × presentes nos 3 lang files (35/35, paridade en/pt/es).

## Deltas não-objetivos

- **Paginação server-side** da caixa (hoje grade integral; criar quando
  houver volume real de contato).
- **Envio de resposta pelo admin** (template de e-mail + mailer) —
  depende da engine de e-mail (roadmap) e do webhook `contact_add`.
- **Anti-spam por IP** (blocklist/rate-limit) — decisão da Fase de
  publicação/hardening; o dado já está coletado.
- **Ações em lote** (marcar várias como lidas) — UI de operação futura.
- **Exclusão em massa automática por idade** — política de retenção a
  definir com o produto (LGPD: dados de contato têm validade).

## Validação

- `dev/tests/e2e/e2e_f215.sh` — **15 checks, 0 falha**: seed-grant ·
  login · lista vazia · card dashboard · fixtures via DB (admin não cria)
  + ausência garantida de `.add` · filtro vazio honesto · **auto-marcação
  lido ao abrir (0→1 no DB)** · contagem por abas · POST status →
  respondido + flash · **status adulterado 9 rejeitado sem mutação** ·
  GET em .status/.delete não muta · delete POST + flash · pt/es ·
  ACL deny → migração ×2 → 200 · `.read|999` → error/not_found.
- Regressão + f212 + f213 + f214 (mesmo sandbox): **10 + 14 + 14 + 15**,
  regressão agora valida 10 grants e 8 cards — total **68 asserts verdes**.
- PHPStan: suíte oficial `[OK] No errors`; análise level-5 ad hoc dos
  arquivos novos mostra apenas a mesma classe de ruído "magic property"
  já presente no tool/tool aprovado (dívida conhecida do bootstrap).
- Sandbox sem nova config oficial do repo (repo privado inacessível sem
  credencial nesta sessão — mirroring + histórico zip garantem base).

## Entrega

- `reamurcms-fase2-tarefa15.zip` — módulo completo (model/controller/
  langs×3/views×2/guards) + dashboard ×4 + seed install.php + migração +
  `dev/tests/e2e/e2e_f215.sh` e `e2e_regressao.sh` atualizada.
