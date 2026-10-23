<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/category — Idiomas del módulo Categorías (es-EU; en-US canónico del ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Categorías del Portafolio';
$_['text_list']            = 'Categorías para agrupar los proyectos del portafolio. El contenido por idioma se edita en el formulario de la categoría.';
$_['text_no_results']      = 'Aún no hay categorías. Cree la primera.';
$_['text_confirm_delete']  = '¿Eliminar esta categoría? Las subcategorías pasan a raíz y las relaciones con proyectos se quitan.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Deshabilitado';
$_['text_add']             = 'Agregar Categoría';
$_['text_edit']            = 'Editar Categoría';
$_['text_general']         = 'Contenido';
$_['text_data']            = 'Datos';
$_['text_languages_note']  = 'Use las pestañas de idioma. El nombre en en-US es obligatorio.';
$_['text_image_cover']     = 'Imagen';
$_['text_upload_hint']     = 'JPG, PNG, WebP o GIF hasta 2 MB. El archivo se guarda ahora; guarde el formulario para vincularlo a la categoría.';
$_['text_success_add']     = 'Categoría creada.';
$_['text_success_edit']    = 'Categoría actualizada.';
$_['text_success_delete']  = 'Categoría eliminada.';
$_['text_placeholder']     = '(sin imagen)';
$_['text_none']            = '(ninguno)';

// Columns
$_['column_image']         = 'Foto';
$_['column_name']          = 'Nombre';
$_['column_parent']        = 'Padre';
$_['column_status']        = 'Estado';
$_['column_sort_order']    = 'Orden';
$_['column_date_modified'] = 'Modificado';
$_['column_action']        = 'Acción';

// Entries
$_['entry_name']             = 'Nombre';
$_['entry_description']      = 'Descripción';
$_['entry_meta_title']       = 'Título Meta Tag';
$_['entry_meta_description'] = 'Descripción Meta Tag';
$_['entry_keyword']          = 'Keyword SEO';
$_['entry_parent']           = 'Padre';
$_['entry_image']            = 'Foto';
$_['entry_status']           = 'Estado';
$_['entry_sort_order']       = 'Orden';

// Buttons
$_['button_add']        = 'Agregar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Eliminar';
$_['button_save']       = 'Guardar';
$_['button_cancel']     = 'Cancelar';
$_['button_upload']     = 'Subir';

// Errors
$_['error_name']          = 'El nombre en en-US es obligatorio (1 a 255 caracteres).';
$_['error_keyword_format'] = 'Keyword SEO (idioma #%s): solo letras minúsculas, números y guiones.';
$_['error_keyword_taken']  = 'La keyword SEO "%s" ya está en uso.';
$_['error_image_path']     = 'La imagen debe ser un archivo subido con el botón Subir.';
$_['error_parent_cycle']   = 'Padre no válido: una categoría no puede ser padre de sí misma ni de sus hijos.';
$_['error_upload']        = 'Error al subir — revise el archivo e inténtelo de nuevo.';
$_['error_upload_method'] = 'La subida debe ser por POST.';
$_['error_upload_size']   = 'El archivo excede el límite de %s.';
$_['error_upload_type']   = 'Tipo no válido: solo JPG, PNG, WebP o GIF.';
$_['error_upload_move']   = 'No se pudo guardar el archivo (revise los permisos del directorio).';
$_['error_permission']    = 'No tiene permiso para modificar las categorías.';

// Fase 8 / Tarefa 07 — interface, filtros/paginação e CSRF.
$_['text_all_status'] = 'Todos los estados';
$_['text_search_placeholder'] = 'Buscar por nombre…';
$_['text_showing'] = 'Mostrando %d–%d de %d categorías';
$_['text_empty_title'] = 'No se encontraron categorías';
$_['text_empty_hint'] = 'Cree una categoría o ajuste los filtros.';
$_['text_meta_group'] = 'Metadatos de búsqueda';
$_['help_sort_order'] = 'Se conserva el orden existente; esta lista es alfabética.';
$_['text_upload_wait'] = 'Espere a que termine la subida antes de guardar.';
$_['button_filter'] = 'Filtrar';
$_['button_reset'] = 'Limpiar';
$_['button_close'] = 'Cerrar';
$_['button_first_page'] = '« Primera';
$_['button_last_page'] = 'Última »';
$_['button_next_page'] = 'Siguiente ›';
$_['button_previous_page'] = '‹ Anterior';
$_['error_csrf'] = 'Token de sesión no válido. Recargue el formulario e inténtelo de nuevo.';
