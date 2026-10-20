# ADR 0029 — Envio de e-mail ativo: factory, notificação do contato e teste no painel

- **Status:** Aceita — 2026-10-20 (F4 Tarefa 05)
- **Contexto:** A F4-01 (ADR 0024) entregou a seção **E-mail** de
  `setting/setting` que PERSISTE `mail_engine`, `mail_smtp_*` e
  `mail_reply_to` em `rms_setting`, e as pré-ações `startup/setting`
  (admin/catalog) já injetam essas chaves no Config da requisição. Mas o
  comentário do próprio controller declarava a renúncia: *"o consumo real
  chega quando a engine de envio for acoplada"*. Sem consumidor, `mail_*`
  era configuração inerte: o formulário público de contato (F2-17) só
  gravava na caixa de entrada (`rms_contact_message`) e o dono do site
  jamais ficava sabendo. `system/library/mail/` já tinha os três adapters
  (`Mail`, `Smtp`, `Capture`) com API comum (`__construct(array &$option)`,
  `send(?array): bool`) — sem ninguém para montá-los.
- **Leitura real (antes de escrever — prova do estado do HEAD `45a428b`):**
  - `admin/controller/setting/setting.php` linha 13: "Sem fakear: mail_*
    persiste hoje; o **consumo real** chega quando a engine for acoplada".
  - `catalog/controller/common/contact.php`: pós-validação, apenas
    `addContactMessage()` — zero chamadas a `Library\Mail` em TODO o código
    (grep `Reamur\System\Library\Mail` fora da própria pasta: 0 hits).
  - `system/config/default.php` já define `mail_engine`, `mail_from`,
    `mail_sender`, `mail_reply_to`, `mail_smtp_*` — o esqueleto das chaves
    pré-existia; as injeções via startup cobrem `mail_engine/smtp_*/reply_to`.
  - `setting/setting.clearCache` é o precedente exato de ação-POST separada
    na mesma tela (camelCase rota+file — casa já documenta em ADR 0018).
  - `system/engine/autoloader.php`: `toSnake` mapeia
    `Reamur\System\Library\Mail\Factory` → `library/mail/factory.php` —
    classe nova autoloadável sem tocar o autoloader.
- **Decisões:**

  1. **`Mail\Factory` estática, não serviço de Registry.** A regra 01 §4.3
     pede serviços novos como classe em `library/` + registro — mas os
     adapters já são classes de `library/` consumidos pontualmente (mesmo
     padrão do `RateLimit`, instanciado com `new` no controller). Uma
     factory estática `create(Config, array $data)` evita acoplar o
     Registry a um objeto efêmero por-envio e não muda assinatura pública
     nenhuma (aditivo puro). Ela lê o Config **efetivo** (startup/setting já
     injetou o banco) e despacha `mail|smtp|capture`, com fallback `mail`
     para valores fora da whitelist.

  2. **Identidade de remetente SEM tela nova.** `mail_from` e
     `mail_sender` existem no `default.php` mas ninguém os edita (fora da
     allowlist do model). Em vez de crescer a tela (escopo), a factory
     aplica fallback em runtime: `from = mail_from ?: config_contact_email`
     (F4-03, endereço vivo) e `sender = mail_sender ?: config_name`. Se um
     dia houver tela dedicada, basta gravar as duas chaves — injeção e
     factory já honram. **Recusado**: adicionar os dois campos à seção
     E-mail agora — duplicaria o que `site_info` já governa.

  3. **Notificação do contato com degradação silenciosa (renúncia herdada
     da ADR 0023).** Após o INSERT em `rms_contact_message`,
     `common/contact` dispara `notifyOwner()` para `config_contact_email`
     (o próprio visitante preencheu e-mail válido via régua do F2-17 e a
     mensagem JÁ está persistida — a fila de entrada não se perde). Falha
     de envio (SMTP fora, mail() indisponível no Windows/XAMPP) é
     registrada em `log->write()` e **jamais** muda o flash de sucesso.
     Reply-To = `mail_reply_to` fixo quando configurado; senão o e-mail do
     visitante (responder cai direto nele — caso de uso real).

  4. **`capture` vira engine selecionável no painel (ferramenta de
     dev/CI).** O adapter `Capture` existia órfão desde a fundação. Na
     whitelist passa `{mail, smtp, capture}`; grava o payload JSON em
     `DIR_STORAGE/logs/mail_capture.json` (DIR_STORAGE é único
     admin/catalog — mesmo arquivo). Justificativa: XAMPP/Windows sem MTA
     torna `mail()` falso-negativo no teste de envio, e CI/bateria e2e não
     pode depender de rede — o capture dá **determinismo** ao check
     ponta-a-ponta (a prova vira assert no JSON capturado). Risco de
     alguém deixar `capture` em produção: aceito — o envio real falharia
     silenciosamente mesmo com `mail()` quebrado; documentado no help da
     tela ("dev/CI").

  5. **`setting/setting.testMail` espelha `clearCache`.** POST dedicado,
     `modify` em `setting/setting`, destinatário = e-mail do usuário logado
     (`User::getEmail()` — prova de identidade ponta-a-ponta), flash
     sucesso/erro + redirect à tela. Sem CSRF extra — padrão da casa
     (POST + ACL + sessão admin, igual a clearCache e profile F4-04).
     Sem rota/grant novo → **sem upgrade SQL** (bootstrap, ADR 0005 §Regra).

  6. **Dedupe das allowlists startup/setting (defeito do repair).** O
     refator manual `61057c1`+`3cfe860` mais a restauração F4-repair
     deixaram os blocos F4-02/F4-03 **duplicados** no array `ALLOWED` das
     duas pré-ações. Inócuo em runtime (mesma chave duas vezes), corrigido
     por remoção das entradas repetidas — comportamento inalterado.

- **Alternativas consideradas:**
  1. **Serviço `mail` no Registry** (`framework.php`) — recusado: objeto
     efêmero por-mensagem; nenhum consumidor prévio exige compatibilidade;
     adicionar boot-cost a toda requisição para 2 disparos/evento.
  2. **Tela dedicada `setting/mail`** (padrão F4-03) — recusado pelo
     usuário (ask_user F4-05): os campos já vivem em `setting/setting`
     desde F4-01; mover = desfazer trabalho entregue.
  3. **Notificação como webhook** (`system/webhook/` existe para pedidos) —
     recusado: webhooks são integração async para parceiros; notificar o
     dono do site é função interna do CMS; escopo da F5 é MercadoPago.
  4. **`mail_from`/`mail_sender` na tela** — ver §2.

- **DoD verificado:** bateria `e2e_f405.sh` com 10 checks: wizard + grants,
  tela GET, save `engine=capture` persiste em rms_setting, repopulação do
  select, `testMail` com flash sucesso + **JSON capturado com destinatário
  = e-mail do admin**, POST público de contato válido grava inbox **e**
  notifica (subject `New contact from the site:` no capture), honeypot não
  grava nem notifica, engine smtp inválida → `testMail` flash de erro sem
  quebrar tela (prova do try/catch), muralha anônima, i18n ×3.
- **Registro de suposições (anti-alucinação):** `mail_parameter` presente
  no `default.php` repassa ao adapter `Mail` sem exposição de tela (avanço
  futuro — SendGrid etc.); `mail_verp=false` repassado ao `Smtp` (flag já
  suportada pelo adapter); segfault de `Reply-To=from` quando ambos vazios
  é comportamento nativo do adapter `Mail` (header com fallback próprio) —
  não reescrito aqui.
