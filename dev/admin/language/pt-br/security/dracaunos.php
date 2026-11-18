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
$_['text_home']            = 'Início';
$_['text_internal_note']   = 'Ferramenta INTERNA do ReamurCMS: visível somente no admin, nunca no catálogo público.';
$_['text_password_policy']  = 'Política de senhas';
$_['entry_min_length']     = 'Tamanho mínimo';
$_['entry_max_age']        = 'Validade máxima (dias, 0 = sem expiração)';
$_['entry_require_upper']  = 'Exigir letra maiúscula';
$_['entry_require_digit']  = 'Exigir número';
$_['entry_require_symbol'] = 'Exigir símbolo';
$_['text_admin_route']     = 'Rota do admin';
$_['help_admin_route']     = 'Slug da pasta do painel (aplicado na próxima Implantação/rewrite do servidor).';
$_['text_2fa']            = 'Verificação em duas etapas (2FA)';
$_['text_2fa_enabled_state'] = '2FA ativo nesta conta.';
$_['help_2fa']             = 'Gere um segredo, adicione no app autenticador (Google Authenticator, Authy, 1Password) e confirme com o código de 6 dígitos.';
$_['entry_2fa_secret']     = 'Segredo (base32)';
$_['placeholder_2fa_secret'] = 'Ex.: JBSWY3DPEHPK3PXP';
$_['entry_2fa_code']       = 'Código de verificação (6 dígitos)';
$_['button_2fa_enable']    = 'Ativar 2FA';
$_['button_2fa_disable']   = 'Desativar 2FA';
$_['text_login_log']       = 'Log de logins (últimos 30)';
$_['text_recovery']        = 'Recuperação de senha / conta / verificação (pendentes)';
$_['text_ip_rules']        = 'Regras de IP (verificação de IP)';
$_['text_users']           = 'Usuários e perfil';
$_['help_users']           = 'Gerencie logins no módulo Usuários; aqui a lista rápida com link para o perfil de cada conta.';
$_['col_date']  = 'Data'; $_['col_user'] = 'Usuário'; $_['col_result'] = 'Resultado';
$_['col_email'] = 'E-mail'; $_['col_type'] = 'Tipo'; $_['col_action'] = 'Ação';
$_['col_scope'] = 'Escopo'; $_['col_note'] = 'Nota'; $_['col_status'] = 'Status';
$_['text_ok'] = 'OK'; $_['text_fail'] = 'FALHOU'; $_['text_empty'] = 'Nenhum registro.';
$_['text_enabled'] = 'Ativo'; $_['text_disabled'] = 'Inativo';
$_['opt_deny'] = 'Bloquear'; $_['opt_allow'] = 'Permitir';
$_['entry_note'] = 'Nota';
$_['button_save'] = 'Salvar'; $_['button_add'] = 'Adicionar'; $_['button_delete'] = 'Excluir';
$_['button_approve'] = 'Aprovar'; $_['button_deny'] = 'Negar'; $_['button_edit_profile'] = 'Perfil';
$_['text_policy_saved'] = 'Política de senhas salva.';
$_['text_route_saved']  = 'Rota do admin salva.';
$_['text_2fa_enabled']   = '2FA ativado nesta conta.';
$_['text_2fa_disabled']  = '2FA desativado.';
$_['text_recovery_handled'] = 'Solicitação atualizada.';
$_['text_ip_saved']      = 'Regra de IP salva.';
$_['text_ip_deleted']    = 'Regra de IP removida.';
$_['error_route_invalid'] = 'Rota inválida (use letras minúsculas, números e hífen).';
$_['error_2fa_code']      = 'Código de 6 dígitos inválido para o segredo informado.';

// KZ-39 — Fase B: auditoria do admin
$_['text_audit']   = 'Auditoria do admin (últimos 50)';
$_['help_audit']   = 'Toda requisição autenticada do painel é registrada (usuário, rota, método, IP). Regras de IP valem para TODAS as rotas do admin, e a rota configurada acima é aplicada de verdade (a pasta física /admin/ é recusada com 403 quando há rota custom).';
$_['col_route']    = 'Rota'; $_['col_method'] = 'Método';

// KZ-41 — 2FA obrigatório no admin
$_['text_force_2fa'] = 'A política exige 2FA: ative a verificação em duas etapas nesta conta para continuar usando o painel.';
$_['entry_2fa_required'] = 'Exigir 2FA para todos os usuários do admin';
$_['help_2fa_required'] = 'Com isto ativo, quem ainda não tem 2FA configurado só acessa esta tela (para ativar) e a saída.';
$_['text_2fa_required_saved'] = 'Obrigatoriedade de 2FA atualizada.';

// KZ-42 — exportação CSV e limpeza por retenção
$_['button_export_csv'] = 'Exportar CSV';
$_['button_prune']      = 'Limpar registros antigos';
$_['help_prune']        = 'Remove auditoria e log de logins mais antigos que a retenção configurada (dracaunos_audit_days / dracaunos_login_log_days).';
$_['text_pruned']       = 'Limpeza concluída: %d registro(s) de auditoria e %d de log de logins removidos.';
