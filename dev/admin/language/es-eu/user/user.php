<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language es-eu — user/user (CRUD de usuários del panel). Traducido del en-us (ADR 0001).
 */
// Heading
$_['heading_title'] = 'Usuarios';

// Text
$_['text_list']           = 'Cuentas de administradores de este panel';
$_['text_no_results']     = 'No se encontraron usuarios.';
$_['text_confirm_delete'] = '¿Eliminar este usuario? No se puede deshacer.';
$_['text_enabled']        = 'Activo';
$_['text_disabled']       = 'Inactivo';
$_['text_add']            = 'Nuevo usuario';
$_['text_edit']           = 'Editar usuario';
$_['text_password_hint']  = 'Deje en blanco para conservar la contraseña actual.';
$_['text_success_add']    = 'Usuario creado correctamente.';
$_['text_success_edit']   = 'Usuario actualizado correctamente.';
$_['text_success_delete'] = 'Usuario eliminado correctamente.';

// Column
$_['column_username'] = 'Usuario';
$_['column_email']    = 'E-mail';
$_['column_group']    = 'Grupo';
$_['column_status']   = 'Estado';
$_['column_action']   = 'Acciones';

// Entry
$_['entry_username']  = 'Usuario';
$_['entry_firstname'] = 'Nombre';
$_['entry_lastname']  = 'Apellidos';
$_['entry_email']     = 'E-mail';
$_['entry_password']  = 'Contraseña';
$_['entry_confirm']   = 'Confirmar Contraseña';
$_['entry_group']     = 'Grupo de Usuarios';
$_['entry_status']    = 'Activo';

// Button
$_['button_save']   = 'Guardar';
$_['button_cancel'] = 'Cancelar';
$_['button_add']    = 'Añadir Usuario';
$_['button_edit']   = 'Editar';
$_['button_delete'] = 'Eliminar';

// Error
$_['error_username']        = '¡El usuario debe tener entre 3 y 32 caracteres!';
$_['error_username_exists'] = '¡Este usuario ya está en uso!';
$_['error_email']           = '¡Dirección de e-mail no válida!';
$_['error_firstname']       = '¡El nombre debe tener entre 1 y 32 caracteres!';
$_['error_lastname']        = '¡Los apellidos deben tener entre 1 y 32 caracteres!';
$_['error_password']        = '¡La contraseña debe tener al menos 8 caracteres!';
$_['error_confirm']         = '¡La confirmación de la contraseña no coincide!';
$_['error_group']           = '¡Elija un grupo de usuarios!';
$_['error_self_delete']     = '¡No puede eliminar su propio usuario conectado!';
$_['error_self_disable']      = 'No puedes desactivar tu propia cuenta mientras estás conectado.';
$_['error_last_active_admin'] = 'No se puede inactivar/quitar/eliminar al último usuario activo con administración de usuarios (user/user).';
