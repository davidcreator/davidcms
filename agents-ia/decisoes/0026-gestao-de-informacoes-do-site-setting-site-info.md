# ADR 0026 — Gestão de informações do site (setting/site_info)

- **Status:** Aceita — 2026-10-19 (F4 Tarefa 03)
- **Contexto:** Dados institucionais do site público (contato, endereço, rodapé,
  redes sociais, link do WhatsApp) nasceram **hardcoded** em
  `catalog/language/{en-us,pt-br,es-eu}/common/{home,footer}.php` — ainda do
  pipeline de divisão full-print → one-page (F1/F2). O mantenedor muda endereço
  ou WhatsApp alterando git, deployando manualmente — sem trilha de auditoria
  de quem mexeu. A decisão-árvore da Fase 4 (F4-01 operacional / F4-02 idiomas /
  F4-03 institucional) já deixa essa separação explícita.
- **Decisões:**

  1. **Tela dedicada `setting/site_info`** (decisão pedida ao mantenedor na
     Tarefa F4-03 — recusada a opção de virar um fieldset em `setting/setting`).
     Operacional (mail/cache/name/meta) e institutional (contato/rodapé/social)
     são conceitos distintos e ligam/desligam isoladamente na ACL.

  2. **Campos textuais `por idioma`** (endereço e copyright; decisão pedida ao
     mantenedor): `config_contact_address_{code}` e `config_copyright_{code}`².
     Contato e redes sociais são **globais** (`config_contact_email`,
     `config_contact_whatsapp_text`, `config_contact_whatsapp_href` — número
     puro para `wa.me/`, antes hardcoded em `partial/contact.twig:10` —
     e `config_social_{network}_href` para as 6 redes).

  3. **Contrato de degradação silenciosa ADR 0023 aprimorado**: a renderização
     do catálogo (home partial contact + footer copyright/socials) lê de
     `rms_setting code='config'` **somente se a chave EXISTIR**; chave ausente
     cai para o lang file — instalações sem o upgrade F4-03 seguem 100%
     funcionais. (Distinguir "ausente" de "presente vazio" — que é a operação
     semântica "remover do site" — exigiu ler com `array_key_exists` do mapa
     `getSettings()` em vez do `Config->get`.)

  4. **`%s` no copyright permanece como contrato**: mantenedor não perde o ano
     corrente dinâmico — validação da tela exige o placeholder.

  5. **Allowlist dupla/tripla NUNCA esquecida (lição ADR 0025)**: novas chaves
     adicionadas em `admin/model/setting/setting.php::editSettings` **e**
     `admin/controller/startup/setting.php::ALLOWED` **e**
     `catalog/controller/startup/setting.php::ALLOWED` (esta última injeta no
     catálogo o mapa inteiro — idiomas valem multi-chave porque a injeção é
     por key-prefix de nome completo). Sem isso, o save grava mas o runtime
     NÃO lê — bug autêntico que já capturamos antes.

  6. **Tela expõe campos SÓ para idiomas status=1** (mesma governança F4-02):
     desativar um idioma esconde o seu form de endereço/copyright, o registro
     permanece no DB para reativação futura.

  7. **Dashboard ganha módulo "Site information"** (`text_manage_site_info`)
     gateado por `hasPermission('access', 'setting/site_info')` ×3 langs.

  8. **Upgrade idempotente no padrão da casa**: grants JSON access+modify +
     seeds INSERT IGNORE (UNIQUE(code,key)) com os valores hardcoded atuais
     — nunca pisa valor salvo (mesma doutrina dos upgrades F4-01/F4-02).

  9. **WhatsApp href normalizado**: só dígitos (8–16) — `preg_replace('/\D/')`
     no sanitize + validação na tela. Se vazio, o span WhatsApp inteiro some
     da home — o `partial/contact.twig` passa a renderizá-lo apenas se
     `contact_whatsapp_href !== ''`; o texto visível sem número puro seria
     um link quebrado.

- **Consequências recusadas (documentadas):**
  - **Tela única gigante**: recusado — pressuposto #1 acima.
  - **Config por idioma em JSON serializado serialized=1**: recusado — achatar
    em keys `…_{code}` é flat/simple e casa com o padrão do resto do schema.

- **Validação:** e2e_f403 10 checks: grant/seed, muralha, form montado, save
  válido + round-trip no banco, aplicação REAL no catálogo (home span address/
  whatsapp/email, copyright no footer, socials href alterado), chave vazia
  remove social network, validações (email inválido bloqueia; whatsapp href
  com letra bloqueia; copyright sem %s bloqueia), ACL deny módulo Some do
  dashboard, i18n ×3, migrations 2× idempotência, preservação/sanitização de
  bug anterior — verde junto a suite completa.
