<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language es-eu — paso 3 del wizard: Configuración unificada (ADR 0007).
 * Traducido del en-us (ADR 0001).
 */

// Heading
$_['heading_title'] = 'Configuración';

// Text
$_['text_step_3']            = 'Introduzca los datos de su base de datos y administración';
$_['text_db_connection']     = '1. Introduzca los datos de conexión a su base de datos.';
$_['text_db_administration'] = '2. Introduzca un nombre de usuario y una contraseña para la administración.';
$_['text_db_ok']             = 'Conexión con la base de datos realizada con éxito.';
$_['text_password_hint']     = 'Use al menos 8 caracteres. Se aceptan letras, números y símbolos.';

// Text (JavaScript de la pantalla)
$_['text_js_password_mismatch']  = 'Las contraseñas no coinciden.';
$_['text_js_password_weak']       = 'Débil';
$_['text_js_password_medium']     = 'Media';
$_['text_js_password_strong']     = 'Fuerte';
$_['text_js_password_generated']  = 'Contraseña generada — guárdela en un lugar seguro';
$_['text_js_db_testing']          = 'Probando…';
$_['text_js_username_hint']       = 'Introduzca un nombre de usuario de al menos 3 caracteres.';

// Entry
$_['entry_db_driver']   = 'Driver de Base de Datos';
$_['entry_db_hostname'] = 'Servidor';
$_['entry_db_username'] = 'Usuario';
$_['entry_db_password'] = 'Contraseña';
$_['entry_db_database'] = 'Base de Datos';
$_['entry_db_port']     = 'Puerto';
$_['entry_db_prefix']   = 'Prefijo de Tablas';
$_['entry_http_server'] = 'URL del Sitio';
$_['entry_username']    = 'Nombre de Usuario';
$_['entry_firstname']   = 'Nombre';
$_['entry_lastname']    = 'Apellidos';
$_['entry_email']       = 'E-mail';
$_['entry_password']    = 'Contraseña';
$_['entry_confirm']     = 'Confirmar Contraseña';

// Button
$_['button_back']            = 'Volver';
$_['button_continue']        = 'Continuar';
$_['button_test_db']         = 'Probar conexión';
$_['button_generate']        = 'Generar contraseña';
$_['button_toggle_password'] = 'Mostrar/ocultar contraseña';

// Error
$_['error_db_driver']   = '¡Driver de Base de Datos obligatorio!';
$_['error_required']    = '¡Campo obligatorio!';
$_['error_db_prefix']   = '¡El prefijo de tablas solo puede contener letras minúsculas a-z, números 0-9 y guiones bajos, y debe empezar con letra!';
$_['error_db_port']     = '¡Puerto de la base de datos obligatorio!';
$_['error_http_server'] = '¡Introduzca una URL del Sitio válida (ej.: https://ejemplo.com/)!';
$_['error_db_connect']  = 'Error: No se pudo conectar a la base de datos. Revise servidor, usuario y contraseña.';
$_['error_db_exists']   = 'La base de datos ya contiene tablas con este prefijo. Use una base vacía para no sobrescribir una instalación existente.';
$_['error_config']      = 'No se pudieron escribir los archivos de configuración:';
$_['error_username']    = '¡El nombre de usuario debe tener entre 3 y 32 caracteres!';
$_['error_firstname']   = '¡El nombre debe tener entre 1 y 32 caracteres!';
$_['error_lastname']    = '¡Los apellidos deben tener entre 1 y 32 caracteres!';
$_['error_email']       = '¡Dirección de e-mail no válida!';
$_['error_password']    = '¡La contraseña debe tener al menos 8 caracteres!';
$_['error_confirm']     = '¡La confirmación de la contraseña no coincide!';
