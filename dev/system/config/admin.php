<?php
// Site
$_['site_url']         = HTTP_SERVER;
//$_['site_ssl']       = HTTPS_SERVER;

// Database
$_['db_autostart']      = true;
$_['db_engine']         = 'DB_DRIVER'; // mysqli, pdo or pgsql
$_['db_engine']         = 'DB_HOSTNAME';
$_['db_username']       = 'DB_USERNAME';
$_['db_password']       = 'DB_PASSWORD';
$_['db_database']       = 'DB_DATABASE';
$_['db_port']           = 'DB_PORT';

// Session
$_['session_autostart'] = true;
$_['session_engine']    = 'db'; // db or file

// Template
$_['template_cache']    = true;

// Error
$_['error_display']     = true;

// Actions
$_['action_pre_action'] = [
    'startup/setting',
	'startup/session',
	'startup/language',
	'startup/application',
	'startup/extension',
	'startup/startup',
	'startup/error',
	'startup/event',
	'startup/sass',
	'startup/login',
	'startup/authorize',
	'startup/permission'
]; 

// Actions
$_['action_default']     = 'common/dashboard';

// Action Events
$_['action_event']       = [
	'controller/*/before' => [
		0 => 'event/language.before'
	],
	'controller/*/after' => [
		0 => 'event/language.after'
	],
	//'model/*/after' => [
	//	0 => 'event/debug.before'
	//],
	//'model/*/after' => [
	//	0 => 'event/debug.after'
	//],
	'view/*/before' => [
		999  => 'event/language',
		1000 => 'event/theme'
	],
    'language/*/after' => [
        0 => 'startup/language.after'
    ]
];

// Webhook Events
$_['webhook_event'] = array (
    'order/create' => [
        0 => 'event/order/create'
    ],
    'order/update' => [
        0 => 'event/order/update'
    ],
    'order/cancel' => [
        0 => 'event/order/cancel'
    ],
    'order/refund' => [
        0 => 'event/order/refund'
    ],
    'order/ship' => [
        0 => 'event/order/ship'
    ],
    'order/return' => [
        0 => 'event/order/return'
    ],
    'order/capture' => [
        0 => 'event/order/capture'
    ],
    'order/void' => [
        0 => 'event/order/void'
    ],
    'order/confirm' => [
        0 => 'event/order/confirm'
    ],
    'order/confirm' => [
        0 => 'event/order/confirm'
    ],
);