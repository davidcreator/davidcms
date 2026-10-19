-- ═══════════════════════════════════════════════════════════════════
-- F4-03 (ADR 0026): Gestão de informações do site (setting/site_info)
-- Execução: sudo mysql <database> < install/sql/upgrade_f4_03_site_info_op_grant.sql
-- Idempotente: pode ser executado N vezes.
--
--   1) grants JSON access+modify 'setting/site_info' (guarda JSON_SEARCH)
--   2) seeds INSERT IGNORE (UNIQUE(code,key)) — NUNCA pisam valor já salvo
--      (padrão do seed F4-01 ADR 0024 / F4-02 ADR 0025)
-- ═══════════════════════════════════════════════════════════════════

-- 1) Grants
UPDATE `rms_user_group`
   SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/site_info')
 WHERE `name` = 'Administrator'
   AND JSON_SEARCH(`permission`, 'one', 'setting/site_info', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
   SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/site_info')
 WHERE `name` = 'Administrator'
   AND JSON_SEARCH(`permission`, 'one', 'setting/site_info', NULL, '$.modify') IS NULL;

-- 2) Seeds com os valores que hoje estão hardcoded em
--    catalog/language/{en-us,pt-br,es-eu}/common/home.php + footer.php
--    (documentados na ADR 0026 antes da migração p/ rms_setting):
INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 -- Contato (globais)
 ('config', 'config_contact_email',          'contato@davidalmeida.xyz', 0),
 ('config', 'config_contact_whatsapp_text',  '+55 11 98393-8347',        0),
 ('config', 'config_contact_whatsapp_href',  '5511983938347',            0),
 -- Endereço (por idioma — decisão do mantenedor na Tarefa F4-03)
 ('config', 'config_contact_address_en-us',  'Home and Office: São Paulo, Brazil', 0),
 ('config', 'config_contact_address_pt-br',  'Home and Office: São Paulo, Brasil', 0),
 ('config', 'config_contact_address_es-eu',  'Home and Office: São Paulo, Brasil', 0),
 -- Copyright do rodapé (por idioma; placeholder %s = ano corrente, sprintf
 --  na renderização do footer — mesmo contrato do lang original)
 ('config', 'config_copyright_en-us', '© %s All rights reserved. David L. Almeida — Creative Developer.',          0),
 ('config', 'config_copyright_pt-br', '© %s Todos os direitos reservados. David L. Almeida — Desenvolvedor Criativo.',  0),
 ('config', 'config_copyright_es-eu', '© %s Todos los derechos reservados. David L. Almeida — Desarrollador Creativo.',   0),
 -- Redes sociais (globais)
 ('config', 'config_social_facebook_href',        'https://www.facebook.com/davidalmeidadesigner/', 0),
 ('config', 'config_social_instagram_href',       'https://www.instagram.com/davidalmeidades/',      0),
 ('config', 'config_social_pinterest_href',       'https://br.pinterest.com/davidalmeidades/',       0),
 ('config', 'config_social_x_href',               'https://x.com/DavidAlmeidaDes',                   0),
 ('config', 'config_social_google_business_href', 'https://www.google.com/search?q=David+Creator',   0),
 ('config', 'config_social_redbubble_href',       'https://www.redbubble.com/people/DavidCreator/',  0);
