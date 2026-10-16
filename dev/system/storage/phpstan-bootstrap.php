<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * phpstan-bootstrap.php — define as constantes do Contrato-Bootstrap §1.1 SOMENTE
 * para análise estática. Em execução real elas vêm do config.php gerado pelo wizard
 * ou do Adendo F1-4 (install) — este arquivo NUNCA é carregado em produção.
 * Referência: phpstan.neon → parameters.bootstrapFiles.
 */

$constants = [
    'REAMUR_VERSION'  => '0.0.0-dev',
    'APPLICATION'     => 'Dev',
    'HTTP_SERVER'     => 'http://localhost/',
    'HTTPS_SERVER'    => 'http://localhost/',
    'DIR_REAMUR'      => '/app/',
    'DIR_APPLICATION' => '/app/dev/catalog/',
    'DIR_SYSTEM'      => '/app/dev/system/',
    'DIR_LANGUAGE'    => '/app/dev/catalog/language/',
    'DIR_TEMPLATE'    => '/app/dev/catalog/view/template/',
    'DIR_CONFIG'      => '/app/dev/system/config/',
    'DIR_STORAGE'     => '/app/dev/system/storage/',
    'DIR_EXTENSION'   => '/app/dev/extension/',
    'DB_DRIVER'       => 'mysqli',
    'DB_HOSTNAME'     => 'localhost',
    'DB_USERNAME'     => 'phpstan',
    'DB_PASSWORD'     => 'phpstan',
    'DB_DATABASE'     => 'phpstan',
    'DB_PORT'         => '3306',
    'DB_PREFIX'       => 'rms_',
];

foreach ($constants as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}
