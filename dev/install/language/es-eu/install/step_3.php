<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language es-eu — wizard paso 3 (base de datos). Traducido del en-us (ADR 0001).
 */

// Heading
$_['heading_title'] = 'Configuración de la Base de Datos';

// Text
$_['text_step'] = 'Paso %s de 5';
$_['text_db']   = 'Introduzca los datos de conexión de su base de datos MySQL/MariaDB y la URL pública del sitio.';

// Entry
$_['entry_db_driver']   = 'Controlador de BD';
$_['entry_db_hostname'] = 'Servidor';
$_['entry_db_username'] = 'Usuario';
$_['entry_db_password'] = 'Contraseña';
$_['entry_db_database'] = 'Base de Datos';
$_['entry_db_port']     = 'Puerto';
$_['entry_db_prefix']   = 'Prefijo de Tablas';
$_['entry_http_server'] = 'URL del Sitio';

// Button
$_['button_back']     = 'Volver';
$_['button_continue'] = 'Continuar';

// Error
$_['error_db_driver']   = '¡Controlador de base de datos no válido!';
$_['error_required']    = '¡Este campo es obligatorio!';
$_['error_db_prefix']   = '¡El prefijo debe empezar por una letra y contener solo letras minúsculas, números y guiones bajos (máx. 20 caracteres)!';
$_['error_http_server'] = '¡URL del sitio no válida (p. ej. https://ejemplo.com/)!';
$_['error_connect']     = 'No se pudo conectar con la base de datos:';
$_['error_db_exists']   = '¡La base de datos ya contiene tablas con este prefijo! Use una base de datos vacía o un prefijo diferente para no sobrescribir una instalación existente.';
$_['error_config']      = 'No se pudo escribir el archivo de configuración:';
