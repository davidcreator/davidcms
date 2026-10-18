<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Front controller — aplicação INSTALL (wizard de instalação/upgrade).
 * Especificação: docs/Contrato-Bootstrap.md §2. A ÚNICA linha específica
 * da aplicação é o valor de APPLICATION (demais apps mudam apenas ela).
 */

// 1. Versão do núcleo (bump somente em release — ver Contrato-Bootstrap §5.3)
define('REAMUR_VERSION', '1.0.0');

// 2. Aplicação em execução
define('APPLICATION', 'Install');

// 3. Configuração da instalação (gerada pelo wizard install/)
if (is_file('config.php')) {
    require_once('config.php');
}

// 4. Install NUNCA redireciona (ela É o wizard). Para rodar ANTES do config.php
//    existir, autodefine o ambiente usando SOMENTE constantes do Contrato-Bootstrap §1.1
//    (Adendo F1-4 — nenhuma constante nova). Se config.php existir, o bloco é ignorado.
if (!defined('DIR_APPLICATION')) {
    $app_dir = str_replace('\\', '/', (string)realpath(__DIR__));

    // DIR (§1.1) — raízes derivadas da posição real de dev/install
    define('DIR_REAMUR', dirname(dirname($app_dir)) . '/');
    define('DIR_APPLICATION', $app_dir . '/');
    define('DIR_SYSTEM', DIR_REAMUR . 'dev/system/');
    define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
    define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
    define('DIR_CONFIG', DIR_SYSTEM . 'config/');
    define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
    define('DIR_EXTENSION', DIR_REAMUR . 'dev/extension/');

    // HTTP (§1.1) — URL base da própria aplicação install
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')));

    define('HTTP_SERVER', $scheme . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($base, '/') . '/');
    define('HTTPS_SERVER', HTTP_SERVER);
}

// 5. Trava pós-instalação (F2-18, ADR 0018): o passo 4 (Concluído) grava
//    system/storage/install.lock; depois disso o wizard fica SELADO e todo
//    acesso recebe 403. Para reinstalar: apague install.lock + os config.php.
if (is_file(DIR_STORAGE . 'install.lock')) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>ReamurCMS — Install locked</title></head>'
        . '<body style="font-family:sans-serif;max-width:640px;margin:64px auto;text-align:center;">'
        . '<h1>Installation locked</h1>'
        . '<p>This ReamurCMS is already installed and the installer is sealed.</p>'
        . '<p>To reinstall, delete <code>dev/system/storage/install.lock</code> and the generated <code>catalog/config.php</code> and <code>admin/config.php</code>.</p>'
        . '</body></html>';
    exit();
}

// 6. Bootstrap do núcleo — ordem inviolável, nada entre estes requires
require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'framework.php');

// 6. A partir daqui o fluxo segue o ciclo MVCL (docs/Arquitetura-MVCL.md §4)
