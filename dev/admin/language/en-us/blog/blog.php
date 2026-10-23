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
