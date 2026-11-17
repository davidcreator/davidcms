-- KZ-33 — Cadastro completo das ferramentas novas JOW-payments e Dracaunos-Security
-- (rms_tool + rms_tool_description nos 3 idiomas: 1=en-us, 2=pt-br, 3=es-eu).
-- Idempotente: nao duplica se ja existir (slug pelo par vendor+url).

SET @tid = NULL;
SELECT `tool_id` INTO @tid FROM `rms_tool` WHERE `vendor` = 'JOW' AND `url` = 'https://jow.example/payments' LIMIT 1;
INSERT INTO `rms_tool` (`image`, `vendor`, `url`, `status`, `sort_order`, `date_added`)
SELECT '', 'JOW', 'https://jow.example/payments', 1, 10, NOW() FROM DUAL
WHERE @tid IS NULL;
SET @tid = COALESCE(@tid, LAST_INSERT_ID());
INSERT INTO `rms_tool_description` (`tool_id`, `language_id`, `name`, `description`)
VALUES (@tid, 1, 'JOW-payments', 'JOW payments module — charges, PIX and receivables reports.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
INSERT INTO `rms_tool_description` (`tool_id`, `language_id`, `name`, `description`)
VALUES (@tid, 2, 'JOW-payments', 'Módulo de pagamentos JOW — cobranças, PIX e relatório de recebimentos.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
INSERT INTO `rms_tool_description` (`tool_id`, `language_id`, `name`, `description`)
VALUES (@tid, 3, 'JOW-payments', 'Módulo de pagos JOW — cobros, PIX e informe de cobros.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

SET @tid = NULL;
SELECT `tool_id` INTO @tid FROM `rms_tool` WHERE `vendor` = 'Dracaunos' AND `url` = 'https://dracaunos.example/security' LIMIT 1;
INSERT INTO `rms_tool` (`image`, `vendor`, `url`, `status`, `sort_order`, `date_added`)
SELECT '', 'Dracaunos', 'https://dracaunos.example/security', 1, 10, NOW() FROM DUAL
WHERE @tid IS NULL;
SET @tid = COALESCE(@tid, LAST_INSERT_ID());
INSERT INTO `rms_tool_description` (`tool_id`, `language_id`, `name`, `description`)
VALUES (@tid, 1, 'Dracaunos-Security', 'Dracaunos security module — firewall, ACL and admin access audit.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
INSERT INTO `rms_tool_description` (`tool_id`, `language_id`, `name`, `description`)
VALUES (@tid, 2, 'Dracaunos-Security', 'Módulo de segurança Dracaunos — firewall, ACL e auditoria de acessos do admin.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
INSERT INTO `rms_tool_description` (`tool_id`, `language_id`, `name`, `description`)
VALUES (@tid, 3, 'Dracaunos-Security', 'Módulo de seguridad Dracaunos — firewall, ACL y auditoría de accesos del admin.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Verificacao:
SELECT t.`tool_id`, t.`vendor`, t.`url`, t.`status`, d.`language_id`, d.`name`, d.`description`
FROM `rms_tool` t JOIN `rms_tool_description` d ON d.`tool_id` = t.`tool_id`
WHERE t.`vendor` IN ('JOW','Dracaunos') ORDER BY t.`tool_id`, d.`language_id`;
