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
