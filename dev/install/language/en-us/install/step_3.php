<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language en-us — wizard step 3: unified Configuration (ADR 0007).
 */

// Heading
$_['heading_title'] = 'Configuration';

// Text
$_['text_step_3']            = 'Enter your database and administration details';
$_['text_db_connection']     = '1. Please enter your database connection details.';
$_['text_db_administration'] = '2. Please enter a username and password for the administration.';
$_['text_db_ok']             = 'Database connection successful.';
$_['text_password_hint']     = 'Use at least 8 characters. Letters, numbers and symbols are accepted.';

$_['text_http_server_hint'] = 'Public address of the STORE. We prefill it from your browser address (catalog/ sits next to install/). The admin panel is derived automatically to .../admin/. If the store answers at the domain root, enter e.g. https://example.com/.';

// Text (JavaScript da tela — nunca hardcoded no Twig)
$_['text_js_password_mismatch']  = 'Passwords do not match.';
$_['text_js_password_weak']       = 'Weak';
$_['text_js_password_medium']     = 'Medium';
$_['text_js_password_strong']     = 'Strong';
$_['text_js_password_generated']  = 'Password generated — store it safely';
$_['text_js_db_testing']          = 'Testing…';
$_['text_js_username_hint']       = 'Enter a username with at least 3 characters.';

// Entry
$_['entry_db_driver']   = 'DB Driver';
$_['entry_db_hostname'] = 'Hostname';
$_['entry_db_username'] = 'Username';
$_['entry_db_password'] = 'Password';
$_['entry_db_database'] = 'Database';
$_['entry_db_port']     = 'Port';
$_['entry_db_prefix']   = 'Table Prefix';
$_['entry_http_server'] = 'Site URL';
$_['entry_username']    = 'Username';
$_['entry_firstname']   = 'First Name';
$_['entry_lastname']    = 'Last Name';
$_['entry_email']       = 'E-mail';
$_['entry_password']    = 'Password';
$_['entry_confirm']     = 'Confirm Password';

// Button
$_['button_back']            = 'Back';
$_['button_continue']        = 'Continue';
$_['button_test_db']         = 'Test connection';
$_['button_generate']        = 'Generate password';
$_['button_toggle_password'] = 'Show/hide password';

// Error
$_['error_db_driver']   = 'DB Driver required!';
$_['error_required']    = 'Field required!';
$_['error_db_prefix']   = 'The table prefix may only contain lowercase a-z, numbers 0-9 and underscores, starting with a letter!';
$_['error_db_port']     = 'Database port required!';
$_['error_http_server'] = 'A valid Site URL is required (ex.: https://example.com/)!';
$_['error_db_connect']  = 'Error: could not connect to the database. Check server, user and password.';
$_['error_db_exists']   = 'The database already contains tables with this prefix. Use an empty database to avoid overwriting an existing installation.';
$_['error_config']      = 'Could not write the configuration files:';
$_['error_username']    = 'Username must be between 3 and 32 characters!';
$_['error_firstname']   = 'First name must be between 1 and 32 characters!';
$_['error_lastname']    = 'Last name must be between 1 and 32 characters!';
$_['error_email']       = 'Invalid e-mail address!';
$_['error_password']    = 'Password must be at least 8 characters!';
$_['error_confirm']     = 'Password confirmation does not match!';
