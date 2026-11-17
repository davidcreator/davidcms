<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * security/dracaunos — Dracaunos-Security (ferramenta interna, KZ-34).
 */

$_['heading_title']        = 'Dracaunos-Security';
$_['text_home']            = 'Inicio';
$_['text_internal_note']   = 'Herramienta INTERNA de ReamurCMS: solo admin, nunca en el catálogo público.';
$_['text_password_policy']  = 'Política de contraseñas';
$_['entry_min_length']     = 'Longitud mínima';
$_['entry_max_age']        = 'Vigencia máxima (días, 0 = sin caducidad)';
$_['entry_require_upper']  = 'Exigir mayúscula';
$_['entry_require_digit']  = 'Exigir número';
$_['entry_require_symbol'] = 'Exigir símbolo';
$_['text_admin_route']     = 'Ruta del admin';
$_['help_admin_route']     = 'Slug de la carpeta del panel (se aplica en el próximo despliegue/rewrite).';
$_['text_2fa']            = 'Verificación en dos pasos (2FA)';
$_['text_2fa_enabled_state'] = '2FA activo en esta cuenta.';
$_['help_2fa']             = 'Genera un secreto, añádelo en tu app autenticadora y confirma con el código de 6 dígitos.';
$_['entry_2fa_secret']     = 'Secreto (base32)';
$_['placeholder_2fa_secret'] = 'Ej.: JBSWY3DPEHPK3PXP';
$_['entry_2fa_code']       = 'Código de verificación (6 dígitos)';
$_['button_2fa_enable']    = 'Activar 2FA';
$_['button_2fa_disable']   = 'Desactivar 2FA';
$_['text_login_log']       = 'Registro de inicios de sesión (últimos 30)';
$_['text_recovery']        = 'Recuperación de contraseña/cuenta/verificación (pendientes)';
$_['text_ip_rules']        = 'Reglas de IP (verificación de IP)';
$_['text_users']           = 'Usuarios y perfil';
$_['help_users']           = 'Gestiona los inicios en el módulo Usuarios; lista rápida aquí con enlace al perfil.';
$_['col_date']  = 'Fecha'; $_['col_user'] = 'Usuario'; $_['col_result'] = 'Resultado';
$_['col_email'] = 'Correo'; $_['col_type'] = 'Tipo'; $_['col_action'] = 'Acción';
$_['col_scope'] = 'Ámbito'; $_['col_note'] = 'Nota'; $_['col_status'] = 'Estado';
$_['text_ok'] = 'OK'; $_['text_fail'] = 'FALLÓ'; $_['text_empty'] = 'Sin registros.';
$_['text_enabled'] = 'Activo'; $_['text_disabled'] = 'Inactivo';
$_['opt_deny'] = 'Bloquear'; $_['opt_allow'] = 'Permitir';
$_['entry_note'] = 'Nota';
$_['button_save'] = 'Guardar'; $_['button_add'] = 'Añadir'; $_['button_delete'] = 'Eliminar';
$_['button_approve'] = 'Aprobar'; $_['button_deny'] = 'Denegar'; $_['button_edit_profile'] = 'Perfil';
$_['text_policy_saved'] = 'Política de contraseñas guardada.';
$_['text_route_saved']  = 'Ruta del admin guardada.';
$_['text_2fa_enabled']   = '2FA activado en esta cuenta.';
$_['text_2fa_disabled']  = '2FA desactivado.';
$_['text_recovery_handled'] = 'Solicitud actualizada.';
$_['text_ip_saved']      = 'Regla de IP guardada.';
$_['text_ip_deleted']    = 'Regla de IP eliminada.';
$_['error_route_invalid'] = 'Ruta inválida (solo minúsculas, números y guion).';
$_['error_2fa_code']      = 'Código de 6 dígitos inválido para el secreto indicado.';
