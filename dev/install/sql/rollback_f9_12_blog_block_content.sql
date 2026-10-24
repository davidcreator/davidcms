-- ════════════════════════════════════════════════════════════════════
-- F9-12 — rollback seguro da aplicação do editor de blocos.
-- Esta migration é somente aditiva. O downgrade suportado inicialmente é
-- reverter o código para o leitor HTML legado, mantendo a tabela sidecar:
-- blog_post_description.description continua sendo o snapshot HTML compatível.
-- Portanto este script é intencionalmente NÃO DESTRUTIVO e não apaga conteúdo.
-- ════════════════════════════════════════════════════════════════════

SELECT 'F9-12 rollback seguro: sidecar preservado; reverta o código para HTML legado.' AS `rollback_notice`;

-- Remoção física eventual só após export/backup independente e aprovação manual:
-- DROP TABLE IF EXISTS `rms_blog_post_content`;
