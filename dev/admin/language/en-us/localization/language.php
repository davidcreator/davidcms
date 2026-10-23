<?php
// Heading
$_['heading_title'] = 'Languages';

// Text
$_['text_list']            = 'Installed languages. Adding/removing a language means shipping a new language directory — this screen governs metadata, status and per-app defaults.';
$_['text_no_results']      = 'No language rows found in rms_language — check the installer seed.';
$_['text_enabled']         = 'Enabled';
$_['text_disabled']        = 'Disabled';
$_['text_dir_missing']     = 'language directory is missing on disk';
$_['text_default_catalog'] = 'Catalog default';
$_['text_default_admin']   = 'Admin default';
$_['text_defaults_note']   = 'The default language must be enabled. Persists in setting/setting (F4-01) and applies on the next request.';
$_['text_default_locked']  = 'This language is the default of an app — you cannot disable it here. Move the default first.';
$_['text_success_defaults'] = 'Defaults saved.';
$_['text_success_edit']    = 'Language updated.';

// Entry
$_['entry_name']          = 'Display name';
$_['entry_code']          = 'Code (filesystem directory)';

// Column
$_['entry_locale']        = 'Locale';
$_['entry_sort_order']    = 'Sort order';
$_['entry_status']        = 'Enabled';

$_['column_name']         = 'Language';
$_['column_code']         = 'Code';
$_['column_locale']       = 'Locale';
$_['column_sort_order']   = 'Sort order';
$_['column_status']       = 'Status';
$_['column_action']       = 'Action';

// Button
$_['button_save']   = 'Save';
$_['button_edit']   = 'Edit';
$_['button_cancel'] = 'Back';

// Error
$_['error_name']              = 'Display name is required (up to 32 chars).';
$_['error_locale']            = 'Locale is required (up to 32 chars, ex.: pt_BR.UTF-8).';
$_['error_defaults_required'] = 'Pick a default for both apps.';
$_['error_default_disabled']  = 'The default must be an ENABLED language.';
$_['error_disable_default']   = 'You cannot disable the current default of an app. Move the default first.';
$_['error_not_found']         = 'Language not found.';

// Fase 8 / Tarefa 11 — shell, busca/paginação e CSRF.
$_['text_home'] = 'Home';
$_['text_all_status'] = 'All statuses';
$_['text_search_placeholder'] = 'Search name, code or locale…';
$_['text_showing'] = 'Showing %d–%d of %d languages';
$_['text_empty_title'] = 'No matching languages';
$_['text_empty_hint'] = 'Adjust the search or status filter.';
$_['text_readonly_notice'] = 'You may view languages; saving changes requires modify permission.';
$_['button_filter'] = 'Filter';
$_['button_reset'] = 'Reset';
$_['button_close'] = 'Close';
$_['button_first_page'] = '« First';
$_['button_last_page'] = 'Last »';
$_['button_next_page'] = 'Next ›';
$_['button_previous_page'] = '‹ Previous';
$_['error_csrf'] = 'Invalid session token. Reload the form and try again.';
