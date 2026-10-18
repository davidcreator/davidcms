<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/client_logo — Client Logos CRUD language (en-US canonical, ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Client Logos';
$_['text_list']            = 'Logos of clients displayed on the home page. Single-language entries.';
$_['text_no_results']      = 'No client logos yet. Create the first one.';
$_['text_confirm_delete']  = 'Delete this client logo? The image file is removed as well.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_add']             = 'Add Client Logo';
$_['text_edit']            = 'Edit Client Logo';
$_['text_data']            = 'Data';
$_['text_image']           = 'Logo Image';
$_['text_upload_hint']     = 'JPG, PNG, WebP or GIF up to 2 MB. The file is stored now; save the form to bind it to the logo.';
$_['text_success_add']     = 'Client logo created.';
$_['text_success_edit']    = 'Client logo updated.';
$_['text_success_delete']  = 'Client logo deleted.';
$_['text_placeholder']     = '(no logo)';
$_['text_none']            = '(none)';

// Columns
$_['column_image']         = 'Logo';
$_['column_name']          = 'Name';
$_['column_url']           = 'URL';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Sort Order';
$_['column_action']        = 'Action';

// Entries
$_['entry_name']       = 'Name';
$_['entry_url']        = 'URL';
$_['entry_image']      = 'Logo';
$_['entry_status']     = 'Status';
$_['entry_sort_order'] = 'Sort Order';

// Buttons
$_['button_add']        = 'Add New';
$_['button_edit']       = 'Edit';
$_['button_delete']     = 'Delete';
$_['button_save']       = 'Save';
$_['button_cancel']     = 'Cancel';
$_['button_upload']     = 'Upload';

// Errors
$_['error_name']          = 'Name is required (1 to 128 characters).';
$_['error_url']           = 'URL must start with http:// or https:// (or leave it empty).';
$_['error_image_path']    = 'Logo must be a file uploaded through the Upload button.';
$_['error_upload']        = 'Upload failed — check the file and try again.';
$_['error_upload_method'] = 'Upload must be a POST.';
$_['error_upload_size']   = 'File exceeds the %s limit.';
$_['error_upload_type']   = 'Invalid type: only JPG, PNG, WebP or GIF.';
$_['error_upload_move']   = 'Could not store the uploaded file (check directory permissions).';
$_['error_permission']    = 'You do not have permission to modify the client logos.';
