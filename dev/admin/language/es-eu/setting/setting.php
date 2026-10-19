<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/setting — Pantalla de configuración operativa (es-eu).
 */

// Headings
$_['heading_title']      = 'Configuración';
$_['text_settings']      = 'Llaves operativas que sobrescriben los valores de la instalación. Aplicado en la próxima petición.';
$_['text_edit']          = 'Editar = exige ACL modify en "setting/setting".';
$_['text_success']       = 'Configuración guardada.';
$_['text_cache_cleared'] = 'Caché vaciada.';

// Fieldsets
$_['fieldset_store']     = 'Tienda';
$_['fieldset_mail']      = 'Correo';
$_['fieldset_cache']     = 'Caché';

// Entries
$_['entry_config_name']             = 'Nombre de la tienda';
$_['entry_config_meta_title']       = 'Meta título por defecto';
$_['entry_config_meta_description'] = 'Meta descripción por defecto';
$_['entry_mail_engine']             = 'Motor de correo';
$_['entry_mail_smtp_hostname']      = 'Host SMTP';
$_['entry_mail_smtp_username']      = 'Usuario SMTP';
$_['entry_mail_smtp_password']      = 'Contraseña SMTP';
$_['entry_mail_smtp_port']          = 'Puerto SMTP';
$_['entry_mail_smtp_timeout']       = 'Timeout SMTP (s)';
$_['entry_mail_reply_to']           = 'Reply-To';
$_['entry_cache_engine']            = 'Motor de caché';
$_['entry_cache_expire']            = 'TTL de la caché (s)';

// Helps
$_['help_config_name']             = 'Usado en <title> y correos alineados a la marca.';
$_['help_config_meta_title']       = 'Título de respaldo cuando la página no tiene uno (120 caracteres).';
$_['help_config_meta_description'] = '<meta name="description"> para páginas sin uno propio (ideal ~160; el campo valida hasta 255).';
$_['help_mail_engine']             = 'mail() renderiza local; SMTP para relé externo (en cola hasta que llegue el envío).';
$_['help_mail_smtp_hostname']      = 'Ej.: smtp.hostinger.com';
$_['help_mail_smtp_username']      = 'Normalmente el usuario/correo completo.';
$_['help_mail_smtp_password']      = 'Nunca se muestra de nuevo. Deja espacios si pertenecen a la contraseña.';
$_['help_mail_smtp_port']          = '25 (inseguro), 465 (ssl), 587 (starttls) o 2525.';
$_['help_mail_smtp_timeout']       = 'Segundos antes del timeout (1–60).';
$_['help_mail_reply_to']           = 'Opcional. Cabecera Reply-To de los correos salientes.';
$_['help_cache_engine']            = 'Aplicado en la próxima petición (caché montado pronto en el boot).';
$_['help_cache_expire']            = '60 a 2.592.000 segundos (máx 30 días).';

// Errors
$_['error_config_name']            = 'El nombre de la tienda es obligatorio.';
$_['error_mail_engine']            = 'Motor de correo inválido.';
$_['error_smtp_hostname_required'] = 'Host SMTP obligatorio con motor=smtp.';
$_['error_smtp_port']              = 'Puertos aceptados: 25, 465, 587, 2525.';
$_['error_smtp_timeout']           = 'El timeout debe ser de 1 a 60 segundos.';
$_['error_mail_reply_to']          = 'Correo de Reply-To inválido.';
$_['error_cache_engine']           = 'Motor de caché inválido.';
$_['error_cache_expire']           = 'El TTL debe ser de 60s a 30 días.';

// Buttons
$_['button_save']        = 'Guardar configuración';
$_['button_clear_cache'] = 'Vaciar caché';
$_['button_back']        = 'Volver al dashboard';
