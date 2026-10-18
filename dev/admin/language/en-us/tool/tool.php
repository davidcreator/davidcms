<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * tool/tool — Connected Tools CRUD language (en-US canonical, ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Connected Tools';
$_['text_list']            = 'Tools and integrations showcased on the site (icons + links). Per-language content is edited in the tool form.';
$_['text_no_results']      = 'No tools yet. Create the first one.';
$_['text_confirm_delete']  = 'Delete this tool? Its descriptions and the icon file are removed.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_add']             = 'Add Tool';
$_['text_edit']            = 'Edit Tool';
$_['text_general']         = 'Content';
$_['text_data']            = 'Data';
$_['text_languages_note']  = 'One block per installed language. The en-US name is required.';
$_['text_image']           = 'Icon';
$_['text_upload_hint']     = 'JPG, PNG, WebP or GIF up to 2 MB. The file is stored now; save the form to bind it to the tool.';
$_['text_success_add']     = 'Tool created.';
$_['text_success_edit']    = 'Tool updated.';
$_['text_success_delete']  = 'Tool deleted.';
$_['text_placeholder']     = '(no icon)';
$_['text_none']            = '(none)';

// Columns
$_['column_image']         = 'Icon';
$_['column_name']          = 'Name';
$_['column_vendor']        = 'Vendor';
$_['column_url']           = 'URL';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Sort Order';
$_['column_date_added']    = 'Added';
$_['column_action']        = 'Action';

// Entries
$_['entry_name']        = 'Name';
$_['entry_description'] = 'Description';
$_['entry_vendor']      = 'Vendor';
$_['entry_url']         = 'URL';
$_['entry_image']       = 'Icon';
$_['entry_status']      = 'Status';
$_['entry_sort_order']  = 'Sort Order';

// Buttons
$_['button_add']        = 'Add New';
$_['button_edit']       = 'Edit';
$_['button_delete']     = 'Delete';
$_['button_save']       = 'Save';
$_['button_cancel']     = 'Cancel';
$_['button_upload']     = 'Upload';

// Errors
$_['error_name']          = 'The en-US name is required (1 to 128 characters).';
$_['error_url']           = 'URL must start with http:// or https:// (or leave it empty).';
$_['error_image_path']    = 'Icon must be a file uploaded through the Upload button.';
$_['error_upload']        = 'Upload failed — check the file and try again.';
$_['error_upload_method'] = 'Upload must be a POST.';
$_['error_upload_size']   = 'File exceeds the %s limit.';
$_['error_upload_type']   = 'Invalid type: only JPG, PNG, WebP or GIF.';
$_['error_upload_move']   = 'Could not store the uploaded file (check directory permissions).';
$_['error_permission']    = 'You do not have permission to modify the tools.';
