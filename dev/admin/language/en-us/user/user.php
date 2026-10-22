<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language en-us — user/user (CRUD de usuários do painel).
 */
// Heading
$_['heading_title'] = 'Users';

// Text
$_['text_list']           = 'Administrator accounts of this panel';
$_['text_no_results']     = 'No users found.';
$_['text_confirm_delete'] = 'Delete this user? This cannot be undone.';
$_['text_enabled']        = 'Enabled';
$_['text_disabled']       = 'Disabled';
$_['text_add']            = 'New user';
$_['text_edit']           = 'Edit user';
$_['text_password_hint']  = 'Leave blank to keep the current password.';
$_['text_success_add']    = 'User created successfully.';
$_['text_success_edit']   = 'User updated successfully.';
$_['text_success_delete'] = 'User deleted successfully.';

// Column
$_['column_username'] = 'Username';
$_['column_email']    = 'E-mail';
$_['column_group']    = 'Group';
$_['column_status']   = 'Status';
$_['column_action']   = 'Actions';

// Entry
$_['entry_username']  = 'Username';
$_['entry_firstname'] = 'First Name';
$_['entry_lastname']  = 'Last Name';
$_['entry_email']     = 'E-mail';
$_['entry_password']  = 'Password';
$_['entry_confirm']   = 'Confirm Password';
$_['entry_group']     = 'User Group';
$_['entry_status']    = 'Enabled';

// Button
$_['button_save']   = 'Save';
$_['button_cancel'] = 'Cancel';
$_['button_add']    = 'Add User';
$_['button_edit']   = 'Edit';
$_['button_delete'] = 'Delete';

// Error
$_['error_username']        = 'Username must be between 3 and 32 characters!';
$_['error_username_exists'] = 'Username is already in use!';
$_['error_email']           = 'Invalid e-mail address!';
$_['error_firstname']       = 'First name must be between 1 and 32 characters!';
$_['error_lastname']        = 'Last name must be between 1 and 32 characters!';
$_['error_password']        = 'Password must be at least 8 characters!';
$_['error_confirm']         = 'Password confirmation does not match!';
$_['error_group']           = 'Choose a user group!';
$_['error_self_delete']     = 'You cannot delete your own user while logged in!';
$_['error_self_disable']      = 'You cannot deactivate your own account while you are logged in.';
$_['error_last_active_admin'] = 'Cannot inactivate/demote/delete the last active user able to administer users (user/user).';

// F8-03: search, pagination and request protection
$_['text_filter'] = 'Filter users';
$_['text_all_groups'] = 'All groups';
$_['text_all_status'] = 'All statuses';
$_['text_showing'] = 'Showing %s–%s of %s';
$_['text_self'] = 'You';
$_['entry_filter_username'] = 'Username';
$_['entry_filter_email'] = 'E-mail';
$_['entry_filter_group'] = 'User group';
$_['button_filter'] = 'Apply filters';
$_['button_reset'] = 'Reset';
$_['error_csrf'] = 'This form expired or could not be verified. Reload the page and try again.';
$_['text_status'] = 'Status';
$_['text_close'] = 'Close';
$_['text_page_first'] = '« First';
$_['text_page_last'] = 'Last »';
$_['text_page_next'] = 'Next ›';
$_['text_page_prev'] = '‹ Prev';
