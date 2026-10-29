<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * cms/page — Content Pages (information) CRUD language (en-US canonical, ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Content Pages';
$_['text_list']            = 'Informational pages of the site (About, Policies, Cookies). Per-language content is edited in the page form.';
$_['text_no_results']      = 'No pages yet. Create the first one.';
$_['text_confirm_delete']  = 'Delete this page? Its descriptions, SEO keywords, and custom field values are removed.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_add']             = 'Add Page';
$_['text_edit']            = 'Edit Page';
$_['text_general']         = 'Content';
$_['text_data']            = 'Data';
$_['text_languages_note']  = 'Use the language tabs. The en-US title is required.';
$_['text_success_add']     = 'Page created.';
$_['text_success_edit']    = 'Page updated.';
$_['text_success_delete']  = 'Page deleted.';

// Columns
$_['column_title']         = 'Title';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Sort Order';
$_['column_date_modified'] = 'Modified';
$_['column_action']        = 'Action';

// Entries
$_['entry_title']            = 'Title';
$_['entry_description']      = 'Description';
$_['entry_meta_title']       = 'Meta Tag Title';
$_['entry_meta_description'] = 'Meta Tag Description';
$_['entry_keyword']          = 'SEO Keyword';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Sort Order';

// Buttons
$_['button_add']        = 'Add New';
$_['button_edit']       = 'Edit';
$_['button_delete']     = 'Delete';
$_['button_save']       = 'Save';
$_['button_cancel']     = 'Cancel';

// Errors
$_['error_title']         = 'The en-US title is required (1 to 255 characters).';
$_['error_keyword_format'] = 'SEO Keyword (language #%s): only lowercase letters, numbers and hyphens.';
$_['error_keyword_taken']  = 'SEO Keyword "%s" is already in use.';
$_['error_permission']    = 'You do not have permission to modify the pages.';

// F8-06 — lista/form no shell, filtros/paginação e CSRF.
$_['text_all_status'] = 'All statuses';
$_['text_search_placeholder'] = 'Search by title…';
$_['text_showing'] = 'Showing %d–%d of %d pages';
$_['text_empty_title'] = 'No matching pages';
$_['text_empty_hint'] = 'Create a page or adjust the filters.';
$_['text_meta_group'] = 'Search metadata';
$_['help_sort_order'] = 'Lower numbers appear first.';
$_['help_description'] = 'Enter the page content. HTML remains supported.';
$_['button_filter'] = 'Filter';
$_['button_reset'] = 'Reset';
$_['button_first_page'] = '« First';
$_['button_last_page'] = 'Last »';
$_['button_next_page'] = 'Next ›';
$_['button_previous_page'] = '‹ Previous';
$_['error_csrf'] = 'Invalid session token. Reload the form and try again.';
$_['button_close'] = 'Close';

// F9-07 — Custom field values on CMS pages (admin-only).
$_['text_custom_fields'] = 'Custom fields';
$_['text_custom_fields_note'] = 'Values are saved with this page. Public rendering is not enabled by this change.';
$_['text_shared_value'] = 'Shared value';
$_['text_select_option'] = '— Select —';
$_['text_yes'] = 'Yes';
$_['text_no'] = 'No';
$_['error_custom_field_required'] = 'A required custom field is empty.';
$_['error_custom_field_number'] = 'A custom field must contain a valid number.';
$_['error_custom_field_boolean'] = 'A custom boolean field has an invalid value.';
$_['error_custom_field_url'] = 'A custom URL must be a valid http(s) URL.';
$_['error_custom_field_select'] = 'A custom field selection is not one of the configured options.';
$_['error_custom_field_invalid'] = 'A custom field value is invalid.';
$_['error_custom_field_save'] = 'The page was saved, but custom field values could not be saved. Please reopen the page and try again.';
$_['error_custom_field_delete'] = 'Custom values could not be removed, so the page was not deleted.';

// Dedicated page content editing flow.
$_['text_page_context_page'] = 'Page';
$_['text_page_context_language'] = 'Language';
$_['text_page_context_untitled'] = 'Untitled page';
$_['text_page_context_save_hint'] = 'Save here uses the existing form submission and validation.';
$_['text_page_context_readonly_hint'] = 'View-only mode: this user cannot save this page.';
$_['text_content_summary_note'] = 'Content summary. Open the dedicated editor to edit text and blocks.';
$_['text_content_empty'] = 'This page has no content yet.';
$_['text_content_save_first'] = 'Save the general details to open the editor.';
$_['text_content_block_count'] = '%d content blocks';
$_['text_page_content_editor'] = 'Page content editor';
$_['text_content_editor_help'] = 'Edit content by language. General details, SEO, and status stay on the page form.';
$_['button_edit_content'] = 'Edit content';
$_['button_save_content'] = 'Save content';
$_['text_content_invalid'] = 'The stored content must be restored before editing.';
$_['text_editor_home'] = 'Home';
$_['text_editor_insert'] = 'Insert';
$_['text_editor_view'] = 'View';
$_['text_editor_clipboard'] = 'Clipboard';
$_['text_editor_text_format'] = 'Text formatting';
$_['text_editor_paragraph_alignment'] = 'Alignment';
$_['text_editor_group_insert'] = 'Insert content';
$_['text_editor_group_panels'] = 'Panels';
$_['text_editor_group_preview'] = 'Preview';
$_['text_editor_close_panel'] = 'Close panel';
$_['text_editor_align_left'] = 'Align left';
$_['text_editor_align_center'] = 'Center';
$_['text_editor_align_right'] = 'Align right';
$_['text_editor_import_file'] = 'Import file';
$_['text_editor_import_success'] = 'Content imported into the local draft: ';
$_['text_editor_preview_title'] = 'Content preview';
$_['text_editor_preview_mode'] = 'Preview';
$_['text_editor_edit_mode'] = 'Back to editing';
$_['text_editor_import_hint'] = 'Local conversion only; no file is uploaded. Media is referenced only when already in an approved library.';
$_['text_editor_import_unavailable'] = 'Importer unavailable.';
$_['text_editor_import_audio_library'] = 'MP3, OGG, and WAV are not document imports. Insert an Audio block, then select or upload audio through the Blog library.';
$_['text_editor_import_error'] = 'Could not import the file.';
$_['text_editor_preview_video_link'] = 'Embedded video';
$_['text_editor_preview_video_open'] = 'Open in new tab';

$_['text_block_type_video_file'] = 'Local video';

$_['text_block_video_file_source'] = 'Local video path';

$_['text_block_video_file_choose'] = 'Select or upload video';

$_['text_editor_import_video_library'] = 'MP4, WebM, and OGV are not document imports. Insert a Local video block, then select or upload video through the Blog library.';

$_['text_block_type_threejs_thumb'] = '3D thumbnail (Three.js)';

$_['text_block_threejs_label'] = 'Accessible label';

$_['text_block_threejs_scene_config'] = 'Scene configuration (JSON)';

$_['text_block_threejs_config_hint'] = 'Declarative JSON scene with strict bounds. Optional custom JavaScript is in a separate field and runs in an isolated sandbox. Do not use URLs, textures, or external resources; Three.js is self-hosted.';

$_['text_block_threejs_preview_hint'] = 'The full editor Preview and public thumbnail run custom scripts only in an isolated opaque sandboxed iframe. The block card does not execute them.';

$_['text_block_threejs_objects'] = 'Scene objects';

$_['text_block_threejs_lights'] = 'Lighting';

$_['text_block_threejs_addShape'] = 'Add shape';

$_['text_block_threejs_addLight'] = 'Add light';

$_['text_block_threejs_remove'] = 'Remove';

$_['text_block_threejs_color'] = 'Color';

$_['text_block_threejs_animation'] = 'Animation';

$_['text_block_threejs_intensity'] = 'Intensity';

$_['text_block_threejs_invalidScene'] = 'Scene JSON is invalid or out of bounds. Correct the configuration to edit this scene.';

$_['text_block_threejs_sceneConfig'] = 'Scene JSON (advanced)';

$_['text_block_threejs_scriptLabel'] = 'Custom JavaScript (sandbox)';

$_['text_block_threejs_scriptHint'] = 'Paste plain JavaScript only, without <script> tags. It runs in an isolated iframe with no CMS DOM/cookie access; CSP blocks network/external resources. API: THREE, scene, camera, renderer, canvas, and onFrame(fn). 8 KiB limit. Code can hang this thumbnail; use trusted scripts only. Example: const mesh = new THREE.Mesh(new THREE.TorusGeometry(.8,.2,16,48), new THREE.MeshStandardMaterial({color:0xff66aa})); scene.add(mesh); onFrame(({delta}) => { mesh.rotation.y += delta; });';

$_['text_block_threejs_editInline'] = 'Edit thumbnail and script';

$_['text_block_threejs_closeInline'] = 'Close editor';

$_['text_block_threejs_deleteScript'] = 'Delete script';

$_['text_block_threejs_scriptDeleted'] = 'Thumbnail script deleted. Use Undo to restore it.';

$_['text_block_threejs_scriptPlaceholder'] = 'Add JavaScript to customize the Three.js scene…';

$_['text_block_threejs_addScript'] = 'Edit thumbnail / add script';

// CMS Pages private draft preview.
$_['text_page_preview_button'] = 'Open preview';
$_['text_page_preview_title'] = 'Private page preview';
$_['text_page_preview_notice'] = 'Private preview using the current Catalog shell and page template. Blog, Portfolio, Tools, and other module-owned areas show clearly labeled sample records; nothing is published or saved.';
$_['text_page_preview_sample_content'] = 'Sample content for preview only';
$_['text_page_preview_sample_item'] = 'Example item';
$_['text_page_preview_untitled'] = 'Untitled page';
$_['error_page_preview_permission'] = 'You do not have permission to preview CMS Pages.';
$_['error_page_preview_method'] = 'Preview requires a POST request.';
$_['error_page_preview_csrf'] = 'Invalid or expired CSRF token.';
$_['error_page_preview_document'] = 'The block document is invalid or exceeds the allowed size.';
$_['error_page_preview_unavailable'] = 'Preview is unavailable.';
// CMS Pages image library (block media only; no Page entity image field).
$_['text_page_media_library'] = 'Pages image library';
$_['text_page_media_empty'] = 'No images found.';
$_['text_page_media_loading'] = 'Loading images…';
$_['text_page_media_search'] = 'Search by filename';
$_['text_page_media_upload_hint'] = 'JPEG, PNG, WebP, or GIF; maximum 2 MiB. Pages-only image library.';
$_['text_page_media_uploading'] = 'Uploading image…';
$_['text_page_media_select'] = 'Select';
$_['text_page_media_page'] = 'Page %1 of %2';
$_['text_page_media_upload'] = 'Upload file';
$_['text_page_media_error'] = 'The media operation could not be completed.';
$_['error_page_media_permission'] = 'You do not have permission to access the Pages media library.';
$_['error_page_media_method'] = 'Invalid HTTP method.';
$_['error_page_media_csrf'] = 'Invalid or expired CSRF token.';
$_['error_page_media_list'] = 'Could not list Pages media files.';
$_['error_page_media_no_file'] = 'Choose a file to upload.';
$_['error_page_media_size'] = 'The selected file exceeds the size limit for this media type.';
$_['error_page_media_type'] = 'File extension and detected MIME type do not match an allowed format.';
$_['error_page_media_root'] = 'The Pages-only image directory is unavailable.';
$_['error_page_media_upload'] = 'Image upload failed.';

// F10-01 — páginas públicas do Catalog vinculadas ao CMS.
$_['text_system_page'] = 'Catalog page';
$_['text_system_page_help'] = 'This page is linked to a public Catalog route. Its route key is fixed; edit the content and metadata here.';
$_['error_system_page_delete'] = 'This system page cannot be deleted. You can disable or edit its content.';

// Biblioteca independiente de medios de CMS > Páginas.
$_['text_page_media_tab_image'] = 'Images';
$_['text_page_media_tab_audio'] = 'Audio';
$_['text_page_media_tab_video'] = 'Video';
$_['text_page_media_tab_download'] = 'Downloads';
$_['text_page_media_empty_audio'] = 'No audio files found.';
$_['text_page_media_empty_video'] = 'No video files found.';
$_['text_page_media_empty_download'] = 'No downloadable files found.';
$_['text_page_media_upload_hint_audio'] = 'MP3, OGG, WAV · up to 20 MiB';
$_['text_page_media_upload_hint_video'] = 'MP4, WebM, OGV · up to 100 MiB';
$_['text_page_media_upload_hint_download'] = 'PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV, TXT · up to 2 MiB';

// F9-25 — BDRE personal pattern library
$_['text_block_saved_patterns'] = 'Saved patterns';
$_['text_block_save_pattern'] = 'Save as pattern';
$_['text_block_rename_pattern'] = 'Rename';
$_['text_block_delete_pattern'] = 'Delete';
$_['text_block_pattern_name_prompt'] = 'Pattern name';
$_['text_block_pattern_rename_prompt'] = 'New pattern name';
$_['text_block_pattern_delete_confirm'] = 'Delete pattern';
$_['text_block_pattern_saved'] = 'Pattern saved.';
$_['text_block_pattern_renamed'] = 'Pattern renamed.';
$_['text_block_pattern_deleted'] = 'Pattern deleted.';
$_['text_block_pattern_error'] = 'Could not update the pattern.';
$_['text_block_pattern_incompatible'] = 'This pattern is not compatible with this editor location.';
$_['text_block_pattern_invalid'] = 'This block cannot be saved as a standalone pattern.';
$_['text_block_pattern_custom_js_rejected'] = 'Patterns cannot contain JavaScript.';

$_['text_block_draft_recovery_prompt'] = 'An unsaved BDRE draft from this browser tab was found. Restore it? The temporary draft is stored only in this tab until saved or discarded.';
$_['text_block_draft_recovery_hint'] = 'A temporary recovery copy is kept in this browser tab. The site content is saved only when you click Save.';

// F9-29 — portable BDRE document files
$_['text_block_export_document'] = 'Export BDRE document';
$_['text_block_export_success'] = 'BDRE document exported.';
$_['text_block_export_error'] = 'Could not export the BDRE document.';
$_['text_block_import_replace_confirm'] = 'Replace the entire current document with this BDRE file?';
