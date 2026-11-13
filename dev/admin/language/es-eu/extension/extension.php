<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * extension/extension — Pantalla de extensiones: lista de la carpeta, activar, desactivar, configurar (EX-01, ADR 0095; es-EU).
 */

$_['heading_title']            = 'Extensiones';
$_['text_list']                = 'Extensiones encontradas en la carpeta de extensiones de este sitio. Actívalas, desactívalas y configúralas aquí.';
$_['text_install_hint']        = 'Para instalar una extensión, copia su carpeta al servidor (FTP o despliegue), dentro de:';
$_['text_trust_hint']          = 'Una extensión activada es código que se ejecuta con el mismo alcance que el propio CMS (base de datos, archivos, configuración). Activa solo extensiones de origen conocido. Mientras una extensión está desactivada, no se ejecuta nada de su código.';
$_['text_no_results']          = 'Todavía no hay ninguna extensión en la carpeta.';
$_['text_close']               = 'Cerrar';
$_['text_enabled']             = 'Activada';
$_['text_disabled']            = 'Desactivada';
$_['text_invalid']             = 'Descripción no válida';
$_['text_incompatible']        = 'Incompatible';
$_['text_folder_missing']      = 'Carpeta no encontrada';
$_['text_folder_missing_hint'] = 'Esta extensión tiene datos guardados, pero su carpeta ya no está en el servidor. No se ejecuta nada de ella. Vuelve a poner la carpeta o elimina los datos.';
$_['text_runs_in']             = 'Se ejecuta en';
$_['text_app_catalog']         = 'sitio público';
$_['text_app_admin']           = 'admin';
$_['text_events']              = 'Escucha';
$_['text_no_events']           = 'ningún evento';
$_['text_sources']             = 'Direcciones externas que pide';
$_['text_no_sources']          = 'ninguna';
$_['text_problems']            = 'El archivo extension.json fue rechazado:';
$_['text_blockers']            = 'Todavía no se puede activar:';
$_['text_details']             = 'Qué hace esta extensión en el sitio';
$_['text_version']             = 'v';
$_['text_author']              = 'por';
$_['text_confirm_enable']      = '¿Activar esta extensión? Su código empieza a ejecutarse en el sitio de inmediato. Activa solo extensiones de origen conocido.';
$_['text_confirm_purge']       = '¿Eliminar todo lo que esta extensión guardó (configuración y secretos)? No se puede deshacer.';
$_['text_off_by_config']       = 'Todas las extensiones están desactivadas por la línea REAMUR_EXTENSIONS de config.php. Lo que aparece como activado abajo vuelve a ejecutarse cuando se quite esa línea.';
$_['text_no_folder']           = 'La carpeta de extensiones no existe en este servidor:';
$_['text_form']                = 'Configuración de esta extensión.';
$_['text_secret_set']          = 'Valor guardado';
$_['text_secret_unset']        = 'Ningún valor guardado';
$_['text_secret_hint']         = 'Este valor no se vuelve a mostrar. Déjalo vacío para mantener el guardado, o escribe otro para sustituirlo.';
$_['text_secret_locked']       = 'Hay un secreto guardado que la clave de config.php no abre. La extensión lo recibe vacío. Escríbelo de nuevo o restaura la clave que lo guardó.';
$_['text_secret_clear']        = 'Borrar el valor guardado';
$_['text_secret_clear_help']   = 'Solo se aplica si el campo de arriba está vacío.';
$_['text_secret_no_key']       = 'La clave de los secretos no está en config.php (Configuración > Protección de los secretos). Un secreto guardado ahora queda legible en la base de datos hasta que se haga.';
$_['text_select_none']         = '(ninguno)';
$_['text_required']            = 'Obligatorio';
$_['text_success_save']        = 'Configuración de "%s" guardada.';
$_['text_success_enable']      = 'Extensión "%s" activada.';
$_['text_success_disable']     = 'Extensión "%s" desactivada.';
$_['text_success_purge']       = 'Datos guardados de "%s" eliminados.';
$_['column_extension']         = 'Extensión';
$_['column_status']            = 'Estado';
$_['column_action']            = 'Acciones';
$_['button_enable']            = 'Activar';
$_['button_disable']           = 'Desactivar';
$_['button_configure']         = 'Configurar';
$_['button_purge']             = 'Eliminar datos';
$_['button_save']              = 'Guardar';
$_['button_cancel']            = 'Cancelar';
$_['error_csrf']               = 'El formulario ha caducado o no se pudo verificar. Recarga la página e inténtalo de nuevo.';
$_['error_form']               = 'Algunos campos necesitan atención. No se guardó nada.';
$_['error_required']           = 'Este campo es obligatorio.';
$_['error_too_long']           = 'Como máximo %d caracteres.';
$_['error_pattern']            = 'Este valor no tiene el formato que la extensión acepta.';
$_['error_option']             = 'Elige una de las opciones de la lista.';
$_['error_not_found']          = 'Extensión no encontrada en la carpeta, o su extension.json no es válido.';
$_['error_no_settings']        = 'Esta extensión no tiene nada que configurar.';
$_['error_blocked']            = '"%s" no se puede activar: %s';
$_['error_purge_enabled']      = 'Desactiva la extensión antes de eliminar sus datos.';
