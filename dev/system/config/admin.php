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

// DB: será ligado na Tarefa 6c junto com library/db (mysqli/pdo)
$_['db_autostart']        = false;

// Actions
$_['action_default']      = 'common/dashboard'; // Contrato §4 (após login — Fase 2)
$_['action_router']       = '';                 // Fase 1: dispatch direto
$_['action_error']        = 'error/not_found';
$_['action_pre_action']   = [];                 // startup/{login,permission}: Fase 2
$_['action_post_action']  = [];

// Erros
$_['error_display']       = true; // virar false em produção
$_['error_log']           = true;
