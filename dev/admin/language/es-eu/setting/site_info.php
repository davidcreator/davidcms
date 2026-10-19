<?php
/**
 * Language: es-eu — setting/site_info (F4-03, ADR 0026)
 */

// Heading
$_['heading_title'] = 'Información del sitio';

// Text
$_['text_site_info'] = 'Datos institucionales mostrados en el sitio público: contacto, dirección, pie y redes sociales.';
$_['text_success']   = '¡Información del sitio guardada correctamente!';

// Fieldsets
$_['fieldset_contact']   = 'Contacto (global)';
$_['fieldset_address']   = 'Dirección (por idioma)';
$_['fieldset_copyright'] = 'Pie de página (por idioma)';
$_['fieldset_social']    = 'Redes sociales (globales)';

// Entries — globales
$_['entry_config_contact_email']           = 'E-mail de contacto';
$_['entry_config_contact_whatsapp_text']   = 'WhatsApp — texto visible';
$_['entry_config_contact_whatsapp_href']   = 'WhatsApp — número del enlace (wa.me)';
$_['entry_config_social_facebook_href']    = 'URL de Facebook';
$_['entry_config_social_instagram_href']   = 'URL de Instagram';
$_['entry_config_social_pinterest_href']   = 'URL de Pinterest';
$_['entry_config_social_x_href']           = 'URL de X (Twitter)';
$_['entry_config_social_google_business_href'] = 'URL de Google Business';
$_['entry_config_social_redbubble_href']   = 'URL de Redbubble';

// Entries — por idioma
$_['entry_contact_address'] = 'Texto de la dirección';
$_['entry_copyright']       = 'Texto de copyright';

// Helps
$_['help_config_contact_email']         = 'Destino del formulario de contacto y dirección mostrada en el sitio.';
$_['help_config_contact_whatsapp_text'] = 'Texto mostrado junto al icono de WhatsApp (ej.: +55 11 99999-0000).';
$_['help_config_contact_whatsapp_href'] = 'Solo dígitos (país + área + número). Usado en https://wa.me/<número>. Vacío mantiene el icono, pero sin destino de enlace.';
$_['help_contact_address']              = 'Línea de dirección mostrada en la sección Contacto.';
$_['help_copyright']                    = 'El marcador %s se reemplaza por el año actual — consérvelo en el texto.';
$_['help_fallback']                     = 'Si un campo queda intencionalmente vacío en la base, el sitio cae al texto del archivo de idioma (degradación silenciosa — ADR 0023).';

// Errors
$_['error_required']       = '¡%s es obligatorio!';
$_['error_email']          = '¡%s debe ser un correo válido!';
$_['error_whatsapp_href']  = '¡El número del enlace de WhatsApp debe contener solo dígitos (8–16)!';
$_['error_copyright_year'] = '¡El copyright debe contener el marcador %s (año actual)!';
$_['error_url']            = '¡%s debe comenzar con http:// o https://!';

// Buttons
$_['button_save'] = 'Guardar';
$_['button_back'] = 'Volver';
