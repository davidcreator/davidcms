<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * payments/jow — JOW-payments (ferramenta interna, KZ-35).
 */

$_['heading_title']      = 'JOW-payments';
$_['text_home']          = 'Home';
$_['text_internal_note'] = 'INTERNAL ReamurCMS tool: admin-only, never shown in the public catalog.';
$_['text_new_charge']   = 'New charge';
$_['text_store']        = 'Payee data (used in PIX)';
$_['text_pix_keys']     = 'PIX keys';
$_['text_report']       = 'Receivables report';
$_['text_charges']      = 'Charges (last 50)';
$_['entry_description'] = 'Description'; $_['entry_amount'] = 'Amount (BRL)'; $_['entry_method'] = 'Method';
$_['entry_payer_email'] = 'Payer email'; $_['entry_due_date'] = 'Due date';
$_['entry_store_name']  = 'Payee name'; $_['entry_city'] = 'City'; $_['entry_key'] = 'Key'; $_['entry_holder'] = 'Holder';
$_['opt_card'] = 'Card'; $_['opt_boleto'] = 'Boleto';
$_['help_pix'] = 'PIX charges automatically generate the BR Code (Copy & Paste) in the list.';
$_['col_type']='Type'; $_['col_key']='Key'; $_['col_holder']='Holder'; $_['col_status']='Status';
$_['col_method']='Method'; $_['col_count']='Qty'; $_['col_total']='Total'; $_['col_description']='Description';
$_['col_amount']='Amount'; $_['col_date']='Date';
$_['text_empty']='No records.';
$_['button_charge']='Create charge'; $_['button_save']='Save'; $_['button_add']='Add';
$_['button_delete']='Delete'; $_['button_update']='Update';
$_['text_charge_added']='Charge created (PIX generated if applicable).';
$_['text_charge_updated']='Charge status updated.';
$_['text_pix_saved']='PIX key saved.'; $_['text_pix_deleted']='PIX key removed.';
$_['text_store_saved']='Payee data saved.';
$_['error_amount']='Enter a BRL amount greater than zero with at most two decimal places.'; $_['error_pix_invalid']='Invalid PIX key.';

// Additive Mercado Pago Checkout Pro integration through JOW-Payments MultiGateway.
$_['entry_provider'] = 'Provider';
$_['opt_manual'] = 'Manual / local (existing flow)';
$_['opt_mercadopago_checkout'] = 'Mercado Pago Checkout Pro';
$_['help_provider'] = 'With Checkout Pro, the payer chooses PIX, card, or boleto at Mercado Pago. Manual charges and local PIX remain available.';
$_['text_mp_gateway_config'] = 'Mercado Pago — Reamur/platform-owned account';
$_['help_mp_account_isolation'] = 'These credentials belong to JOW’s own platform account. They are not the seller OAuth tokens used by the public Marketplace checkout.';
$_['text_mp_not_configured'] = 'Account not configured';
$_['text_mp_enabled'] = 'Enabled';
$_['text_mp_disabled'] = 'Disabled';
$_['entry_mp_environment'] = 'Environment';
$_['text_sandbox'] = 'Sandbox / test';
$_['text_live'] = 'Production';
$_['entry_mp_access_token'] = 'Platform account Access Token';
$_['help_mp_access_token'] = 'The token is encrypted at rest. For Sandbox, use the Access Token of a test seller account (TEST- credentials are not accepted by the Orders API); if you enter a payer email, use an @testuser.com address. Never use the real account token in Sandbox. Leave blank to keep the current value.';
$_['entry_mp_access_token_clear'] = 'Remove the stored Access Token';
$_['entry_mp_gateway_webhook_secret'] = 'Webhook signature secret — Orders API';
$_['help_mp_gateway_webhook_secret'] = 'Secret for the application that sends Checkout Pro Orders API Order events. It is separate from mp_webhook_secret and seller tokens.';
$_['entry_mp_gateway_webhook_secret_clear'] = 'Remove the stored webhook secret';
$_['entry_mp_enabled'] = 'Enable this account for new JOW charges';
$_['text_mp_webhook_url'] = 'Public URL to configure in the Mercado Pago app (Order event)';
$_['text_gateway_migration_required'] = 'The MultiGateway foundation is not installed in this database. Apply tools/upgrade_jow_payments_multigateway_core.sql with authorization; this integration never runs migrations automatically.';
$_['text_gateway_load_error'] = 'Could not load the MultiGateway configuration. Check the log and the additive SQL installation.';
$_['text_mp_credentials_locked'] = 'Stored credentials cannot be opened with the current config.php key. Restore the correct key or enter the secrets again.';
$_['text_live_gate_blocked'] = 'Production is blocked by default. To allow real charges, explicitly set REAMUR_PAYMENTS_ALLOW_LIVE=true in config.php.';
$_['text_mp_account_saved'] = 'Mercado Pago configuration saved; new charges remain disabled.';
$_['text_mp_account_enabled'] = 'The platform-owned Mercado Pago account is enabled for new JOW charges.';
$_['text_mp_link_ready'] = 'Mercado Pago checkout link created or reused. See the Checkout column in the charges list.';
$_['text_open_checkout'] = 'Open link';
$_['text_no_checkout'] = 'Link not generated yet';
$_['col_checkout'] = 'MP checkout';
$_['button_mp_link'] = 'Generate / reuse link';
$_['error_description'] = 'Enter a charge description.';
$_['error_method'] = 'Invalid provider or payment method.';
$_['error_payer_email'] = 'Enter a valid payer email.';
$_['error_mp_credentials'] = 'Invalid Access Token or webhook secret; check its length and remove spaces/control characters.';
$_['error_mp_credentials_required'] = 'Enter both an Access Token and the webhook signature secret before enabling the account.';
$_['error_mp_account_save'] = 'Could not save the Mercado Pago account. It remains disabled; check the log, encryption key, and SQL.';
$_['error_gateway_migration'] = 'The MultiGateway foundation is not installed. No Mercado Pago charge was created.';
$_['error_mp_link_failed'] = 'Could not create the Mercado Pago checkout. Check the account, environment, HTTPS URL, and Orders webhook configuration; the JOW charge is available to retry.';
