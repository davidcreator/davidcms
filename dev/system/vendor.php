<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * vendor.php — ponte única para o autoloader do Composer (Contrato-Bootstrap §3).
 * FAZ: require do storage/vendor/autoload.php. NUNCA FAZ: qualquer outra coisa.
 */

$autoload = DIR_SYSTEM . 'storage/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    exit('ReamurCMS: dependências do Composer ausentes em dev/system/storage/vendor/. Execute: composer install --working-dir=dev/system/storage');
}

require_once($autoload);
