<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * sale/order — Idiomas del módulo Pedidos/Presupuestos (es-EU; en-US canónico del ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Pedidos y Presupuestos';
$_['text_list']            = 'Pedidos de servicio y solicitudes de presupuesto. Los totales se recalculan al guardar (cantidad × precio por ítem).';
$_['text_no_results']      = 'Aún no hay pedidos. Cree el primero.';
$_['text_confirm_delete']  = '¿Eliminar este pedido? Se quitarán sus ítems y el historial de estado.';
$_['text_add']             = 'Agregar Pedido';
$_['text_edit']            = 'Editar Pedido';
$_['text_customer']        = 'Cliente';
$_['text_products']        = 'Ítems';
$_['text_data']            = 'Datos del Pedido';
$_['text_history']         = 'Historial de Estado';
$_['text_status_hint']     = 'Cada cambio de estado se anexa al historial abajo.';
$_['text_success_add']     = 'Pedido creado.';
$_['text_success_edit']    = 'Pedido actualizado.';
$_['text_success_delete']  = 'Pedido eliminado.';
$_['text_no_products']     = 'Aún no hay ítems — agregue la primera fila.';

// Columns
$_['column_order']         = 'Pedido';
$_['column_customer']      = 'Cliente';
$_['column_email']         = 'E-mail';
$_['column_total']         = 'Total';
$_['column_status']        = 'Estado';
$_['column_date_added']    = 'Agregado';
$_['column_date_modified'] = 'Modificado';
$_['column_action']        = 'Acción';
$_['column_comment']       = 'Comentario';

// Entries
$_['entry_firstname']  = 'Nombre';
$_['entry_lastname']   = 'Apellido';
$_['entry_email']      = 'E-mail';
$_['entry_telephone']  = 'Teléfono / WhatsApp';
$_['entry_product']    = 'Ítem (servicio/proyecto)';
$_['entry_quantity']   = 'Cant';
$_['entry_price']      = 'Precio Unitario';
$_['entry_total']      = 'Total de Fila';
$_['entry_comment']    = 'Briefing / Notas';
$_['entry_status']     = 'Estado';

// Buttons
$_['button_add']          = 'Agregar';
$_['button_edit']         = 'Editar';
$_['button_delete']       = 'Eliminar';
$_['button_save']         = 'Guardar';
$_['button_cancel']       = 'Cancelar';
$_['button_add_product']  = 'Agregar Ítem';
$_['button_remove']       = 'Quitar';

// Errors
$_['error_firstname']     = 'El nombre es obligatorio (1 a 32 caracteres).';
$_['error_email']         = 'Un e-mail válido es obligatorio (hasta 96 caracteres).';
$_['error_product']       = 'Fila de ítem incompleta: rellene el nombre del ítem o limpie la fila.';
$_['error_status']        = 'Estado seleccionado inválido.';
$_['error_permission']    = 'No tiene permiso para modificar los pedidos.';
