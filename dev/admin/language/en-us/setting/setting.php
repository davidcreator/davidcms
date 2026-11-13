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
$_['entry_three_tools_enabled'] = '3D tools icons';
$_['help_three_tools_enabled'] = 'Adds a subtle floating 3D treatment to up to four active tool cards. Disabled by default; HTML labels and links remain available.';
$_['error_three_tools_enabled'] = 'Invalid tools scene toggle.';
$_['error_three_portfolio_frame_limit'] = 'The panel limit must be an integer from 1 to 12.';

// ADR 0094 (S9): segredos cifrados no banco
$_['fieldset_secrets'] = 'Secret protection';
$_['help_secrets'] = 'Mercado Pago tokens, webhook secrets and the SMTP password are stored encrypted in the database. The key that opens them lives in config.php, outside the database.';
$_['text_secret_status_ok'] = 'The key is set in both config.php files. Every stored secret is encrypted.';
$_['text_secret_status_locked'] = 'The key is set in both config.php files, but it is not the key that encrypted the values stored in the database.';
$_['text_secret_status_pending'] = 'The key is set, but some stored secrets are still readable in the database (or encrypted with the previous key). Use the button below.';
$_['text_secret_status_missing'] = 'REAMUR_SECRET_KEY is not in config.php yet. Secrets keep working, but they stay readable in the database. Paste the line below into BOTH files, catalog/config.php and admin/config.php (the same line in both), then open this screen again.';
$_['text_secret_status_invalid'] = 'REAMUR_SECRET_KEY in admin/config.php is not a valid key (it must be exactly a line generated on this screen). Until it is fixed, secrets are stored readable. Replace it with the line below in both config.php files.';
$_['text_secret_status_unavailable'] = 'PHP on this server has no OpenSSL extension with AES-256-GCM; without it secrets cannot be encrypted. Ask your hosting provider to enable the openssl extension.';
$_['text_secret_status_peer_missing'] = 'The key is in admin/config.php but not in catalog/config.php. Copy the define(\'REAMUR_SECRET_KEY\', …) line from admin/config.php into catalog/config.php. Until then nothing is encrypted, so the public site does not lose access to the secrets.';
$_['text_secret_status_peer_different'] = 'The key in catalog/config.php is different from the one in admin/config.php. Both must be identical: copy the line from admin/config.php into catalog/config.php. Until then nothing new is encrypted.';
$_['text_secret_status_peer_unreadable'] = 'catalog/config.php could not be read to confirm it has the same key. Until then nothing is encrypted.';
$_['text_secret_locked_count'] = '%d encrypted value(s) cannot be opened with the current key. If the key was changed, restore the previous one (or put it in REAMUR_SECRET_KEY_OLD). If it was lost, reconnect the Mercado Pago sellers and type the listed secrets again.';
$_['text_secret_key_line'] = 'Line to paste into both config.php files';
$_['text_secret_key_note'] = 'This key was generated just now and is not in use yet; a different one appears each time the screen opens. Pick one, paste it into both files and keep a copy with your backups: without it the encrypted secrets cannot be opened.';
$_['column_secret_item'] = 'Secret';
$_['column_secret_sealed'] = 'Encrypted';
$_['column_secret_clear'] = 'Readable';
$_['column_secret_old'] = 'Previous key';
$_['column_secret_locked'] = 'Cannot be opened';
$_['text_secret_group_mp_seller'] = 'Seller tokens (Mercado Pago)';
$_['text_secret_group_webhook'] = 'Webhook secrets';
$_['text_secret_group_mp_client_secret'] = 'Mercado Pago app secret';
$_['text_secret_group_mp_webhook_secret'] = 'Mercado Pago notification secret';
$_['text_secret_group_mail_smtp_password'] = 'SMTP password';
$_['text_secret_group_extension'] = 'Extension secrets'; // EX-01 (ADR 0095)
$_['text_secret_none'] = 'No secret stored yet.';
$_['button_protect_secrets'] = 'Protect secrets';
$_['text_secrets_protected'] = '%d value(s) encrypted.';
$_['text_secrets_nothing'] = 'Nothing to convert: the stored secrets are already encrypted with the current key.';
$_['text_secrets_webhook_column'] = '%d webhook secret(s) were left out: the database column cannot hold the encrypted value yet. Run install/sql/upgrade_s9_webhook_secret.sql in phpMyAdmin and repeat.';
$_['error_secrets_not_ready'] = 'Secrets cannot be encrypted yet: check the key status on this screen.';
