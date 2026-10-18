<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * sale/order — Orders/Quotes CRUD language (en-US canonical, ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Orders & Quotes';
$_['text_list']            = 'Service orders and quote requests. Totals are recomputed on save (quantity × price per item).';
$_['text_no_results']      = 'No orders yet. Create the first one.';
$_['text_confirm_delete']  = 'Delete this order? Its items and status history are removed.';
$_['text_add']             = 'Add Order';
$_['text_edit']            = 'Edit Order';
$_['text_customer']        = 'Customer';
$_['text_products']        = 'Items';
$_['text_data']            = 'Order Data';
$_['text_history']         = 'Status History';
$_['text_status_hint']     = 'Every status change is appended to the history below.';
$_['text_success_add']     = 'Order created.';
$_['text_success_edit']    = 'Order updated.';
$_['text_success_delete']  = 'Order deleted.';
$_['text_no_products']     = 'No items yet — add the first row.';

// Columns
$_['column_order']         = 'Order';
$_['column_customer']      = 'Customer';
$_['column_email']         = 'E-mail';
$_['column_total']         = 'Total';
$_['column_status']        = 'Status';
$_['column_date_added']    = 'Added';
$_['column_date_modified'] = 'Modified';
$_['column_action']        = 'Action';
$_['column_comment']       = 'Comment';

// Entries
$_['entry_firstname']  = 'First Name';
$_['entry_lastname']   = 'Last Name';
$_['entry_email']      = 'E-mail';
$_['entry_telephone']  = 'Phone / WhatsApp';
$_['entry_product']    = 'Item (service/project)';
$_['entry_quantity']   = 'Qty';
$_['entry_price']      = 'Unit Price';
$_['entry_total']      = 'Row Total';
$_['entry_comment']    = 'Briefing / Notes';
$_['entry_status']     = 'Status';

// Buttons
$_['button_add']          = 'Add New';
$_['button_edit']         = 'Edit';
$_['button_delete']       = 'Delete';
$_['button_save']         = 'Save';
$_['button_cancel']       = 'Cancel';
$_['button_add_product']  = 'Add Item';
$_['button_remove']       = 'Remove';

// Errors
$_['error_firstname']     = 'First name is required (1 to 32 characters).';
$_['error_email']         = 'A valid e-mail is required (up to 96 characters).';
$_['error_product']       = 'Item row incomplete: fill the item name or clear the row.';
$_['error_status']        = 'Invalid status selected.';
$_['error_permission']    = 'You do not have permission to modify the orders.';
