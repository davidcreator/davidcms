<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * communication/contact_message — Contact inbox language (en-US canonical, ADR 0001).
 * Status semantics per Dicionário-de-Dados §9: 0 new / 1 read / 2 replied.
 */

// Headings & texts
$_['heading_title']         = 'Contact Messages';
$_['text_list']             = 'Messages sent through the site contact form. Replies go out by e-mail — mark the message as Replied when done.';
$_['text_no_results']       = 'No messages yet.';
$_['text_empty_filter']     = 'No messages with this status.';
$_['text_confirm_delete']   = 'Delete this message permanently?';
$_['text_message_detail']   = 'Read-only view. New messages are marked as Read when opened.';
$_['text_actions']          = 'Actions';
$_['text_all']              = 'All';
$_['text_new']              = 'New';
$_['text_read']             = 'Read';
$_['text_replied']          = 'Replied';
$_['text_success_status']   = 'Message status updated.';
$_['text_success_delete']   = 'Message deleted.';

// Columns
$_['column_name']           = 'Name';
$_['column_email']          = 'E-mail';
$_['column_subject']        = 'Subject';
$_['column_status']         = 'Status';
$_['column_date_added']     = 'Received';
$_['column_action']         = 'Action';

// Entries (read view)
$_['entry_name']            = 'Name';
$_['entry_email']           = 'E-mail';
$_['entry_telephone']       = 'Telephone';
$_['entry_subject']         = 'Subject';
$_['entry_message']         = 'Message';
$_['entry_status']          = 'Status';
$_['entry_ip']              = 'IP';
$_['entry_date_added']      = 'Received at';

// Buttons
$_['button_read']           = 'Read';
$_['button_delete']         = 'Delete';
$_['button_back']           = 'Back to list';
$_['button_reply']          = 'Reply by e-mail';
$_['button_mark_replied']   = 'Mark as Replied';
$_['button_mark_read']      = 'Mark as Read';
$_['button_mark_new']       = 'Mark as New';

// Errors
$_['error_status']          = 'Invalid status (allowed: New, Read, Replied).';
