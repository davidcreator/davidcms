<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/category — Idiomas do módulo Categorias (pt-BR; en-US canônico no ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Categorias do Portfólio';
$_['text_list']            = 'Categorias usadas para agrupar os projetos do portfólio. O conteúdo por idioma é editado no formulário da categoria.';
$_['text_no_results']      = 'Nenhuma categoria ainda. Crie a primeira.';
$_['text_confirm_delete']  = 'Excluir esta categoria? Subcategorias sobem para a raiz e as relações com projetos são removidas.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Desabilitado';
$_['text_add']             = 'Adicionar Categoria';
$_['text_edit']            = 'Editar Categoria';
$_['text_general']         = 'Conteúdo';
$_['text_data']            = 'Dados';
$_['text_languages_note']  = 'Um bloco por idioma instalado. O nome em en-US é obrigatório.';
$_['text_image_cover']     = 'Imagem';
$_['text_upload_hint']     = 'JPG, PNG, WebP ou GIF até 2 MB. O arquivo é gravado na hora; salve o formulário para vinculá-lo à categoria.';
$_['text_success_add']     = 'Categoria criada.';
$_['text_success_edit']    = 'Categoria atualizada.';
$_['text_success_delete']  = 'Categoria excluída.';
$_['text_placeholder']     = '(sem imagem)';
$_['text_none']            = '(nenhum)';

// Columns
$_['column_image']         = 'Foto';
$_['column_name']          = 'Nome';
$_['column_parent']        = 'Pai';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Ordenação';
$_['column_date_modified'] = 'Modificado';
$_['column_action']        = 'Ação';

// Entries
$_['entry_name']             = 'Nome';
$_['entry_description']      = 'Descrição';
$_['entry_meta_title']       = 'Meta Tag Título';
$_['entry_meta_description'] = 'Meta Tag Descrição';
$_['entry_keyword']          = 'Keyword SEO';
$_['entry_parent']           = 'Pai';
$_['entry_image']            = 'Foto';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Ordenação';

// Buttons
$_['button_add']        = 'Adicionar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Excluir';
$_['button_save']       = 'Salvar';
$_['button_cancel']     = 'Cancelar';
$_['button_upload']     = 'Enviar';

// Errors
$_['error_name']          = 'O nome em en-US é obrigatório (1 a 255 caracteres).';
$_['error_keyword_format'] = 'Keyword SEO (idioma #%s): apenas letras minúsculas, números e hífens.';
$_['error_keyword_taken']  = 'A keyword SEO "%s" já está em uso.';
$_['error_image_path']     = 'A imagem precisa ser um arquivo enviado pelo botão Enviar.';
$_['error_parent_cycle']   = 'Pai inválido: uma categoria não pode ser pai de si mesma nem de seus filhos.';
$_['error_upload']        = 'Falha no envio — verifique o arquivo e tente novamente.';
$_['error_upload_method'] = 'O envio precisa ser via POST.';
$_['error_upload_size']   = 'Arquivo excede o limite de %s.';
$_['error_upload_type']   = 'Tipo inválido: apenas JPG, PNG, WebP ou GIF.';
$_['error_upload_move']   = 'Não foi possível gravar o arquivo (verifique permissões do diretório).';
$_['error_permission']    = 'Você não tem permissão para modificar as categorias.';
