<?php
/**
 * config.php gerado pelo wizard de instalação (dev/install) — NUNCA versionar.
 * Constantes permitidas: docs/Contrato-Bootstrap.md §1.1 (nada além desta lista).
 * Placeholders {{...}} são substituídos por install/step_3.php via strtr().
 */

// HTTP
define('HTTP_SERVER', '{{HTTP_SERVER}}');
define('HTTPS_SERVER', '{{HTTPS_SERVER}}');

// DIR (raízes calculadas a partir de DIR_REAMUR — ver §1.1)
define('DIR_REAMUR', '{{DIR_REAMUR}}');
define('DIR_APPLICATION', DIR_REAMUR . 'dev/{{APP_DIR}}/');
define('DIR_SYSTEM', DIR_REAMUR . 'dev/system/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
define('DIR_EXTENSION', DIR_REAMUR . 'dev/extension/');

// DB
define('DB_DRIVER', '{{DB_DRIVER}}');
define('DB_HOSTNAME', '{{DB_HOSTNAME}}');
define('DB_USERNAME', '{{DB_USERNAME}}');
define('DB_PASSWORD', '{{DB_PASSWORD}}');
define('DB_DATABASE', '{{DB_DATABASE}}');
define('DB_PORT', '{{DB_PORT}}');
define('DB_PREFIX', '{{DB_PREFIX}}');
