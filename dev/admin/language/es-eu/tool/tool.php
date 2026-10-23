<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * tool/tool — Idiomas del módulo Herramientas (es-EU; en-US canónico del ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Herramientas Conectadas';
$_['text_list']            = 'Herramientas e integraciones mostradas en el sitio (iconos + enlaces). El contenido por idioma se edita en el formulario de la herramienta.';
$_['text_no_results']      = 'Aún no hay herramientas. Cree la primera.';
$_['text_confirm_delete']  = '¿Eliminar esta herramienta? Se quitarán sus descripciones y el archivo del icono.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Deshabilitado';
$_['text_add']             = 'Agregar Herramienta';
$_['text_edit']            = 'Editar Herramienta';
$_['text_general']         = 'Contenido';
$_['text_data']            = 'Datos';
$_['text_languages_note']  = 'Use las pestañas de idioma. El nombre en en-US es obligatorio.';
$_['text_image']           = 'Icono';
$_['text_upload_hint']     = 'JPG, PNG, WebP o GIF hasta 2 MB. El archivo se guarda ahora; guarde el formulario para vincularlo a la herramienta.';
$_['text_success_add']     = 'Herramienta creada.';
$_['text_success_edit']    = 'Herramienta actualizada.';
$_['text_success_delete']  = 'Herramienta eliminada.';
$_['text_placeholder']     = '(sin icono)';
$_['text_none']            = '(ninguno)';

// Columns
$_['column_image']         = 'Icono';
$_['column_name']          = 'Nombre';
$_['column_vendor']        = 'Proveedor';
$_['column_url']           = 'URL';
$_['column_status']        = 'Estado';
$_['column_sort_order']    = 'Orden';
$_['column_date_added']    = 'Agregado';
$_['column_action']        = 'Acción';

// Entries
$_['entry_name']        = 'Nombre';
$_['entry_description'] = 'Descripción';
$_['entry_vendor']      = 'Proveedor';
$_['entry_url']         = 'URL';
$_['entry_image']       = 'Icono';
$_['entry_status']      = 'Estado';
$_['entry_sort_order']  = 'Orden';

// Buttons
$_['button_add']        = 'Agregar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Eliminar';
$_['button_save']       = 'Guardar';
$_['button_cancel']     = 'Cancelar';
$_['button_upload']     = 'Subir';

// Errors
$_['error_name']          = 'El nombre en en-US es obligatorio (1 a 128 caracteres).';
$_['error_url']           = 'La URL debe empezar con http:// o https:// (o quedar vacía).';
$_['error_image_path']    = 'El icono debe ser un archivo subido mediante el botón Subir.';
$_['error_upload']        = 'Falló la subida — revise el archivo e inténtelo de nuevo.';
$_['error_upload_method'] = 'La subida debe ser un POST.';
$_['error_upload_size']   = 'El archivo excede el límite de %s.';
$_['error_upload_type']   = 'Tipo inválido: solo JPG, PNG, WebP o GIF.';
$_['error_upload_move']   = 'No se pudo guardar el archivo subido (revise los permisos del directorio).';
$_['error_permission']    = 'No tiene permiso para modificar las herramientas.';

// Fase 8 / Tarefa 09 — interface, filtros/paginação e CSRF.
$_['text_all_status'] = 'Todos los estados';
$_['text_search_placeholder'] = 'Buscar por nombre…';
$_['text_vendor_placeholder'] = 'Buscar por proveedor…';
$_['text_showing'] = 'Mostrando %d–%d de %d herramientas';
$_['text_empty_title'] = 'No se encontraron herramientas';
$_['text_empty_hint'] = 'Cree una herramienta o ajuste los filtros.';
$_['text_upload_wait'] = 'Espere a que termine la subida antes de guardar.';
$_['button_filter'] = 'Filtrar';
$_['button_reset'] = 'Limpiar';
$_['button_close'] = 'Cerrar';
$_['button_first_page'] = '« Primera';
$_['button_last_page'] = 'Última »';
$_['button_next_page'] = 'Siguiente ›';
$_['button_previous_page'] = '‹ Anterior';
$_['error_csrf'] = 'Token de sesión no válido. Recargue el formulario e inténtelo de nuevo.';
