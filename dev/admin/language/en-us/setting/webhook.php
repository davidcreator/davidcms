<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/webhook — Webhook endpoints CRUD + delivery log language (en-US canonical, ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Webhooks';
$_['text_list']            = 'HTTPS endpoints notified on site events (orders, contact, products). Disabling pauses delivery without losing history.';
$_['text_form']            = 'Register the endpoint that will receive signed events (HMAC with the secret below).';
$_['text_no_results']      = 'No webhooks yet. Create the first one.';
$_['text_confirm_delete']  = 'Delete this webhook? ALL its delivery history is removed too.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_trigger_hint']    = 'Event syntax: order_add, order_edit, contact_add, product_* ... wilcards match event prefixes.';
$_['text_url_hint']        = 'HTTPS only — signed payloads travel protected.';
$_['text_secret_hint']     = 'HMAC secret used to sign each delivery (16 to 64 chars). Keep it private.';
$_['text_status_hint']     = 'Disabled endpoints keep their history but receive no new deliveries.';
$_['text_logs_all']        = 'All deliveries';
$_['text_logs_of']         = 'Deliveries of %s (%s)';
$_['text_logs_hint']       = 'Most recent deliveries (up to 200). 2xx = success; anything else = retry queue candidate.';
$_['text_no_logs']         = 'No deliveries yet.';
$_['text_log_detail']      = 'Delivery detail (read-only).';
$_['text_success_add']     = 'Webhook created.';
$_['text_success_edit']    = 'Webhook updated.';
$_['text_success_delete']  = 'Webhook deleted.';

// Columns
$_['column_trigger']       = 'Trigger';
$_['column_url']           = 'URL';
$_['column_secret']        = 'Secret';
$_['column_description']   = 'Description';
$_['column_status']        = 'Status';
$_['column_deliveries']    = 'Deliveries';
$_['column_delivery']      = 'Delivery';
$_['column_http_code']     = 'HTTP';
$_['column_duration']      = 'Duration';
$_['column_attempt']       = 'Attempt';
$_['column_date_added']    = 'Date';
$_['column_action']        = 'Action';

// Entries
$_['entry_trigger']        = 'Trigger';
$_['entry_url']            = 'Endpoint URL';
$_['entry_secret']         = 'HMAC Secret';
$_['entry_description']    = 'Description';
$_['entry_status']         = 'Enabled';
$_['entry_delivery']       = 'Delivery ID';
$_['entry_http_code']      = 'HTTP Code';
$_['entry_duration']       = 'Duration';
$_['entry_attempt']        = 'Attempt';
$_['entry_date_added']     = 'Delivered at';
$_['entry_payload']        = 'Payload';

// Buttons
$_['button_add']           = 'Add New';
$_['button_edit']          = 'Edit';
$_['button_delete']        = 'Delete';
$_['button_save']          = 'Save';
$_['button_cancel']        = 'Cancel';
$_['button_logs']          = 'Deliveries';
$_['button_view']          = 'View';
$_['button_back']          = 'Back to webhooks';
$_['button_back_logs']     = 'Back to deliveries';
$_['button_generate']      = 'Generate';

// Errors
$_['error_trigger']        = 'Invalid trigger: use lowercase event syntax (ex.: order_add, order_*, contact_add), up to 64 chars.';
$_['error_url_https']      = 'URL must start with https:// (up to 255 chars).';
$_['error_secret']         = 'Secret must be 16 to 64 characters long.';
$_['error_description']    = 'Description is required (1 to 255 characters).';
$_['error_duplicate']      = 'This trigger + URL pair already exists (the pair must be unique).';
