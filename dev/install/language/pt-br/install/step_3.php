<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language pt-br — wizard passo 3 (banco de dados). Traduzido do en-us (ADR 0001).
 */

// Heading
$_['heading_title'] = 'Configuração do Banco de Dados';

// Text
$_['text_step'] = 'Passo %s de 5';
$_['text_db']   = 'Informe os dados de conexão do seu banco MySQL/MariaDB e a URL pública do site.';

// Entry
$_['entry_db_driver']   = 'Driver do Banco';
$_['entry_db_hostname'] = 'Servidor';
$_['entry_db_username'] = 'Usuário';
$_['entry_db_password'] = 'Senha';
$_['entry_db_database'] = 'Banco de Dados';
$_['entry_db_port']     = 'Porta';
$_['entry_db_prefix']   = 'Prefixo das Tabelas';
$_['entry_http_server'] = 'URL do Site';

// Button
$_['button_back']     = 'Voltar';
$_['button_continue'] = 'Continuar';

// Error
$_['error_db_driver']   = 'Driver de banco de dados inválido!';
$_['error_required']    = 'Este campo é obrigatório!';
$_['error_db_prefix']   = 'O prefixo precisa começar com letra e conter apenas letras minúsculas, números e sublinhados (máx. 20 caracteres)!';
$_['error_http_server'] = 'URL do site inválida (ex.: https://exemplo.com/)!';
$_['error_connect']     = 'Não foi possível conectar ao banco de dados:';
$_['error_db_exists']   = 'O banco de dados já contém tabelas com este prefixo! Use um banco vazio ou um prefixo diferente para não sobrescrever uma instalação existente.';
$_['error_config']      = 'Não foi possível gravar o arquivo de configuração:';
