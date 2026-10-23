<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * cms/page — Idiomas do módulo Páginas (pt-BR; en-US canônico no ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Páginas de Conteúdo';
$_['text_list']            = 'Páginas informativas do site (Sobre, Políticas, Cookies). O conteúdo por idioma é editado no formulário da página.';
$_['text_no_results']      = 'Nenhuma página ainda. Crie a primeira.';
$_['text_confirm_delete']  = 'Excluir esta página? Suas descrições e keywords SEO serão removidas.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Desabilitado';
$_['text_add']             = 'Adicionar Página';
$_['text_edit']            = 'Editar Página';
$_['text_general']         = 'Conteúdo';
$_['text_data']            = 'Dados';
$_['text_languages_note']  = 'Use as abas de idioma. O título em en-US é obrigatório.';
$_['text_success_add']     = 'Página criada.';
$_['text_success_edit']    = 'Página atualizada.';
$_['text_success_delete']  = 'Página excluída.';

// Columns
$_['column_title']         = 'Título';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Ordenação';
$_['column_date_modified'] = 'Modificado';
$_['column_action']        = 'Ação';

// Entries
$_['entry_title']            = 'Título';
$_['entry_description']      = 'Descrição';
$_['entry_meta_title']       = 'Meta Tag Título';
$_['entry_meta_description'] = 'Meta Tag Descrição';
$_['entry_keyword']          = 'Keyword SEO';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Ordenação';

// Buttons
$_['button_add']        = 'Adicionar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Excluir';
$_['button_save']       = 'Salvar';
$_['button_cancel']     = 'Cancelar';

// Errors
$_['error_title']         = 'O título em en-US é obrigatório (1 a 255 caracteres).';
$_['error_keyword_format'] = 'Keyword SEO (idioma #%s): apenas letras minúsculas, números e hífens.';
$_['error_keyword_taken']  = 'A keyword SEO "%s" já está em uso.';
$_['error_permission']    = 'Você não tem permissão para modificar as páginas.';

// F8-06 — lista/form no shell, filtros/paginação e CSRF.
$_['text_all_status'] = 'Todos os status';
$_['text_search_placeholder'] = 'Buscar por título…';
$_['text_showing'] = 'Exibindo %d–%d de %d páginas';
$_['text_empty_title'] = 'Nenhuma página encontrada';
$_['text_empty_hint'] = 'Crie uma página ou ajuste os filtros.';
$_['text_meta_group'] = 'Metadados de busca';
$_['help_sort_order'] = 'Números menores aparecem primeiro.';
$_['help_description'] = 'Informe o conteúdo da página. HTML continua aceito.';
$_['button_filter'] = 'Filtrar';
$_['button_reset'] = 'Limpar';
$_['button_first_page'] = '« Primeira';
$_['button_last_page'] = 'Última »';
$_['button_next_page'] = 'Próxima ›';
$_['button_previous_page'] = '‹ Anterior';
$_['error_csrf'] = 'Token de sessão inválido. Recarregue o formulário e tente novamente.';
$_['button_close'] = 'Fechar';
