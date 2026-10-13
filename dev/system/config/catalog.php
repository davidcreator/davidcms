<?php
// Site
$_['site_url']           = HTTP_SERVER;
//$_['site_ssl']		 = HTTPS_SERVER;

// Url
$_['url_autostart']		 = false;

// Database
$_['db_autostart']       = true;
$_['db_engine']          = DB_DRIVER; // mysqli, pdo or pgsql
$_['db_hostname']        = DB_HOSTNAME;
$_['db_username']        = DB_USERNAME;
$_['db_password']        = DB_PASSWORD;
$_['db_database']        = DB_DATABASE;
$_['db_port']            = DB_PORT;

// Session
$_['session_autostart']  = false;
$_['session_engine']     = 'db'; // db or file
$_['session_name']		 = 'RMSSESSIOM';

// Template
$_['template_engine']    = 'twig';
$_['template_directory'] = '';
$_['template_cache']	 = true;

// Autoload Libraries
$_['library_autoload']   = [
	'openbay'
];

// Actions
$_['action_pre_action']  = [
	'startup/setting',
	'startup/seo_url',
	'startup/session',
	'startup/language',
	'startup/customer',
	'startup/currency',
	'startup/tax',
	'startup/application',
	'startup/extension',
	'startup/startup',
	'startup/marketing',
	'startup/error',
	'startup/event',
	'startup/sass',
	'startup/api',
	'startup/maintenance'
];

// Action Events
$_['action_event']      = [
	'controller/*/before' => [
		0 => 'event/language.before',
	//	1 => 'event/debug.before'
	],
	'controller/*/after' => [
		0 => 'event/language.after',
	//	2 => 'event/debug.after'
	],
	'view/*/before' => [
		500 => 'event/theme',
		998 => 'event/language'
	],
	'language/*/after' => [
		0 => 'startup/language.after',
		1 => 'event/translation'
	]
];

// Webhook Events
$_['action_webhook']     = [
	'model/checkout/order/addOrderHistory/before' => [
		'catalog/product/order'
	],
	'model/checkout/order/editOrder/before' => [
		'sale/order/edit'
	],
	'model/checkout/order/addOrderHistory/before' => [
		'sale/order/addOrderHistory'
	],
	'model/checkout/order/addOrder/after' => [
		'sale/order/add'
	],
];