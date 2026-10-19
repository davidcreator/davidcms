# ADR 0024: Configurações operacionais do painel — `setting/setting` unificado

- **Status:** Aceita
- **Data:** 2026-10-19
- **Autor:** Cascade (Coevolve AI)
- **Tarefa:** Fase 4 — Tarefa 01 (F4-01)
- **Regras relacionadas:** agents-ia/regras/00-AGENT-PROTOCOL.md

## Contexto

A tabela `rms_setting` existia **órfã** desde o schema inicial: nenhum model,
controller ou serviço PHP a lia. As únicas configurações eficazes eram os
defaults gravados em `system/config/default.php` (l. 27–38) e os configs
gerados pelo wizard — sem nenhuma tela que permitisse ao administrador
revisar/alterar credenciais operacionais. O dashboard era hardcoded e
`rms_user_group.permission` já usava o padrão idempotente de grant (JSON).

O escopo proposto inicialmente previa três telas separadas
(`setting/store`, `setting/mail`, `setting/cache`). Após leitura dos
consumidores reais, o escopo foi consolidado **numa única tela** por coesão
de produto (to-do operacional de uma loja/painel pequeno cabe num formulário)
e por consistência com os cards modulares do dashboard.

## Decisão

### 1 — Modelo único de persistência
- `admin/model/setting/setting.php`: `getSettings()` / `editSettings()` com
  `code='config'`, **allowlist de 12 chaves** (nunca persistir arbitrário),
  cache de leitura na chave `setting.config` com **invalidação no save**.
- `catalog/model/setting/setting.php`: somente reader.
- Helper `private function t()` redeclarado por model — o padrão do projeto é
  retornar `DB_PREFIX . $table` sem “mágica” de base Model.

### 2 — A aplicação real imediata (o que ESTA tarefa muda no runtime)
Duas prateleiras separadas por honestidade de engenharia:

**Prateleira A · Efeito real já neste build:**
- Pre-actions `startup/setting` (catalog **e** admin): hidratam o `Config`
  da request com as 12 chaves allowlisted vindas do banco, **antes** do
  dispatch. `system/config/catalog.php` ganha `['startup/language',
  'startup/setting']`; `admin.php` idem + `'startup/login','startup/permission'`.
- `catalog/controller/common/header.php`: `title`/`description` do Document
  quando **vazios** caem no fallback `config_meta_title` /
  `config_meta_description` — impacto de SEO real em qualquer página que não
  sete meta própria (comprovado E2E com `error/not_found`: title do lang
  NÃO é pisado; description vazia é preenchida pelo valor do painel).
- Módulo “Settings” no dashboard (`admin/controller/common/dashboard.php`),
  gated por `hasPermission('access','setting/setting')`.

**Prateleira B · Persistido hoje, disparo eficaz pendente (não há consumidor):**
- `mail_*`: a lib `Reamur\System\Library\Mail` existe (`mail.php` + `smtp.php`),
  mas **nenhum controlador a consome hoje** — o formulário de contato grava
  `contact_message` + dispara webhook; não envia e-mail. Registrar as
  credenciais no painel agora é válido como contrato; o envio real depende
  do futuro gatilho de disparo (provável junto à decisão de orçamento).
- `cache_engine`: o engine (file|apc|memcached|redis) é montado pelo
  framework **antes** das pre-actions de startup, com o valor do arquivo de
  config (`system/config/*.php`). Persistir a escolha agora é correto — mas
  ele só entra em vigor quando o próprio `config.php` for ajustado (via
  wizard ou manual). `cache_expire` tem o mesmo comportamento. Não há
  envelopamento: o painel é a fonte da verdade dos valores e o Config da
  request os carrega; a aplicação no construtor do engine é caminho separado.

### 3 — Tela e segurança
- Uma tela `setting/setting` com 3 fieldsets (Store / Mail / Cache), padrões
  visuais da casa (`panel form`, `.field`, `.grid two`, `alert error|success`),
  validações: porta SMTP 1–65535; timeout ≥ 0; TTL (60s–30d); `mail_engine`
  `mail|smtp` (SMTP exige `hostname` — validação server-side apenas; nunca
  anotação de erro por-campo: o erro consolidado aborta em alert no topo,
  padrão testimonial).
- **clearCache**: rota POST-only `setting/setting.clearCache`, gated por
  `hasPermission('modify','setting/setting')`, apaga `system/storage/cache/*`
  via `$this->cache->clear()` + `flash success` na tela ao voltar.

### 4 — ACL e seeding
- `install/sql/upgrade_f4_01_setting_op_grant.sql`:
  1. `JSON_ARRAY_APPEND` idempotente do grant `setting/setting` em
     `rms_user_group.permission -> '$.access'` e `'$.modify'` (guarda por
     `JSON_SEARCH` NULL);
  2. **Seed `INSERT IGNORE` (não `ON DUPLICATE KEY UPDATE`)** das 6 chaves
     obrigatórias — re-rodar o upgrade **nunca pisa uma configuração salva
     pela tela**. Este detalhe saiu do e2e: um `ON DUPLICATE` re-aplicaria
     defaults por cima de customizações da loja — inaceitável aditivo.

### 5 — Engine interna·correção cirúrgica documentada
`system/library/cache/file.php` ganhou `public function clear(): void`. O
wrapper `Cache::clear()` era `method_exists`-protegido; mas o **framework
registra o adapter cru no Registry** (`new File()`), não o wrapper — e o
controller recebe o adapter. Sem esse método, `clearCache` Fatal. A
implementação (apagar `cache.*`) é idêntica ao contrato já exposto pelo
wrapper.

## Implementação por arquivo (lista única de entrega)

| Arquivo | Ação |
|---|---|
| `dev/admin/model/setting/setting.php` | ➕ getSettings/editSettings |
| `dev/catalog/model/setting/setting.php` | ➕ reader |
| `dev/admin/controller/startup/setting.php` | ➕ pre-action (injeta no Config) |
| `dev/catalog/controller/startup/setting.php` | ➕ pre-action |
| `dev/admin/controller/setting/setting.php` | ➕ index + clearCache |
| `dev/admin/view/template/setting/setting.twig` | ➕ tela |
| `dev/admin/language/{en-us,pt-br,es-eu}/setting/setting.php` | ➕ ×3 i18n |
| `dev/system/config/catalog.php` | ✏ pre_actions |
| `dev/system/config/admin.php` | ✏ pre_actions |
| `dev/admin/controller/common/dashboard.php` | ✏ módulo |
| `dev/admin/language/{en-us,pt-br,es-eu}/common/dashboard.php` | ✏ `text_manage_settings` ×3 |
| `dev/catalog/controller/common/header.php` | ✏ fallback meta |
| `dev/system/library/cache/file.php` | ✏ `+clear()` |
| `dev/install/sql/upgrade_f4_01_setting_op_grant.sql` | ➕ grants + seed (idempotente) |

## DoD / Validação

- `tools/e2e/e2e_f401.sh` (10 checks): login+muralha, seeds na tela,
  save→banco→round-trip, **fallback real** (home não é pisada; 404 pega
  description do config), validação (SMTP sem host → volta 200 com alert,
  banco intacto), clearCache físico (POST-only + esvazia storage/cache),
  ACL (retirar grant → some do dashboard + 302 permission; restore), i18n ×3.
- Suíte regressiva: **todas as baterias f212…f218, f301…f305, regressao −
  verde** com o instalado novo + upgrade F4-01 aplicado.
- Bug real encontrado pelo e2e (e corrigido): `File::clear()` ausente.

## Rejeições / itens fora desta ADR

- **3 telas separadas** (proposta original): rejeitadas em favor de 1 painel
  coeso.
- **`setting/language.php`**: recusado neste delta — `rms_language` já governa
  os 3 idiomas fixos e a UI de idioma fica para fase própria.
- **Dirty check / confirmação de senha antes de salvar settings**: fica fora
  (requer workflow maior); padrão atual = salvar sempre, validando campos.

## Rollback

`$B/install/sql/upgrade_f4_01_setting_op_grant.sql` é re-aplicável inalterado;
as chaves novas têm `INSERT IGNORE` — elas **não sobrescrevem** dados
imediatamente. Para reverter: remover o grant JSON de `rms_user_group`,
apagar os arquivos `➕` e reverter os 5 `✏` — sem impedimento.
