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

// Integración aditiva Mercado Pago Checkout Pro mediante JOW-Payments MultiGateway.
$_['entry_provider'] = 'Proveedor';
$_['opt_manual'] = 'Manual / local (flujo actual)';
$_['opt_mercadopago_checkout'] = 'Mercado Pago Checkout Pro';
$_['help_provider'] = 'En Checkout Pro, el pagador elige PIX, tarjeta o boleto en Mercado Pago. Los cobros manuales y el PIX local siguen disponibles.';
$_['text_mp_gateway_config'] = 'Mercado Pago — cuenta propia de la plataforma/Reamur';
$_['help_mp_account_isolation'] = 'Estas credenciales pertenecen a la cuenta propia de JOW. No son los tokens OAuth de los vendedores del checkout Marketplace público.';
$_['text_mp_not_configured'] = 'Cuenta sin configurar';
$_['text_mp_enabled'] = 'Activa';
$_['text_mp_disabled'] = 'Desactivada';
$_['entry_mp_environment'] = 'Entorno';
$_['text_sandbox'] = 'Sandbox / pruebas';
$_['text_live'] = 'Producción';
$_['entry_mp_access_token'] = 'Access Token de la cuenta propia';
$_['help_mp_access_token'] = 'El token se cifra en la base de datos. En Sandbox, use la credencial de una cuenta de vendedor de prueba (la Orders API no acepta tokens TEST-); si indica el correo del pagador, use una dirección @testuser.com. Nunca use en Sandbox el token de la cuenta real. Déjelo vacío para conservar el valor actual.';
$_['entry_mp_access_token_clear'] = 'Eliminar el Access Token guardado';
$_['entry_mp_gateway_webhook_secret'] = 'Secreto de firma Webhooks — Orders API';
$_['help_mp_gateway_webhook_secret'] = 'Secreto de la aplicación que envía eventos Order de Checkout Pro Orders API. Es independiente de mp_webhook_secret y de los tokens de vendedores.';
$_['entry_mp_gateway_webhook_secret_clear'] = 'Eliminar el secreto de webhook guardado';
$_['entry_mp_enabled'] = 'Habilitar la cuenta para nuevos cobros JOW';
$_['text_mp_webhook_url'] = 'URL pública para configurar en la app Mercado Pago (evento Order)';
$_['text_gateway_migration_required'] = 'La fundación MultiGateway aún no está instalada en esta base de datos. Aplique, con autorización, tools/upgrade_jow_payments_multigateway_core.sql; esta integración no ejecuta migraciones automáticamente.';
$_['text_gateway_load_error'] = 'No se pudo cargar la configuración MultiGateway. Revise el log y la instalación del SQL aditivo.';
$_['text_mp_credentials_locked'] = 'No se pueden abrir las credenciales guardadas con la clave actual de config.php. Restaure la clave correcta o vuelva a introducir los secretos.';
$_['text_live_gate_blocked'] = 'Producción está bloqueada por defecto. Para permitir cobros reales, configure explícitamente REAMUR_PAYMENTS_ALLOW_LIVE=true en config.php.';
$_['text_mp_account_saved'] = 'Configuración de Mercado Pago guardada; los nuevos cobros siguen desactivados.';
$_['text_mp_account_enabled'] = 'Cuenta propia de Mercado Pago habilitada para nuevos cobros JOW.';
$_['text_mp_link_ready'] = 'Enlace de Mercado Pago creado o reutilizado. Consulte la columna Checkout de los cobros.';
$_['text_open_checkout'] = 'Abrir enlace';
$_['text_no_checkout'] = 'Enlace aún no generado';
$_['col_checkout'] = 'Checkout MP';
$_['button_mp_link'] = 'Generar / reutilizar enlace';
$_['error_description'] = 'Indique la descripción del cobro.';
$_['error_method'] = 'Proveedor o método de pago no válido.';
$_['error_payer_email'] = 'Indique un correo válido para el pagador.';
$_['error_mp_credentials'] = 'Access Token o secreto de webhook no válido; revise la longitud y elimine espacios/caracteres de control.';
$_['error_mp_credentials_required'] = 'Para habilitar la cuenta, introduzca un Access Token y el secreto de firma del webhook.';
$_['error_mp_account_save'] = 'No se pudo guardar la cuenta de Mercado Pago. Quedó desactivada; revise el log, la clave de cifrado y el SQL.';
$_['error_gateway_migration'] = 'La fundación MultiGateway no está instalada. No se creó ningún cobro de Mercado Pago.';
$_['error_mp_link_failed'] = 'No se pudo generar el checkout de Mercado Pago. Revise la cuenta, el entorno, la URL HTTPS y el webhook Orders; el cobro JOW queda disponible para reintentar.';
