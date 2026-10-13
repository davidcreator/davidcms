<?php
// Site
$_['site_url']          = HTTP_SERVER;
//$_['site_ssl']        = HTTPS_SERVER;

// Language
$_['language_code']     = 'en-gb';
$_['language_autoload'] = ['en-gb'];

// Session
$_['session_engine']    = 'file';
$_['session_autostart'] = true;
$_['session_name']	    = 'RMSSESSION';

// Template
$_['template_engine']   = 'twig';
$_['template_cache']    = true;

// Error
$_['error_display']     = true;

// Actions
$_['action_default']    = 'install/step_1';
$_['action_router']     = 'start/router';
$_['action_error']      = 'error/not_found';
$_['action_pre_action'] = [
							'startup/install',
							'startup/upgrade',
							'startup/database'
						  ];

// Action Events
$_['action_event']      = [];
