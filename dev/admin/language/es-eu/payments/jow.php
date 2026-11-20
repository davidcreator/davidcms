<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * payments/jow — JOW-payments (herramienta interna, KZ-35) + cuentas propias multi-gateway.
 */

$_['heading_title']      = 'JOW Payments';
$_['text_home']          = 'Inicio';
$_['text_internal_note'] = 'Herramienta INTERNA de ReamurCMS: solo admin, nunca en el catálogo público.';
$_['text_new_charge']   = 'Nuevo cobro';
$_['text_store']        = 'Datos del beneficiario (usados en PIX)';
$_['text_pix_keys']     = 'Claves PIX';
$_['text_report']       = 'Informe de cobros';
$_['text_charges']      = 'Cobros (últimos 50)';
$_['entry_description'] = 'Descripción'; $_['entry_amount'] = 'Importe (BRL)'; $_['entry_method'] = 'Método';
$_['entry_payer_email'] = 'Correo del pagador'; $_['entry_due_date'] = 'Vencimiento';
$_['entry_store_name']  = 'Nombre del beneficiario'; $_['entry_city'] = 'Ciudad'; $_['entry_key'] = 'Clave'; $_['entry_holder'] = 'Titular';
$_['opt_card'] = 'Tarjeta'; $_['opt_boleto'] = 'Boleto';
$_['help_pix'] = 'Los cobros por PIX generan el BR Code (Copia y Pega) automáticamente en el listado.';
$_['col_type']='Tipo'; $_['col_key']='Clave'; $_['col_holder']='Titular'; $_['col_status']='Estado';
$_['col_method']='Método'; $_['col_count']='Cant.'; $_['col_total']='Total'; $_['col_description']='Descripción';
$_['col_amount']='Importe'; $_['col_date']='Fecha';
$_['text_empty']='Sin registros.';
$_['button_charge']='Crear cobro'; $_['button_save']='Guardar'; $_['button_add']='Añadir';
$_['button_delete']='Eliminar'; $_['button_update']='Actualizar';
$_['text_charge_added']='Cobro creado (PIX generado si aplica).';
$_['text_charge_updated']='Estado del cobro actualizado.';
$_['text_pix_saved']='Clave PIX guardada.'; $_['text_pix_deleted']='Clave PIX eliminada.';
$_['text_store_saved']='Datos del beneficiario guardados.';
$_['error_amount']='Indica un importe BRL mayor que cero, con hasta dos decimales.'; $_['error_pix_invalid']='Clave PIX inválida.';

// Integración aditiva de gateways mediante JOW-Payments MultiGateway (Mercado Pago, Stripe, PayPal).
$_['entry_provider'] = 'Proveedor';
$_['opt_manual'] = 'Manual / local (flujo actual)';
$_['help_provider'] = 'Con el checkout hospedado (Mercado Pago, Stripe o PayPal), el pagador elige el medio de pago en la página del proveedor. Los cobros manuales y el PIX local siguen disponibles.';
$_['text_gateway_config'] = 'Gateways — cuentas propias de la plataforma/Reamur';
$_['help_gateway_account_isolation'] = 'Estas credenciales pertenecen a las cuentas propias de JOW: esta cuenta recibe el importe íntegro de cada cobro. JOW no divide pagos ni cobra en nombre de terceros.';
$_['help_charge_status_no_refund'] = 'Esta situación es un registro interno: cambiarla NO reembolsa dinero en el proveedor. El reembolso se hace en el panel de Mercado Pago, Stripe o PayPal.';
$_['text_status_mismatch'] = 'Introducido aquí: %s — difiere de la situación del proveedor.';
$_['text_gateway_not_configured'] = 'Cuenta sin configurar';
$_['text_gateway_enabled'] = 'Activa';
$_['text_gateway_disabled'] = 'Desactivada';
$_['entry_gateway_environment'] = 'Entorno';
$_['text_sandbox'] = 'Sandbox / pruebas';
$_['text_live'] = 'Producción';
$_['entry_secret_clear'] = 'Eliminar el valor guardado';
$_['entry_gateway_enabled'] = 'Habilitar la cuenta para nuevos cobros JOW';
$_['text_gateway_webhook_url'] = 'URL pública para configurar en la app del proveedor';
$_['text_gateway_migration_required'] = 'La fundación MultiGateway aún no está instalada en esta base de datos. Aplique, con autorización, tools/upgrade_jow_payments_multigateway_core.sql; esta integración no ejecuta migraciones automáticamente.';
$_['text_gateway_load_error'] = 'No se pudo cargar la configuración MultiGateway. Revise el log y la instalación del SQL aditivo.';
$_['text_gateway_credentials_locked'] = 'No se pueden abrir las credenciales guardadas con la clave actual de config.php. Restaure la clave correcta o vuelva a introducir los secretos.';
$_['text_live_gate_blocked'] = 'Producción está bloqueada por defecto. Para permitir cobros reales, configure explícitamente REAMUR_PAYMENTS_ALLOW_LIVE=true en config.php.';
$_['text_gateway_account_saved'] = 'Configuración del gateway guardada; los nuevos cobros siguen desactivados.';
$_['text_gateway_account_enabled'] = 'Cuenta del gateway habilitada para nuevos cobros JOW.';
$_['text_gateway_link_ready'] = 'Enlace de checkout creado o reutilizado. Consulte la columna Checkout de los cobros.';
$_['text_gateway_link_reused'] = 'El enlace de pago ya existía para este pedido; se mantuvo el mismo enlace.';
$_['text_order_gateway_link'] = 'Enlace de pago de un pedido (ledger nuevo)';
$_['help_order_gateway_link'] = 'Crea el intento de pago en rms_payment_transaction para el proveedor elegido. El cliente abre el enlace y paga en el proveedor; el estado vuelve por notificación firmada o por conciliación.';
$_['text_no_orders'] = 'Ningún pedido con importe pendiente de pago.';
$_['text_order_already_paid'] = 'Este pedido ya está pagado; no se creó un enlace nuevo.';
$_['error_order_without_total'] = 'El pedido no tiene importe para cobrar.';
$_['text_open_checkout'] = 'Abrir enlace';
$_['text_no_checkout'] = 'Enlace aún no generado';
$_['col_checkout'] = 'Checkout';
$_['button_gateway_link'] = 'Generar / reutilizar enlace';
$_['error_description'] = 'Indique la descripción del cobro.';
$_['error_method'] = 'Proveedor o método de pago no válido.';
$_['error_payer_email'] = 'Indique un correo válido para el pagador.';
$_['error_gateway_credentials'] = 'Secreto no válido; revise la longitud y elimine espacios/caracteres de control.';
$_['error_gateway_credentials_required'] = 'Para habilitar la cuenta, introduzca todos los campos de credencial del proveedor.';
$_['error_gateway_account_save'] = 'No se pudo guardar la cuenta del gateway. Quedó desactivada; revise el log, la clave de cifrado y el SQL.';
$_['error_gateway_migration'] = 'La fundación MultiGateway no está instalada. No se creó ningún cobro de gateway.';
$_['error_gateway_link_failed'] = 'No se pudo generar el checkout del proveedor. Revise la cuenta, el entorno, la URL HTTPS y el webhook; el cobro JOW queda disponible para reintentar.';

// Mercado Pago (Orders API, cuenta propia).
$_['entry_mp_access_token'] = 'Access Token de la cuenta propia';
$_['help_mp_access_token'] = 'El token se cifra en la base de datos. En Sandbox, use la credencial de una cuenta de vendedor de prueba (la Orders API no acepta tokens TEST-); si indica el correo del pagador, use una dirección @testuser.com. Nunca use en Sandbox el token de la cuenta real. Déjelo vacío para conservar el valor actual.';
$_['entry_mp_gateway_webhook_secret'] = 'Secreto de firma Webhooks — Orders API';
$_['help_mp_gateway_webhook_secret'] = 'Secreto de la aplicación que envía eventos Order de Checkout Pro Orders API. Es independiente de mp_webhook_secret y de los tokens de vendedores.';

// Stripe (Checkout Session hospedado, cuenta propia).
$_['entry_stripe_secret_key'] = 'Secret Key de la cuenta propia';
$_['help_stripe_secret_key'] = 'Clave secreta sk_... (se recomienda una restricted key con permiso de Checkout). Se cifra en la base de datos; déjela vacía para conservar el valor actual.';
$_['entry_stripe_webhook_secret'] = 'Secreto de firma Webhooks (whsec_...)';
$_['help_stripe_webhook_secret'] = 'Secreto del endpoint que recibe los eventos checkout.session.* y charge.refunded. Es independiente de las credenciales Stripe Connect del Marketplace.';

// PayPal (Orders API v2, cuenta propia).
$_['entry_paypal_client_id'] = 'Client ID de la cuenta propia';
$_['help_paypal_client_id'] = 'Client ID de las credenciales REST del app. Se cifra en la base de datos; déjelo vacío para conservar el valor actual.';
$_['entry_paypal_client_secret'] = 'Client Secret de la cuenta propia';
$_['help_paypal_client_secret'] = 'Secret de las credenciales REST del app, usado para obtener el token OAuth2. Se cifra en la base de datos.';
$_['entry_paypal_webhook_id'] = 'Webhook ID del app';
$_['help_paypal_webhook_id'] = 'Identificador del webhook registrado en el app PayPal; la verificación de firma usa este ID. Se cifra en la base de datos.';
