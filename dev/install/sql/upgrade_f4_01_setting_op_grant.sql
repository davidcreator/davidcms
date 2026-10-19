-- ════════════════════════════════════════════════════════════════════
-- F4 Tarefa 01 — Configurações operacionais (setting/setting): grant para
-- instalações EXISTENTES (instalações novas nascem com o grant no seed do
-- wizard, step_3). IDEMPOTENTE — ajuste o prefixo se não for rms_.
-- ════════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/setting')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'setting/setting', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/setting')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'setting/setting', NULL, '$.modify') IS NULL;

-- Seed minimalista das chaves 'config' (idempotente): garante que o form
-- abre com valores sensatos mesmo em instalação sem upgrade rodado.
-- INSERT IGNORE (não ON DUPLICATE KEY UPDATE): o seed entra apenas se a
-- chave AINDA não existir. Re-rodar o upgrade após operação NÃO pisa
-- configurações salvas pela tela (idempotente + não-destrutivo).
INSERT IGNORE INTO `rms_setting` (`code`,`key`,`value`,`serialized`) VALUES
 ('config','config_name','DavidCreator',0),
 ('config','config_meta_title','David Almeida — Creative Developer',0),
 ('config','config_meta_description','Creative Developer — Sites com foco em performance e identidade visual forte para marcas e negócios.',0),
 ('config','mail_engine','mail',0),
 ('config','cache_engine','file',0),
 ('config','cache_expire','3600',0);
