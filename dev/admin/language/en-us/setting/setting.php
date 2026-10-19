<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/setting — Operational settings screen (en-US canonical).
 */

// Headings
$_['heading_title']      = 'Settings';
$_['text_settings']      = 'Operational keys that override the install defaults. Applied on the next request.';
$_['text_edit']          = 'Change manage = requires ACL modify on "setting/setting".';
$_['text_success']       = 'Settings saved.';
$_['text_cache_cleared'] = 'Cache cleared.';

// Fieldsets
$_['fieldset_store']     = 'Store';
$_['fieldset_mail']      = 'Mail';
$_['fieldset_cache']     = 'Cache';

// Entries
$_['entry_config_name']             = 'Store name';
$_['entry_config_meta_title']       = 'Default meta title';
$_['entry_config_meta_description'] = 'Default meta description';
$_['entry_mail_engine']             = 'Mail engine';
$_['entry_mail_smtp_hostname']      = 'SMTP hostname';
$_['entry_mail_smtp_username']      = 'SMTP username';
$_['entry_mail_smtp_password']      = 'SMTP password';
$_['entry_mail_smtp_port']          = 'SMTP port';
$_['entry_mail_smtp_timeout']       = 'SMTP timeout (s)';
$_['entry_mail_reply_to']           = 'Reply-To';
$_['entry_cache_engine']            = 'Cache engine';
$_['entry_cache_expire']            = 'Cache TTL (s)';

// Helps
$_['help_config_name']             = 'Used in <title> and emails aligned with the brand.';
$_['help_config_meta_title']       = 'Fallback title when the page has no specific title (120 chars).';
$_['help_config_meta_description'] = '<meta name="description"> used by pages without their own (max ~160 chars ideal; field validates up to 255).';
$_['help_mail_engine']             = 'mail() renders locally; SMTP to an external relay (queued for sending feature).';
$_['help_mail_smtp_hostname']      = 'Ex.: smtp.hostinger.com';
$_['help_mail_smtp_username']      = 'Usually the username/full email.';
$_['help_mail_smtp_password']      = 'Never shown again. Leave empty spaces if they belong to the password.';
$_['help_mail_smtp_port']          = '25 (insecure), 465 (ssl), 587 (starttls) or 2525.';
$_['help_mail_smtp_timeout']       = 'Seconds before time-out (1–60).';
$_['help_mail_reply_to']           = 'Optional. Reply-To header of outbound emails.';
$_['help_cache_engine']            = 'Applied on next request (cache assembled early in boot).';
$_['help_cache_expire']            = '60 to 2,592,000 seconds (max 30 days).';

// Errors
$_['error_config_name']            = 'Store name is required.';
$_['error_mail_engine']            = 'Invalid mail engine.';
$_['error_smtp_hostname_required'] = 'SMTP hostname is required with engine=smtp.';
$_['error_smtp_port']              = 'Accepted ports: 25, 465, 587, 2525.';
$_['error_smtp_timeout']           = 'Timeout must be between 1 and 60 seconds.';
$_['error_mail_reply_to']          = 'Invalid Reply-To email.';
$_['error_cache_engine']           = 'Invalid cache engine.';
$_['error_cache_expire']           = 'TTL must be 60s to 30 days.';

// Buttons
$_['button_save']        = 'Save settings';
$_['button_clear_cache'] = 'Clear cache';
$_['button_back']        = 'Back to dashboard';
