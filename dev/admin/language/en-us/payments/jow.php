<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * payments/jow — JOW-payments (internal tool, KZ-35) + platform-owned multi-gateway accounts.
 */

$_['heading_title']      = 'JOW Payments';
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

// Additive gateway integration through JOW-Payments MultiGateway (Mercado Pago, Stripe, PayPal).
$_['entry_provider'] = 'Provider';
$_['opt_manual'] = 'Manual / local (existing flow)';
$_['help_provider'] = 'With hosted checkout (Mercado Pago, Stripe, or PayPal), the payer chooses the payment method on the provider page. Manual charges and local PIX remain available.';
$_['text_gateway_config'] = 'Gateways — Reamur/platform-owned accounts';
$_['help_gateway_account_isolation'] = 'These credentials belong to JOW’s own platform accounts: this account receives the full amount of every charge. JOW does not split payments or collect on behalf of third parties.';
$_['help_charge_status_no_refund'] = 'This status is an internal record: changing it does NOT refund money at the provider. Refunds are issued in the Mercado Pago, Stripe or PayPal dashboard.';
$_['text_status_mismatch'] = 'Entered here: %s — differs from the provider status.';
$_['text_gateway_not_configured'] = 'Account not configured';
$_['text_gateway_enabled'] = 'Enabled';
$_['text_gateway_disabled'] = 'Disabled';
$_['entry_gateway_environment'] = 'Environment';
$_['text_sandbox'] = 'Sandbox / test';
$_['text_live'] = 'Production';
$_['entry_secret_clear'] = 'Remove the stored value';
$_['entry_gateway_enabled'] = 'Enable this account for new JOW charges';
$_['text_gateway_webhook_url'] = 'Public URL to configure in the provider app';
$_['text_gateway_migration_required'] = 'The MultiGateway foundation is not installed in this database. Apply tools/upgrade_jow_payments_multigateway_core.sql with authorization; this integration never runs migrations automatically.';
$_['text_gateway_load_error'] = 'Could not load the MultiGateway configuration. Check the log and the additive SQL installation.';
$_['text_gateway_credentials_locked'] = 'Stored credentials cannot be opened with the current config.php key. Restore the correct key or enter the secrets again.';
$_['text_live_gate_blocked'] = 'Production is blocked by default. To allow real charges, explicitly set REAMUR_PAYMENTS_ALLOW_LIVE=true in config.php.';
$_['text_gateway_account_saved'] = 'Gateway configuration saved; new charges remain disabled.';
$_['text_gateway_account_enabled'] = 'The gateway account is enabled for new JOW charges.';
$_['text_gateway_link_ready'] = 'Checkout link created or reused. See the Checkout column in the charges list.';
$_['text_gateway_link_reused'] = 'Payment link already existed for this order; the same link was kept.';
$_['text_order_gateway_link'] = 'Payment link for an order (new ledger)';
$_['help_order_gateway_link'] = 'Creates the payment attempt in rms_payment_transaction for the selected provider. The customer opens the link and pays at the provider; the status returns by signed notification or by reconciliation.';
$_['text_no_orders'] = 'No order with an amount awaiting payment.';
$_['text_order_already_paid'] = 'This order is already paid; no new link was created.';
$_['error_order_without_total'] = 'Order has no amount to charge.';
$_['text_open_checkout'] = 'Open link';
$_['text_no_checkout'] = 'Link not generated yet';
$_['col_checkout'] = 'Checkout';
$_['button_gateway_link'] = 'Generate / reuse link';
$_['error_description'] = 'Enter a charge description.';
$_['error_method'] = 'Invalid provider or payment method.';
$_['error_payer_email'] = 'Enter a valid payer email.';
$_['error_gateway_credentials'] = 'Invalid secret; check its length and remove spaces/control characters.';
$_['error_gateway_credentials_required'] = 'Enter every provider credential field before enabling the account.';
$_['error_gateway_account_save'] = 'Could not save the gateway account. It remains disabled; check the log, encryption key, and SQL.';
$_['error_gateway_migration'] = 'The MultiGateway foundation is not installed. No gateway charge was created.';
$_['error_gateway_link_failed'] = 'Could not create the provider checkout. Check the account, environment, HTTPS URL, and webhook configuration; the JOW charge is available to retry.';

// Mercado Pago (Orders API, platform-owned account).
$_['entry_mp_access_token'] = 'Platform account Access Token';
$_['help_mp_access_token'] = 'The token is encrypted at rest. For Sandbox, use the Access Token of a test seller account (TEST- credentials are not accepted by the Orders API); if you enter a payer email, use an @testuser.com address. Never use the real account token in Sandbox. Leave blank to keep the current value.';
$_['entry_mp_gateway_webhook_secret'] = 'Webhook signature secret — Orders API';
$_['help_mp_gateway_webhook_secret'] = 'Secret for the application that sends Checkout Pro Orders API Order events. It is separate from mp_webhook_secret and seller tokens.';

// Stripe (hosted Checkout Session, platform-owned account).
$_['entry_stripe_secret_key'] = 'Platform account Secret Key';
$_['help_stripe_secret_key'] = 'Secret key sk_... (a restricted key with Checkout permission is recommended). Encrypted at rest; leave blank to keep the current value.';
$_['entry_stripe_webhook_secret'] = 'Webhook signature secret (whsec_...)';
$_['help_stripe_webhook_secret'] = 'Secret of the endpoint that receives checkout.session.* and charge.refunded events. Separate from the Marketplace Stripe Connect credentials.';

// PayPal (Orders API v2, platform-owned account).
$_['entry_paypal_client_id'] = 'Platform account Client ID';
$_['help_paypal_client_id'] = 'Client ID of the app REST credentials. Encrypted at rest; leave blank to keep the current value.';
$_['entry_paypal_client_secret'] = 'Platform account Client Secret';
$_['help_paypal_client_secret'] = 'Secret of the app REST credentials, used to obtain the OAuth2 token. Encrypted at rest.';
$_['entry_paypal_webhook_id'] = 'App Webhook ID';
$_['help_paypal_webhook_id'] = 'Identifier of the webhook registered in the PayPal app; signature verification uses this ID. Encrypted at rest.';
