<?php
/**
 * common/dashboard (ADMIN) — painel inicial (F8-01, ADR 0045) — en-us
 */

$_['heading_title']         = 'Dashboard';
$_['text_welcome_overview'] = 'Your operation at a glance — counters respect your permissions.';
$_['text_stats_overview']   = 'Overview';
$_['text_quick_actions']    = 'Quick actions';
$_['text_latest_posts']     = 'Latest posts';
$_['text_latest_messages']  = 'Latest messages';
$_['text_view_all']         = 'View all';
$_['text_no_posts']         = 'Nothing here yet — as soon as content exists, it shows up here.';
$_['text_no_messages']      = 'No messages.';
$_['text_no_subject']       = '(no subject)';
$_['text_panel_user']       = 'User';
$_['text_panel_tech']       = 'Account & system';

// DASH F1-F7 legacy module list (mantido — contrato de baterias F2-F7)
$_['text_modules']              = 'Modules';
$_['text_manage_portfolio']     = 'Portfolio';
$_['text_manage_categories']    = 'Categories';
$_['text_manage_pages']         = 'Pages';
$_['text_manage_blog']          = 'Blog';
$_['text_manage_testimonials']  = 'Testimonials';
$_['text_manage_client_logos']  = 'Client Logos';
$_['text_manage_tools']         = 'Tools';
$_['text_manage_orders']        = 'Orders';
$_['text_manage_messages']      = 'Messages';
$_['text_manage_webhooks']      = 'Webhooks';
$_['text_manage_marketplace']   = 'Mercado Pago Marketplace';
$_['text_manage_users']         = 'Users';
$_['text_manage_user_groups']   = 'User Groups';
$_['text_manage_settings']      = 'Settings';
$_['text_manage_site_info']     = 'Site Information';
$_['text_manage_languages']     = 'Languages';

// Profile / tech panel
$_['text_profile']          = 'Profile';
$_['text_username']         = 'Username';
$_['text_email']            = 'E-mail';
$_['text_user_group']       = 'User group';
$_['text_manage_profile']   = 'Edit profile';
$_['text_acl']              = 'ACL';
$_['text_access_routes']    = 'Access routes';
$_['text_modify_routes']    = 'Modify routes';
$_['text_acl_note']         = 'Managed in Users → Groups. Cards and menus only appear for permitted routes.';
$_['text_cache_indicator']  = 'Cache: %s · TTL %ss';
$_['text_cache_fallback']   = 'the panel configured "%s" — fallback active';
$_['text_version']          = 'ReamurCMS v%s';
$_['button_logout']         = 'Sign out';

// Cards
$_['card_posts']         = 'Published posts';
$_['card_pages']         = 'CMS pages';
$_['card_projects']      = 'Projects';
$_['card_orders']        = 'Orders & quotes';
$_['card_messages']      = 'Messages';
$_['card_testimonials']  = 'Testimonials';
$_['card_tools']         = 'Tools';
$_['card_webhook_fails'] = 'Webhook failures';
$_['card_today']         = 'today: %s';
$_['card_7d']            = 'last 7 days: %s';
$_['card_24h']           = 'last 24h: %s';
$_['card_30d']           = 'last 30 days: %s';

// Quick actions
$_['action_new_post']    = 'New post';
$_['action_new_page']    = 'Pages (CMS)';
$_['action_new_project'] = 'New project';
$_['action_new_tool']    = 'Tools';
$_['action_import_wp']   = 'Import from WordPress';

// ADR 0088 (S3)
$_['warning_error_display'] = 'Error display is on for this installation: if something fails, technical details (file, line, SQL) are shown on screen to any visitor. In production, set REAMUR_ERROR_DISPLAY to false in the config.php files of catalog/ and admin/.';

// ADR 0089 (S5): conexão, proxy e CDN
$_['text_conn_heading']           = 'Connection';
$_['text_conn_ip']                = 'Your IP for the system';
$_['text_conn_peer']              = 'Connection received from';
$_['text_conn_https']             = 'HTTPS';
$_['text_conn_https_no']          = 'no';
$_['text_conn_https_direct']      = 'yes, seen by the server';
$_['text_conn_https_proxy']       = 'yes, reported by the proxy';
$_['text_conn_cookie']            = 'Session cookie';
$_['text_conn_cookie_secure']     = 'with Secure';
$_['text_conn_cookie_plain']      = 'without Secure';
$_['text_conn_trust']             = 'Trusted proxies';
$_['text_conn_trust_none']        = 'not configured';
$_['text_conn_trust_list']        = 'list with %d entry(ies)';
$_['text_conn_trust_hops']        = '%d proxy(ies) in front';
$_['text_conn_note']              = 'If both addresses are the same and are not your IP, the site is behind a proxy or CDN that has not been declared in config.php yet.';
$_['warning_proxy_unconfigured']  = 'The connection came from %s carrying proxy headers. If the site sits behind a CDN or reverse proxy, all visitors currently count as a single IP for the login, contact and quote attempt limits. Set REAMUR_TRUSTED_PROXIES or REAMUR_TRUSTED_PROXY_HOPS in catalog/config.php and admin/config.php.';
$_['warning_proxy_untrusted_peer']= 'The connection came from %s, which is not in REAMUR_TRUSTED_PROXIES. The proxy headers were ignored. If that address belongs to your CDN, add it to the list in both config.php files.';
$_['warning_proxy_invalid']       = 'REAMUR_TRUSTED_PROXIES has entries that are neither an address nor a range and were ignored: %s';
$_['warning_proxy_hops_direct']   = 'REAMUR_TRUSTED_PROXY_HOPS is %d, but this connection arrived without X-Forwarded-For. If the server can be reached without going through the proxy, the visitor IP can be forged: prefer REAMUR_TRUSTED_PROXIES with the proxy addresses.';
