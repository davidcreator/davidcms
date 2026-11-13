<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * extension/extension — Extensions screen: folder list, enable, disable, configure (EX-01, ADR 0095; en-US canonical, ADR 0001).
 */

$_['heading_title']            = 'Extensions';
$_['text_list']                = 'Extensions found in the extension folder of this site. Enable, disable and configure them here.';
$_['text_install_hint']        = 'To install an extension, copy its folder to the server (FTP or deploy) into:';
$_['text_trust_hint']          = 'An enabled extension is code that runs with the same reach as the CMS itself (database, files, settings). Only enable extensions from a source you know. While an extension is disabled, none of its code runs.';
$_['text_no_results']          = 'No extension in the folder yet.';
$_['text_close']               = 'Close';
$_['text_enabled']             = 'Enabled';
$_['text_disabled']            = 'Disabled';
$_['text_invalid']             = 'Invalid description';
$_['text_incompatible']        = 'Incompatible';
$_['text_folder_missing']      = 'Folder not found';
$_['text_folder_missing_hint'] = 'This extension has saved data, but its folder is no longer on the server. Nothing of it runs. Put the folder back, or remove the data.';
$_['text_runs_in']             = 'Runs in';
$_['text_app_catalog']         = 'public site';
$_['text_app_admin']           = 'admin';
$_['text_events']              = 'Listens to';
$_['text_no_events']           = 'no event';
$_['text_sources']             = 'Outside addresses it asks for';
$_['text_no_sources']          = 'none';
$_['text_problems']            = 'The extension.json file was refused:';
$_['text_blockers']            = 'Cannot be enabled yet:';
$_['text_details']             = 'What this extension does on the site';
$_['text_version']             = 'v';
$_['text_author']              = 'by';
$_['text_confirm_enable']      = 'Enable this extension? Its code starts running on the site right away. Only enable extensions from a source you know.';
$_['text_confirm_purge']       = 'Remove everything this extension saved (settings and secrets)? This cannot be undone.';
$_['text_off_by_config']       = 'All extensions are switched off by the REAMUR_EXTENSIONS line in config.php. What is marked as enabled below starts running again when that line is removed.';
$_['text_no_folder']           = 'The extension folder does not exist on this server:';
$_['text_form']                = 'Settings of this extension.';
$_['text_secret_set']          = 'Value saved';
$_['text_secret_unset']        = 'No value saved';
$_['text_secret_hint']         = 'This value is never shown again. Leave empty to keep the saved one, or type a new one to replace it.';
$_['text_secret_locked']       = 'A saved secret cannot be opened with the key in config.php. The extension receives it as empty. Type it again, or restore the key that saved it.';
$_['text_secret_clear']        = 'Remove the saved value';
$_['text_secret_clear_help']   = 'Used only if the field above is empty.';
$_['text_secret_no_key']       = 'The secrets key is not set in config.php (Settings > Secret protection). A secret saved now stays readable in the database until that is done.';
$_['text_select_none']         = '(none)';
$_['text_required']            = 'Required';
$_['text_success_save']        = 'Settings of "%s" saved.';
$_['text_success_enable']      = 'Extension "%s" enabled.';
$_['text_success_disable']     = 'Extension "%s" disabled.';
$_['text_success_purge']       = 'Saved data of "%s" removed.';
$_['column_extension']         = 'Extension';
$_['column_status']            = 'Status';
$_['column_action']            = 'Actions';
$_['button_enable']            = 'Enable';
$_['button_disable']           = 'Disable';
$_['button_configure']         = 'Configure';
$_['button_purge']             = 'Remove data';
$_['button_save']              = 'Save';
$_['button_cancel']            = 'Cancel';
$_['error_csrf']               = 'This form expired or could not be verified. Reload the page and try again.';
$_['error_form']               = 'Some fields need attention. Nothing was saved.';
$_['error_required']           = 'This field is required.';
$_['error_too_long']           = 'At most %d characters.';
$_['error_pattern']            = 'This value is not in the format the extension accepts.';
$_['error_option']             = 'Choose one of the listed options.';
$_['error_not_found']          = 'Extension not found in the folder, or its extension.json is invalid.';
$_['error_no_settings']        = 'This extension has nothing to configure.';
$_['error_blocked']            = '"%s" cannot be enabled: %s';
$_['error_purge_enabled']      = 'Disable the extension before removing its data.';
