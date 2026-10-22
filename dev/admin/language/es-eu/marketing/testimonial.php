<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/testimonial — Idiomas del módulo Testimonios (es-EU; en-US canónico del ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Testimonios';
$_['text_list']            = 'Testimonios de clientes mostrados en la home. El contenido por idioma se edita en el formulario del testimonio.';
$_['text_no_results']      = 'Aún no hay testimonios. Cree el primero.';
$_['text_confirm_delete']  = '¿Eliminar este testimonio? Se quitarán sus descripciones y el archivo de la foto.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Deshabilitado';
$_['text_add']             = 'Agregar Testimonio';
$_['text_edit']            = 'Editar Testimonio';
$_['text_general']         = 'Contenido';
$_['text_data']            = 'Datos';
$_['text_languages_note']  = 'Un bloque por idioma instalado. El autor en en-US es obligatorio.';
$_['text_image']           = 'Foto del Autor';
$_['text_upload_hint']     = 'JPG, PNG, WebP o GIF hasta 2 MB. El archivo se guarda ahora; guarde el formulario para vincularlo al testimonio.';
$_['text_success_add']     = 'Testimonio creado.';
$_['text_success_edit']    = 'Testimonio actualizado.';
$_['text_success_delete']  = 'Testimonio eliminado.';
$_['text_placeholder']     = '(sin foto)';

// F8-04 — filtros, paginação e proteção CSRF
$_['text_filter'] = 'Filtrar testimonios';
$_['text_all_status'] = 'Todos los estados';
$_['text_showing'] = 'Mostrando %s–%s de %s testimonios';
$_['text_no_results_filtered'] = 'Ningún testimonio coincide con estos filtros.';
$_['text_close'] = 'Cerrar';
$_['text_page_first'] = '« Primero';
$_['text_page_last'] = 'Último »';
$_['text_page_next'] = 'Siguiente ›';
$_['text_page_prev'] = '‹ Anterior';
$_['entry_filter_author'] = 'Autor o empresa';
$_['button_filter'] = 'Aplicar filtros';
$_['button_reset'] = 'Limpiar';
$_['error_csrf'] = 'El token de seguridad no es válido o ha caducado. Actualice la página e inténtelo de nuevo.';

// Columns
$_['column_image']         = 'Foto';
$_['column_author']        = 'Autor';
$_['column_company']       = 'Empresa';
$_['column_status']        = 'Estado';
$_['column_sort_order']    = 'Orden';
$_['column_date_added']    = 'Agregado';
$_['column_action']        = 'Acción';

// Entries
$_['entry_author']     = 'Autor';
$_['entry_company']    = 'Empresa';
$_['entry_text']       = 'Texto del Testimonio';
$_['entry_image']      = 'Foto';
$_['entry_status']     = 'Estado';
$_['entry_sort_order'] = 'Orden';

// Buttons
$_['button_add']        = 'Agregar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Eliminar';
$_['button_save']       = 'Guardar';
$_['button_cancel']     = 'Cancelar';
$_['button_upload']     = 'Subir';

// Errors
$_['error_author']        = 'El autor en en-US es obligatorio (1 a 128 caracteres).';
$_['error_image_path']    = 'La foto debe ser un archivo subido mediante el botón Subir.';
$_['error_upload']        = 'Falló la subida — revise el archivo e inténtelo de nuevo.';
$_['error_upload_method'] = 'La subida debe ser un POST.';
$_['error_upload_size']   = 'El archivo excede el límite de %s.';
$_['error_upload_type']   = 'Tipo inválido: solo JPG, PNG, WebP o GIF.';
$_['error_upload_move']   = 'No se pudo guardar el archivo subido (revise los permisos del directorio).';
$_['error_permission']    = 'No tiene permiso para modificar los testimonios.';
