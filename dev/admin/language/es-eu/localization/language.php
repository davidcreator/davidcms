<?php
// Heading
$_['heading_title'] = 'Idiomas';

// Text
$_['text_list']            = 'Idiomas instalados. Añadir/quitar un idioma implica publicar un directorio nuevo — esta pantalla gobierna metadatos, estado y los valores por defecto de cada aplicación.';
$_['text_no_results']      = 'No hay filas en rms_language — revisa el seed del instalador.';
$_['text_enabled']         = 'Activo';
$_['text_disabled']        = 'Inactivo';
$_['text_dir_missing']     = 'el directorio del idioma no existe en el disco';
$_['text_default_catalog'] = 'Predeterminado del catálogo';
$_['text_default_admin']   = 'Predeterminado del admin';
$_['text_defaults_note']   = 'El idioma predeterminado debe estar activo. Persiste en setting/setting (F4-01) y aplica en la siguiente petición.';
$_['text_default_locked']  = 'Este idioma es el predeterminado de una aplicación — no puedes desactivarlo aquí. Cambia el predeterminado antes.';
$_['text_success_defaults'] = 'Valores predeterminados guardados.';
$_['text_success_edit']    = 'Idioma actualizado.';

// Entry
$_['entry_name']          = 'Nombre para mostrar';
$_['entry_code']          = 'Código (directorio en filesystem)';

// Column
$_['entry_locale']        = 'Locale';
$_['entry_sort_order']    = 'Orden';
$_['entry_status']        = 'Activo';

$_['column_name']         = 'Idioma';
$_['column_code']         = 'Código';
$_['column_locale']       = 'Locale';
$_['column_sort_order']   = 'Orden';
$_['column_status']       = 'Estado';
$_['column_action']       = 'Acción';

// Button
$_['button_save']   = 'Guardar';
$_['button_edit']   = 'Editar';
$_['button_cancel'] = 'Volver';

// Error
$_['error_name']              = 'El nombre es obligatorio (hasta 32 caracteres).';
$_['error_locale']            = 'El locale es obligatorio (hasta 32 caracteres, p. ej.: pt_BR.UTF-8).';
$_['error_defaults_required'] = 'Elige un predeterminado para las dos aplicaciones.';
$_['error_default_disabled']  = 'El predeterminado debe ser un idioma ACTIVO.';
$_['error_disable_default']   = 'No se puede desactivar el predeterminado vigente de una aplicación. Cambia el predeterminado antes.';
$_['error_not_found']         = 'Idioma no encontrado.';

// Fase 8 / Tarefa 11 — shell, busca/paginação e CSRF.
$_['text_home'] = 'Inicio';
$_['text_all_status'] = 'Todos los estados';
$_['text_search_placeholder'] = 'Buscar nombre, código o locale…';
$_['text_showing'] = 'Mostrando %d–%d de %d idiomas';
$_['text_empty_title'] = 'No se encontraron idiomas';
$_['text_empty_hint'] = 'Ajuste la búsqueda o el filtro de estado.';
$_['text_readonly_notice'] = 'Puede visualizar; guardar cambios requiere permiso de modificar.';
$_['button_filter'] = 'Filtrar';
$_['button_reset'] = 'Limpiar';
$_['button_close'] = 'Cerrar';
$_['button_first_page'] = '« Primera';
$_['button_last_page'] = 'Última »';
$_['button_next_page'] = 'Siguiente ›';
$_['button_previous_page'] = '‹ Anterior';
$_['error_csrf'] = 'Token de sesión no válido. Recargue el formulario e inténtelo de nuevo.';
