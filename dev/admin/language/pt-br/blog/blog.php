<?php
/**
 * blog/blog — Blog (F7-01, ADR 0042) — pt-br
 */

// Heading
$_['heading_title']           = 'Blog';

// Text
$_['text_list']               = 'Notícias e artigos, mais recentes primeiro. Seção pública (F7-02): /blog';
$_['text_add']                = 'Novo post';
$_['text_edit']               = 'Editar post';
$_['text_general']            = 'Conteúdo (por idioma)';
$_['text_data']               = 'Dados';
$_['text_languages_note']     = 'Apenas inglês (en-us) é obrigatório; os demais idiomas são opcionais.';
$_['text_no_results']         = 'Nenhum post ainda. Comece pelo seu primeiro artigo!';
$_['text_confirm_delete']     = 'Excluir este post permanentemente?';
$_['text_enabled']            = 'Publicado';
$_['text_disabled']           = 'Rascunho';
$_['text_success_add']        = 'Post publicado.';
$_['text_success_edit']       = 'Post atualizado.';
$_['text_success_delete']     = 'Post excluído.';
$_['text_flash']              = '';

// Column
$_['column_title']            = 'Título';
$_['column_status']           = 'Status';
$_['column_date_published']   = 'Publicação';
$_['column_date_modified']    = 'Modificado';
$_['column_action']           = 'Ação';

// Entry
$_['entry_title']             = 'Título';
$_['entry_description']       = 'Conteúdo';
$_['entry_meta_title']        = 'Meta título';
$_['entry_meta_description']  = 'Meta descrição';
$_['entry_keyword']           = 'Keyword SEO (opcional)';
$_['entry_image']             = 'Imagem destaque';
$_['entry_date_published']    = 'Data de publicação';
$_['entry_status']            = 'Publicado';

// Help
$_['help_image']              = 'Caminho relativo ou URL externa. O importador WordPress (F7-03) preenche sozinho.';
$_['help_date_published']     = 'A ordem pública é mais recente primeiro. Deixe como está para publicar agora.';

// Button
$_['button_add']              = 'Adicionar post';
$_['button_edit']             = 'Editar';
$_['button_delete']           = 'Excluir';
$_['button_save']             = 'Salvar';
$_['button_cancel']           = 'Cancelar';
$_['button_import_wp']        = 'Importar do WordPress'; // F7-03 (ADR 0044)

// Error
$_['error_title']             = 'Título (en-us) é obrigatório e deve ter até 255 caracteres.';
$_['error_date_published']    = 'Data de publicação: formato esperado AAAA-MM-DD HH:MM (opcional).';
$_['error_keyword_format']    = 'Keyword do idioma %d: apenas letras minúsculas, dígitos e hífens.';
$_['error_keyword_taken']     = "A keyword '%s' já é usada por outra página, projeto, ferramenta ou post.";
