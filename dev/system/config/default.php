<?php declare(strict_types=1);
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
// F6-01 (ADR 0037 §1): default SEGURO de produção — erros NUNCA na tela.
// Override por config.php (define REAMUR_ERROR_DISPLAY — semeado pelo wizard
// a partir desta tarefa): true em dev/sandbox, false (ou constante ausente)
// em produção. O log em storage/logs/error.log vale independentemente.
$_['error_display']        = defined('REAMUR_ERROR_DISPLAY') ? (bool)REAMUR_ERROR_DISPLAY : false;
$_['error_log']            = true;
$_['error_filename']       = 'error.log';
$_['error_page']           = 'error.html';

// Proxy / CDN (ADR 0089, S5)
// Atrás de CDN ou proxy reverso o PHP recebe a conexão do proxy: todos os
// visitantes chegam com o mesmo IP e o HTTPS não aparece. Os cabeçalhos
// X-Forwarded-For e X-Forwarded-Proto só são aceitos quando o config.php da
// instalação declara em quem confiar:
//   REAMUR_TRUSTED_PROXIES    endereços e faixas dos proxies ('203.0.113.0/24, 2001:db8::/32')
//   REAMUR_TRUSTED_PROXY_HOPS quantos proxies ficam na frente (usado só sem a lista)
// Sem nenhuma das duas, vale a conexão direta.
$_['proxy_trusted']        = (defined('REAMUR_TRUSTED_PROXIES') && (is_string(REAMUR_TRUSTED_PROXIES) || is_array(REAMUR_TRUSTED_PROXIES))) ? REAMUR_TRUSTED_PROXIES : '';
$_['proxy_hops']           = (defined('REAMUR_TRUSTED_PROXY_HOPS') && is_numeric(REAMUR_TRUSTED_PROXY_HOPS)) ? max(0, (int)REAMUR_TRUSTED_PROXY_HOPS) : 0;

// Response
$_['response_header']      = [
    'Content-Type: text/html; charset=utf-8',
    // Hardening F2-18 (ADR 0018): defesa padrão de enquadramento/sniff/referrer.
    // Apps podem sobrescrever nos próprios configs quando precisarem divergir.
    'X-Content-Type-Options: nosniff',
    'X-Frame-Options: SAMEORIGIN',
    'Referrer-Policy: strict-origin-when-cross-origin',
    // F6-01 (ADR 0037 §2): minimização de superfície sem quebrar jornadas —
    // desliga sensores que o CMS não usa.
    'Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()',
    // Content-Security-Policy e Strict-Transport-Security NÃO ficam nesta
    // lista: são montados por requisição no framework.php (ADR 0093, S8),
    // com os valores de csp_mode / csp_sources / hsts_max_age logo abaixo.
];

// Content-Security-Policy e HSTS (ADR 0093, S8)
// A política é aplicada por padrão: scripts só do próprio site e dos blocos
// que o servidor marcou com nonce; sem eval. O config.php da instalação pode:
//   REAMUR_CSP_MODE      'report' = só relata no console do navegador, nada é bloqueado
//                        'off'    = nenhum cabeçalho de política
//                        (qualquer outro valor, ou ausente = aplicada)
//   REAMUR_CSP_SOURCES   sites de fora liberados no site público, por diretiva. Só https://site:
//                        ['font-src' => 'https://fonts.gstatic.com', 'style-src' => 'https://fonts.googleapis.com']
//   REAMUR_HSTS_MAX_AGE  prazo do HSTS em segundos (padrão 15552000 = 180 dias); 0 desliga.
//                        Só sai quando o endereço do site é https; nunca em localhost.
$_['csp_mode']             = defined('REAMUR_CSP_MODE') ? REAMUR_CSP_MODE : 'enforce';
$_['csp_sources']          = (defined('REAMUR_CSP_SOURCES') && is_array(REAMUR_CSP_SOURCES)) ? REAMUR_CSP_SOURCES : [];
$_['hsts_max_age']         = (defined('REAMUR_HSTS_MAX_AGE') && is_numeric(REAMUR_HSTS_MAX_AGE)) ? (int)REAMUR_HSTS_MAX_AGE : 15552000;

// EX-01 (ADR 0095): extensões (dev/extension/). Saída de emergência: se uma
// extensão ligada quebrar o site ou o admin, esta linha no config.php desliga
// TODAS de uma vez, sem tocar no banco (o que estava ligado volta ao tirar a linha):
//   define('REAMUR_EXTENSIONS', false);
$_['extension_off']        = defined('REAMUR_EXTENSIONS') && \Reamur\System\Library\Extension\Manager::switchedOff(REAMUR_EXTENSIONS);

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
