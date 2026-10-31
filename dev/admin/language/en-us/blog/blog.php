<?php
/**
 * blog/blog — Blog (F7-01, ADR 0042) — en-us
 */

// Heading
$_['heading_title']           = 'Blog';

// Text
$_['text_list']               = 'News & articles, newest first. Public section (F7-02): /blog';
$_['text_add']                = 'New post';
$_['text_edit']               = 'Edit post';
$_['text_general']            = 'Content (per language)';
$_['text_data']               = 'Data';
$_['text_languages_note']     = 'Only English (en-us) is required; other languages are optional.';
$_['text_no_results']         = 'No posts yet. Start with your first article!';
$_['text_confirm_delete']     = 'Delete this post permanently?';
$_['text_enabled']            = 'Published';
$_['text_disabled']           = 'Draft';
$_['text_success_add']        = 'Post published.';
$_['text_success_edit']       = 'Post updated.';
$_['text_success_delete']     = 'Post deleted.';
$_['text_flash']              = '';

// Column
$_['column_title']            = 'Title';
$_['column_status']           = 'Status';
$_['column_date_published']   = 'Published';
$_['column_date_modified']    = 'Modified';
$_['column_action']           = 'Action';

// Entry
$_['entry_title']             = 'Title';
$_['entry_description']       = 'Content';
$_['entry_meta_title']        = 'Meta title';
$_['entry_meta_description']  = 'Meta description';
$_['entry_keyword']           = 'SEO keyword (optional)';
$_['entry_image']             = 'Featured image';
$_['entry_date_published']    = 'Publish date';
$_['entry_status']            = 'Published';

// Help
$_['help_image']              = 'Relative path or external URL. The WordPress importer (F7-03) fills this for you.';
$_['help_date_published']     = 'Public order is newest first. Leave as-is to publish now.';

// Button
$_['button_add']              = 'Add post';
$_['button_edit']             = 'Edit';
$_['button_delete']           = 'Delete';
$_['button_save']             = 'Save';
$_['button_cancel']           = 'Cancel';
$_['button_import_wp']        = 'Import from WordPress'; // F7-03 (ADR 0044)

// Private Catalog preview for Blog block drafts.
$_['text_catalog_preview_untitled'] = 'Untitled post';
$_['text_catalog_preview_button'] = 'Preview in Catalog';
$_['text_catalog_preview_error'] = 'The editor is not ready or the document is invalid. Check the content and try again.';
$_['text_catalog_preview_notice'] = 'Private preview of this unsaved Blog post. Nothing has been saved or published.';
$_['text_published_on'] = 'Published on';
$_['button_back'] = 'Back to Blog';
$_['text_content_review'] = 'This content is under editorial review.';

// Error
$_['error_title']             = 'Title (en-us) is required and must be up to 255 characters.';
$_['error_date_published']    = 'Publish date: expected format YYYY-MM-DD HH:MM (optional).';
$_['error_keyword_format']    = 'Keyword of language %d: only lowercase letters, digits and hyphens.';
$_['error_keyword_taken']     = "Keyword '%s' is already used by another page, project, tool or post.";

// F8-02 (ADR 0046): filters bar, badges, states, publish sidebar
$_['text_total_posts']         = 'posts';
$_['text_showing']             = 'Showing %d–%d of %d posts';
$_['text_search_placeholder']  = 'Search by title…';
$_['text_all_status']          = 'All statuses';
$_['text_published']           = 'Published';
$_['text_draft']               = 'Draft';
$_['text_view_site']           = 'View on site';
$_['text_empty_state']         = 'No posts matched — adjust the filters or create the first one.';
$_['text_empty_state_cta']     = 'Create post';
$_['button_filter']            = 'Filter';
$_['button_reset']             = 'Reset';
$_['text_publish_card']        = 'Publication';
$_['text_content_card']        = 'Content';
$_['text_meta_group']          = 'SEO';
$_['text_seo_url_preview']     = 'SEO URL prefix';
$_['text_cover_preview']       = 'Cover image';
$_['text_status_help']         = 'Published posts appear on the journal area of the site.';
$_['text_chars']               = 'characters';

// F8-05 (ADR 0049): filesystem media library for Blog cover images
$_['text_media_library'] = 'Blog image library';
$_['text_media_browse'] = 'Browse';
$_['text_media_upload'] = 'Upload';
$_['text_media_create_folder'] = 'New folder';
$_['text_media_external_url'] = 'External URL';
$_['text_media_loading'] = 'Loading media…';
$_['text_media_empty'] = 'No images or folders here yet.';
$_['text_media_no_matches'] = 'No images or folders matched your search.';
$_['text_media_page'] = 'Page %d of %d';
$_['text_media_confirm_delete_file'] = 'Delete this image permanently? This cannot be undone.';
$_['text_media_confirm_delete_folder'] = 'Delete this empty folder?';
$_['text_media_upload_success'] = 'Image uploaded and selected.';
$_['text_media_folder_created'] = 'Folder created.';
$_['text_media_file_deleted'] = 'Image deleted.';
$_['text_media_folder_deleted'] = 'Folder deleted.';
$_['text_media_upload_hint'] = 'JPG, PNG, WebP or GIF; maximum 2 MB.';
$_['text_media_no_folders'] = 'No folders';
$_['text_media_in_use'] = 'This image is used by a blog post and cannot be deleted.';

$_['entry_media_search'] = 'Search images and folders';
$_['entry_media_folder_name'] = 'Folder name';
$_['entry_media_external_url'] = 'HTTP(S) image URL';

$_['button_media_library'] = 'Choose from library';
$_['button_media_upload'] = 'Upload and use image';
$_['button_media_create_folder'] = 'Create folder';
$_['button_media_use_url'] = 'Use URL';
$_['button_media_select'] = 'Select image';
$_['button_media_delete'] = 'Delete';
$_['button_media_back'] = 'Back';
$_['button_media_previous'] = 'Previous page';
$_['button_media_next'] = 'Next page';
$_['button_media_close'] = 'Close';

$_['error_permission'] = 'You do not have permission to manage Blog media.';
$_['error_csrf'] = 'The security token is invalid or expired. Refresh the page and try again.';
$_['error_media_method'] = 'This media action does not allow the requested HTTP method.';
$_['error_media_invalid_path'] = 'The requested folder or image path is invalid.';
$_['error_media_folder_not_found'] = 'The selected Blog media folder was not found.';
$_['error_media_folder_unreadable'] = 'The selected folder could not be read.';
$_['error_media_folder_name'] = 'Use a folder name of up to 64 letters, numbers, spaces, hyphens or underscores.';
$_['error_media_root'] = 'The Blog media folder is not available or writable.';
$_['error_media_folder_create'] = 'The folder could not be created.';
$_['error_media_folder_exists'] = 'A file or folder with that name already exists.';
$_['error_media_not_found'] = 'The requested image or folder was not found.';
$_['error_media_file_in_use'] = 'This image is referenced by a Blog post and cannot be deleted.';
$_['error_media_folder_not_empty'] = 'Only empty folders can be deleted.';
$_['error_media_delete'] = 'The media item could not be deleted.';
$_['error_media_upload_no_file'] = 'Choose an image file to upload.';
$_['error_media_upload_size'] = 'The image must be no larger than %s.';
$_['error_media_upload_type'] = 'Choose a valid JPG, PNG, WebP or GIF image.';
$_['error_media_upload_move'] = 'The image could not be saved. Check the catalog folder permissions.';
$_['error_media_upload'] = 'The image could not be uploaded.';
$_['error_media_generic'] = 'The media library could not complete the request. Try again.';
$_['error_media_url'] = 'Enter a valid HTTP or HTTPS image URL.';

// F9-16: block editor and safe legacy conversion
$_['text_block_editor_intro'] = 'Edit content as blocks. Changes are saved only when you submit this form; there is no autosave.';
$_['text_block_conversion_note'] = 'Legacy content was converted to blocks where possible. Review it before saving; the original HTML is retained as a snapshot.';
$_['text_block_review_required'] = 'This legacy content could not be converted safely. Review it in the legacy editor before changing its format.';
$_['text_block_invalid'] = 'The block document is invalid or incompatible. Its content will not be replaced.';
$_['text_block_saved'] = 'This language uses the block format.';
$_['error_block_schema_unavailable'] = 'Block storage is unavailable. Update the schema before saving block content.';
$_['error_block_document_invalid'] = 'The submitted block document is invalid or was not prepared by the editor.';
$_['error_block_legacy_review'] = 'Legacy content requires manual review before it can be converted into blocks.';
$_['error_block_mode_invalid'] = 'The submitted content mode is invalid or inconsistent. Reload the form.';
$_['error_block_editor_unavailable'] = 'The block editor did not initialize. Reload the form and try again.';

// F9-22: inline rich-text toolbar
$_['text_inline_bold'] = 'Bold';
$_['text_inline_italic'] = 'Italic';
$_['text_inline_underline'] = 'Underline';
$_['text_inline_strike'] = 'Strikethrough';
$_['text_inline_code'] = 'Code';
$_['text_inline_link'] = 'Link';
$_['text_inline_link_placeholder'] = 'https:// or /path';
$_['text_inline_apply_link'] = 'Apply link';
$_['text_inline_remove_link'] = 'Remove link';
$_['text_inline_invalid_link'] = 'Enter a valid HTTP(S) URL or local path.';
$_['text_inline_toolbar'] = 'Text formatting';
$_['text_inline_text_label'] = 'Block text';
$_['text_inline_placeholder'] = 'Write text…';
$_['text_inline_editor_label'] = 'Blog visual block editor';

// F9-23: block UX, searchable inserter, outline and structural actions
$_['text_block_add'] = 'Add block';
$_['text_block_search'] = 'Search blocks…';
$_['text_block_no_results'] = 'No blocks found.';
$_['text_block_category_content'] = 'Content';
$_['text_block_category_media'] = 'Media';
$_['text_block_category_layout'] = 'Layout';
$_['text_block_outline'] = 'Document outline';
$_['text_block_outline_empty'] = 'The document has no blocks yet.';
$_['text_block_move_up'] = 'Move up';
$_['text_block_move_down'] = 'Move down';
$_['text_block_duplicate'] = 'Duplicate';
$_['text_block_transform'] = 'Transform block';
$_['text_block_remove'] = 'Remove block';
$_['text_block_add_after'] = 'Insert after this block';
$_['text_block_drag'] = 'Drag to reorder; Alt+↑/↓ also moves.';
$_['text_block_type_paragraph'] = 'Paragraph';
$_['text_block_type_heading'] = 'Heading';
$_['text_block_type_list'] = 'List';
$_['text_block_type_image'] = 'Image';
$_['text_block_type_quote'] = 'Quote';
$_['text_block_type_video'] = 'Video';
$_['text_block_type_button'] = 'Button';
$_['text_block_type_separator'] = 'Separator';
$_['text_block_type_group'] = 'Group';
$_['text_block_type_columns'] = 'Columns';
$_['text_block_type_list_item'] = 'List item';
$_['text_block_transform_hint'] = 'Transform preserves only compatible text content.';
$_['text_block_insert_empty'] = 'No block types can be inserted at this position.';

// F9-23: accessible action status
$_['text_block_status_inserted'] = 'Block inserted.';
$_['text_block_status_moved'] = 'Block moved.';
$_['text_block_status_duplicated'] = 'Block duplicated.';
$_['text_block_status_transformed'] = 'Block transformed.';
$_['text_block_status_removed'] = 'Block removed.';
$_['text_block_status_undo'] = 'Change undone.';
$_['text_block_status_redo'] = 'Change redone.';
$_['text_block_type_column'] = 'Column';

// F9-23: localized canvas and history controls
$_['text_block_undo'] = 'Undo';
$_['text_block_redo'] = 'Redo';
$_['text_block_preview_desktop'] = 'Desktop';
$_['text_block_preview_tablet'] = 'Tablet';
$_['text_block_preview_mobile'] = 'Mobile';
$_['text_block_editor_tools'] = 'Block editor tools';
$_['text_block_workspace_rail_label'] = 'Workspace tools';
$_['text_block_workspace_tool_text'] = 'Text';
$_['text_block_workspace_tool_appearance'] = 'Appearance';
$_['text_block_workspace_tool_patterns'] = 'Patterns';
$_['text_block_canvas'] = 'Content canvas';
$_['text_block_canvas_empty'] = 'Choose a block from the inserter to get started.';
$_['text_block_inspector'] = 'Block inspector';
$_['text_block_inspector_empty'] = 'Select a block to edit its attributes.';
$_['text_block_column_count'] = 'columns · stack on narrow screens';

// F9-24: media blocks, responsive controls and built-in patterns
$_['text_block_type_gallery'] = 'Gallery';
$_['text_block_type_cover'] = 'Cover';

// BDREditor: structured table controls
$_['text_block_type_table'] = 'Table';
$_['text_block_table_header'] = 'First row is a header';
$_['text_block_table_add_row'] = 'Add row';
$_['text_block_table_remove_row'] = 'Remove last row';
$_['text_block_table_add_column'] = 'Add column';
$_['text_block_table_remove_column'] = 'Remove last column';
$_['text_block_table_toggle_header'] = 'Toggle header';
$_['text_block_table_cell_label'] = 'Cell %1$d, %2$d';
$_['text_block_caption'] = 'Caption';
$_['text_block_alignment'] = 'Image alignment';
$_['text_block_image_size'] = 'Size';
$_['text_block_mobile_size'] = 'Mobile size';
$_['text_block_gallery_columns'] = 'Gallery columns';
$_['text_block_media_source'] = 'Local image path';
$_['text_block_image_alt'] = 'Alternative text';
$_['text_block_image_title'] = 'Image title';
$_['text_block_align_left'] = 'Left';
$_['text_block_align_center'] = 'Center';
$_['text_block_align_right'] = 'Right';
$_['text_block_align_content'] = 'Content';
$_['text_block_align_wide'] = 'Wide';
$_['text_block_align_full'] = 'Full width';
$_['text_block_size_small'] = 'Small';
$_['text_block_size_medium'] = 'Medium';
$_['text_block_size_large'] = 'Large';
$_['text_block_size_full'] = 'Full width';
$_['text_block_mobile_full'] = 'Full width';
$_['text_block_mobile_large'] = 'Large';
$_['text_block_mobile_medium'] = 'Medium';
$_['text_block_video_preview'] = 'Video preview';
$_['text_block_video_preview_hint'] = 'Safe admin preview; playback is available on the public page.';
$_['text_block_media_placeholder'] = 'Choose an image from the library.';
$_['text_block_pattern_category'] = 'Layout patterns';
$_['text_block_pattern_two_columns'] = 'Two-column feature';
$_['text_block_pattern_quote_cta'] = 'Quote and call to action';
$_['text_block_pattern_section_intro'] = 'Section introduction';
$_['text_block_pattern_cover_hero'] = 'Hero cover';
$_['text_block_pattern_hint'] = 'Built-in local patterns to insert; they are not saved as global templates.';
$_['text_block_outline_label'] = 'Label in outline';
$_['text_block_initial_invalid'] = 'The initial block document is invalid. Its source is preserved for review; editing is blocked.';
$_['text_block_document_too_large'] = 'The block document exceeds the 4 MiB size limit.';

// BDREditor core/file — biblioteca de descargas local.
$_['text_block_type_file'] = 'Download file';
$_['text_block_file_source'] = 'Local file reference';
$_['text_block_file_choose'] = 'Choose a library file';
$_['text_download_library'] = 'Download files';
$_['text_download_empty'] = 'No documents available.';
$_['text_download_upload'] = 'Upload document';
$_['text_download_hint'] = 'PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV, or TXT; maximum 2 MiB.';
$_['text_download_limit'] = 'Up to 2 MiB per document.';
$_['button_download_select'] = 'Use this file';
$_['button_download_upload'] = 'Upload and use file';
$_['error_download_upload_no_file'] = 'Choose a document to upload.';
$_['error_download_upload_size'] = 'The document must be no larger than %s.';
$_['error_download_upload_type'] = 'Invalid document type or mismatched content.';
$_['error_download_upload_move'] = 'The document could not be saved. Check catalog folder permissions.';
$_['error_download_generic'] = 'Could not load the download library.';

// BDREditor media: allowlisted audio/video uploads; no deletion.
$_['text_block_type_audio'] = 'Audio';
$_['text_block_audio_source'] = 'Local audio reference';
$_['text_block_audio_choose'] = 'Choose audio from library';
$_['text_audio_library'] = 'Audio library';
$_['text_audio_empty'] = 'No supported audio found in catalog/view/audio/blog/.';
$_['text_audio_hint'] = 'Local MP3, OGG, or WAV. Uploads up to 20 MiB; the picker does not delete files.';
$_['text_audio_drag_hint']                 = 'Drag into the editor to insert this audio.';
$_['button_audio_select'] = 'Use this audio';
$_['error_audio_generic'] = 'Could not load the audio library.';

// BDREditor keyboard help
$_['text_block_keyboard_shortcuts_title'] = 'Keyboard shortcuts';
$_['text_block_keyboard_shortcuts'] = 'Ctrl/⌘+F find · Ctrl/⌘+B bold · Ctrl/⌘+I italic · Ctrl/⌘+U underline · Ctrl/⌘+K link · Ctrl/⌘+Z undo · Ctrl/⌘+Shift+Z or Ctrl+Y redo · Alt+↑/↓ move block · / insert block';

// BDREditor local block clipboard
$_['text_block_copy'] = 'Copy block';
$_['text_block_paste'] = 'Paste block';
$_['text_block_status_copied'] = 'Block copied in this editor.';
$_['text_block_status_pasted'] = 'Block pasted.';
$_['text_block_paste_unavailable'] = 'This block cannot be pasted here or a document limit would be exceeded.';

// BDREditor hierarchical navigation
$_['text_block_navigation_path'] = 'Selected block path';
$_['text_block_navigation_cell'] = 'Cell';
$_['text_block_status_focus_changed'] = 'Focus moved to the selected block.';
$_['text_block_navigation_row'] = 'Row';

// BDREditor outline keyboard navigation
$_['text_block_outline_keyboard_hint'] = '↑/↓ navigate · → first child · ← parent block · Home/End first/last';
$_['text_block_status_outline_changed'] = 'Selected block updated from outline navigation.';

// BDREditor indent/outdent
$_['text_block_indent'] = 'Indent into previous block';
$_['text_block_outdent'] = 'Outdent after parent';
$_['text_block_status_indented'] = 'Block moved into the previous container.';
$_['text_block_status_outdented'] = 'Block moved after its parent.';
$_['text_block_hierarchy_move_unavailable'] = 'This move is not allowed here or would exceed a document limit.';

// BDREditor save-state feedback
$_['text_block_save_state_saved'] = 'All changes are saved.';
$_['text_block_save_state_unsaved'] = 'You have unsaved changes.';
$_['text_block_save_state_saving'] = 'Saving changes…';

// BDREditor editorial callout
$_['text_block_type_callout'] = 'Callout';
$_['text_block_callout_tone'] = 'Callout style';
$_['text_block_callout_tone_info'] = 'Information';
$_['text_block_callout_tone_success'] = 'Success';
$_['text_block_callout_tone_warning'] = 'Warning';
$_['text_block_callout_tone_danger'] = 'Critical';
$_['text_block_callout_default'] = 'Add an important note for readers.';

// BDREditor accessible details block
$_['text_block_type_details'] = 'Expandable details';
$_['text_block_details_summary_label'] = 'Expandable section title';
$_['text_block_details_summary_default'] = 'More information';
$_['text_block_details_default'] = 'Add the details here.';

// BDREditor vertical spacer block
$_['text_block_type_spacer'] = 'Vertical spacer';
$_['text_block_spacer_height'] = 'Spacing height';
$_['text_block_spacer_16'] = 'Extra small · 16 px';
$_['text_block_spacer_32'] = 'Small · 32 px';
$_['text_block_spacer_48'] = 'Medium · 48 px';
$_['text_block_spacer_64'] = 'Large · 64 px';
$_['text_block_spacer_96'] = 'Extra large · 96 px';

// BDREditor preformatted code block
$_['text_block_type_code'] = 'Preformatted code';
$_['text_block_code_label'] = 'Preformatted code';
$_['text_block_code_default'] = '';
$_['text_block_code_too_large'] = 'The code block exceeds the 256 KiB limit.';

// BDREditor: secure uploads to dedicated local audio/video roots; no deletion.
$_['text_audio_upload_hint'] = 'Upload MP3, OGG, or WAV · up to 20 MiB. The file will be stored in the local Blog library.';
$_['text_video_library'] = 'Blog video library';
$_['text_video_empty'] = 'No local videos are registered.';
$_['text_video_hint'] = 'Local MP4, WebM, or OGV only.';
$_['text_video_upload_hint'] = 'Upload MP4, WebM, or OGV · up to 100 MiB. The file will be stored in the local Blog library.';
$_['text_video_drag_hint'] = 'Drag into the editor to insert this video.';
$_['button_audio_upload'] = 'Upload audio';
$_['button_video_select'] = 'Use this video';
$_['button_video_upload'] = 'Upload video';
$_['error_audio_upload_type'] = 'Invalid audio type. Accepted: MP3, OGG, or WAV with matching content.';
$_['error_video_upload_type'] = 'Invalid video type. Accepted: MP4, WebM, or OGV with matching content.';
$_['error_video_generic'] = 'Could not load the video library.';
$_['error_audio_video_upload'] = 'Could not store the media. Try again or contact an administrator.';
$_['error_audio_video_root'] = 'The dedicated media folder is unavailable.';

$_['error_audio_video_upload_size'] = 'The media exceeds the allowed limit: %s.';

$_['text_block_type_video_file'] = 'Local video';

$_['text_block_video_file_source'] = 'Local video path';

$_['text_block_video_file_choose'] = 'Select or upload video';

$_['text_block_type_threejs_thumb'] = '3D thumbnail (Three.js)';

$_['text_block_threejs_label'] = 'Accessible label';

$_['text_block_threejs_scene_config'] = 'Scene configuration (JSON)';

$_['text_block_threejs_config_hint'] = 'Declarative JSON scene with strict bounds. Optional custom JavaScript is in a separate field and runs in an isolated sandbox. Do not use URLs, textures, or external resources; Three.js is self-hosted.';

$_['text_block_threejs_preview_hint'] = 'The editor preview is illustrative; custom scripts run only in the public thumbnail inside a sandboxed iframe.';

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

// F9-30 — temporary draft history
$_['text_block_history_button'] = 'Draft history';
$_['text_block_history_title'] = 'Temporary draft history';
$_['text_block_history_empty'] = 'No previous temporary drafts in this tab.';
$_['text_block_history_restore'] = 'Restore';
$_['text_block_history_restore_confirm'] = 'Restore this temporary revision into the editor?';
$_['text_block_history_restored'] = 'Temporary revision restored as an unsaved draft.';
// F9-32 — optimistic edit conflict protection
$_['error_edit_conflict'] = 'This post was changed and saved in another session after you opened it. Your changes were not saved. Reload the current version before continuing.';
$_['button_reload_current'] = 'Reload current version';
// F9-33 — compare persisted revisions safely before restore
$_['text_revision_compare'] = 'Compare with form';
$_['text_revision_version'] = 'Selected revision';
$_['text_revision_current'] = 'Current form (includes unsaved changes)';
$_['text_revision_body'] = 'Content (BDRE documents; legacy HTML is shown as text)';
$_['text_revision_meta'] = 'Editorial metadata';$_['text_revision_error'] = 'Could not load this revision comparison.';
// F9-34 — bounded, safe textual revision diff
$_['text_revision_diff'] = 'Text differences';
$_['text_revision_unchanged'] = 'No textual differences.';
$_['text_revision_diff_limit'] = 'Document is too large for word-level comparison; use the columns above.';
// F9-36 — document-wide plain-text find and replace
$_['text_block_find_replace'] = 'Find and replace';
$_['text_block_find_label'] = 'Find text';
$_['text_block_replace_label'] = 'Replace with';
$_['text_block_find_next'] = 'Next match';
$_['text_block_replace_one'] = 'Replace';
$_['text_block_replace_all'] = 'Replace all';
$_['text_block_find_case_sensitive'] = 'Case-sensitive';
$_['text_block_find_cross_run_hint'] = 'Search spans differently formatted text runs within a block, but does not cross block boundaries.';
$_['text_block_find_none'] = 'No matches found.';
$_['text_block_find_count'] = 'Match %1 of %2';
$_['text_block_replace_done'] = 'Replacements made: %1.';
$_['text_block_replace_limit'] = 'Replacement exceeds the 1,000-match limit; nothing was changed.';
$_['text_block_find_empty'] = 'Enter text to find.';
$_['text_block_find_previous'] = 'Previous match';
$_['text_block_find_whole_word'] = 'Whole words only';
$_['text_block_find_keyboard_hint'] = 'Enter next · Shift+Enter previous · Esc close';
$_['text_block_document_statistics'] = 'Document statistics';
$_['text_block_document_words'] = 'Words';
$_['text_block_document_characters'] = 'Characters (including spaces)';
$_['text_block_inspector_tabs'] = 'Block settings';
$_['text_block_custom_content'] = 'Content';
$_['text_block_customize'] = 'Customize';
$_['text_block_custom_text_color'] = 'Text color';
$_['text_block_custom_background'] = 'Background color';
$_['text_block_custom_border_color'] = 'Border color';
$_['text_block_custom_border_style'] = 'Border style';
$_['text_block_custom_border_solid'] = 'Solid';
$_['text_block_custom_border_dashed'] = 'Dashed';
$_['text_block_custom_border_dotted'] = 'Dotted';
$_['text_block_custom_border_width'] = 'Border width (px)';
$_['text_block_custom_radius'] = 'Corner radius (px)';
$_['text_block_custom_padding'] = 'Inner spacing (px)';
$_['text_block_custom_margin'] = 'Outer spacing (px)';
$_['text_block_custom_font_size'] = 'Font size (px)';
$_['text_block_custom_text_align'] = 'Text alignment';
$_['text_block_custom_inherit'] = 'Theme default';
$_['text_block_custom_hint'] = 'Safe custom styles apply to this block and are included in the saved document.';
$_['text_block_custom_reset'] = 'Reset customization';
$_['text_block_gallery_preview'] = 'Gallery preview';
$_['text_block_columns_add'] = 'Add column';
$_['text_block_columns_remove'] = 'Remove last column (move content to previous)';
$_['text_block_columns_limit'] = 'Use 2 or 3 columns. Removing the last moves its blocks to the previous column; you can undo the change.';
$_['text_block_column_default'] = 'Column';

$_['text_block_ribbon_review'] = 'Review';

$_['text_block_ribbon_home'] = 'Home';
$_['text_block_ribbon_insert'] = 'Insert';
$_['text_block_ribbon_view'] = 'View';
