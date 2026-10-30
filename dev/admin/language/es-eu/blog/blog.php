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

// Private Catalog preview for Blog block drafts.
$_['text_catalog_preview_untitled'] = 'Entrada sin título';
$_['text_catalog_preview_button'] = 'Vista previa en Catalog';
$_['text_catalog_preview_error'] = 'El editor no está listo o el documento no es válido. Revisa el contenido e inténtalo de nuevo.';
$_['text_catalog_preview_notice'] = 'Vista previa privada de esta entrada de Blog sin guardar. No se ha guardado ni publicado nada.';
$_['text_published_on'] = 'Publicado el';
$_['button_back'] = 'Volver al Blog';
$_['text_content_review'] = 'Este contenido está pendiente de revisión editorial.';

// Error
$_['error_title']             = 'Título (en-us) es obligatorio y debe tener hasta 255 caracteres.';
$_['error_date_published']    = 'Fecha de publicación: formato esperado YYYY-MM-DD HH:MM (opcional).';
$_['error_keyword_format']    = 'Palabra clave del idioma %d: solo minúsculas, dígitos y guiones.';
$_['error_keyword_taken']     = "La palabra clave '%s' ya la usa otra página, proyecto, herramienta o entrada.";

// F8-02 (ADR 0046): barra de filtros, badges, estados, sidebar de publicación
$_['text_total_posts']         = 'entradas';
$_['text_showing']             = 'Mostrando %d–%d de %d entradas';
$_['text_search_placeholder']  = 'Buscar por título…';
$_['text_all_status']          = 'Todos los estados';
$_['text_published']           = 'Publicado';
$_['text_draft']               = 'Borrador';
$_['text_view_site']           = 'Ver en el sitio';
$_['text_empty_state']         = 'Ninguna entrada encontrada — ajusta los filtros o crea la primera.';
$_['text_empty_state_cta']     = 'Crear entrada';
$_['button_filter']            = 'Filtrar';
$_['button_reset']             = 'Limpiar';
$_['text_publish_card']        = 'Publicación';
$_['text_content_card']        = 'Contenido';
$_['text_meta_group']          = 'SEO';
$_['text_seo_url_preview']     = 'Prefijo de la URL SEO';
$_['text_cover_preview']       = 'Imagen de portada';
$_['text_status_help']         = 'Las entradas publicadas aparecen en el área del journal del sitio.';
$_['text_chars']               = 'caracteres';

// F8-05 (ADR 0049): biblioteca de imágenes del Blog
$_['text_media_library'] = 'Biblioteca de imágenes del Blog';
$_['text_media_browse'] = 'Explorar';
$_['text_media_upload'] = 'Subir imagen';
$_['text_media_create_folder'] = 'Nueva carpeta';
$_['text_media_external_url'] = 'URL externa';
$_['text_media_loading'] = 'Cargando archivos…';
$_['text_media_empty'] = 'Todavía no hay imágenes ni carpetas aquí.';
$_['text_media_no_matches'] = 'Ninguna imagen o carpeta coincide con la búsqueda.';
$_['text_media_page'] = 'Página %d de %d';
$_['text_media_confirm_delete_file'] = '¿Eliminar esta imagen permanentemente? Esta acción no se puede deshacer.';
$_['text_media_confirm_delete_folder'] = '¿Eliminar esta carpeta vacía?';
$_['text_media_upload_success'] = 'Imagen subida y seleccionada.';
$_['text_media_folder_created'] = 'Carpeta creada.';
$_['text_media_file_deleted'] = 'Imagen eliminada.';
$_['text_media_folder_deleted'] = 'Carpeta eliminada.';
$_['text_media_upload_hint'] = 'JPG, PNG, WebP o GIF; máximo 2 MB.';
$_['text_media_no_folders'] = 'No hay carpetas';
$_['text_media_in_use'] = 'Esta imagen se utiliza en una entrada y no se puede eliminar.';

$_['entry_media_search'] = 'Buscar imágenes y carpetas';
$_['entry_media_folder_name'] = 'Nombre de la carpeta';
$_['entry_media_external_url'] = 'URL de imagen HTTP(S)';

$_['button_media_library'] = 'Elegir de la biblioteca';
$_['button_media_upload'] = 'Subir y usar imagen';
$_['button_media_create_folder'] = 'Crear carpeta';
$_['button_media_use_url'] = 'Usar URL';
$_['button_media_select'] = 'Seleccionar imagen';
$_['button_media_delete'] = 'Eliminar';
$_['button_media_back'] = 'Volver';
$_['button_media_previous'] = 'Página anterior';
$_['button_media_next'] = 'Página siguiente';
$_['button_media_close'] = 'Cerrar';

$_['error_permission'] = 'No tienes permiso para gestionar los archivos del Blog.';
$_['error_csrf'] = 'El token de seguridad no es válido o ha caducado. Actualiza la página e inténtalo de nuevo.';
$_['error_media_method'] = 'Esta acción de medios no permite el método HTTP solicitado.';
$_['error_media_invalid_path'] = 'La ruta de la carpeta o imagen solicitada no es válida.';
$_['error_media_folder_not_found'] = 'No se encontró la carpeta de medios del Blog seleccionada.';
$_['error_media_folder_unreadable'] = 'No se pudo leer la carpeta seleccionada.';
$_['error_media_folder_name'] = 'Usa hasta 64 letras, números, espacios, guiones o guiones bajos en el nombre.';
$_['error_media_root'] = 'La carpeta de medios del Blog no está disponible o no permite escritura.';
$_['error_media_folder_create'] = 'No se pudo crear la carpeta.';
$_['error_media_folder_exists'] = 'Ya existe un archivo o carpeta con ese nombre.';
$_['error_media_not_found'] = 'No se encontró la imagen o carpeta solicitada.';
$_['error_media_file_in_use'] = 'Esta imagen está referenciada por una entrada del Blog y no se puede eliminar.';
$_['error_media_folder_not_empty'] = 'Solo se pueden eliminar las carpetas vacías.';
$_['error_media_delete'] = 'No se pudo eliminar el elemento de medios.';
$_['error_media_upload_no_file'] = 'Elige una imagen para subir.';
$_['error_media_upload_size'] = 'La imagen no puede superar %s.';
$_['error_media_upload_type'] = 'Elige una imagen JPG, PNG, WebP o GIF válida.';
$_['error_media_upload_move'] = 'No se pudo guardar la imagen. Comprueba los permisos de la carpeta catalog.';
$_['error_media_upload'] = 'No se pudo subir la imagen.';
$_['error_media_generic'] = 'La biblioteca de medios no pudo completar la solicitud. Inténtalo de nuevo.';
$_['error_media_url'] = 'Introduce una URL de imagen HTTP o HTTPS válida.';

// F9-16: block editor and safe legacy conversion
$_['text_block_editor_intro'] = 'Edita el contenido en bloques. Los cambios se guardan al enviar este formulario; no hay guardado automático.';
$_['text_block_conversion_note'] = 'El contenido heredado se convirtió en bloques cuando fue posible. Revísalo antes de guardar; el HTML original se conserva como instantánea.';
$_['text_block_review_required'] = 'Este contenido heredado no se pudo convertir de forma segura. Revísalo en el editor heredado antes de cambiar el formato.';
$_['text_block_invalid'] = 'El documento de bloques no es válido o no es compatible. El contenido no se reemplazará.';
$_['text_block_saved'] = 'Este idioma utiliza el formato de bloques.';
$_['error_block_schema_unavailable'] = 'El almacenamiento de bloques no está disponible. Actualiza el esquema antes de guardar contenido en bloques.';
$_['error_block_document_invalid'] = 'El documento de bloques enviado no es válido o no fue preparado por el editor.';
$_['error_block_legacy_review'] = 'El contenido heredado requiere revisión manual antes de convertirse en bloques.';
$_['error_block_mode_invalid'] = 'El modo de contenido enviado no es válido o es incoherente. Vuelve a cargar el formulario.';
$_['error_block_editor_unavailable'] = 'El editor de bloques no se inicializó. Vuelve a cargar el formulario e inténtalo de nuevo.';

// F9-22: inline rich-text toolbar
$_['text_inline_bold'] = 'Negrita';
$_['text_inline_italic'] = 'Cursiva';
$_['text_inline_underline'] = 'Subrayado';
$_['text_inline_strike'] = 'Tachado';
$_['text_inline_code'] = 'Código';
$_['text_inline_link'] = 'Enlace';
$_['text_inline_link_placeholder'] = 'https:// o /ruta';
$_['text_inline_apply_link'] = 'Aplicar enlace';
$_['text_inline_remove_link'] = 'Eliminar enlace';
$_['text_inline_invalid_link'] = 'Introduce una URL HTTP(S) o ruta local válida.';
$_['text_inline_toolbar'] = 'Formato de texto';
$_['text_inline_text_label'] = 'Texto del bloque';
$_['text_inline_placeholder'] = 'Escribe el texto…';
$_['text_inline_editor_label'] = 'Editor visual de bloques del Blog';

// F9-23: block UX, searchable inserter, outline and structural actions
$_['text_block_add'] = 'Añadir bloque';
$_['text_block_search'] = 'Buscar bloques…';
$_['text_block_no_results'] = 'No se encontraron bloques.';
$_['text_block_category_content'] = 'Contenido';
$_['text_block_category_media'] = 'Medios';
$_['text_block_category_layout'] = 'Diseño';
$_['text_block_outline'] = 'Estructura del documento';
$_['text_block_outline_empty'] = 'El documento aún no tiene bloques.';
$_['text_block_move_up'] = 'Mover arriba';
$_['text_block_move_down'] = 'Mover abajo';
$_['text_block_duplicate'] = 'Duplicar';
$_['text_block_transform'] = 'Transformar bloque';
$_['text_block_remove'] = 'Eliminar bloque';
$_['text_block_add_after'] = 'Insertar después de este bloque';
$_['text_block_drag'] = 'Arrastra para reordenar; Alt+↑/↓ también mueve.';
$_['text_block_type_paragraph'] = 'Párrafo';
$_['text_block_type_heading'] = 'Encabezado';
$_['text_block_type_list'] = 'Lista';
$_['text_block_type_image'] = 'Imagen';
$_['text_block_type_quote'] = 'Cita';
$_['text_block_type_video'] = 'Vídeo';
$_['text_block_type_button'] = 'Botón';
$_['text_block_type_separator'] = 'Separador';
$_['text_block_type_group'] = 'Grupo';
$_['text_block_type_columns'] = 'Columnas';
$_['text_block_type_list_item'] = 'Elemento de lista';
$_['text_block_transform_hint'] = 'La transformación conserva solo contenido textual compatible.';
$_['text_block_insert_empty'] = 'No se puede insertar ningún tipo de bloque en esta posición.';

// F9-23: accessible action status
$_['text_block_status_inserted'] = 'Bloque insertado.';
$_['text_block_status_moved'] = 'Bloque movido.';
$_['text_block_status_duplicated'] = 'Bloque duplicado.';
$_['text_block_status_transformed'] = 'Bloque transformado.';
$_['text_block_status_removed'] = 'Bloque eliminado.';
$_['text_block_status_undo'] = 'Cambio deshecho.';
$_['text_block_status_redo'] = 'Cambio rehecho.';
$_['text_block_type_column'] = 'Columna';

// F9-23: localized canvas and history controls
$_['text_block_undo'] = 'Deshacer';
$_['text_block_redo'] = 'Rehacer';
$_['text_block_preview_desktop'] = 'Escritorio';
$_['text_block_preview_tablet'] = 'Tableta';
$_['text_block_preview_mobile'] = 'Móvil';
$_['text_block_editor_tools'] = 'Herramientas del editor de bloques';
$_['text_block_canvas'] = 'Lienzo de contenido';
$_['text_block_canvas_empty'] = 'Elige un bloque en el insertador para empezar.';
$_['text_block_inspector'] = 'Inspector de bloques';
$_['text_block_inspector_empty'] = 'Selecciona un bloque para editar sus atributos.';
$_['text_block_column_count'] = 'columnas · se apilan en pantallas estrechas';

// F9-24: media blocks, responsive controls and built-in patterns
$_['text_block_type_gallery'] = 'Galería';
$_['text_block_type_cover'] = 'Portada';

// BDREditor: structured table controls
$_['text_block_type_table'] = 'Tabla';
$_['text_block_table_header'] = 'La primera fila es encabezado';
$_['text_block_table_add_row'] = 'Añadir fila';
$_['text_block_table_remove_row'] = 'Quitar última fila';
$_['text_block_table_add_column'] = 'Añadir columna';
$_['text_block_table_remove_column'] = 'Quitar última columna';
$_['text_block_table_toggle_header'] = 'Alternar encabezado';
$_['text_block_table_cell_label'] = 'Celda %1$d, %2$d';
$_['text_block_caption'] = 'Pie de foto';
$_['text_block_alignment'] = 'Alineación de imagen';
$_['text_block_image_size'] = 'Tamaño';
$_['text_block_mobile_size'] = 'Tamaño móvil';
$_['text_block_gallery_columns'] = 'Columnas de la galería';
$_['text_block_media_source'] = 'Ruta local de imagen';
$_['text_block_image_alt'] = 'Texto alternativo';
$_['text_block_image_title'] = 'Título de imagen';
$_['text_block_align_left'] = 'Izquierda';
$_['text_block_align_center'] = 'Centro';
$_['text_block_align_right'] = 'Derecha';
$_['text_block_align_content'] = 'Contenido';
$_['text_block_align_wide'] = 'Ancho';
$_['text_block_align_full'] = 'Ancho completo';
$_['text_block_size_small'] = 'Pequeño';
$_['text_block_size_medium'] = 'Mediano';
$_['text_block_size_large'] = 'Grande';
$_['text_block_size_full'] = 'Ancho completo';
$_['text_block_mobile_full'] = 'Ancho completo';
$_['text_block_mobile_large'] = 'Grande';
$_['text_block_mobile_medium'] = 'Mediano';
$_['text_block_video_preview'] = 'Vista previa del vídeo';
$_['text_block_video_preview_hint'] = 'Vista previa segura en administración; la reproducción está en la página pública.';
$_['text_block_media_placeholder'] = 'Selecciona una imagen de la biblioteca.';
$_['text_block_pattern_category'] = 'Patrones de diseño';
$_['text_block_pattern_two_columns'] = 'Destacado en dos columnas';
$_['text_block_pattern_quote_cta'] = 'Cita y llamada a la acción';
$_['text_block_pattern_section_intro'] = 'Introducción de sección';
$_['text_block_pattern_cover_hero'] = 'Portada destacada';
$_['text_block_pattern_hint'] = 'Patrones locales listos para insertar; no se guardan como plantillas globales.';
$_['text_block_outline_label'] = 'Título en la estructura';
$_['text_block_initial_invalid'] = 'El documento inicial de bloques no es válido. Se conserva la fuente para revisión y se bloquea la edición.';
$_['text_block_document_too_large'] = 'El documento de bloques supera el límite de 4 MiB.';

// BDREditor core/file — biblioteca de descargas local.
$_['text_block_type_file'] = 'Archivo para descargar';
$_['text_block_file_source'] = 'Referencia local del archivo';
$_['text_block_file_choose'] = 'Elegir archivo de la biblioteca';
$_['text_download_library'] = 'Archivos para descargar';
$_['text_download_empty'] = 'No hay documentos disponibles.';
$_['text_download_upload'] = 'Subir documento';
$_['text_download_hint'] = 'PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV o TXT; máximo 2 MiB.';
$_['text_download_limit'] = 'Hasta 2 MiB por documento.';
$_['button_download_select'] = 'Usar este archivo';
$_['button_download_upload'] = 'Subir y usar archivo';
$_['error_download_upload_no_file'] = 'Elige un documento para subir.';
$_['error_download_upload_size'] = 'El documento no puede superar %s.';
$_['error_download_upload_type'] = 'Tipo de documento inválido o contenido incompatible.';
$_['error_download_upload_move'] = 'No se pudo guardar el documento. Comprueba los permisos de la carpeta catalog.';
$_['error_download_generic'] = 'No se pudo cargar la biblioteca de descargas.';

// BDREditor media: allowlisted audio/video uploads; no deletion.
$_['text_block_type_audio'] = 'Audio';
$_['text_block_audio_source'] = 'Referencia local del audio';
$_['text_block_audio_choose'] = 'Elegir audio de la biblioteca';
$_['text_audio_library'] = 'Biblioteca de audio';
$_['text_audio_empty'] = 'No se encontró audio compatible en catalog/view/audio/blog/.';
$_['text_audio_hint'] = 'Audio MP3, OGG o WAV local. Subidas hasta 20 MiB; el selector no elimina archivos.';
$_['text_audio_drag_hint']                 = 'Arrastra al editor para insertar este audio.';
$_['button_audio_select'] = 'Usar este audio';
$_['error_audio_generic'] = 'No se pudo cargar la biblioteca de audio.';

// BDREditor keyboard help
$_['text_block_keyboard_shortcuts_title'] = 'Atajos de teclado';
$_['text_block_keyboard_shortcuts'] = 'Ctrl/⌘+F buscar · Ctrl/⌘+B negrita · Ctrl/⌘+I cursiva · Ctrl/⌘+U subrayado · Ctrl/⌘+K enlace · Ctrl/⌘+Z deshacer · Ctrl/⌘+Shift+Z o Ctrl+Y rehacer · Alt+↑/↓ mover bloque · / insertar bloque';

// BDREditor local block clipboard
$_['text_block_copy'] = 'Copiar bloque';
$_['text_block_paste'] = 'Pegar bloque';
$_['text_block_status_copied'] = 'Bloque copiado en este editor.';
$_['text_block_status_pasted'] = 'Bloque pegado.';
$_['text_block_paste_unavailable'] = 'No se puede pegar este bloque aquí o se superaría un límite del documento.';

// BDREditor hierarchical navigation
$_['text_block_navigation_path'] = 'Ruta del bloque seleccionado';
$_['text_block_navigation_cell'] = 'Celda';
$_['text_block_status_focus_changed'] = 'El foco se movió al bloque seleccionado.';
$_['text_block_navigation_row'] = 'Fila';

// BDREditor outline keyboard navigation
$_['text_block_outline_keyboard_hint'] = '↑/↓ navegar · → primer hijo · ← bloque padre · Home/End inicio/fin';
$_['text_block_status_outline_changed'] = 'Bloque seleccionado actualizado desde la navegación de estructura.';

// BDREditor indent/outdent
$_['text_block_indent'] = 'Sangrar dentro del bloque anterior';
$_['text_block_outdent'] = 'Quitar sangría tras el padre';
$_['text_block_status_indented'] = 'Bloque movido dentro del contenedor anterior.';
$_['text_block_status_outdented'] = 'Bloque movido después del padre.';
$_['text_block_hierarchy_move_unavailable'] = 'Este movimiento no está permitido aquí o superaría un límite del documento.';

// BDREditor save-state feedback
$_['text_block_save_state_saved'] = 'Todos los cambios están guardados.';
$_['text_block_save_state_unsaved'] = 'Hay cambios sin guardar.';
$_['text_block_save_state_saving'] = 'Guardando cambios…';

// BDREditor editorial callout
$_['text_block_type_callout'] = 'Aviso';
$_['text_block_callout_tone'] = 'Estilo del aviso';
$_['text_block_callout_tone_info'] = 'Información';
$_['text_block_callout_tone_success'] = 'Éxito';
$_['text_block_callout_tone_warning'] = 'Advertencia';
$_['text_block_callout_tone_danger'] = 'Crítico';
$_['text_block_callout_default'] = 'Añade una información importante.';

// BDREditor accessible details block
$_['text_block_type_details'] = 'Detalles desplegables';
$_['text_block_details_summary_label'] = 'Título de la sección desplegable';
$_['text_block_details_summary_default'] = 'Más información';
$_['text_block_details_default'] = 'Añade los detalles aquí.';

// BDREditor vertical spacer block
$_['text_block_type_spacer'] = 'Espaciador vertical';
$_['text_block_spacer_height'] = 'Altura del espacio';
$_['text_block_spacer_16'] = 'Muy pequeño · 16 px';
$_['text_block_spacer_32'] = 'Pequeño · 32 px';
$_['text_block_spacer_48'] = 'Mediano · 48 px';
$_['text_block_spacer_64'] = 'Grande · 64 px';
$_['text_block_spacer_96'] = 'Muy grande · 96 px';

// BDREditor preformatted code block
$_['text_block_type_code'] = 'Código preformateado';
$_['text_block_code_label'] = 'Código preformateado';
$_['text_block_code_default'] = '';
$_['text_block_code_too_large'] = 'El bloque de código supera el límite de 256 KiB.';

// BDREditor: secure uploads to dedicated local audio/video roots; no deletion.
$_['text_audio_upload_hint'] = 'Subir MP3, OGG o WAV · hasta 20 MiB. El archivo se guardará en la biblioteca local del Blog.';
$_['text_video_library'] = 'Biblioteca de vídeos del Blog';
$_['text_video_empty'] = 'No hay vídeos locales registrados.';
$_['text_video_hint'] = 'Solo MP4, WebM u OGV locales.';
$_['text_video_upload_hint'] = 'Subir MP4, WebM u OGV · hasta 100 MiB. El archivo se guardará en la biblioteca local del Blog.';
$_['text_video_drag_hint'] = 'Arrastra al editor para insertar este vídeo.';
$_['button_audio_upload'] = 'Subir audio';
$_['button_video_select'] = 'Usar este vídeo';
$_['button_video_upload'] = 'Subir vídeo';
$_['error_audio_upload_type'] = 'Tipo de audio no válido. Se aceptan MP3, OGG o WAV con contenido coincidente.';
$_['error_video_upload_type'] = 'Tipo de vídeo no válido. Se aceptan MP4, WebM u OGV con contenido coincidente.';
$_['error_video_generic'] = 'No se pudo cargar la biblioteca de vídeo.';
$_['error_audio_video_upload'] = 'No se pudo guardar el archivo multimedia. Inténtalo de nuevo o contacta con administración.';
$_['error_audio_video_root'] = 'La carpeta multimedia dedicada no está disponible.';

$_['error_audio_video_upload_size'] = 'El archivo multimedia supera el límite permitido: %s.';

$_['text_block_type_video_file'] = 'Vídeo local';

$_['text_block_video_file_source'] = 'Ruta local del vídeo';

$_['text_block_video_file_choose'] = 'Seleccionar o subir vídeo';

$_['text_block_type_threejs_thumb'] = 'Miniatura 3D (Three.js)';

$_['text_block_threejs_label'] = 'Etiqueta accesible';

$_['text_block_threejs_scene_config'] = 'Configuración de escena (JSON)';

$_['text_block_threejs_config_hint'] = 'Escena JSON declarativa con límites estrictos. El JavaScript personalizado opcional va en un campo separado y se ejecuta en un sandbox aislado. No uses URL, texturas ni recursos externos; Three.js es local.';

$_['text_block_threejs_preview_hint'] = 'La vista previa del editor es indicativa; los scripts personalizados solo se ejecutan en la miniatura pública, dentro de un iframe aislado.';

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
$_['error_edit_conflict'] = 'Esta entrada se modificó y guardó en otra sesión desde que la abriste. No se guardaron tus cambios. Recarga la versión actual antes de continuar.';
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
