<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Config da aplicação CATALOG — mescla sobre default.php (Contrato-Bootstrap §4).
 */

// Site
$_['site_url']            = defined('HTTP_SERVER') ? HTTP_SERVER : '';

// Sessão de visitante (Contrato §4): cookie RMSSESSION do default.php
$_['session_autostart']   = true;

// DB: driver e credenciais vêm das constantes do config.php gerado pelo wizard (Contrato §1.1)
$_['db_autostart']        = true;
$_['db_engine']           = defined('DB_DRIVER') ? DB_DRIVER : 'mysqli';
$_['db_hostname']         = defined('DB_HOSTNAME') ? DB_HOSTNAME : 'localhost';
$_['db_username']         = defined('DB_USERNAME') ? DB_USERNAME : '';
$_['db_password']         = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$_['db_database']         = defined('DB_DATABASE') ? DB_DATABASE : '';
$_['db_port']             = defined('DB_PORT') ? (int)DB_PORT : 3306;

// Actions
$_['action_default']      = 'common/home'; // Contrato §4
$_['action_router']       = 'startup/router'; // F2-5: URLs limpas (ADR 0006); ?route= segue funcionando
$_['action_error']        = 'error/not_found';
$_['action_pre_action']   = ['startup/language']; // F2-3: idioma da requisição (Adendo ADR 0001)
$_['action_post_action']  = [];

// Erros
$_['error_display']       = true; // virar false em produção
$_['error_log']           = true;
