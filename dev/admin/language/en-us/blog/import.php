<?php
/**
 * blog/import — WordPress importer (F7-03, ADR 0044) — en-us
 */

// Heading
$_['heading_title']            = 'Import from WordPress';

// Text
$_['text_form_intro']          = 'Reads the public WP REST API, downloads featured images and materializes the posts in the internal blog. Idempotent: already-imported slugs are skipped; keyword collisions get a -2/-3 suffix. Language anchor: every post lands in en-US (coalesce makes it visible in all three languages).';
$_['text_preview']             = 'Preview (no writing)';
$_['text_run']                 = 'Run import';
$_['text_confirm']             = 'Really import posts and write them to the database?';
$_['text_summary_header']      = 'Report';
$_['text_summary_preview']     = 'Preview: %d post(s) would be imported (%d already skipped, %d with keyword suffix).';
$_['text_summary_run']         = "Import: %d imported (%d with suffix), %d skipped, %d errors.\n%s";
$_['text_map_written']         = '301 redirect map written (%d bytes) to system/storage/cache/wp-301-map.txt';
$_['text_map_failed']          = 'Warning: redirect map could not be written (check storage permissions).';
$_['text_actions']             = 'Import';
$_['text_action_imported']     = 'Imported';
$_['text_action_import_with_suffix'] = 'Import with suffix';
$_['text_action_skip']         = 'Skip';
$_['text_action_error']        = 'Error';
$_['text_log_header']          = 'Operation log';
$_['text_no_rows']             = 'No posts returned in this page.';
$_['text_imported_edit']       = 'edit';

// Entry
$_['entry_base_url']           = 'WordPress base URL';
$_['entry_page']               = 'Page';
$_['entry_per_page']           = 'Per page';
$_['entry_public_url']         = 'Public catalog URL';

// Help
$_['help_base_url']            = 'The public /wp-json/ endpoint must be reachable. No credentials are sent.';
$_['help_page']                = 'WP REST pagination (X-WP-TotalPages). Start at 1.';
$_['help_per_page']            = 'Max 100 per request (WP API hard limit).';
$_['help_public_url']          = 'Base for the 301 map (absolute URLs of the NEW blog). Derived from HTTP_SERVER; adjust for exotic topologies.';

// Column
$_['column_wp_id']             = 'WP ID';
$_['column_title']             = 'Title';
$_['column_date']              = 'Date';
$_['column_keyword']           = 'Keyword';
$_['column_action_taken']      = 'Action';
$_['column_image']             = 'Image';
$_['column_status_label']      = 'Status';

// Button
$_['button_back_list']         = 'Back to the blog';
$_['button_submit']            = 'Go';

// Error
$_['error_action']             = 'Unknown action (expected preview or run).';
$_['error_base_url']           = 'Base URL is required.';
$_['error_fetch']              = 'Failed to read the WordPress endpoint: %s';

// F8-02 (ADR 0046): cards
$_['text_source_card']     = 'Source';
$_['text_pagination_card'] = 'Sample window';
