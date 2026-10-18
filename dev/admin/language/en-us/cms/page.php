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
$_['text_confirm_delete']  = 'Delete this page? Its descriptions and SEO keywords are removed.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_add']             = 'Add Page';
$_['text_edit']            = 'Edit Page';
$_['text_general']         = 'Content';
$_['text_data']            = 'Data';
$_['text_languages_note']  = 'One block per installed language. The en-US title is required.';
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
