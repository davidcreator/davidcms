<?php
/**
 * Language en-us — setting/appearance (F8-V, ADR 0047): Panel Appearance.
 */

// Heading
$_['heading_title'] = 'Panel Appearance';

// Text
$_['text_intro']            = 'Curated, WCAG-AA-validated themes for the panel. Set the global default and/or override it just for your account.';
$_['text_global_section']   = 'Panel default (all users)';
$_['text_user_section']     = 'My appearance';
$_['text_global_locked']    = 'Your group has view-only access to the global default. You can still personalize your own experience below.';
$_['text_apply_override']   = 'Override the panel default for my account';
$_['text_override_hint']    = 'When off, you follow the global default. Turning on the topbar dark mode already creates an override for you.';
$_['text_skin_card']        = 'Skin';
$_['text_accent_card']      = 'Accent color';
$_['text_layout_card']      = 'Layout and behavior';
$_['text_mode']             = 'Color mode';
$_['text_mode_system']      = 'System';
$_['text_mode_light']       = 'Light';
$_['text_mode_dark']        = 'Dark';
$_['text_sidebar']          = 'Sidebar';
$_['text_sidebar_auto']     = 'Auto';
$_['text_sidebar_light']    = 'Always light';
$_['text_sidebar_dark']     = 'Always dark';
$_['text_density']          = 'Density';
$_['text_density_comfortable'] = 'Comfortable';
$_['text_density_compact']  = 'Compact';
$_['text_font_scale']       = 'Font scale';
$_['text_accent_skin']      = 'From the skin';
$_['text_accent_custom']    = 'Custom color';
$_['text_accent_custom_dark'] = 'Dark-mode variant';
$_['help_accent_dark']      = 'Optional. Used in dark mode; if empty, the base color is applied there too.';
$_['text_accent_help']      = 'Colors must reach a 4.5:1 contrast ratio on both light and dark surfaces (WCAG AA). Use the magic button to suggest a dark-mode variant.';
$_['text_validating_contrast'] = 'Checking contrast…';
$_['text_contrast_fail']    = 'Fails WCAG AA (ratio below 4.5:1). Choose another color or use the suggestion button.';
$_['text_success_global']   = 'Panel default updated for all users.';
$_['text_success_user']     = 'Your appearance preferences were saved.';
$_['button_save_global']    = 'Save panel default';
$_['button_save_user']      = 'Save my appearance';

// Skins
$_['skin_light']          = 'Classic light';
$_['skin_light_desc']     = 'Neutral Bootstrap theme — the shell default.';
$_['skin_ocean']          = 'Ocean';
$_['skin_ocean_desc']     = 'Cool blues with a deep-sea sidebar.';
$_['skin_forest']         = 'Forest';
$_['skin_forest_desc']    = 'Calm greens with high legibility.';
$_['skin_plum']           = 'Plum';
$_['skin_plum_desc']      = 'Soft purples, gentle on the eyes.';
$_['skin_slate']          = 'Slate';
$_['skin_slate_desc']     = 'Desaturated grays for dense panels.';
$_['skin_contrast']       = 'High contrast';
$_['skin_contrast_desc']  = 'Maximum distinction for accessibility.';

// Accents
$_['accent_blue']    = 'Institutional blue';
$_['accent_ocean']   = 'Ocean';
$_['accent_forest']  = 'Forest';
$_['accent_plum']    = 'Plum';
$_['accent_slate']   = 'Slate';
$_['accent_wine']    = 'Wine';
$_['accent_gold']    = 'Gold';

// Entry
$_['error_modify_denied']        = 'Your group cannot change the global default.';
$_['error_accent-custom-need-light'] = 'The custom accent needs a base color (light mode).';
$_['error_accent-light-fails']   = 'The accent color does not reach a 4.5:1 contrast on the light surface.';
$_['error_accent-dark-fails']    = 'The dark-mode variant does not reach a 4.5:1 contrast on the dark surface.';
$_['error_accent-dark-missing']  = 'This color fails contrast in dark mode — provide a dark-mode variant (or use the suggestion button).';
$_['error_accent-contrast-fails']= 'No readable text color (white or black) reaches 4.5:1 over this accent. Choose a stronger color.';
