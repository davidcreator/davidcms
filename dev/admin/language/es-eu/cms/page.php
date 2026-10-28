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

// F10-01 — páginas públicas do Catalog vinculadas ao CMS.
$_['text_system_page'] = 'Página del Catalog';
$_['text_system_page_help'] = 'Esta página está vinculada a una ruta pública del Catalog. La clave de ruta es fija; edita aquí el contenido y los metadatos.';
$_['error_system_page_delete'] = 'Esta página del sistema no se puede eliminar. Puedes desactivarla o editar su contenido.';
