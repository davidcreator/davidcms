<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/project — Idiomas do módulo Portfólio (pt-BR; en-US canônico no ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Portfólio';
$_['text_list']            = 'Projetos do portfólio exibidos no site. O conteúdo por idioma é editado no formulário do projeto.';
$_['text_no_results']      = 'Nenhum projeto ainda. Crie o primeiro.';
$_['text_confirm_delete']  = 'Excluir este projeto? Imagens, descrições e keywords SEO dele serão removidas.';
$_['text_enabled']         = 'Habilitado';
$_['text_disabled']        = 'Desabilitado';
$_['text_yes']             = 'Sim';
$_['text_no']              = 'Não';
$_['text_add']             = 'Adicionar Projeto';
$_['text_edit']            = 'Editar Projeto';
$_['text_general']         = 'Conteúdo';
$_['text_data']            = 'Dados';
$_['text_languages_note']  = 'Um bloco por idioma instalado. O título em en-US é obrigatório.';
$_['text_image_cover']     = 'Imagem de capa';
$_['text_gallery']         = 'Galeria';
$_['text_upload_hint']     = 'JPG, PNG, WebP ou GIF até 2 MB. O arquivo é gravado na hora; salve o formulário para vinculá-lo ao projeto.';
$_['text_success_add']     = 'Projeto criado.';
$_['text_success_edit']    = 'Projeto atualizado.';
$_['text_success_delete']  = 'Projeto excluído.';
$_['text_placeholder']     = '(sem capa)';

// Columns
$_['column_image']         = 'Imagem';
$_['column_name']          = 'Nome';
$_['column_client']        = 'Cliente';
$_['column_featured']      = 'Destaque';
$_['column_status']        = 'Status';
$_['column_sort_order']    = 'Ordenação';
$_['column_date_modified'] = 'Modificado';
$_['column_action']        = 'Ação';

// Entries
$_['entry_name']             = 'Título';
$_['entry_summary']          = 'Resumo';
$_['entry_description']      = 'Descrição';
$_['entry_tag']              = 'Tags (separadas por vírgula)';
$_['entry_meta_title']       = 'Meta Tag Título';
$_['entry_meta_description'] = 'Meta Tag Descrição';
$_['entry_keyword']          = 'Keyword SEO';
$_['entry_client']           = 'Client';
$_['entry_url']              = 'URL do Projeto';
$_['entry_image']            = 'Capa';
$_['entry_category']         = 'Categorias';
$_['entry_featured']         = 'Featured';
$_['entry_status']           = 'Status';
$_['entry_sort_order']       = 'Sort Order';
$_['entry_alt']              = 'Texto alternativo';

// Buttons
$_['button_add']        = 'Adicionar';
$_['button_edit']       = 'Editar';
$_['button_delete']     = 'Excluir';
$_['button_save']       = 'Salvar';
$_['button_cancel']     = 'Cancelar';
$_['button_upload']     = 'Enviar';
$_['button_remove']     = 'Remover';
$_['button_add_image']  = 'Adicionar Imagem';

// Errors
$_['error_name']          = 'O título em en-US é obrigatório (1 a 255 caracteres).';
$_['error_url']           = 'A URL do projeto precisa ser uma URL válida (com esquema).';
$_['error_keyword_format'] = 'Keyword SEO (idioma #%s): apenas letras minúsculas, números e hífens.';
$_['error_keyword_taken']  = 'A keyword SEO "%s" já está em uso.';
$_['error_image_path']     = 'A capa precisa ser um arquivo enviado pelo botão Enviar.';
$_['error_upload']        = 'Falha no envio — verifique o arquivo e tente novamente.';
$_['error_upload_method'] = 'O envio precisa ser via POST.';
$_['error_upload_size']   = 'Arquivo excede o limite de %s.';
$_['error_upload_type']   = 'Tipo inválido: apenas JPG, PNG, WebP ou GIF.';
$_['error_upload_move']   = 'Não foi possível gravar o arquivo (verifique permissões do diretório).';
$_['error_permission']    = 'Você não tem permissão para modificar o portfólio.';
