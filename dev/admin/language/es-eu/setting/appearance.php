<?php
/**
 * Language es-eu — setting/appearance (F8-V, ADR 0047): Apariencia del Panel.
 */

// Heading
$_['heading_title'] = 'Apariencia del Panel';

// Text
$_['text_intro']            = 'Temas curados y validados WCAG AA para el panel. Defina el valor global y/o personalice solo su cuenta.';
$_['text_global_section']   = 'Predeterminado del panel (todos los usuarios)';
$_['text_user_section']     = 'Mi apariencia';
$_['text_global_locked']    = 'Su grupo tiene acceso de solo lectura al valor global. Puede personalizar su propia experiencia abajo.';
$_['text_apply_override']   = 'Usar una apariencia personal en mi cuenta';
$_['text_override_hint']    = 'Desactivado, todas las páginas del panel usan el valor global. Actívelo antes de cambiar o aplicar preferencias personales; el selector de tema de la barra superior también lo activa.';
$_['text_skin_card']        = 'Skin';
$_['text_accent_card']      = 'Color de acento';
$_['text_layout_card']      = 'Diseño y comportamiento';
$_['text_mode']             = 'Modo de color';
$_['text_mode_system']      = 'Sistema';
$_['text_mode_light']       = 'Claro';
$_['text_mode_dark']        = 'Oscuro';
$_['text_sidebar']          = 'Sidebar';
$_['text_sidebar_auto']     = 'Automática';
$_['text_sidebar_light']    = 'Siempre clara';
$_['text_sidebar_dark']     = 'Siempre oscura';
$_['text_density']          = 'Densidad';
$_['text_density_comfortable'] = 'Cómoda';
$_['text_density_compact']  = 'Compacta';
$_['text_font_scale']       = 'Escala de fuente';
$_['text_accent_skin']      = 'De la propia skin';
$_['text_accent_custom']    = 'Color personalizado';
$_['text_accent_custom_dark'] = 'Variante del modo oscuro';
$_['help_accent_dark']      = 'Opcional. Usada en el modo oscuro; si está vacía, el color base también se aplica ahí.';
$_['text_accent_help']      = 'Los colores deben alcanzar contraste 4.5:1 en superficies claras y oscuras (WCAG AA). Use el botón mágico para sugerir la variante del modo oscuro.';
$_['text_validating_contrast'] = 'Comprobando contraste…';
$_['text_contrast_fail']    = 'No cumple WCAG AA (razón por debajo de 4.5:1). Elija otro color o use la sugerencia.';
$_['text_success_global']   = 'Valor predeterminado del panel actualizado para todos.';
$_['text_success_user']     = 'Sus preferencias de apariencia se guardaron y aplicaron a su cuenta.';
$_['text_success_user_reset'] = 'Su cuenta ahora usa el valor predeterminado global. Sus opciones personales se conservan.';
$_['text_global_override_note'] = 'Cuando está activa, la preferencia personal prevalece sobre el valor global en su propia cuenta.';
$_['button_save_global']    = 'Guardar valor predeterminado';
$_['button_save_user']      = 'Guardar y aplicar mi apariencia';
$_['button_use_global']     = 'Usar valor predeterminado';

// Skins
$_['skin_light']          = 'Clásico claro';
$_['skin_light_desc']     = 'Tema neutro de Bootstrap — el predeterminado.';
$_['skin_ocean']          = 'Océano';
$_['skin_ocean_desc']     = 'Azules fríos con sidebar de mar profundo.';
$_['skin_forest']         = 'Bosque';
$_['skin_forest_desc']    = 'Verdes tranquilos de alta legibilidad.';
$_['skin_plum']           = 'Ciruela';
$_['skin_plum_desc']      = 'Violetas suaves, cómodo a la vista.';
$_['skin_slate']          = 'Pizarra';
$_['skin_slate_desc']     = 'Grises desaturados para paneles densos.';
$_['skin_contrast']       = 'Alto contraste';
$_['skin_contrast_desc']  = 'Distinción máxima para accesibilidad.';

// Accents
$_['accent_blue']    = 'Azul institucional';
$_['accent_ocean']   = 'Océano';
$_['accent_forest']  = 'Bosque';
$_['accent_plum']    = 'Ciruela';
$_['accent_slate']   = 'Pizarra';
$_['accent_wine']    = 'Vino';
$_['accent_gold']    = 'Oro';

// Entry
$_['error_modify_denied']        = 'Su grupo no puede cambiar el valor global.';
$_['error_csrf']                 = 'Falta el token de seguridad o ha caducado. Recargue la página e inténtelo de nuevo.';
$_['error_invalid_scope']        = 'El ámbito de la configuración de apariencia no es válido.';
$_['error_preferences_unavailable'] = 'No se pudieron guardar las preferencias. Aplique la actualización de base de datos requerida y vuelva a intentarlo.';
$_['error_accent-custom-need-light'] = 'El color personalizado necesita una base (modo claro).';
$_['error_accent-light-fails']   = 'El acento no alcanza contraste 4.5:1 en la superficie clara.';
$_['error_accent-dark-fails']    = 'La variante del modo oscuro no alcanza contraste 4.5:1 en la superficie oscura.';
$_['error_accent-dark-missing']  = 'Este color falla en modo oscuro — informe la variante (o use el botón de sugerencia).';
$_['error_accent-contrast-fails']= 'Ningún color de texto legible (blanco o negro) alcanza 4.5:1 sobre este acento. Elija un color más fuerte.';
