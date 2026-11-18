<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * security/dracaunos — Dracaunos-Security (ferramenta interna, KZ-34).
 */

$_['heading_title']        = 'Dracaunos-Security';
$_['text_home']            = 'Home';
$_['text_internal_note']   = 'INTERNAL ReamurCMS tool: admin-only, never shown in the public catalog.';
$_['text_password_policy']  = 'Password policy';
$_['entry_min_length']     = 'Minimum length';
$_['entry_max_age']        = 'Max age (days, 0 = never expires)';
$_['entry_require_upper']  = 'Require uppercase letter';
$_['entry_require_digit']  = 'Require digit';
$_['entry_require_symbol'] = 'Require symbol';
$_['text_admin_route']     = 'Admin route';
$_['help_admin_route']     = 'Admin folder slug (applied on the next server deploy/rewrite).';
$_['text_2fa']            = 'Two-factor authentication (2FA)';
$_['text_2fa_enabled_state'] = '2FA is enabled on this account.';
$_['help_2fa']             = 'Generate a secret, add it to your authenticator app (Google Authenticator, Authy, 1Password) and confirm with the 6-digit code.';
$_['entry_2fa_secret']     = 'Secret (base32)';
$_['placeholder_2fa_secret'] = 'e.g. JBSWY3DPEHPK3PXP';
$_['entry_2fa_code']       = 'Verification code (6 digits)';
$_['button_2fa_enable']    = 'Enable 2FA';
$_['button_2fa_disable']   = 'Disable 2FA';
$_['text_login_log']       = 'Login log (last 30)';
$_['text_recovery']        = 'Password / account recovery & verification (pending)';
$_['text_ip_rules']        = 'IP rules (IP verification)';
$_['text_users']           = 'Users & profile';
$_['help_users']           = 'Manage logins in the Users module; quick list here with a link to each account profile.';
$_['col_date']  = 'Date'; $_['col_user'] = 'User'; $_['col_result'] = 'Result';
$_['col_email'] = 'Email'; $_['col_type'] = 'Type'; $_['col_action'] = 'Action';
$_['col_scope'] = 'Scope'; $_['col_note'] = 'Note'; $_['col_status'] = 'Status';
$_['text_ok'] = 'OK'; $_['text_fail'] = 'FAILED'; $_['text_empty'] = 'No records.';
$_['text_enabled'] = 'Enabled'; $_['text_disabled'] = 'Disabled';
$_['opt_deny'] = 'Deny'; $_['opt_allow'] = 'Allow';
$_['entry_note'] = 'Note';
$_['button_save'] = 'Save'; $_['button_add'] = 'Add'; $_['button_delete'] = 'Delete';
$_['button_approve'] = 'Approve'; $_['button_deny'] = 'Deny'; $_['button_edit_profile'] = 'Profile';
$_['text_policy_saved'] = 'Password policy saved.';
$_['text_route_saved']  = 'Admin route saved.';
$_['text_2fa_enabled']   = '2FA enabled on this account.';
$_['text_2fa_disabled']  = '2FA disabled.';
$_['text_recovery_handled'] = 'Request updated.';
$_['text_ip_saved']      = 'IP rule saved.';
$_['text_ip_deleted']    = 'IP rule removed.';
$_['error_route_invalid'] = 'Invalid route (lowercase letters, digits and hyphen only).';
$_['error_2fa_code']      = 'Invalid 6-digit code for the given secret.';

// KZ-39 — Phase B: admin audit log
$_['text_audit']   = 'Admin audit log (last 50)';
$_['help_audit']   = 'Every authenticated admin request is recorded (user, route, method, IP). IP rules apply to ALL admin routes, and the route configured above is enforced for real (the physical /admin/ folder is refused with 403 when a custom route is set).';
$_['col_route']    = 'Route'; $_['col_method'] = 'Method';

// KZ-41 — mandatory 2FA in the admin
$_['text_force_2fa'] = 'Policy requires 2FA: enable two-factor verification on this account to keep using the panel.';
$_['entry_2fa_required'] = 'Require 2FA for all admin users';
$_['help_2fa_required'] = 'When enabled, users without 2FA configured can only reach this screen (to enable it) and logout.';
$_['text_2fa_required_saved'] = '2FA requirement updated.';

// KZ-42 — CSV export and retention cleanup
$_['button_export_csv'] = 'Export CSV';
$_['button_prune']      = 'Prune old records';
$_['help_prune']        = 'Deletes audit and login logs older than the configured retention (dracaunos_audit_days / dracaunos_login_log_days).';
$_['text_pruned']       = 'Cleanup done: %d audit record(s) and %d login log record(s) removed.';
