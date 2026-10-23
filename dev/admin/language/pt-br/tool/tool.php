<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * tool/tool — Idiomas do módulo Ferramentas (pt-BR; en-US canônico no ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Ferramentas Conectadas';
$_['text_list']            = 'Ferramentas e integrações exibidas no site (ícones + links). O conteúdo por idioma é editado no formulário da ferramenta.';
$_['text_no_results']      = 'Nenhuma ferramenta ainda. Crie a primeira.';
$_['text_confirm_delete']  = 'Excluir esta ferramenta? Suas descrições e o arquivo do ícone serão removidos.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Desabilitado';
$_['text_add']             = 'Adicionar Ferramenta';
$_['text_edit']            = 'Editar Ferramenta';
$_['text_general']         = 'Conteúdo';
$_['text_data']            = 'Dados';
$_['text_languages_note']  = 'Use as abas de idioma. O nome em en-US é obrigatório.';
$_['text_image']           = 'Ícone';
$_['text_upload_hint']     = 'JPG, PNG, WebP ou GIF até 2 MB. O arquivo é gravado agora; salve o formulário para vinculá-lo à ferramenta.';
$_['text_success_add']     = 'Ferramenta criada.';
$_['text_success_edit']    = 'Ferramenta atualizada.';
$_['text_success_delete']  = 'Ferramenta excluída.';
$_['text_placeholder']     = '(sem ícone)';
$_['text_none']            = '(nenhum)';

// Columns
$_['column_image']         = 'Ícone';
$_['column_name']          = 'Nome';
$_['column_vendor']        = 'Fornecedor';
$_['column_url']           = 'URL';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Ordenação';
$_['column_date_added']    = 'Adicionado';
$_['column_action']        = 'Ação';

// Entries
$_['entry_name']        = 'Nome';
$_['entry_description'] = 'Descrição';
$_['entry_vendor']      = 'Fornecedor';
$_['entry_url']         = 'URL';
$_['entry_image']       = 'Ícone';
$_['entry_status']      = 'Status';
$_['entry_sort_order']  = 'Ordenação';

// Buttons
$_['button_add']        = 'Adicionar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Excluir';
$_['button_save']       = 'Salvar';
$_['button_cancel']     = 'Cancelar';
$_['button_upload']     = 'Enviar';

// Errors
$_['error_name']          = 'O nome em en-US é obrigatório (1 a 128 caracteres).';
$_['error_url']           = 'A URL deve começar com http:// ou https:// (ou ficar vazia).';
$_['error_image_path']    = 'O ícone deve ser um arquivo enviado pelo botão Enviar.';
$_['error_upload']        = 'Falha no envio — verifique o arquivo e tente novamente.';
$_['error_upload_method'] = 'O envio deve ser um POST.';
$_['error_upload_size']   = 'O arquivo excede o limite de %s.';
$_['error_upload_type']   = 'Tipo inválido: apenas JPG, PNG, WebP ou GIF.';
$_['error_upload_move']   = 'Não foi possível gravar o arquivo enviado (verifique as permissões do diretório).';
$_['error_permission']    = 'Você não tem permissão para modificar as ferramentas.';

// Fase 8 / Tarefa 09 — interface, filtros/paginação e CSRF.
$_['text_all_status'] = 'Todos os status';
$_['text_search_placeholder'] = 'Buscar por nome…';
$_['text_vendor_placeholder'] = 'Buscar por fornecedor…';
$_['text_showing'] = 'Exibindo %d–%d de %d ferramentas';
$_['text_empty_title'] = 'Nenhuma ferramenta encontrada';
$_['text_empty_hint'] = 'Crie uma ferramenta ou ajuste os filtros.';
$_['text_upload_wait'] = 'Aguarde o término do envio antes de salvar.';
$_['button_filter'] = 'Filtrar';
$_['button_reset'] = 'Limpar';
$_['button_close'] = 'Fechar';
$_['button_first_page'] = '« Primeira';
$_['button_last_page'] = 'Última »';
$_['button_next_page'] = 'Próxima ›';
$_['button_previous_page'] = '‹ Anterior';
$_['error_csrf'] = 'Token de sessão inválido. Recarregue o formulário e tente novamente.';
