<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/testimonial — Idiomas do módulo Depoimentos (pt-BR; en-US canônico no ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Depoimentos';
$_['text_list']            = 'Depoimentos de clientes exibidos na home. O conteúdo por idioma é editado no formulário do depoimento.';
$_['text_no_results']      = 'Nenhum depoimento ainda. Crie o primeiro.';
$_['text_confirm_delete']  = 'Excluir este depoimento? Suas descrições e o arquivo da foto serão removidos.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Desabilitado';
$_['text_add']             = 'Adicionar Depoimento';
$_['text_edit']            = 'Editar Depoimento';
$_['text_general']         = 'Conteúdo';
$_['text_data']            = 'Dados';
$_['text_languages_note']  = 'Um bloco por idioma instalado. O autor em en-US é obrigatório.';
$_['text_image']           = 'Foto do Autor';
$_['text_upload_hint']     = 'JPG, PNG, WebP ou GIF até 2 MB. O arquivo é gravado agora; salve o formulário para vinculá-lo ao depoimento.';
$_['text_success_add']     = 'Depoimento criado.';
$_['text_success_edit']    = 'Depoimento atualizado.';
$_['text_success_delete']  = 'Depoimento excluído.';
$_['text_placeholder']     = '(sem foto)';

// F8-04 — filtros, paginação e proteção CSRF
$_['text_filter'] = 'Filtrar depoimentos';
$_['text_all_status'] = 'Todos os status';
$_['text_showing'] = 'Exibindo %s–%s de %s depoimentos';
$_['text_no_results_filtered'] = 'Nenhum depoimento corresponde a esses filtros.';
$_['text_close'] = 'Fechar';
$_['text_page_first'] = '« Primeiro';
$_['text_page_last'] = 'Último »';
$_['text_page_next'] = 'Próximo ›';
$_['text_page_prev'] = '‹ Anterior';
$_['entry_filter_author'] = 'Autor ou empresa';
$_['button_filter'] = 'Aplicar filtros';
$_['button_reset'] = 'Limpar';
$_['error_csrf'] = 'O token de segurança é inválido ou expirou. Atualize a página e tente novamente.';

// Columns
$_['column_image']         = 'Foto';
$_['column_author']        = 'Autor';
$_['column_company']       = 'Empresa';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Ordenação';
$_['column_date_added']    = 'Adicionado';
$_['column_action']        = 'Ação';

// Entries
$_['entry_author']     = 'Autor';
$_['entry_company']    = 'Empresa';
$_['entry_text']       = 'Texto do Depoimento';
$_['entry_image']      = 'Foto';
$_['entry_status']     = 'Status';
$_['entry_sort_order'] = 'Ordenação';

// Buttons
$_['button_add']        = 'Adicionar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Excluir';
$_['button_save']       = 'Salvar';
$_['button_cancel']     = 'Cancelar';
$_['button_upload']     = 'Enviar';

// Errors
$_['error_author']        = 'O autor em en-US é obrigatório (1 a 128 caracteres).';
$_['error_image_path']    = 'A foto deve ser um arquivo enviado pelo botão Enviar.';
$_['error_upload']        = 'Falha no envio — verifique o arquivo e tente novamente.';
$_['error_upload_method'] = 'O envio deve ser um POST.';
$_['error_upload_size']   = 'O arquivo excede o limite de %s.';
$_['error_upload_type']   = 'Tipo inválido: apenas JPG, PNG, WebP ou GIF.';
$_['error_upload_move']   = 'Não foi possível gravar o arquivo enviado (verifique as permissões do diretório).';
$_['error_permission']    = 'Você não tem permissão para modificar os depoimentos.';
