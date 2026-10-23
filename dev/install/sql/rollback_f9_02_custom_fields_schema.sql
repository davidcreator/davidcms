-- ════════════════════════════════════════════════════════════════════
-- ROLLBACK EM LAUDO de upgrade_f9_02_custom_fields_schema.sql — NÃO idempotente.
-- Executar SÓ em banco de teste sem dados ou após backup validado; DROP remove
-- todas as definições/labels/quaisquer valores de campos personalizados.
-- Não automaticamente restaura dados dos campos removidos.
-- Não use após migrações futuras de dados existentes.
-- ════════════════════════════════════════════════════════════════════

DROP TABLE IF EXISTS `rms_custom_field_value`;
DROP TABLE IF EXISTS `rms_custom_field_description`;
DROP TABLE IF EXISTS `rms_custom_field`;
DROP TABLE IF EXISTS `rms_custom_field_group_description`;
DROP TABLE IF EXISTS `rms_custom_field_group`;
