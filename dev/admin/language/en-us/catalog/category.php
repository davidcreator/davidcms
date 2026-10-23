<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/category — Portfolio Categories CRUD language (en-US canonical, ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Portfolio Categories';
$_['text_list']            = 'Categories used to group the portfolio projects. Per-language content is edited in the category form.';
$_['text_no_results']      = 'No categories yet. Create the first one.';
$_['text_confirm_delete']  = 'Delete this category? Child categories become top-level and project relations are removed.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_add']             = 'Add Category';
$_['text_edit']            = 'Edit Category';
$_['text_general']         = 'Content';
$_['text_data']            = 'Data';
$_['text_languages_note']  = 'Use the language tabs. The en-US name is required.';
$_['text_image_cover']     = 'Image';
$_['text_upload_hint']     = 'JPG, PNG, WebP or GIF up to 2 MB. The file is stored now; save the form to bind it to the category.';
$_['text_success_add']     = 'Category created.';
$_['text_success_edit']    = 'Category updated.';
$_['text_success_delete']  = 'Category deleted.';
$_['text_placeholder']     = '(no image)';
$_['text_none']            = '(none)';

// Columns
$_['column_image']         = 'Image';
$_['column_name']          = 'Name';
$_['column_parent']        = 'Parent';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Sort Order';
$_['column_date_modified'] = 'Modified';
$_['column_action']        = 'Action';

// Entries
$_['entry_name']             = 'Name';
$_['entry_description']      = 'Description';
$_['entry_meta_title']       = 'Meta Tag Title';
$_['entry_meta_description'] = 'Meta Tag Description';
$_['entry_keyword']          = 'SEO Keyword';
$_['entry_parent']           = 'Parent';
$_['entry_image']            = 'Image';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Sort Order';

// Buttons
$_['button_add']        = 'Add New';
$_['button_edit']       = 'Edit';
$_['button_delete']     = 'Delete';
$_['button_save']       = 'Save';
$_['button_cancel']     = 'Cancel';
$_['button_upload']     = 'Upload';

// Errors
$_['error_name']          = 'The en-US name is required (1 to 255 characters).';
$_['error_keyword_format'] = 'SEO Keyword (language #%s): only lowercase letters, numbers and hyphens.';
$_['error_keyword_taken']  = 'SEO Keyword "%s" is already in use.';
$_['error_image_path']     = 'Image must be a file uploaded through the Upload button.';
$_['error_parent_cycle']   = 'Invalid parent: a category cannot be its own parent or child.';
$_['error_upload']        = 'Upload failed — check the file and try again.';
$_['error_upload_method'] = 'Upload must be a POST.';
$_['error_upload_size']   = 'File exceeds the %s limit.';
$_['error_upload_type']   = 'Invalid type: only JPG, PNG, WebP or GIF.';
$_['error_upload_move']   = 'Could not store the uploaded file (check directory permissions).';
$_['error_permission']    = 'You do not have permission to modify the categories.';

// Fase 8 / Tarefa 07 — interface, filtros/paginação e CSRF.
$_['text_all_status'] = 'All statuses';
$_['text_search_placeholder'] = 'Search by name…';
$_['text_showing'] = 'Showing %d–%d of %d categories';
$_['text_empty_title'] = 'No matching categories';
$_['text_empty_hint'] = 'Create a category or adjust the filters.';
$_['text_meta_group'] = 'Search metadata';
$_['help_sort_order'] = 'Existing sort order is preserved; this list is alphabetical.';
$_['text_upload_wait'] = 'Wait for the upload to finish before saving.';
$_['button_filter'] = 'Filter';
$_['button_reset'] = 'Reset';
$_['button_close'] = 'Close';
$_['button_first_page'] = '« First';
$_['button_last_page'] = 'Last »';
$_['button_next_page'] = 'Next ›';
$_['button_previous_page'] = '‹ Previous';
$_['error_csrf'] = 'Invalid session token. Reload the form and try again.';
