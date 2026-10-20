<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language pt-br — formulário de contato público (common/contact, F2-17).
 */

$_['heading_title']      = 'Vamos conversar?';
$_['text_lead']          = 'Preencha o formulário e eu respondo por e-mail.';
$_['text_success']       = 'Mensagem enviada! Retorno em breve.';
$_['text_honeypot_label']= 'Empresa';

$_['entry_name']      = 'Nome';
$_['entry_email']     = 'E-mail';
$_['entry_telephone'] = 'Telefone / WhatsApp';
$_['entry_subject']   = 'Assunto';
$_['entry_message']   = 'Mensagem';

$_['button_send']      = 'Enviar mensagem';
$_['button_back_home'] = 'Voltar à home';

$_['error_name']      = 'Informe seu nome (até 128 caracteres).';
$_['error_email']     = 'Informe um e-mail válido.';
$_['error_telephone'] = 'Telefone muito longo (até 32 caracteres).';
$_['error_subject']   = 'Informe o assunto (até 128 caracteres).';
$_['error_message']   = 'Escreva sua mensagem (de 3 a 5000 caracteres).';

$_['error_csrf']       = 'Falha na verificação de segurança — reenvie o formulário.';
$_['error_rate_limit'] = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';

// F4-05 (ADR 0029): notificação por e-mail ao dono do site
$_['text_mail_subject'] = 'Novo contato pelo site: %s';
$_['text_mail_body']    = "Nome: %s\nE-mail: %s\nTelefone: %s\n\nMensagem:\n%s";
