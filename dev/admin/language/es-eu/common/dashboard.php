<?php
/**
 * common/dashboard (ADMIN) — panel inicial (F8-01, ADR 0045) — es-eu
 */

$_['heading_title']         = 'Panel';
$_['text_welcome_overview'] = 'Tu operación de un vistazo — los contadores respetan tus permisos.';
$_['text_stats_overview']   = 'Vista general';
$_['text_quick_actions']    = 'Acciones rápidas';
$_['text_latest_posts']     = 'Últimas entradas';
$_['text_latest_messages']  = 'Últimos mensajes';
$_['text_view_all']         = 'Ver todo';
$_['text_no_posts']         = 'Nada por aquí todavía — en cuanto haya contenido, aparecerá aquí.';
$_['text_no_messages']      = 'Sin mensajes.';
$_['text_no_subject']       = '(sin asunto)';
$_['text_panel_user']       = 'Usuario';
$_['text_panel_tech']       = 'Cuenta y sistema';

// Panel F1-F7 legacy module list (mantido — contrato de baterias F2-F7)
$_['text_modules']              = 'Módulos';
$_['text_manage_portfolio']     = 'Portafolio';
$_['text_manage_categories']    = 'Categorías';
$_['text_manage_pages']         = 'Páginas';
$_['text_manage_blog']          = 'Blog';
$_['text_manage_testimonials']  = 'Testimonios';
$_['text_manage_client_logos']  = 'Logotipos de Clientes';
$_['text_manage_tools']         = 'Herramientas';
$_['text_manage_orders']        = 'Pedidos';
$_['text_manage_messages']      = 'Mensajes';
$_['text_manage_webhooks']      = 'Webhooks';
$_['text_manage_marketplace']   = 'Mercado Pago Marketplace';
$_['text_manage_users']         = 'Usuarios';
$_['text_manage_user_groups']   = 'Grupos de Usuarios';
$_['text_manage_settings']      = 'Configuración';
$_['text_manage_site_info']     = 'Información del Sitio';
$_['text_manage_languages']     = 'Idiomas';

// Perfil / panel técnico
$_['text_profile']          = 'Perfil';
$_['text_username']         = 'Usuario';
$_['text_email']            = 'Correo';
$_['text_user_group']       = 'Grupo de usuarios';
$_['text_manage_profile']   = 'Editar perfil';
$_['text_acl']              = 'ACL';
$_['text_access_routes']    = 'Rutas de acceso';
$_['text_modify_routes']    = 'Rutas de modificación';
$_['text_acl_note']         = 'Gestionada en Usuarios → Grupos. Cards y menús solo aparecen para rutas permitidas.';
$_['text_cache_indicator']  = 'Caché: %s · TTL %ss';
$_['text_cache_fallback']   = 'el panel configurado "%s" — fallback active';
$_['text_version']          = 'ReamurCMS v%s';
$_['button_logout']         = 'Cerrar sesión';

// Cards
$_['card_posts']         = 'Entradas publicadas';
$_['card_pages']         = 'Páginas CMS';
$_['card_projects']      = 'Proyectos';
$_['card_orders']        = 'Pedidos y presupuestos';
$_['card_messages']      = 'Mensajes';
$_['card_testimonials']  = 'Testimonios';
$_['card_tools']         = 'Herramientas';
$_['card_webhook_fails'] = 'Fallos de webhook';
$_['card_today']         = 'hoy: %s';
$_['card_7d']            = 'últimos 7 días: %s';
$_['card_24h']           = 'últimas 24h: %s';
$_['card_30d']           = 'últimos 30 días: %s';

// Acciones rápidas
$_['action_new_post']    = 'Nueva entrada';
$_['action_new_page']    = 'Páginas (CMS)';
$_['action_new_project'] = 'Nuevo proyecto';
$_['action_new_tool']    = 'Herramientas';
$_['action_import_wp']   = 'Importar de WordPress';

// ADR 0088 (S3)
$_['warning_error_display'] = 'La visualización de errores está activada en esta instalación: si algo falla, los detalles técnicos (archivo, línea, SQL) se muestran en pantalla a cualquier visitante. En producción, define REAMUR_ERROR_DISPLAY como false en los archivos config.php de catalog/ y admin/.';

// ADR 0089 (S5): conexão, proxy e CDN
$_['text_conn_heading']           = 'Conexión';
$_['text_conn_ip']                = 'Tu IP para el sistema';
$_['text_conn_peer']              = 'Conexión recibida de';
$_['text_conn_https']             = 'HTTPS';
$_['text_conn_https_no']          = 'no';
$_['text_conn_https_direct']      = 'sí, visto por el servidor';
$_['text_conn_https_proxy']       = 'sí, informado por el proxy';
$_['text_conn_cookie']            = 'Cookie de sesión';
$_['text_conn_cookie_secure']     = 'con Secure';
$_['text_conn_cookie_plain']      = 'sin Secure';
$_['text_conn_trust']             = 'Proxies de confianza';
$_['text_conn_trust_none']        = 'no configurado';
$_['text_conn_trust_list']        = 'lista con %d entrada(s)';
$_['text_conn_trust_hops']        = '%d proxy(s) delante';
$_['text_conn_note']              = 'Si las dos direcciones son iguales y no son tu IP, el sitio está detrás de un proxy o CDN que aún no se ha declarado en config.php.';
$_['warning_proxy_unconfigured']  = 'La conexión llegó desde %s con cabeceras de proxy. Si el sitio está detrás de una CDN o un proxy inverso, hoy todos los visitantes cuentan como una sola IP en el límite de intentos de acceso, contacto y presupuesto. Define REAMUR_TRUSTED_PROXIES o REAMUR_TRUSTED_PROXY_HOPS en catalog/config.php y admin/config.php.';
$_['warning_proxy_untrusted_peer']= 'La conexión llegó desde %s, que no está en REAMUR_TRUSTED_PROXIES. Las cabeceras de proxy se ignoraron. Si esa dirección es de tu CDN, añádela a la lista en los dos archivos config.php.';
$_['warning_proxy_invalid']       = 'REAMUR_TRUSTED_PROXIES tiene entradas que no son dirección ni rango y se ignoraron: %s';
$_['warning_proxy_hops_direct']   = 'REAMUR_TRUSTED_PROXY_HOPS vale %d, pero esta conexión llegó sin X-Forwarded-For. Si se puede acceder al servidor sin pasar por el proxy, la IP del visitante se puede falsificar: usa REAMUR_TRUSTED_PROXIES con las direcciones del proxy.';
