<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/project — Idiomas del módulo Portafolio (es-EU; en-US canónico del ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Portafolio';
$_['text_list']            = 'Proyectos del portafolio mostrados en el sitio. El contenido por idioma se edita en el formulario del proyecto.';
$_['text_no_results']      = 'Aún no hay proyectos. Cree el primero.';
$_['text_confirm_delete']  = '¿Eliminar este proyecto? Se quitarán sus imágenes, descripciones y keywords SEO.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Deshabilitado';
$_['text_yes']             = 'Sí';
$_['text_no']              = 'No';
$_['text_add']             = 'Agregar Proyecto';
$_['text_edit']            = 'Editar Proyecto';
$_['text_general']         = 'Contenido';
$_['text_data']            = 'Datos';
$_['text_languages_note']  = 'Un bloque por idioma instalado. El título en en-US es obligatorio.';
$_['text_image_cover']     = 'Imagen de portada';
$_['text_gallery']         = 'Galería';
$_['text_upload_hint']     = 'JPG, PNG, WebP o GIF hasta 2 MB. El archivo se guarda ahora; guarde el formulario para vincularlo al proyecto.';
$_['text_success_add']     = 'Proyecto creado.';
$_['text_success_edit']    = 'Proyecto actualizado.';
$_['text_success_delete']  = 'Proyecto eliminado.';
$_['text_placeholder']     = '(sin portada)';

// Columns
$_['column_image']         = 'Imagen';
$_['column_name']          = 'Nombre';
$_['column_client']        = 'Cliente';
$_['column_featured']      = 'Destacado';
$_['column_status']        = 'Estado';
$_['column_sort_order']    = 'Orden';
$_['column_date_modified'] = 'Modificado';
$_['column_action']        = 'Acción';

// Entries
$_['entry_name']             = 'Título';
$_['entry_summary']          = 'Resumen';
$_['entry_description']      = 'Descripción';
$_['entry_tag']              = 'Etiquetas (separadas por coma)';
$_['entry_meta_title']       = 'Título Meta Tag';
$_['entry_meta_description'] = 'Descripción Meta Tag';
$_['entry_keyword']          = 'Keyword SEO';
$_['entry_client']           = 'Client';
$_['entry_url']              = 'URL del Proyecto';
$_['entry_image']            = 'Portada';
$_['entry_category']         = 'Categorías';
$_['entry_featured']         = 'Featured';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Sort Order';
$_['entry_alt']              = 'Texto alternativo';

// Buttons
$_['button_add']        = 'Agregar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Eliminar';
$_['button_save']       = 'Guardar';
$_['button_cancel']     = 'Cancelar';
$_['button_upload']     = 'Subir';
$_['button_remove']     = 'Quitar';
$_['button_add_image']  = 'Agregar Imagen';

// Errors
$_['error_name']          = 'El título en en-US es obligatorio (1 a 255 caracteres).';
$_['error_url']           = 'La URL del proyecto debe ser una URL válida (con esquema).';
$_['error_keyword_format'] = 'Keyword SEO (idioma #%s): solo letras minúsculas, números y guiones.';
$_['error_keyword_taken']  = 'La keyword SEO "%s" ya está en uso.';
$_['error_image_path']     = 'La portada debe ser un archivo subido con el botón Subir.';
$_['error_upload']        = 'Error al subir — revise el archivo e inténtelo de nuevo.';
$_['error_upload_method'] = 'La subida debe ser por POST.';
$_['error_upload_size']   = 'El archivo excede el límite de %s.';
$_['error_upload_type']   = 'Tipo no válido: solo JPG, PNG, WebP o GIF.';
$_['error_upload_move']   = 'No se pudo guardar el archivo (revise los permisos del directorio).';
$_['error_permission']    = 'No tiene permiso para modificar el portafolio.';
