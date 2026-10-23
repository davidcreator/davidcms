<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/project — Portfolio CRUD language (en-US canonical, ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Portfolio';
$_['text_list']            = 'Projects of the portfolio shown on the site. Per-language content is edited in the project form.';
$_['text_no_results']      = 'No projects yet. Create the first one.';
$_['text_confirm_delete']  = 'Delete this project? Images, descriptions and SEO keywords of it are removed.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_yes']             = 'Yes';
$_['text_no']              = 'No';
$_['text_add']             = 'Add Project';
$_['text_edit']            = 'Edit Project';
$_['text_general']         = 'Content';
$_['text_data']            = 'Data';
$_['text_languages_note']  = 'Use the language tabs. The en-US title is required.';
$_['text_image_cover']     = 'Cover image';
$_['text_gallery']         = 'Gallery';
$_['text_upload_hint']     = 'JPG, PNG, WebP or GIF up to 2 MB. The file is stored now; save the form to bind it to the project.';
$_['text_success_add']     = 'Project created.';
$_['text_success_edit']    = 'Project updated.';
$_['text_success_delete']  = 'Project deleted.';
$_['text_placeholder']     = '(no cover)';

// Columns
$_['column_image']         = 'Image';
$_['column_name']          = 'Name';
$_['column_client']        = 'Client';
$_['column_featured']      = 'Featured';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Sort Order';
$_['column_date_modified'] = 'Modified';
$_['column_action']        = 'Action';

// Entries
$_['entry_name']             = 'Title';
$_['entry_summary']          = 'Summary';
$_['entry_description']      = 'Description';
$_['entry_tag']              = 'Tags (comma separated)';
$_['entry_meta_title']       = 'Meta Tag Title';
$_['entry_meta_description'] = 'Meta Tag Description';
$_['entry_keyword']          = 'SEO Keyword';
$_['entry_client']           = 'Client';
$_['entry_url']              = 'Project URL';
$_['entry_image']            = 'Cover';
$_['entry_category']         = 'Categories';
$_['entry_featured']         = 'Featured';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Sort Order';
$_['entry_alt']              = 'Alt text';

// Buttons
$_['button_add']        = 'Add New';
$_['button_edit']       = 'Edit';
$_['button_delete']     = 'Delete';
$_['button_save']       = 'Save';
$_['button_cancel']     = 'Cancel';
$_['button_upload']     = 'Upload';
$_['button_remove']     = 'Remove';
$_['button_add_image']  = 'Add Image';

// Errors
$_['error_name']          = 'The en-US title is required (1 to 255 characters).';
$_['error_url']           = 'Project URL must be a valid URL (with scheme).';
$_['error_keyword_format'] = 'SEO Keyword (language #%s): only lowercase letters, numbers and hyphens.';
$_['error_keyword_taken']  = 'SEO Keyword "%s" is already in use.';
$_['error_image_path']     = 'Cover must be a file uploaded through the Upload button.';
$_['error_upload']        = 'Upload failed — check the file and try again.';
$_['error_upload_method'] = 'Upload must be a POST.';
$_['error_upload_size']   = 'File exceeds the %s limit.';
$_['error_upload_type']   = 'Invalid type: only JPG, PNG, WebP or GIF.';
$_['error_upload_move']   = 'Could not store the uploaded file (check directory permissions).';
$_['error_permission']    = 'You do not have permission to modify the portfolio.';

// Fase 8 / Tarefa 08 — interface, filtros/paginação e CSRF.
$_['text_all_status'] = 'All statuses';
$_['text_all_featured'] = 'All featured states';
$_['text_search_placeholder'] = 'Search by name…';
$_['text_showing'] = 'Showing %d–%d of %d projects';
$_['text_empty_title'] = 'No matching projects';
$_['text_empty_hint'] = 'Create a project or adjust the filters.';
$_['text_meta_group'] = 'Search metadata';
$_['text_upload_wait'] = 'Wait for the upload to finish before saving.';
$_['text_no_categories'] = 'No categories available.';
$_['button_filter'] = 'Filter';
$_['button_reset'] = 'Reset';
$_['button_close'] = 'Close';
$_['button_first_page'] = '« First';
$_['button_last_page'] = 'Last »';
$_['button_next_page'] = 'Next ›';
$_['button_previous_page'] = '‹ Previous';
$_['error_csrf'] = 'Invalid session token. Reload the form and try again.';
