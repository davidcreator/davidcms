<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * communication/contact_message — idioma del buzón de contacto (es-EU).
 * Semántica del estado según Dicionário-de-Dados §9: 0 nuevo / 1 leído / 2 respondido.
 */

// Headings & texts
$_['heading_title']         = 'Mensajes de Contacto';
$_['text_list']             = 'Mensajes enviados desde el formulario de contacto del sitio. Las respuestas salen por e-mail — marca el mensaje como Respondido al terminar.';
$_['text_no_results']       = 'Aún no hay mensajes.';
$_['text_empty_filter']     = 'No hay mensajes con este estado.';
$_['text_confirm_delete']   = '¿Eliminar este mensaje permanentemente?';
$_['text_message_detail']   = 'Contenido de solo lectura. El botón Leer de la lista marca mensajes nuevos como Leído cuando hay permiso de modificar.';
$_['text_actions']          = 'Acciones';
$_['text_all']              = 'Todos';
$_['text_new']              = 'Nuevo';
$_['text_read']             = 'Leído';
$_['text_replied']          = 'Respondido';
$_['text_success_status']   = 'Estado del mensaje actualizado.';
$_['text_success_delete']   = 'Mensaje eliminado.';

// Columns
$_['column_name']           = 'Nombre';
$_['column_email']          = 'E-mail';
$_['column_subject']        = 'Asunto';
$_['column_status']         = 'Estado';
$_['column_date_added']     = 'Recibido';
$_['column_action']         = 'Acción';

// Entries (read view)
$_['entry_name']            = 'Nombre';
$_['entry_email']           = 'E-mail';
$_['entry_telephone']       = 'Teléfono';
$_['entry_subject']         = 'Asunto';
$_['entry_message']         = 'Mensaje';
$_['entry_status']          = 'Estado';
$_['entry_ip']              = 'IP';
$_['entry_date_added']      = 'Recibido en';

// Buttons
$_['button_read']           = 'Leer';
$_['button_delete']         = 'Eliminar';
$_['button_back']           = 'Volver a la lista';
$_['button_reply']          = 'Responder por e-mail';
$_['button_mark_replied']   = 'Marcar como Respondido';
$_['button_mark_read']      = 'Marcar como Leído';
$_['button_mark_new']       = 'Marcar como Nuevo';

// Errors
$_['error_status']          = 'Estado inválido (permitidos: Nuevo, Leído, Respondido).';

// Fase 8 / Tarefa 10 — caixa, busca/paginação e leitura POST+ACL+CSRF.
$_['text_empty_title'] = 'No se encontraron mensajes';
$_['text_empty_hint'] = 'Ajuste la búsqueda o el filtro de estado.';
$_['text_search_placeholder'] = 'Buscar nombre, e-mail o asunto…';
$_['text_showing'] = 'Mostrando %d–%d de %d mensajes';
$_['text_readonly_notice'] = 'Puede visualizar; cambiar el estado o eliminar requiere permiso de modificar.';
$_['button_filter'] = 'Filtrar';
$_['button_reset'] = 'Limpiar';
$_['button_close'] = 'Cerrar';
$_['button_first_page'] = '« Primera';
$_['button_last_page'] = 'Última »';
$_['button_next_page'] = 'Siguiente ›';
$_['button_previous_page'] = '‹ Anterior';
$_['error_csrf'] = 'Token de sesión no válido. Recargue la lista e inténtelo de nuevo.';
