<?php
/**
 * blog/blog — Blog (F7-01, ADR 0042) — es-eu
 */

// Heading
$_['heading_title']           = 'Blog';

// Text
$_['text_list']               = 'Noticias y artículos, lo más nuevo primero. Sección pública (F7-02): /blog';
$_['text_add']                = 'Nueva entrada';
$_['text_edit']               = 'Editar entrada';
$_['text_general']            = 'Contenido (por idioma)';
$_['text_data']               = 'Datos';
$_['text_languages_note']     = 'Solo inglés (en-us) es obligatorio; los demás idiomas son opcionales.';
$_['text_no_results']         = 'Aún no hay entradas. ¡Empieza por tu primer artículo!';
$_['text_confirm_delete']     = '¿Eliminar esta entrada permanentemente?';
$_['text_enabled']            = 'Publicada';
$_['text_disabled']           = 'Borrador';
$_['text_success_add']        = 'Entrada publicada.';
$_['text_success_edit']       = 'Entrada actualizada.';
$_['text_success_delete']     = 'Entrada eliminada.';
$_['text_flash']              = '';

// Column
$_['column_title']            = 'Título';
$_['column_status']           = 'Estado';
$_['column_date_published']   = 'Publicación';
$_['column_date_modified']    = 'Modificada';
$_['column_action']           = 'Acción';

// Entry
$_['entry_title']             = 'Título';
$_['entry_description']       = 'Contenido';
$_['entry_meta_title']        = 'Meta título';
$_['entry_meta_description']  = 'Meta descripción';
$_['entry_keyword']           = 'Palabra clave SEO (opcional)';
$_['entry_image']             = 'Imagen destacada';
$_['entry_date_published']    = 'Fecha de publicación';
$_['entry_status']            = 'Publicada';

// Help
$_['help_image']              = 'Ruta relativa o URL externa. El importador WordPress (F7-03) lo rellena solo.';
$_['help_date_published']     = 'El orden público es lo más nuevo primero. Deja así para publicar ahora.';

// Button
$_['button_add']              = 'Añadir entrada';
$_['button_edit']             = 'Editar';
$_['button_delete']           = 'Eliminar';
$_['button_save']             = 'Guardar';
$_['button_cancel']           = 'Cancelar';
$_['button_import_wp']        = 'Importar de WordPress'; // F7-03 (ADR 0044)

// Error
$_['error_title']             = 'Título (en-us) es obligatorio y debe tener hasta 255 caracteres.';
$_['error_date_published']    = 'Fecha de publicación: formato esperado YYYY-MM-DD HH:MM (opcional).';
$_['error_keyword_format']    = 'Palabra clave del idioma %d: solo minúsculas, dígitos y guiones.';
$_['error_keyword_taken']     = "La palabra clave '%s' ya la usa otra página, proyecto, herramienta o entrada.";
