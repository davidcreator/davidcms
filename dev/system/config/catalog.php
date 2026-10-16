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

// DB: será ligado na Tarefa 6c junto com library/db (mysqli/pdo)
$_['db_autostart']        = false;

// Actions
$_['action_default']      = 'common/home'; // Contrato §4
$_['action_router']       = '';            // Fase 1: dispatch direto
$_['action_error']        = 'error/not_found';
$_['action_pre_action']   = [];            // startup/{session,language,seo_url}: Fase 2
$_['action_post_action']  = [];

// Erros
$_['error_display']       = true; // virar false em produção
$_['error_log']           = true;
