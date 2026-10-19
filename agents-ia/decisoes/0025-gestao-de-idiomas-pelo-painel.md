# ADR 0025 — Gestão de idiomas pelo painel (localization/language)

- **Status:** Aceita — 2026-10-19 (F4 Tarefa 02)
- **Contexto:** ADR 0001 definiu idiomas por linguagem-como-conteúdo (filesystem +
  `rms_language`), mas a tabela era apenas semente morta: não havia nenhuma tela
  para gerir metadados (nome, locale, sort_order), ligar/desligar idiomas e
  definir o idioma padrão **por aplicação** (catálogo × admin). O site público
  nasceu operando nos três idiomas sem nenhum interruptor de governança.
- **Decisões:**

  1. **CRUD leve de governança, não de conteúdo.** `localization/language` edita
     metadados do registro `rms_language` (nome/locale/sort_order/status) — o
     conteúdo continua nos arquivos `language/{code}/...` (Adendo ADR 0001).
     Diretório de idioma novo continua exigindo deploy de pastas.

  2. **Gate do idioma é cruzamento `status=1` × diretório existente**
     (`catalog/model/localization/language.php::getEnabledCodes()`). Um idioma
     só entra no switcher público e na pre-ação se estiver ATIVO **e** tiver a
     pasta real no filesystem das duas aplicações — nenhum dos dois critérios é
     suficiente sozinho. A lista do painel, ao contrário, mostra o registro
     mesmo sem pasta (com indicação), porque é lá que a lacuna se resolve.

  3. **Ativação deliberada do trio en-us/pt-br/es-eu no upgrade.**
     `INSERT INTO rms_language` do schema nasceu com `pt-br/es-eu status=0`
     quando a coluna era inerte. A F4-02 dá efeito à coluna, então:
     - **instalações novas:** schema.sql passa a semear os três com `status=1`;
     - **instalações existentes:** `upgrade_f4_02_localization_language_op_grant.sql`
       roda `UPDATE rms_language SET status=1` no trio (idempotente nativo).
     Fundamento: o site público já publica versões nos três idiomas; manter
     `status=0` quebraria o switcher e as baterias F2/F3 que usam
     `?language=pt-br&es-eu` no admin. Validado por suíte completa (até f402).

  4. **Idioma padrão por aplicação vive em `rms_setting`** com as chaves
     `config_language_catalog` / `config_language_admin` (code=`config`) —
     estende a F4-01 (ADR 0024). As allowlists existem em DUAS camadas que rato
     pelo objeto Config: `admin/model/setting/setting.php::editSettings` e
     `startup/setting.php` (admin + catalog). Qualquer chave nova de configuração
     precisa entrar **nos dois** — caso contrário o save grava mas o runtime nunca
     lê (bug real capturado pela bateria: round-trip sempre voltava `en-us`).

  5. **Resolução do idioma efetivo (`startup/language`, admin + catalog):**
     DB default (`config_language_<app>`) → primeiro idioma ativo → `en-us` como
     rede final. `?language=` ou cookie só valem se o código estiver entre os
     ativos; idioma salvo em sessão só sobrevive se ainda ativo. Desativar um
     idioma em uso joga o usuário para o novo padrão silenciosamente.

  6. **Proteção do padrão na própria tela:** a validação impede desativar o
     idioma configurado como default de qualquer aplicação, com mensagem
     direcionando mudar o default antes (evita estado incoerente).

  6bis. **Inversão da ordem das pre-actions.** A F4-01 documentou
     `['startup/language','startup/setting',...]`; a F4-02 passa para
     `['startup/setting','startup/language',...]` (admin e catalog) porque o
     `startup/language` agora consome `config_language_*` do objeto Config —
     injetado justamente pelo `startup/setting`. Os pacotes são sequenciais:
     quem aplicar apenas o F4-01 recebe a ordem documentada na ADR 0024; ao
     aplicar o F4-02, os `system/config/*.php` finais invertem.

  7. **ACL no padrão da casa:** grants `access`/`modify` via
     `upgrade_f4_02_localization_language_op_grant.sql` (JSON_ARRAY_APPEND com
     guarda JSON_SEARCH — idempotente). Sem CSRF extra no form, seguindo os
     CRUDs anteriores (muralha de login + ACL como defesa).

  8. **Dashboard ganha módulo "Idiomas"** (`text_manage_languages`) ao lado de
     "Configurações", ambos gateados por `hasPermission('access', ...)`.

- **Consequências:**
  - `installer/schema.sql` agora semeia o trio como ATIVO — novo comportamento
    padronizado a partir da F4-02 (doctrine: trio nasce ligado).
  - Bateria `tools/e2e/e2e_f402.sh` (10 checks: grant/seed, muralha, lista,
    round-trip dos defaults, ativação/desativação real no switcher, validações,
    ACL deny, i18n ×3 das telas) — **10/10** + suite completa 14 baterias verde.
  - Consolidação: uma proposta duplicada de upgrade (`language_governance`) foi
    absorvida pelo arquivo canônico `localization_language_op_grant` — regra:
    1 tarefa = 1 arquivo de upgrade idempotente.
- **Validação:** e2e_f402 10/10; suíte canônica (regressao, f212..f218,
  f301..f305, f401, f402) = **191 asserts, 0 falhas** — executada em 2026-10-19
  contra a working tree espelhada no sandbox live (`/var/www/html/davidcms/dev`);
  ainda não consta commitada/pushada no GitHub nesta data (pacote é entregue
  em zip e o mantenedor aplica).
