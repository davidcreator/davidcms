<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * startup.php — validação mínima do ambiente (Contrato-Bootstrap §1.3/§3).
 * FAZ: valida PHP, valida constantes/pastas, saneia o ambiente mínimo.
 * NUNCA FAZ: definir constantes novas, registrar serviços, conectar banco.
 */

// 1. Falha cedo, mensagem clara (Contrato §1.3)
if (version_compare(PHP_VERSION, '8.0', '<')) {
    http_response_code(500);
    exit('ReamurCMS requer PHP >= 8.0 (executando ' . PHP_VERSION . ').');
}

// 2. Constantes obrigatórias — vêm do index.php + config.php/Adendo F1-4
foreach (['REAMUR_VERSION', 'APPLICATION', 'DIR_SYSTEM', 'DIR_APPLICATION'] as $constant) {
    if (!defined($constant)) {
        http_response_code(500);
        exit('ReamurCMS bootstrap: constante ausente: ' . $constant);
    }
}

// 3. As pastas-chave precisam resolver de verdade
if (!is_dir(DIR_SYSTEM) || !is_dir(DIR_APPLICATION)) {
    http_response_code(500);
    exit('ReamurCMS bootstrap: DIR_SYSTEM/DIR_APPLICATION não resolvem ("' . DIR_SYSTEM . '" / "' . DIR_APPLICATION . '").');
}

// 4. Ambiente mínimo e saída limpa
error_reporting(E_ALL);

if (ini_get('date.timezone') === '') {
    date_default_timezone_set('UTC');
}

if (extension_loaded('mbstring')) {
    mb_internal_encoding('UTF-8');
}
