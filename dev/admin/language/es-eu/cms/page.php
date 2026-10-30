<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * cms/page — Idiomas del módulo Páginas (es-EU; en-US canónico del ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Páginas de Contenido';
$_['text_list']            = 'Páginas informativas del sitio (Acerca, Políticas, Cookies). El contenido por idioma se edita en el formulario de la página.';
$_['text_no_results']      = 'Aún no hay páginas. Cree la primera.';
$_['text_confirm_delete']  = '¿Eliminar esta página? Se quitarán sus descripciones y keywords SEO.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Deshabilitado';
$_['text_add']             = 'Agregar Página';
$_['text_edit']            = 'Editar Página';
$_['text_general']         = 'Contenido';
$_['text_data']            = 'Datos';
$_['text_languages_note']  = 'Use las pestañas de idioma. El título en en-US es obligatorio.';
$_['text_success_add']     = 'Página creada.';
$_['text_success_edit']    = 'Página actualizada.';
$_['text_success_delete']  = 'Página eliminada.';

// Columns
$_['column_title']         = 'Título';
$_['column_status']        = 'Estado';
$_['column_sort_order']    = 'Orden';
$_['column_date_modified'] = 'Modificado';
$_['column_action']        = 'Acción';

// Entries
$_['entry_title']            = 'Título';
$_['entry_description']      = 'Descripción';
$_['entry_meta_title']       = 'Título Meta Tag';
$_['entry_meta_description'] = 'Descripción Meta Tag';
$_['entry_keyword']          = 'Keyword SEO';
$_['entry_status']           = 'Estado';
$_['entry_sort_order']       = 'Orden';

// Buttons
$_['button_add']        = 'Agregar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Eliminar';
$_['button_save']       = 'Guardar';
$_['button_cancel']     = 'Cancelar';

// Errors
$_['error_title']         = 'El título en en-US es obligatorio (1 a 255 caracteres).';
$_['error_keyword_format'] = 'Keyword SEO (idioma #%s): solo letras minúsculas, números y guiones.';
$_['error_keyword_taken']  = 'La keyword SEO "%s" ya está en uso.';
$_['error_permission']    = 'No tiene permiso para modificar las páginas.';

// F8-06 — lista/form no shell, filtros/paginação e CSRF.
$_['text_all_status'] = 'Todos los estados';
$_['text_search_placeholder'] = 'Buscar por título…';
$_['text_showing'] = 'Mostrando %d–%d de %d páginas';
$_['text_empty_title'] = 'No se encontraron páginas';
$_['text_empty_hint'] = 'Cree una página o ajuste los filtros.';
$_['text_meta_group'] = 'Metadatos de búsqueda';
$_['help_sort_order'] = 'Los números menores aparecen primero.';
$_['help_description'] = 'Introduzca el contenido de la página. HTML sigue permitido.';
$_['button_filter'] = 'Filtrar';
$_['button_reset'] = 'Limpiar';
$_['button_first_page'] = '« Primera';
$_['button_last_page'] = 'Última »';
$_['button_next_page'] = 'Siguiente ›';
$_['button_previous_page'] = '‹ Anterior';
$_['error_csrf'] = 'Token de sesión no válido. Recargue el formulario e inténtelo de nuevo.';
$_['button_close'] = 'Cerrar';

// Flujo de edición del contenido en una página exclusiva.
$_['text_page_context_page'] = 'Página';
$_['text_page_context_language'] = 'Idioma';
$_['text_page_context_untitled'] = 'Página sin título';
$_['text_page_context_save_hint'] = 'Guardar aquí usa el envío y la validación existentes del formulario.';
$_['text_page_context_readonly_hint'] = 'Modo de consulta: este usuario no puede guardar esta página.';
$_['text_content_summary_note'] = 'Resumen del contenido. Abra el editor dedicado para modificar texto y bloques.';
$_['text_content_empty'] = 'Esta página todavía no tiene contenido.';
$_['text_content_save_first'] = 'Guarde los datos generales para abrir el editor.';
$_['text_content_block_count'] = '%d bloques de contenido';
$_['text_page_content_editor'] = 'Editor de contenido de la página';
$_['text_content_editor_help'] = 'Edite el contenido por idioma. Los datos generales, SEO y estado permanecen en el formulario.';
$_['button_edit_content'] = 'Editar contenido';
$_['button_save_content'] = 'Guardar contenido';
$_['text_content_invalid'] = 'El contenido almacenado debe restaurarse antes de editarlo.';
$_['text_editor_home'] = 'Inicio';
$_['text_editor_insert'] = 'Insertar';
$_['text_editor_view'] = 'Ver';
$_['text_editor_clipboard'] = 'Portapapeles';
$_['text_editor_text_format'] = 'Formato de texto';
$_['text_editor_paragraph_alignment'] = 'Alineación';
$_['text_editor_group_insert'] = 'Insertar contenido';
$_['text_editor_group_panels'] = 'Paneles';
$_['text_editor_group_preview'] = 'Vista previa';
$_['text_editor_close_panel'] = 'Cerrar panel';
$_['text_editor_align_left'] = 'Alinear a la izquierda';
$_['text_editor_align_center'] = 'Centrar';
$_['text_editor_align_right'] = 'Alinear a la derecha';
$_['text_editor_import_file'] = 'Importar archivo';
$_['text_editor_import_success'] = 'Contenido importado al borrador local: ';
$_['text_editor_preview_title'] = 'Vista previa del contenido';
$_['text_editor_preview_mode'] = 'Vista previa';
$_['text_editor_edit_mode'] = 'Volver a editar';
$_['text_editor_import_hint'] = 'Conversión local; no se suben archivos. Los medios solo se referencian si ya están en una biblioteca permitida.';
$_['text_editor_import_unavailable'] = 'Importador no disponible.';
$_['text_editor_import_audio_library'] = 'MP3, OGG y WAV no se importan como documentos. Inserta un bloque Audio y selecciona o sube el audio mediante la biblioteca del Blog.';
$_['text_editor_import_error'] = 'No se pudo importar el archivo.';
$_['text_editor_preview_video_link'] = 'Vídeo incrustado';
$_['text_editor_preview_video_open'] = 'Abrir en una pestaña nueva';

$_['text_block_type_video_file'] = 'Vídeo local';

$_['text_block_video_file_source'] = 'Ruta local del vídeo';

$_['text_block_video_file_choose'] = 'Seleccionar o subir vídeo';

$_['text_editor_import_video_library'] = 'MP4, WebM y OGV no se importan como documentos. Inserta un bloque Vídeo local y selecciona o sube el vídeo mediante la biblioteca del Blog.';

$_['text_block_type_threejs_thumb'] = 'Miniatura 3D (Three.js)';

$_['text_block_threejs_label'] = 'Etiqueta accesible';

$_['text_block_threejs_scene_config'] = 'Configuración de escena (JSON)';

$_['text_block_threejs_config_hint'] = 'Escena JSON declarativa con límites estrictos. El JavaScript personalizado opcional va en un campo separado y se ejecuta en un sandbox aislado. No uses URL, texturas ni recursos externos; Three.js es local.';

$_['text_block_threejs_preview_hint'] = 'La vista previa completa del editor y la miniatura pública ejecutan scripts personalizados solo en un iframe sandbox opaco y aislado. La tarjeta del bloque no los ejecuta.';

$_['text_block_threejs_objects'] = 'Formas de la escena';

$_['text_block_threejs_lights'] = 'Iluminación';

$_['text_block_threejs_addShape'] = 'Añadir forma';

$_['text_block_threejs_addLight'] = 'Añadir luz';

$_['text_block_threejs_remove'] = 'Quitar';

$_['text_block_threejs_color'] = 'Color';

$_['text_block_threejs_animation'] = 'Animación';

$_['text_block_threejs_intensity'] = 'Intensidad';

$_['text_block_threejs_invalidScene'] = 'El JSON no es válido o supera los límites. Corrige la configuración para editar la escena.';

$_['text_block_threejs_sceneConfig'] = 'JSON de escena (avanzado)';

$_['text_block_threejs_scriptLabel'] = 'JavaScript personalizado (sandbox)';

$_['text_block_threejs_scriptHint'] = 'Pega solo JavaScript, sin etiquetas <script>. Se ejecuta en un iframe aislado, sin acceso al DOM/cookies del CMS; CSP bloquea red y recursos externos. API: THREE, scene, camera, renderer, canvas y onFrame(fn). Límite: 8 KiB. El código puede bloquear esta miniatura; usa solo scripts de confianza. Ejemplo: const mesh = new THREE.Mesh(new THREE.TorusGeometry(.8,.2,16,48), new THREE.MeshStandardMaterial({color:0xff66aa})); scene.add(mesh); onFrame(({delta}) => { mesh.rotation.y += delta; });';

$_['text_block_threejs_editInline'] = 'Editar miniatura y script';

$_['text_block_threejs_closeInline'] = 'Cerrar editor';

$_['text_block_threejs_deleteScript'] = 'Eliminar script';

$_['text_block_threejs_scriptDeleted'] = 'Script de miniatura eliminado. Usa Deshacer para restaurarlo.';

$_['text_block_threejs_scriptPlaceholder'] = 'Añade JavaScript para personalizar la escena Three.js…';

$_['text_block_threejs_addScript'] = 'Editar miniatura / añadir script';

// CMS Pages private draft preview.
$_['text_page_preview_button'] = 'Abrir vista previa';
$_['text_page_preview_title'] = 'Vista previa privada de la página';
$_['text_page_preview_notice'] = 'Vista previa privada con el shell y la plantilla actuales del Catalog. Las áreas de Blog, Portfolio, Tools y otros módulos muestran registros de ejemplo identificados; no se publica ni se guarda nada.';
$_['text_page_preview_sample_content'] = 'Contenido de ejemplo solo para la vista previa';
$_['text_page_preview_sample_item'] = 'Elemento de ejemplo';
$_['text_page_preview_untitled'] = 'Página sin título';
$_['error_page_preview_permission'] = 'No tienes permiso para previsualizar páginas del CMS.';
$_['error_page_preview_method'] = 'La vista previa requiere una solicitud POST.';
$_['error_page_preview_csrf'] = 'Token CSRF no válido o caducado.';
$_['error_page_preview_document'] = 'El documento de bloques no es válido o supera el tamaño permitido.';
$_['error_page_preview_unavailable'] = 'La vista previa no está disponible.';
// CMS Pages image library (block media only; no Page entity image field).
$_['text_page_media_library'] = 'Biblioteca de imágenes de Pages';
$_['text_page_media_empty'] = 'No se encontraron imágenes.';
$_['text_page_media_loading'] = 'Cargando imágenes…';
$_['text_page_media_search'] = 'Buscar por nombre';
$_['text_page_media_upload_hint'] = 'JPEG, PNG, WebP o GIF; máximo 2 MiB. Biblioteca exclusiva de Pages.';
$_['text_page_media_uploading'] = 'Subiendo imagen…';
$_['text_page_media_select'] = 'Seleccionar';
$_['text_page_media_page'] = 'Página %1 de %2';
$_['text_page_media_upload'] = 'Subir archivo';
$_['text_page_media_error'] = 'No se pudo completar la operación multimedia.';
$_['error_page_media_permission'] = 'No tienes permiso para acceder a la biblioteca multimedia de Pages.';
$_['error_page_media_method'] = 'Método HTTP no válido.';
$_['error_page_media_csrf'] = 'Token CSRF no válido o caducado.';
$_['error_page_media_list'] = 'No se pudieron listar los archivos multimedia de Pages.';
$_['error_page_media_no_file'] = 'Selecciona un archivo para subir.';
$_['error_page_media_size'] = 'El archivo seleccionado supera el límite de tamaño para este tipo de medio.';
$_['error_page_media_type'] = 'La extensión y el MIME detectado no coinciden con un formato permitido.';
$_['error_page_media_root'] = 'La carpeta exclusiva de imágenes de Pages no está disponible.';
$_['error_page_media_upload'] = 'Error al subir la imagen.';

// F10-01 — páginas públicas do Catalog vinculadas ao CMS.
$_['text_system_page'] = 'Página del Catalog';
$_['text_system_page_help'] = 'Esta página está vinculada a una ruta pública del Catalog. La clave de ruta es fija; edita aquí el contenido y los metadatos.';
$_['error_system_page_delete'] = 'Esta página del sistema no se puede eliminar. Puedes desactivarla o editar su contenido.';

// Biblioteca independiente de medios de CMS > Páginas.
$_['text_page_media_tab_image'] = 'Imágenes';
$_['text_page_media_tab_audio'] = 'Audio';
$_['text_page_media_tab_video'] = 'Vídeo';
$_['text_page_media_tab_download'] = 'Descargas';
$_['text_page_media_empty_audio'] = 'No se encontraron archivos de audio.';
$_['text_page_media_empty_video'] = 'No se encontraron archivos de vídeo.';
$_['text_page_media_empty_download'] = 'No se encontraron archivos para descargar.';
$_['text_page_media_upload_hint_audio'] = 'MP3, OGG, WAV · hasta 20 MiB';
$_['text_page_media_upload_hint_video'] = 'MP4, WebM, OGV · hasta 100 MiB';
$_['text_page_media_upload_hint_download'] = 'PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV, TXT · hasta 2 MiB';

// F9-25 — BDRE personal pattern library
$_['text_block_saved_patterns'] = 'Patrones guardados';
$_['text_block_save_pattern'] = 'Guardar como patrón';
$_['text_block_rename_pattern'] = 'Renombrar';
$_['text_block_delete_pattern'] = 'Eliminar';
$_['text_block_pattern_name_prompt'] = 'Nombre del patrón';
$_['text_block_pattern_rename_prompt'] = 'Nuevo nombre del patrón';
$_['text_block_pattern_delete_confirm'] = 'Eliminar el patrón';
$_['text_block_pattern_saved'] = 'Patrón guardado.';
$_['text_block_pattern_renamed'] = 'Patrón renombrado.';
$_['text_block_pattern_deleted'] = 'Patrón eliminado.';
$_['text_block_pattern_error'] = 'No se pudo actualizar el patrón.';
$_['text_block_pattern_incompatible'] = 'Este patrón no es compatible con este punto del editor.';
$_['text_block_pattern_invalid'] = 'Este bloque no puede guardarse como patrón independiente.';
$_['text_block_pattern_custom_js_rejected'] = 'Los patrones no pueden contener JavaScript.';

$_['text_block_draft_recovery_prompt'] = 'Se encontró un borrador BDRE sin guardar en esta pestaña. ¿Quieres recuperarlo? El borrador temporal solo se conserva en esta pestaña hasta guardarlo o descartarlo.';
$_['text_block_draft_recovery_hint'] = 'Se conserva una copia temporal de recuperación en esta pestaña. El contenido del sitio solo se guarda al pulsar Guardar.';

// F9-29 — portable BDRE document files
$_['text_block_export_document'] = 'Exportar documento BDRE';
$_['text_block_export_success'] = 'Documento BDRE exportado.';
$_['text_block_export_error'] = 'No se pudo exportar el documento BDRE.';
$_['text_block_import_replace_confirm'] = '¿Sustituir todo el documento actual por este archivo BDRE?';

// F9-30 — temporary draft history
$_['text_block_history_button'] = 'Historial de borradores';
$_['text_block_history_title'] = 'Historial temporal de borradores';
$_['text_block_history_empty'] = 'No hay borradores temporales anteriores en esta pestaña.';
$_['text_block_history_restore'] = 'Recuperar';
$_['text_block_history_restore_confirm'] = '¿Recuperar esta versión temporal en el editor?';
$_['text_block_history_restored'] = 'Versión temporal recuperada como borrador sin guardar.';
// F9-32 — optimistic edit conflict protection
$_['error_edit_conflict'] = 'Esta página se modificó y guardó en otra sesión desde que la abriste. No se guardaron tus cambios. Recarga la versión actual antes de continuar.';
$_['button_reload_current'] = 'Recargar versión actual';
// F9-33 — compare persisted revisions safely before restore
$_['text_revision_compare'] = 'Comparar con el formulario';
$_['text_revision_version'] = 'Versión seleccionada';
$_['text_revision_current'] = 'Formulario actual (incluye cambios sin guardar)';
$_['text_revision_body'] = 'Contenido (documentos BDRE; el HTML heredado se muestra como texto)';
$_['text_revision_meta'] = 'Metadatos editoriales';$_['text_revision_error'] = 'No se pudo cargar la comparación de esta versión.';
// F9-34 — bounded, safe textual revision diff
$_['text_revision_diff'] = 'Diferencias de contenido';
$_['text_revision_unchanged'] = 'No hay diferencias de texto.';
$_['text_revision_diff_limit'] = 'El documento es demasiado largo para comparar palabra por palabra; usa las columnas anteriores.';
// F9-36 — document-wide plain-text find and replace
$_['text_block_find_replace'] = 'Buscar y reemplazar';
$_['text_block_find_label'] = 'Buscar texto';
$_['text_block_replace_label'] = 'Reemplazar por';
$_['text_block_find_next'] = 'Siguiente coincidencia';
$_['text_block_replace_one'] = 'Reemplazar';
$_['text_block_replace_all'] = 'Reemplazar todo';
$_['text_block_find_case_sensitive'] = 'Distinguir mayúsculas y minúsculas';
$_['text_block_find_cross_run_hint'] = 'La búsqueda abarca fragmentos con distinto formato dentro del mismo bloque, pero no cruza límites entre bloques.';
$_['text_block_find_none'] = 'No se encontraron coincidencias.';
$_['text_block_find_count'] = 'Coincidencia %1 de %2';
$_['text_block_replace_done'] = 'Reemplazos realizados: %1.';
$_['text_block_replace_limit'] = 'El reemplazo supera el límite de 1.000 coincidencias; no se modificó nada.';
$_['text_block_find_empty'] = 'Escribe el texto que quieres buscar.';
$_['text_block_find_previous'] = 'Coincidencia anterior';
$_['text_block_keyboard_shortcuts_title'] = 'Atajos de teclado';
$_['text_block_keyboard_shortcuts'] = 'Ctrl/⌘+F buscar · Ctrl/⌘+B negrita · Ctrl/⌘+I cursiva · Ctrl/⌘+U subrayado · Ctrl/⌘+K enlace · Ctrl/⌘+Z deshacer · Ctrl/⌘+Shift+Z o Ctrl+Y rehacer · Alt+↑/↓ mover bloque · / insertar bloque';
$_['text_block_find_whole_word'] = 'Solo palabras completas';
$_['text_block_find_keyboard_hint'] = 'Enter siguiente · Shift+Enter anterior · Esc cerrar';
$_['text_block_document_statistics'] = 'Estadísticas del documento';
$_['text_block_document_words'] = 'Palabras';
$_['text_block_document_characters'] = 'Caracteres (incluidos los espacios)';
$_['text_block_inspector_tabs'] = 'Ajustes del bloque';
$_['text_block_custom_content'] = 'Contenido';
$_['text_block_customize'] = 'Personalizar';
$_['text_block_custom_text_color'] = 'Color del texto';
$_['text_block_custom_background'] = 'Color de fondo';
$_['text_block_custom_border_color'] = 'Color del borde';
$_['text_block_custom_border_style'] = 'Estilo del borde';
$_['text_block_custom_border_solid'] = 'Continuo';
$_['text_block_custom_border_dashed'] = 'Discontinuo';
$_['text_block_custom_border_dotted'] = 'Punteado';
$_['text_block_custom_border_width'] = 'Grosor del borde (px)';
$_['text_block_custom_radius'] = 'Redondeo (px)';
$_['text_block_custom_padding'] = 'Espaciado interno (px)';
$_['text_block_custom_margin'] = 'Espaciado externo (px)';
$_['text_block_custom_font_size'] = 'Tamaño de fuente (px)';
$_['text_block_custom_text_align'] = 'Alineación del texto';
$_['text_block_custom_inherit'] = 'Predeterminado del tema';
$_['text_block_custom_hint'] = 'Los estilos seguros se aplican a este bloque y se guardan en el documento.';
$_['text_block_custom_reset'] = 'Restablecer personalización';
$_['text_block_gallery_preview'] = 'Vista previa de la galería';
