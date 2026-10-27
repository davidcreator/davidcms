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
$_['text_cache_clear_unsupported'] = 'El motor de caché activo no admite vaciado total desde el panel.';

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
$_['help_mail_engine']             = 'mail() envía vía PHP; SMTP para relé externo; capture graba en storage/logs/mail_capture.json (dev/CI).';
$_['help_mail_smtp_hostname']      = 'Ej.: smtp.hostinger.com';
$_['help_mail_smtp_username']      = 'Normalmente el usuario/correo completo.';
$_['help_mail_smtp_password']      = 'La contraseña actual nunca se muestra. Déjala vacía para conservarla; introduce un nuevo valor para sustituirla. Usa la casilla para borrarla. Si falla la validación, vuelve a introducir la nueva contraseña.';
$_['help_mail_smtp_port']          = '25 (inseguro), 465 (ssl), 587 (starttls) o 2525.';
$_['help_mail_smtp_timeout']       = 'Segundos antes del timeout (1–60).';
$_['help_mail_reply_to']           = 'Opcional. Cabecera Reply-To de los correos salientes.';
$_['help_cache_engine']            = 'Efectivo en el boot (lee rms_setting — F4-06); motor no disponible en el servidor cae en file con aviso en el log.';
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

// F4-05 (ADR 0029): prueba de envío (setting/setting.testMail)
$_['button_test_mail']          = 'Enviar correo de prueba';
$_['text_mail_test_hint']       = 'usa la configuración guardada; va al correo de tu usuario.';
$_['text_mail_test_subject']    = '[ReamurCMS] Prueba de envío';
$_['text_mail_test_body']       = "Si recibiste este mensaje, el motor de correo configurado está funcionando.";
$_['text_mail_test_sent']       = 'Correo de prueba enviado a %s.';
$_['text_mail_test_error']      = 'Fallo al enviar el correo de prueba: %s';
$_['error_mail_test_recipient'] = 'el usuario conectado no tiene un correo válido';

// Buttons
$_['button_save']        = 'Guardar configuración';
$_['button_clear_cache'] = 'Vaciar caché';
$_['button_back']        = 'Volver al dashboard';

// F8-03: tratamiento de secretos y protección de solicitudes
$_['text_smtp_password_set'] = 'Ya hay una contraseña configurada. Deja este campo vacío para conservarla.';
$_['text_smtp_password_unset'] = 'No hay una contraseña SMTP configurada.';
$_['entry_mail_smtp_password_clear'] = 'Borrar la contraseña SMTP guardada';
$_['error_csrf'] = 'El formulario ha caducado o no se pudo verificar. Recarga la página e inténtalo de nuevo.';
$_['text_close'] = 'Cerrar';

$_['fieldset_threejs'] = 'Escenas Three.js del catálogo';
$_['text_enabled'] = 'Activado';
$_['text_disabled'] = 'Desactivado';
$_['entry_three_hero_enabled'] = 'Partículas 3D de la página de inicio';
$_['help_three_hero_enabled'] = 'Activa o desactiva WebGL del hero. El fallback HTML/CSS permanece visible si se desactiva o WebGL no está disponible.';
$_['entry_three_hero_particle_count'] = 'Cantidad de partículas';
$_['help_three_hero_particle_count'] = 'Entre 50 y 800; los valores altos pueden afectar a dispositivos móviles.';
$_['entry_three_hero_color'] = 'Color de la escena';
$_['help_three_hero_color'] = 'Color hexadecimal aplicado a las partículas del hero y de la página 404.';
$_['entry_three_hero_speed'] = 'Velocidad de animación';
$_['help_three_hero_speed'] = 'Multiplicador entre 0,01 y 0,20; aplicado a las escenas del hero y de la 404.';
$_['entry_three_not_found_enabled'] = 'Escena 3D en la página 404';
$_['help_three_not_found_enabled'] = 'Activa o desactiva la escena de partículas 404; el texto y el fallback permanecen.';
$_['error_three_hero_enabled'] = 'Selección no válida para activar la escena de inicio.';
$_['error_three_hero_particle_count'] = 'La cantidad debe ser un entero entre 50 y 800.';
$_['error_three_hero_color'] = 'Introduce un color hexadecimal válido con formato #RRGGBB.';
$_['error_three_hero_speed'] = 'La velocidad debe estar entre 0,01 y 0,20, en incrementos de 0,01.';
$_['error_three_not_found_enabled'] = 'Selección no válida para activar la escena 404.';
$_['entry_three_portfolio_enabled'] = 'Galería 3D del portafolio';
$_['help_three_portfolio_enabled'] = 'Muestra una capa 3D decorativa detrás de las tarjetas accesibles de proyectos. Desactivada por defecto; el tilt CSS y los enlaces HTML siguen disponibles.';
$_['entry_three_portfolio_frame_limit'] = 'Máximo de paneles del portafolio';
$_['help_three_portfolio_frame_limit'] = 'De 1 a 12. Las portadas de proyectos se cargan de forma diferida cuando la escena se acerca al viewport.';
$_['error_three_portfolio_enabled'] = 'Selección no válida para activar la escena del portafolio.';
$_['entry_three_tools_enabled'] = 'Iconos 3D de herramientas';
$_['help_three_tools_enabled'] = 'Añade un movimiento 3D sutil a hasta cuatro tarjetas activas. Desactivado por defecto; las etiquetas y los enlaces HTML siguen disponibles.';
$_['error_three_tools_enabled'] = 'Selección no válida para la escena de herramientas.';
$_['error_three_portfolio_frame_limit'] = 'El límite de paneles debe ser un entero entre 1 y 12.';
