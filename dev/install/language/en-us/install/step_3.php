<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language en-us — wizard passo 3 (banco de dados).
 * PENDÊNCIA (ADR 0001): traduzir para pt-br e es-eu quando o idioma estiver habilitado.
 */

// Heading
$_['heading_title'] = 'Database Configuration';

// Text
$_['text_step'] = 'Step %s of 5';
$_['text_db']   = 'Enter the connection details of your MySQL/MariaDB database and the public URL of the site.';

// Entry
$_['entry_db_driver']   = 'DB Driver';
$_['entry_db_hostname'] = 'Hostname';
$_['entry_db_username'] = 'Username';
$_['entry_db_password'] = 'Password';
$_['entry_db_database'] = 'Database';
$_['entry_db_port']     = 'Port';
$_['entry_db_prefix']   = 'Table Prefix';
$_['entry_http_server'] = 'Site URL';

// Button
$_['button_back']     = 'Back';
$_['button_continue'] = 'Continue';

// Error
$_['error_db_driver']  = 'Invalid database driver!';
$_['error_required']   = 'This field is required!';
$_['error_db_prefix']  = 'Prefix must start with a letter and contain only lowercase letters, numbers and underscores (max 20 chars)!';
$_['error_http_server'] = 'Invalid site URL (e.g. https://example.com/)!';
$_['error_connect']    = 'Could not connect to the database:';
$_['error_db_exists']  = 'The database already contains tables with this prefix! Use an empty database or a different prefix to avoid overwriting an existing installation.';
$_['error_config']     = 'Could not write the configuration file:';
