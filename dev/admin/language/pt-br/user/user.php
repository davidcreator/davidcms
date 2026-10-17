<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language pt-br — user/user (CRUD de usuários do painel). Traduzido do en-us (ADR 0001).
 */
// Heading
$_['heading_title'] = 'Usuários';

// Text
$_['text_list']           = 'Contas de administradores deste painel';
$_['text_no_results']     = 'Nenhum usuário encontrado.';
$_['text_confirm_delete'] = 'Excluir este usuário? A ação não pode ser desfeita.';
$_['text_enabled']        = 'Ativo';
$_['text_disabled']       = 'Inativo';
$_['text_add']            = 'Novo usuário';
$_['text_edit']           = 'Editar usuário';
$_['text_password_hint']  = 'Deixe em branco para manter a senha atual.';
$_['text_success_add']    = 'Usuário criado com sucesso.';
$_['text_success_edit']   = 'Usuário atualizado com sucesso.';
$_['text_success_delete'] = 'Usuário excluído com sucesso.';

// Column
$_['column_username'] = 'Usuário';
$_['column_email']    = 'E-mail';
$_['column_group']    = 'Grupo';
$_['column_status']   = 'Status';
$_['column_action']   = 'Ações';

// Entry
$_['entry_username']  = 'Usuário';
$_['entry_firstname'] = 'Nome';
$_['entry_lastname']  = 'Sobrenome';
$_['entry_email']     = 'E-mail';
$_['entry_password']  = 'Senha';
$_['entry_confirm']   = 'Confirmar Senha';
$_['entry_group']     = 'Grupo de Usuários';
$_['entry_status']    = 'Ativo';

// Button
$_['button_save']   = 'Salvar';
$_['button_cancel'] = 'Cancelar';
$_['button_add']    = 'Adicionar Usuário';
$_['button_edit']   = 'Editar';
$_['button_delete'] = 'Excluir';

// Error
$_['error_username']        = 'O usuário precisa ter entre 3 e 32 caracteres!';
$_['error_username_exists'] = 'Este usuário já está em uso!';
$_['error_email']           = 'Endereço de e-mail inválido!';
$_['error_firstname']       = 'O nome precisa ter entre 1 e 32 caracteres!';
$_['error_lastname']        = 'O sobrenome precisa ter entre 1 e 32 caracteres!';
$_['error_password']        = 'A senha precisa ter pelo menos 8 caracteres!';
$_['error_confirm']         = 'A confirmação da senha não confere!';
$_['error_group']           = 'Escolha um grupo de usuários!';
$_['error_self_delete']     = 'Você não pode excluir o próprio usuário logado!';
