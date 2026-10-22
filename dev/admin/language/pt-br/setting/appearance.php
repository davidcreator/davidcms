<?php
/**
 * Language pt-br — setting/appearance (F8-V, ADR 0047): Aparência do Painel.
 */

// Heading
$_['heading_title'] = 'Aparência do Painel';

// Text
$_['text_intro']            = 'Temas curados e validados em WCAG AA para o painel. Defina o padrão global e/ou personalize só para a sua conta.';
$_['text_global_section']   = 'Padrão do painel (todos os usuários)';
$_['text_user_section']     = 'Minha aparência';
$_['text_global_locked']    = 'Seu grupo tem acesso apenas de leitura ao padrão global. Você ainda pode personalizar a sua experiência abaixo.';
$_['text_apply_override']   = 'Personalizar para a minha conta (sobrescreve o padrão)';
$_['text_override_hint']    = 'Desligado, você segue o padrão do painel. Ligar o modo escuro na barra superior já cria uma personalização para você.';
$_['text_skin_card']        = 'Skin';
$_['text_accent_card']      = 'Cor de destaque';
$_['text_layout_card']      = 'Layout e comportamento';
$_['text_mode']             = 'Modo de cor';
$_['text_mode_system']      = 'Sistema';
$_['text_mode_light']       = 'Claro';
$_['text_mode_dark']        = 'Escuro';
$_['text_sidebar']          = 'Sidebar';
$_['text_sidebar_auto']     = 'Automática';
$_['text_sidebar_light']    = 'Sempre clara';
$_['text_sidebar_dark']     = 'Sempre escura';
$_['text_density']          = 'Densidade';
$_['text_density_comfortable'] = 'Confortável';
$_['text_density_compact']  = 'Compacta';
$_['text_font_scale']       = 'Escala da fonte';
$_['text_accent_skin']      = 'Da própria skin';
$_['text_accent_custom']    = 'Cor customizada';
$_['text_accent_custom_dark'] = 'Variante do modo escuro';
$_['help_accent_dark']      = 'Opcional. Usada no modo escuro; se vazia, a cor base é aplicada nele também.';
$_['text_accent_help']      = 'As cores precisam alcançar contraste de 4.5:1 nas superfícies clara e escura (WCAG AA). Use o botão mágico para sugerir a variante do modo escuro.';
$_['text_validating_contrast'] = 'Verificando contraste…';
$_['text_contrast_fail']    = 'Não atende ao WCAG AA (razão abaixo de 4.5:1). Escolha outra cor ou use o botão de sugestão.';
$_['text_success_global']   = 'Padrão do painel atualizado para todos os usuários.';
$_['text_success_user']     = 'Suas preferências de aparência foram salvas.';
$_['button_save_global']    = 'Salvar padrão do painel';
$_['button_save_user']      = 'Salvar minha aparência';

// Skins
$_['skin_light']          = 'Clássico claro';
$_['skin_light_desc']     = 'Tema neutro do Bootstrap — padrão do shell.';
$_['skin_ocean']          = 'Oceano';
$_['skin_ocean_desc']     = 'Azuis frios com sidebar mar profundo.';
$_['skin_forest']         = 'Floresta';
$_['skin_forest_desc']    = 'Verdes tranquilos com alta legibilidade.';
$_['skin_plum']           = 'Ameixa';
$_['skin_plum_desc']      = 'Roxos suaves, confortável aos olhos.';
$_['skin_slate']          = 'Ardósia';
$_['skin_slate_desc']     = 'Cinzas dessaturados para painéis densos.';
$_['skin_contrast']       = 'Alto contraste';
$_['skin_contrast_desc']  = 'Distinção máxima para acessibilidade.';

// Accents
$_['accent_blue']    = 'Azul institucional';
$_['accent_ocean']   = 'Oceano';
$_['accent_forest']  = 'Floresta';
$_['accent_plum']    = 'Ameixa';
$_['accent_slate']   = 'Ardósia';
$_['accent_wine']    = 'Vinho';
$_['accent_gold']    = 'Ouro';

// Entry
$_['error_modify_denied']        = 'Seu grupo não pode alterar o padrão global.';
$_['error_accent-custom-need-light'] = 'A cor customizada precisa de uma base (modo claro).';
$_['error_accent-light-fails']   = 'A cor de destaque não atinge contraste de 4.5:1 na superfície clara.';
$_['error_accent-dark-fails']    = 'A variante do modo escuro não atinge contraste de 4.5:1 na superfície escura.';
$_['error_accent-dark-missing']  = 'Essa cor falha no modo escuro — informe a variante (ou use o botão de sugestão).';
$_['error_accent-contrast-fails']= 'Nenhuma cor de texto legível (branco ou preto) alcança 4.5:1 sobre esse destaque. Escolha uma cor mais forte.';
