<?php
/**
 * Language: en-us — setting/site_info (F4-03, ADR 0026)
 */

// Heading
$_['heading_title'] = 'Site information';

// Text
$_['text_site_info'] = 'Institutional data shown on the public site: contact, address, footer and social links.';
$_['text_success']   = 'Site information saved successfully!';

// Fieldsets
$_['fieldset_contact']   = 'Contact (global)';
$_['fieldset_address']   = 'Address (per language)';
$_['fieldset_copyright'] = 'Footer (per language)';
$_['fieldset_social']    = 'Social networks (global)';

// Entries — global
$_['entry_config_contact_email']           = 'Contact e-mail';
$_['entry_config_contact_whatsapp_text']   = 'WhatsApp — visible text';
$_['entry_config_contact_whatsapp_href']   = 'WhatsApp — link number (wa.me)';
$_['entry_config_social_facebook_href']    = 'Facebook URL';
$_['entry_config_social_instagram_href']   = 'Instagram URL';
$_['entry_config_social_pinterest_href']   = 'Pinterest URL';
$_['entry_config_social_x_href']           = 'X (Twitter) URL';
$_['entry_config_social_google_business_href'] = 'Google Business URL';
$_['entry_config_social_redbubble_href']   = 'Redbubble URL';

// Entries — per idioma
$_['entry_contact_address'] = 'Address text';
$_['entry_copyright']       = 'Copyright text';

// Helps
$_['help_config_contact_email']         = 'Destination of the contact form and address rendered on the site.';
$_['help_config_contact_whatsapp_text'] = 'Text shown next to the WhatsApp icon (e.g. +55 11 99999-0000).';
$_['help_config_contact_whatsapp_href'] = 'Digits only (country + area code + number). Used in https://wa.me/<number>. Empty keeps the icon, but without a link destination.';
$_['help_contact_address']              = 'Address line displayed in the Contact section.';
$_['help_copyright']                    = 'The placeholder %s is replaced by the current year — keep it in the text.';
$_['help_fallback']                     = 'If a field is left intentionally blank in the database, the site falls back to the language file text (silent degradation — ADR 0023).';

// Errors
$_['error_required']       = '%s is required!';
$_['error_email']          = '%s must be a valid e-mail address!';
$_['error_whatsapp_href']  = 'WhatsApp link number must contain 8–16 digits only!';
$_['error_copyright_year'] = 'Copyright must contain the %s placeholder (current year)!';
$_['error_url']            = '%s must start with http:// or https://!';

// Buttons
$_['button_save'] = 'Save';
$_['button_back'] = 'Back';

$_['error_csrf'] = 'This form expired or could not be verified. Reload the page and try again.';
$_['text_close'] = 'Close';
