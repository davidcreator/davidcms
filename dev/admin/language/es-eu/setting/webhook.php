<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/webhook — idioma del CRUD de webhooks + log de entregas (es-EU).
 */

// Headings & texts
$_['heading_title']        = 'Webhooks';
$_['text_list']            = 'Endpoints HTTPS notificados en los eventos del sitio (pedidos, contacto, productos). Desactivar pausa el envío sin perder el historial.';
$_['text_form']            = 'Registra el endpoint que recibirá los eventos firmados (HMAC con el secret de abajo).';
$_['text_no_results']      = 'Aún no hay webhooks. Crea el primero.';
$_['text_confirm_delete']  = '¿Eliminar este webhook? TODO su historial de entregas se elimina también.';
$_['text_enabled']         = 'Activo';
$_['text_disabled']        = 'Inactivo';
$_['text_trigger_hint']    = 'Sintaxis de evento: order_add, order_edit, contact_add, product_* ... los comodines casan prefijos de evento.';
$_['text_url_hint']        = 'Solo HTTPS — el payload firmado viaja protegido.';
$_['text_secret_hint'] = 'El secreto HMAC nunca se muestra. Déjalo vacío para conservarlo o introduce un nuevo valor para sustituirlo. Si falla la validación, vuelve a introducir el nuevo secreto.';
$_['text_status_hint']     = 'Los endpoints inactivos conservan su historial pero no reciben nuevas entregas.';
$_['text_logs_all']        = 'Todas las entregas';
$_['text_logs_of']         = 'Entregas de %s (%s)';
$_['text_logs_hint'] = 'Filtra por ID de entrega, evento o código HTTP y usa los controles de página para recorrer los resultados. 2xx = éxito; otros códigos pueden requerir revisión o reintento.';
$_['text_no_logs']         = 'Aún no hay entregas.';
$_['text_log_detail']      = 'Detalle de la entrega (solo lectura).';
$_['text_success_add']     = 'Webhook creado.';
$_['text_success_edit']    = 'Webhook actualizado.';
$_['text_success_delete']  = 'Webhook eliminado.';

// Columns
$_['column_trigger']       = 'Evento';
$_['column_url']           = 'URL';
$_['column_secret']        = 'Secret';
$_['column_description']   = 'Descripción';
$_['column_status']        = 'Estado';
$_['column_deliveries']    = 'Entregas';
$_['column_delivery']      = 'Entrega';
$_['column_http_code']     = 'HTTP';
$_['column_duration']      = 'Duración';
$_['column_attempt']       = 'Intento';
$_['column_date_added']    = 'Fecha';
$_['column_action']        = 'Acción';

// Entries
$_['entry_trigger']        = 'Evento (trigger)';
$_['entry_url']            = 'URL del endpoint';
$_['entry_secret']         = 'Secret HMAC';
$_['entry_description']    = 'Descripción';
$_['entry_status']         = 'Activo';
$_['entry_delivery']       = 'ID de entrega';
$_['entry_http_code']      = 'Código HTTP';
$_['entry_duration']       = 'Duración';
$_['entry_attempt']        = 'Intento';
$_['entry_date_added']     = 'Entregado en';
$_['entry_payload']        = 'Payload';

// Buttons
$_['button_add']           = 'Añadir';
$_['button_edit']          = 'Editar';
$_['button_delete']        = 'Eliminar';
$_['button_save']          = 'Guardar';
$_['button_cancel']        = 'Cancelar';
$_['button_logs']          = 'Entregas';
$_['button_view']          = 'Ver';
$_['button_back']          = 'Volver a webhooks';
$_['button_back_logs']     = 'Volver a entregas';
$_['button_generate']      = 'Generar';

// Errors
$_['error_trigger']        = 'Evento inválido: usa la sintaxis de evento en minúsculas (ej.: order_add, order_*, contact_add), hasta 64 caracteres.';
$_['error_url_https']      = 'La URL debe empezar con https:// (hasta 255 caracteres).';
$_['error_secret']         = 'El secret debe tener de 16 a 64 caracteres.';
$_['error_description']    = 'La descripción es obligatoria (1 a 255 caracteres).';
$_['error_duplicate']      = 'Este par evento + URL ya existe (el par debe ser único).';

// F8-03: filtros, paginación y tratamiento de secretos
$_['text_filter'] = 'Filtrar webhooks';
$_['entry_filter_trigger'] = 'Evento / trigger';
$_['entry_filter_url'] = 'URL del endpoint';
$_['text_all_status'] = 'Todos los estados';
$_['button_filter'] = 'Aplicar filtros';
$_['button_reset'] = 'Limpiar';
$_['text_showing'] = 'Mostrando %s–%s de %s';
$_['text_secret_set'] = 'Hay un secreto de firma configurado. Nunca se muestra en este formulario.';
$_['text_secret_unset'] = 'No hay un secreto de firma configurado. El endpoint se desactiva hasta que se defina uno.';
$_['text_secret_badge_set'] = 'Configurado · oculto';
$_['text_secret_badge_unset'] = 'Ausente · inactivo';
$_['entry_secret_clear'] = 'Borrar el secreto guardado y desactivar este endpoint';
$_['text_secret_clear_help'] = 'Al borrar el secreto, el endpoint se desactiva automáticamente para no enviar entregas sin firma.';
$_['error_csrf'] = 'El formulario ha caducado o no se pudo verificar. Recarga la página e inténtalo de nuevo.';
$_['text_close'] = 'Cerrar';
$_['text_page_first'] = '« Primera';
$_['text_page_last'] = 'Última »';
$_['text_page_next'] = 'Siguiente ›';
$_['text_page_prev'] = '‹ Anterior';
