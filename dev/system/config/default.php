<?php
// Site
$_['site_url']             = '';
$_['site_ssl']             = false;

// Url
$_['url_autostart']        = true;

// Language
$_['language_code']        = 'en-us';
$_['language_directory']   = 'en-us';
$_['language_autoload']    = ['en-us'];

// Date
$_['date_timezone']        = 'UTC';

// Database
$_['db_autostart']         = false;
$_['db_engine']            = 'mysqli'; // mysqli, pdo or pgsql
$_['db_hostname']          = 'localhost';
$_['db_username']          = 'root';
$_['db_password']          = '';
$_['db_database']          = '';
$_['db_port']              = 3306;

// Mail
$_['mail_engine']          = 'mail'; // mail or smtp
$_['mail_from']            = ''; // Your E-Mail
$_['mail_sender']          = ''; // Your name or company name
$_['mail_reply_to']        = ''; // Reply to E-Mail
$_['mail_smtp_hostname']   = '';
$_['mail_smtp_username']   = '';
$_['mail_smtp_password']   = '';
$_['mail_smtp_port']       = 25;
$_['mail_smtp_timeout']    = 5;
$_['mail_verp']            = false;
$_['mail_parameter']       = '';

// Cache
$_['cache_engine']         = 'file'; // apc, file, mem, memcached or redis
$_['cache_expire']         = 3600;

// Session
$_['session_autostart']    = false;
$_['session_engine']       = 'file'; // db or file
$_['session_name']         = 'RMSSESSION';
$_['session_domain']       = '';
$_['session_path']         = '/'; // F2-5 (ADR 0006): cookie válido no domínio inteiro — PHP_SELF varia com URL limpa em dev-server
$_['session_expire']       = 86400;
$_['session_probability']  = 1;
$_['session_divisor']      = 5;
$_['session_samesite']     = 'Lax';

// Template
$_['template_engine']      = 'twig';
$_['template_directory']   = '';
$_['template_cache']       = false;
$_['template_extension']   = '.twig';

// Error
$_['error_display']        = true; // You need to change this to false on a live site.
$_['error_log']            = true;
$_['error_filename']       = 'error.log';
$_['error_page']           = 'error.html';

// Response
$_['response_header']      = [
    'Content-Type: text/html; charset=utf-8',
    // Hardening F2-18 (ADR 0018): defesa padrão de enquadramento/sniff/referrer.
    // Apps podem sobrescrever nos próprios configs quando precisarem divergir.
    'X-Content-Type-Options: nosniff',
    'X-Frame-Options: SAMEORIGIN',
    'Referrer-Policy: strict-origin-when-cross-origin',
];
$_['response_compression'] = 0;

// Autoload Configs
$_['config_autoload']      = [];

// Autoload Libraries
$_['library_autoload']     = [];

// Autoload Models
$_['model_autoload']       = [];

// Actions
$_['action_default']       = 'common/home';
$_['action_router']        = 'startup/router';
$_['action_error']         = 'error/not_found';
$_['action_pre_action']    = [];
$_['action_post_action']   = [];
$_['action_event']         = [];
$_['action_cron']          = [];
