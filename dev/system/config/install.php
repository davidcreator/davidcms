<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Config da aplicação INSTALL — mescla sobre default.php (Contrato-Bootstrap §4).
 */

// Site
$_['site_url']            = defined('HTTP_SERVER') ? HTTP_SERVER : '';

// Sessão efêmera do wizard (Contrato §4)
$_['session_autostart']   = true;
$_['session_name']        = 'RMSINSTALL';
$_['session_expire']      = 3600;

// DB: o wizard conecta nativamente via model (sem config.php existir ainda)
$_['db_autostart']        = false;

// Actions
$_['action_default']      = 'install/step_1'; // Contrato §4
$_['action_router']       = '';               // Fase 1: dispatch direto (startup/router virá com startup/*)
$_['action_error']        = 'error/not_found';
$_['action_pre_action']   = [];               // startup/requirements: delegado ao próprio step_2 nesta fase
$_['action_post_action']  = [];

// Erros (ambiente de setup: sempre visível)
$_['error_display']       = true;
$_['error_log']           = true;
