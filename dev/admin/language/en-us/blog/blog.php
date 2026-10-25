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
$_['text_block_canvas'] = 'Content canvas';
$_['text_block_canvas_empty'] = 'Choose a block from the inserter to get started.';
$_['text_block_inspector'] = 'Block inspector';
$_['text_block_inspector_empty'] = 'Select a block to edit its attributes.';
$_['text_block_column_count'] = 'columns · stack on narrow screens';

// F9-24: media blocks, responsive controls and built-in patterns
$_['text_block_type_gallery'] = 'Gallery';
$_['text_block_type_cover'] = 'Cover';
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
