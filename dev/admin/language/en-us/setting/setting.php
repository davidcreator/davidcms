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
$_['text_cache_clear_unsupported'] = 'The active cache engine does not support full clearing from the panel.';

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
$_['help_mail_engine']             = 'mail() sends via PHP; SMTP for an external relay; capture writes to storage/logs/mail_capture.json (dev/CI).';
$_['help_mail_smtp_hostname']      = 'Ex.: smtp.hostinger.com';
$_['help_mail_smtp_username']      = 'Usually the username/full email.';
$_['help_mail_smtp_password']      = 'The current password is never displayed. Leave blank to keep it; enter a new value to replace it. Use the checkbox to clear it. If validation fails, re-enter the new password.';
$_['help_mail_smtp_port']          = '25 (insecure), 465 (ssl), 587 (starttls) or 2525.';
$_['help_mail_smtp_timeout']       = 'Seconds before time-out (1–60).';
$_['help_mail_reply_to']           = 'Optional. Reply-To header of outbound emails.';
$_['help_cache_engine']            = 'Effective at boot (reads rms_setting — F4-06); an engine unavailable on this server falls back to file with a log notice.';
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

// F4-05 (ADR 0029): send test (setting/setting.testMail)
$_['button_test_mail']          = 'Send test e-mail';
$_['text_mail_test_hint']       = 'uses the saved settings; goes to your user e-mail.';
$_['text_mail_test_subject']    = '[ReamurCMS] Send test';
$_['text_mail_test_body']       = "If you received this message, the configured mail engine is working.";
$_['text_mail_test_sent']       = 'Test e-mail sent to %s.';
$_['text_mail_test_error']      = 'Failed to send the test e-mail: %s';
$_['error_mail_test_recipient'] = 'the logged-in user has no valid e-mail';

// Buttons
$_['button_save']        = 'Save settings';
$_['button_clear_cache'] = 'Clear cache';
$_['button_back']        = 'Back to dashboard';

// F8-03: secret handling and request protection
$_['text_smtp_password_set'] = 'A password is already configured. Leave this field blank to keep it.';
$_['text_smtp_password_unset'] = 'No SMTP password is configured.';
$_['entry_mail_smtp_password_clear'] = 'Clear the saved SMTP password';
$_['error_csrf'] = 'This form expired or could not be verified. Reload the page and try again.';
$_['text_close'] = 'Close';

$_['fieldset_threejs'] = 'Catalog Three.js scenes';
$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['entry_three_hero_enabled'] = 'Homepage 3D particles';
$_['help_three_hero_enabled'] = 'Enables or disables hero WebGL. The HTML/CSS fallback remains visible when disabled or WebGL is unavailable.';
$_['entry_three_hero_particle_count'] = 'Particle count';
$_['help_three_hero_particle_count'] = 'From 50 to 800; high values may affect mobile devices.';
$_['entry_three_hero_color'] = 'Scene color';
$_['help_three_hero_color'] = 'Hex color applied to hero and not-found particles.';
$_['entry_three_hero_speed'] = 'Animation speed';
$_['help_three_hero_speed'] = 'Multiplier from 0.01 to 0.20; applied to hero and not-found scenes.';
$_['entry_three_not_found_enabled'] = '3D scene on not-found page';
$_['help_three_not_found_enabled'] = 'Enables or disables the not-found particle scene; text and fallback remain.';
$_['error_three_hero_enabled'] = 'Invalid homepage scene toggle.';
$_['error_three_hero_particle_count'] = 'Particle count must be an integer from 50 to 800.';
$_['error_three_hero_color'] = 'Enter a valid hexadecimal color in #RRGGBB format.';
$_['error_three_hero_speed'] = 'Speed must be from 0.01 to 0.20 in increments of 0.01.';
$_['error_three_not_found_enabled'] = 'Invalid not-found scene toggle.';
$_['entry_three_portfolio_enabled'] = '3D portfolio gallery';
$_['help_three_portfolio_enabled'] = 'Shows a decorative 3D layer behind the existing accessible project cards. Disabled by default; CSS tilt and HTML links remain available.';
$_['entry_three_portfolio_frame_limit'] = 'Maximum portfolio panels';
$_['help_three_portfolio_frame_limit'] = 'From 1 to 12. Project cover images load lazily when the portfolio scene approaches the viewport.';
$_['error_three_portfolio_enabled'] = 'Invalid portfolio scene toggle.';
$_['error_three_portfolio_frame_limit'] = 'The panel limit must be an integer from 1 to 12.';
