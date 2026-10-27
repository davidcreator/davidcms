<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/setting — Tela de configurações operacionais (pt-br).
 */

// Headings
$_['heading_title']      = 'Configurações';
$_['text_settings']      = 'Chaves operacionais que sobrescrevem os padrões da instalação. Aplicado na próxima requisição.';
$_['text_edit']          = 'Alterar = exige ACL modify em "setting/setting".';
$_['text_success']       = 'Configurações salvas.';
$_['text_cache_cleared'] = 'Cache limpo.';
$_['text_cache_clear_unsupported'] = 'A engine de cache ativa não suporta limpeza total pelo painel.';

// Fieldsets
$_['fieldset_store']     = 'Loja';
$_['fieldset_mail']      = 'E-mail';
$_['fieldset_cache']     = 'Cache';

// Entries
$_['entry_config_name']             = 'Nome da loja';
$_['entry_config_meta_title']       = 'Meta título padrão';
$_['entry_config_meta_description'] = 'Meta descrição padrão';
$_['entry_mail_engine']             = 'Engine de e-mail';
$_['entry_mail_smtp_hostname']      = 'Host SMTP';
$_['entry_mail_smtp_username']      = 'Usuário SMTP';
$_['entry_mail_smtp_password']      = 'Senha SMTP';
$_['entry_mail_smtp_port']          = 'Porta SMTP';
$_['entry_mail_smtp_timeout']       = 'Timeout SMTP (s)';
$_['entry_mail_reply_to']           = 'Reply-To';
$_['entry_cache_engine']            = 'Engine de cache';
$_['entry_cache_expire']            = 'TTL do cache (s)';

// Helps
$_['help_config_name']             = 'Usado em <title> e e-mails alinhados à marca.';
$_['help_config_meta_title']       = 'Título fallback quando a página não tem o seu (120 caracteres).';
$_['help_config_meta_description'] = '<meta name="description"> para páginas sem one própria (ideal ~160; campo valida até 255).';
$_['help_mail_engine']             = 'mail() envia via PHP; SMTP para relé externo; capture grava em storage/logs/mail_capture.json (dev/CI).';
$_['help_mail_smtp_hostname']      = 'Ex.: smtp.hostinger.com';
$_['help_mail_smtp_username']      = 'Geralmente o usuário/e-mail completo.';
$_['help_mail_smtp_password']      = 'A senha atual nunca é exibida. Deixe vazio para mantê-la; informe um novo valor para substituí-la. Use a caixa para removê-la. Se a validação falhar, informe novamente a nova senha.';
$_['help_mail_smtp_port']          = '25 (insegura), 465 (ssl), 587 (starttls) ou 2525.';
$_['help_mail_smtp_timeout']       = 'Segundos antes de dar timeout (1–60).';
$_['help_mail_reply_to']           = 'Opcional. Cabeçalho Reply-To dos e-mails enviados.';
$_['help_cache_engine']            = 'Efetivo no boot (lê rms_setting — F4-06); engine indisponível no servidor cai em file com aviso no log.';
$_['help_cache_expire']            = '60 a 2.592.000 segundos (máx 30 dias).';

// Errors
$_['error_config_name']            = 'O nome da loja é obrigatório.';
$_['error_mail_engine']            = 'Engine de e-mail inválida.';
$_['error_smtp_hostname_required'] = 'Host SMTP obrigatório com engine=smtp.';
$_['error_smtp_port']              = 'Portas aceitas: 25, 465, 587, 2525.';
$_['error_smtp_timeout']           = 'Timeout deve ser entre 1 e 60 segundos.';
$_['error_mail_reply_to']          = 'E-mail de Reply-To inválido.';
$_['error_cache_engine']           = 'Engine de cache inválida.';
$_['error_cache_expire']           = 'TTL deve ser de 60s a 30 dias.';

// F4-05 (ADR 0029): teste de envio (setting/setting.testMail)
$_['button_test_mail']          = 'Enviar e-mail de teste';
$_['text_mail_test_hint']       = 'usa as configurações salvas; vai para o e-mail do seu usuário.';
$_['text_mail_test_subject']    = '[ReamurCMS] Teste de envio';
$_['text_mail_test_body']       = "Se você recebeu esta mensagem, a engine de e-mail configurada está funcionando.";
$_['text_mail_test_sent']       = 'E-mail de teste enviado para %s.';
$_['text_mail_test_error']      = 'Falha ao enviar o e-mail de teste: %s';
$_['error_mail_test_recipient'] = 'o usuário logado não tem um e-mail válido';

// Buttons
$_['button_save']        = 'Salvar configurações';
$_['button_clear_cache'] = 'Limpar cache';
$_['button_back']        = 'Voltar ao dashboard';

// F8-03: tratamento de segredo e proteção de requisições
$_['text_smtp_password_set'] = 'Já há uma senha configurada. Deixe este campo vazio para mantê-la.';
$_['text_smtp_password_unset'] = 'Nenhuma senha SMTP está configurada.';
$_['entry_mail_smtp_password_clear'] = 'Remover a senha SMTP salva';
$_['error_csrf'] = 'O formulário expirou ou não pôde ser verificado. Recarregue a página e tente novamente.';
$_['text_close'] = 'Fechar';

$_['fieldset_threejs'] = 'Cenas Three.js do catálogo';
$_['text_enabled'] = 'Ativado';
$_['text_disabled'] = 'Desativado';
$_['entry_three_hero_enabled'] = 'Partículas 3D da página inicial';
$_['help_three_hero_enabled'] = 'Ativa/desativa o WebGL do hero. O fallback HTML/CSS continua visível quando desativado ou quando WebGL não está disponível.';
$_['entry_three_hero_particle_count'] = 'Quantidade de partículas';
$_['help_three_hero_particle_count'] = 'Entre 50 e 800; valores altos podem afetar dispositivos móveis.';
$_['entry_three_hero_color'] = 'Cor da cena';
$_['help_three_hero_color'] = 'Cor hexadecimal aplicada às partículas do hero e da página 404.';
$_['entry_three_hero_speed'] = 'Velocidade de animação';
$_['help_three_hero_speed'] = 'Multiplicador entre 0,01 e 0,20; aplicado às cenas do hero e da 404.';
$_['entry_three_not_found_enabled'] = 'Cena 3D na página 404';
$_['help_three_not_found_enabled'] = 'Ativa/desativa a cena de partículas da 404; o conteúdo textual e o fallback permanecem.';
$_['error_three_hero_enabled'] = 'Seleção inválida para ativar a cena do hero.';
$_['error_three_hero_particle_count'] = 'A quantidade de partículas deve ser um inteiro entre 50 e 800.';
$_['error_three_hero_color'] = 'Informe uma cor hexadecimal válida no formato #RRGGBB.';
$_['error_three_hero_speed'] = 'A velocidade deve estar entre 0,01 e 0,20, em incrementos de 0,01.';
$_['error_three_not_found_enabled'] = 'Seleção inválida para ativar a cena da página 404.';
$_['entry_three_portfolio_enabled'] = 'Galeria 3D do portfólio';
$_['help_three_portfolio_enabled'] = 'Exibe uma camada 3D decorativa atrás dos cards acessíveis dos projetos. Desativada por padrão; o tilt CSS e os links HTML permanecem disponíveis.';
$_['entry_three_portfolio_frame_limit'] = 'Máximo de quadros do portfólio';
$_['help_three_portfolio_frame_limit'] = 'De 1 a 12. As capas dos projetos são carregadas sob demanda quando a cena se aproxima da viewport.';
$_['error_three_portfolio_enabled'] = 'Seleção inválida para ativar a cena do portfólio.';
$_['entry_three_tools_enabled'] = 'Ícones 3D das ferramentas';
$_['help_three_tools_enabled'] = 'Adiciona movimento 3D sutil a até quatro cards ativos. Desativado por padrão; rótulos e links HTML continuam disponíveis.';
$_['error_three_tools_enabled'] = 'Seleção inválida para a cena de ferramentas.';
$_['error_three_portfolio_frame_limit'] = 'O limite de quadros deve ser um inteiro entre 1 e 12.';
