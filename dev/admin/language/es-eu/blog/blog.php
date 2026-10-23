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
