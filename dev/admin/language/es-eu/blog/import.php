<?php
/**
 * blog/import — Importador WordPress (F7-03, ADR 0044) — es-eu
 */

// Heading
$_['heading_title']            = 'Importar de WordPress';

// Text
$_['text_form_intro']          = 'Lee la API pública de WP, descarga las imágenes destacadas y materializa las entradas en el blog interno. Idempotente: slugs ya importados se "saltan"; colisiones de keyword reciben sufijo -2/-3. Ancla de idioma: cada entrada cae en en-US (el coalesce la hace visible en los tres idiomas).';
$_['text_preview']             = 'Vista previa (sin escribir)';
$_['text_run']                 = 'Ejecutar importación';
$_['text_confirm']             = '¿Importar de verdad y escribir las entradas en la base?';
$_['text_summary_header']      = 'Informe';
$_['text_summary_preview']     = 'Vista previa: %d entrada(s) serían importadas (%d ya saltadas, %d con sufijo de keyword).';
$_['text_summary_run']         = "Importación: %d importadas (%d con sufijo), %d saltadas, %d errores.\n%s";
$_['text_map_written']         = 'Mapa 301 escrito (%d bytes) en system/storage/cache/wp-301-map.txt';
$_['text_map_failed']          = 'Atención: el mapa de redirects no pudo escribirse (permisos del storage).';
$_['text_actions']             = 'Importar';
$_['text_action_imported']     = 'Importada';
$_['text_action_import_with_suffix'] = 'Importar con sufijo';
$_['text_action_skip']         = 'Saltar';
$_['text_action_error']        = 'Error';
$_['text_log_header']          = 'Registro de la operación';
$_['text_no_rows']             = 'Ninguna entrada devuelta en esta página.';
$_['text_imported_edit']       = 'editar';

// Entry
$_['entry_base_url']           = 'URL base del WordPress';
$_['entry_page']               = 'Página';
$_['entry_per_page']           = 'Por página';
$_['entry_public_url']         = 'URL pública del catálogo';

// Help
$_['help_base_url']            = 'El endpoint público /wp-json/ debe ser accesible. No se envía ninguna credencial.';
$_['help_page']                = 'Paginación de WP REST (X-WP-TotalPages). Empieza en 1.';
$_['help_per_page']            = 'Máximo 100 por llamada (límite duro de la API de WP).';
$_['help_public_url']          = 'Base del mapa 301 (URLs absolutas del blog NUEVO). Derivada del HTTP_SERVER; ajústela en topologías exóticas.';

// Column
$_['column_wp_id']             = 'ID WP';
$_['column_title']             = 'Título';
$_['column_date']              = 'Fecha';
$_['column_keyword']           = 'Keyword';
$_['column_action_taken']      = 'Acción';
$_['column_image']             = 'Imagen';
$_['column_status_label']      = 'Estado';

// Button
$_['button_back_list']         = 'Volver al blog';
$_['button_submit']            = 'Ir';

// Error
$_['error_action']             = 'Acción desconocida (esperado preview o run).';
$_['error_base_url']           = 'La URL base es obligatoria.';
$_['error_fetch']              = 'Falla al leer el endpoint de WordPress: %s';

// F8-02 (ADR 0046): cards
$_['text_source_card']     = 'Fuente';
$_['text_pagination_card'] = 'Ventana de la muestra';
