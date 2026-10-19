<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Config da aplicação ADMIN — mescla sobre default.php (Contrato-Bootstrap §4).
 */

// Site
$_['site_url']            = defined('HTTP_SERVER') ? HTTP_SERVER : '';

// Sessão do painel (Contrato §4): cookie próprio; token ACL chega na Fase 2 (startup/login)
$_['session_autostart']   = true;
$_['session_name']        = 'RMSADMIN';

// DB: driver e credenciais vêm das constantes do config.php gerado pelo wizard (Contrato §1.1)
$_['db_autostart']        = true;
$_['db_engine']           = defined('DB_DRIVER') ? DB_DRIVER : 'mysqli';
$_['db_hostname']         = defined('DB_HOSTNAME') ? DB_HOSTNAME : 'localhost';
$_['db_username']         = defined('DB_USERNAME') ? DB_USERNAME : '';
$_['db_password']         = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$_['db_database']         = defined('DB_DATABASE') ? DB_DATABASE : '';
$_['db_port']             = defined('DB_PORT') ? (int)DB_PORT : 3306;

// Actions
$_['action_default']      = 'common/dashboard'; // Contrato §4 (atrás da muralha startup/login)
$_['action_router']       = 'startup/router';  // F2-5: URLs limpas + aliases /login /logout (ADR 0006)
$_['action_error']        = 'error/not_found';
$_['action_pre_action']   = ['startup/setting', 'startup/language', 'startup/login', 'startup/permission']; // F2-3 idioma ANTES da muralha; F4-01 rms_setting code=config (ADR 0024); F2-2 sessão→ACL
$_['action_post_action']  = [];

// Erros
$_['error_display']       = true; // virar false em produção
$_['error_log']           = true;
