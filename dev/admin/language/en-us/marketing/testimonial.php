<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/testimonial — Testimonials CRUD language (en-US canonical, ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Testimonials';
$_['text_list']            = 'Client testimonials shown on the home page. Per-language content is edited in the testimonial form.';
$_['text_no_results']      = 'No testimonials yet. Create the first one.';
$_['text_confirm_delete']  = 'Delete this testimonial? Its descriptions and the photo file are removed.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_add']             = 'Add Testimonial';
$_['text_edit']            = 'Edit Testimonial';
$_['text_general']         = 'Content';
$_['text_data']            = 'Data';
$_['text_languages_note']  = 'One block per installed language. The en-US author is required.';
$_['text_image']           = 'Author Photo';
$_['text_upload_hint']     = 'JPG, PNG, WebP or GIF up to 2 MB. The file is stored now; save the form to bind it to the testimonial.';
$_['text_success_add']     = 'Testimonial created.';
$_['text_success_edit']    = 'Testimonial updated.';
$_['text_success_delete']  = 'Testimonial deleted.';
$_['text_placeholder']     = '(no photo)';

// Columns
$_['column_image']         = 'Photo';
$_['column_author']        = 'Author';
$_['column_company']       = 'Company';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Sort Order';
$_['column_date_added']    = 'Added';
$_['column_action']        = 'Action';

// Entries
$_['entry_author']     = 'Author';
$_['entry_company']    = 'Company';
$_['entry_text']       = 'Testimonial Text';
$_['entry_image']      = 'Photo';
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
$_['error_author']        = 'The en-US author is required (1 to 128 characters).';
$_['error_image_path']    = 'Photo must be a file uploaded through the Upload button.';
$_['error_upload']        = 'Upload failed — check the file and try again.';
$_['error_upload_method'] = 'Upload must be a POST.';
$_['error_upload_size']   = 'File exceeds the %s limit.';
$_['error_upload_type']   = 'Invalid type: only JPG, PNG, WebP or GIF.';
$_['error_upload_move']   = 'Could not store the uploaded file (check directory permissions).';
$_['error_permission']    = 'You do not have permission to modify the testimonials.';
