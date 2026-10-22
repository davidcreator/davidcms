<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Language pt-br — user/user_group (grupos + matriz ACL). Traduzido do en-us (ADR 0001).
 */
// Heading
$_['heading_title'] = 'Grupos de Usuários';

// Text
$_['text_list']         = 'Grupos que detêm as permissões do painel';
$_['text_permissions']  = 'Permissões (rotas-base descobertas dos controllers)';
$_['text_access']       = 'Acesso';
$_['text_modify']       = 'Modificação';
$_['text_group_note']   = 'Atenção: remover rotas do próprio grupo pode trancar você fora destas telas.';
$_['text_select_all']   = 'Selecionar tudo';
$_['text_success_add']  = 'Grupo criado com sucesso.';
$_['text_success_edit'] = 'Grupo atualizado com sucesso.';

// Column
$_['column_name']   = 'Nome';
$_['column_users']  = 'Usuários';
$_['column_action'] = 'Ações';

// Column
$_['column_route'] = 'Rota';

// Entry
$_['entry_name'] = 'Nome do Grupo';

// Button
$_['button_save']   = 'Salvar';
$_['button_cancel'] = 'Cancelar';
$_['button_add']    = 'Adicionar Grupo';
$_['button_edit']   = 'Editar';

// Error
$_['error_name'] = 'O nome do grupo precisa ter entre 1 e 64 caracteres!';

$_['button_delete']          = 'Excluir';
$_['text_confirm_delete']    = 'Excluir definitivamente este grupo?';
$_['text_success_delete']    = 'Grupo excluído com sucesso!';
$_['error_group_in_use']     = 'Não é possível excluir: há %d usuário(s) vinculado(s) a este grupo.';
$_['error_group_last_admin'] = 'Não é possível excluir: este é o único grupo que confere administração de usuários (user/user).';

// F8-03: filtros, paginação e proteção de requisições
$_['text_filter'] = 'Filtrar grupos';
$_['text_no_results'] = 'Nenhum grupo de usuários encontrado.';
$_['text_showing'] = 'Exibindo %s–%s de %s';
$_['entry_filter_name'] = 'Nome do grupo';
$_['button_filter'] = 'Aplicar filtros';
$_['button_reset'] = 'Limpar';
$_['error_csrf'] = 'O formulário expirou ou não pôde ser verificado. Recarregue a página e tente novamente.';
$_['text_close'] = 'Fechar';
$_['text_page_first'] = '« Primeira';
$_['text_page_last'] = 'Última »';
$_['text_page_next'] = 'Próxima ›';
$_['text_page_prev'] = '‹ Anterior';
