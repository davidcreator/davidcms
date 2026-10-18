<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language pt-br — wizard passo 3: Configuração unificada (ADR 0007).
 * Traduzido do en-us (ADR 0001); frases comuns alinhadas ao pt-br oficial do ReamurCMS.
 */

// Heading
$_['heading_title'] = 'Configuração';

// Text
$_['text_step_3']            = 'Insira os detalhes do seu banco de dados e administração';
$_['text_db_connection']     = '1. Insira os detalhes da conexão com seu banco de dados.';
$_['text_db_administration'] = '2. Insira um nome de usuário e uma senha para a administração.';
$_['text_db_ok']             = 'Conexão com o banco de dados realizada com sucesso.';
$_['text_password_hint']     = 'Use no mínimo 8 caracteres. Letras, números e símbolos são aceitos.';

$_['text_http_server_hint'] = 'Endereço público da LOJA. Pré-preenchemos a partir do endereço do seu navegador (catalog/ fica ao lado de install/). O painel admin é derivado automaticamente para .../admin/. Se a loja responder na raiz do domínio, informe ex.: https://meusite.com.br/.';

// Text (JavaScript da tela)
$_['text_js_password_mismatch']  = 'As senhas não coincidem.';
$_['text_js_password_weak']       = 'Fraca';
$_['text_js_password_medium']     = 'Média';
$_['text_js_password_strong']     = 'Forte';
$_['text_js_password_generated']  = 'Senha gerada — guarde-a em local seguro';
$_['text_js_db_testing']          = 'Testando…';
$_['text_js_username_hint']       = 'Informe um nome de usuário com pelo menos 3 caracteres.';

// Entry
$_['entry_db_driver']   = 'Driver do Banco de Dados';
$_['entry_db_hostname'] = 'Servidor';
$_['entry_db_username'] = 'Usuário';
$_['entry_db_password'] = 'Senha';
$_['entry_db_database'] = 'Banco de Dados';
$_['entry_db_port']     = 'Porta';
$_['entry_db_prefix']   = 'Prefixo das Tabelas';
$_['entry_http_server'] = 'URL do Site';
$_['entry_username']    = 'Nome de Usuário';
$_['entry_firstname']   = 'Nome';
$_['entry_lastname']    = 'Sobrenome';
$_['entry_email']       = 'E-mail';
$_['entry_password']    = 'Senha';
$_['entry_confirm']     = 'Confirmar Senha';

// Button
$_['button_back']            = 'Voltar';
$_['button_continue']        = 'Continuar';
$_['button_test_db']         = 'Testar conexão';
$_['button_generate']        = 'Gerar senha';
$_['button_toggle_password'] = 'Mostrar/ocultar senha';

// Error
$_['error_db_driver']   = 'Driver do Banco de Dados obrigatório!';
$_['error_required']    = 'Campo obrigatório!';
$_['error_db_prefix']   = 'O prefixo das tabelas só pode conter letras minúsculas a-z, números 0-9 e sublinhados, começando por letra!';
$_['error_db_port']     = 'Porta do banco de dados obrigatória!';
$_['error_http_server'] = 'Informe uma URL do Site válida (ex.: https://exemplo.com/)!';
$_['error_db_connect']  = 'Erro: Não foi possível conectar ao banco de dados. Verifique servidor, usuário e senha.';
$_['error_db_exists']   = 'O banco de dados já contém tabelas com este prefixo. Use um banco vazio para não sobrescrever uma instalação existente.';
$_['error_config']      = 'Não foi possível gravar os arquivos de configuração:';
$_['error_username']    = 'O nome de usuário precisa ter entre 3 e 32 caracteres!';
$_['error_firstname']   = 'O nome precisa ter entre 1 e 32 caracteres!';
$_['error_lastname']    = 'O sobrenome precisa ter entre 1 e 32 caracteres!';
$_['error_email']       = 'Endereço de e-mail inválido!';
$_['error_password']    = 'A senha precisa ter pelo menos 8 caracteres!';
$_['error_confirm']     = 'A confirmação da senha não confere!';
