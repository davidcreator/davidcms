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
