<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language pt-br — common/login (tela de acesso ao painel). Traduzido do en-us (ADR 0001).
 */

// Heading
$_['heading_title'] = 'Administração';

// Text
$_['text_login']    = 'Informe suas credenciais de acesso para entrar no painel.';

// Entry
$_['entry_username'] = 'Usuário';
$_['entry_password'] = 'Senha';

// Button
$_['button_login']  = 'Entrar';

// Error
$_['error_match']   = 'Usuário e/ou senha não encontrados.';

$_['error_csrf']       = 'Falha na verificação de segurança — entre novamente.';
$_['error_rate_limit'] = 'Muitas tentativas de login. Aguarde 15 minutos e tente novamente.';
$_['error_ip_blocked'] = 'Acesso bloqueado para este IP (regra Dracaunos-Security).';
$_['error_2fa']        = 'Código de verificação inválido.';
$_['entry_code_2fa']   = 'Código de verificação (2FA)';
$_['help_2fa_login']   = 'Abra seu app autenticador e informe o código de 6 dígitos.';
