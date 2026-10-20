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
$_['help_mail_smtp_password']      = 'Nunca é exibida novamente. Deixe os espaços se eles pertencem à senha.';
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
