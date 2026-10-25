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

// F8-02 (ADR 0046): barra de filtros, badges, estados, sidebar de publicação
$_['text_total_posts']         = 'posts';
$_['text_showing']             = 'Exibindo %d–%d de %d posts';
$_['text_search_placeholder']  = 'Buscar por título…';
$_['text_all_status']          = 'Todos os status';
$_['text_published']           = 'Publicado';
$_['text_draft']               = 'Rascunho';
$_['text_view_site']           = 'Ver no site';
$_['text_empty_state']         = 'Nenhum post encontrado — ajuste os filtros ou crie o primeiro.';
$_['text_empty_state_cta']     = 'Criar post';
$_['button_filter']            = 'Filtrar';
$_['button_reset']             = 'Limpar';
$_['text_publish_card']        = 'Publicação';
$_['text_content_card']        = 'Conteúdo';
$_['text_meta_group']          = 'SEO';
$_['text_seo_url_preview']     = 'Prefixo da URL SEO';
$_['text_cover_preview']       = 'Imagem de capa';
$_['text_status_help']         = 'Posts publicados aparecem na área de jornal do site.';
$_['text_chars']               = 'caracteres';

// F8-05 (ADR 0049): biblioteca de arquivos do Blog
$_['text_media_library'] = 'Biblioteca de imagens do Blog';
$_['text_media_browse'] = 'Explorar';
$_['text_media_upload'] = 'Enviar imagem';
$_['text_media_create_folder'] = 'Nova pasta';
$_['text_media_external_url'] = 'URL externa';
$_['text_media_loading'] = 'Carregando arquivos…';
$_['text_media_empty'] = 'Ainda não há imagens ou pastas aqui.';
$_['text_media_no_matches'] = 'Nenhuma imagem ou pasta corresponde à busca.';
$_['text_media_page'] = 'Página %d de %d';
$_['text_media_confirm_delete_file'] = 'Excluir esta imagem permanentemente? Esta ação não pode ser desfeita.';
$_['text_media_confirm_delete_folder'] = 'Excluir esta pasta vazia?';
$_['text_media_upload_success'] = 'Imagem enviada e selecionada.';
$_['text_media_folder_created'] = 'Pasta criada.';
$_['text_media_file_deleted'] = 'Imagem excluída.';
$_['text_media_folder_deleted'] = 'Pasta excluída.';
$_['text_media_upload_hint'] = 'JPG, PNG, WebP ou GIF; tamanho máximo de 2 MB.';
$_['text_media_no_folders'] = 'Nenhuma pasta';
$_['text_media_in_use'] = 'Esta imagem está sendo usada por um post e não pode ser excluída.';

$_['entry_media_search'] = 'Buscar imagens e pastas';
$_['entry_media_folder_name'] = 'Nome da pasta';
$_['entry_media_external_url'] = 'URL de imagem HTTP(S)';

$_['button_media_library'] = 'Escolher da biblioteca';
$_['button_media_upload'] = 'Enviar e usar imagem';
$_['button_media_create_folder'] = 'Criar pasta';
$_['button_media_use_url'] = 'Usar URL';
$_['button_media_select'] = 'Selecionar imagem';
$_['button_media_delete'] = 'Excluir';
$_['button_media_back'] = 'Voltar';
$_['button_media_previous'] = 'Página anterior';
$_['button_media_next'] = 'Próxima página';
$_['button_media_close'] = 'Fechar';

$_['error_permission'] = 'Você não tem permissão para gerenciar a mídia do Blog.';
$_['error_csrf'] = 'O token de segurança é inválido ou expirou. Atualize a página e tente novamente.';
$_['error_media_method'] = 'Esta ação de mídia não permite o método HTTP solicitado.';
$_['error_media_invalid_path'] = 'O caminho da pasta ou imagem solicitado é inválido.';
$_['error_media_folder_not_found'] = 'A pasta de mídia do Blog selecionada não foi encontrada.';
$_['error_media_folder_unreadable'] = 'Não foi possível ler a pasta selecionada.';
$_['error_media_folder_name'] = 'Use até 64 letras, números, espaços, hífens ou sublinhados no nome da pasta.';
$_['error_media_root'] = 'A pasta de mídia do Blog não está disponível ou não permite gravação.';
$_['error_media_folder_create'] = 'Não foi possível criar a pasta.';
$_['error_media_folder_exists'] = 'Já existe um arquivo ou pasta com esse nome.';
$_['error_media_not_found'] = 'A imagem ou pasta solicitada não foi encontrada.';
$_['error_media_file_in_use'] = 'Esta imagem é referenciada por um post do Blog e não pode ser excluída.';
$_['error_media_folder_not_empty'] = 'Somente pastas vazias podem ser excluídas.';
$_['error_media_delete'] = 'Não foi possível excluir o item de mídia.';
$_['error_media_upload_no_file'] = 'Escolha uma imagem para enviar.';
$_['error_media_upload_size'] = 'A imagem deve ter no máximo %s.';
$_['error_media_upload_type'] = 'Escolha uma imagem JPG, PNG, WebP ou GIF válida.';
$_['error_media_upload_move'] = 'Não foi possível salvar a imagem. Verifique as permissões da pasta catalog.';
$_['error_media_upload'] = 'Não foi possível enviar a imagem.';
$_['error_media_generic'] = 'A biblioteca de mídia não conseguiu concluir a solicitação. Tente novamente.';
$_['error_media_url'] = 'Informe uma URL de imagem HTTP ou HTTPS válida.';

// F9-16: block editor and safe legacy conversion
$_['text_block_editor_intro'] = 'Edite o conteúdo em blocos. O salvamento ocorre ao enviar este formulário; não há gravação automática.';
$_['text_block_conversion_note'] = 'O conteúdo legado foi convertido para blocos quando possível. Revise antes de salvar; o HTML original é preservado como snapshot.';
$_['text_block_review_required'] = 'Este conteúdo legado não pôde ser convertido com segurança. Revise-o no editor legado antes de alterar o formato.';
$_['text_block_invalid'] = 'O documento de blocos está inválido ou incompatível. O conteúdo não será substituído.';
$_['text_block_saved'] = 'Este idioma está no formato de blocos.';
$_['error_block_schema_unavailable'] = 'O armazenamento de blocos não está disponível. Atualize o schema antes de salvar conteúdo em blocos.';
$_['error_block_document_invalid'] = 'O documento de blocos enviado é inválido ou não foi preparado pelo editor.';
$_['error_block_legacy_review'] = 'O conteúdo legado precisa de revisão manual antes de ser convertido em blocos.';
$_['error_block_mode_invalid'] = 'O modo de conteúdo enviado é inválido ou inconsistente. Recarregue o formulário.';
$_['error_block_editor_unavailable'] = 'O editor de blocos não foi inicializado. Recarregue o formulário e tente novamente.';

// F9-22: inline rich-text toolbar
$_['text_inline_bold'] = 'Negrito';
$_['text_inline_italic'] = 'Itálico';
$_['text_inline_underline'] = 'Sublinhado';
$_['text_inline_strike'] = 'Tachado';
$_['text_inline_code'] = 'Código';
$_['text_inline_link'] = 'Link';
$_['text_inline_link_placeholder'] = 'https:// ou /caminho';
$_['text_inline_apply_link'] = 'Aplicar link';
$_['text_inline_remove_link'] = 'Remover link';
$_['text_inline_invalid_link'] = 'Informe uma URL HTTP(S) ou caminho local válida.';
$_['text_inline_toolbar'] = 'Formatação do texto';
$_['text_inline_text_label'] = 'Texto do bloco';
$_['text_inline_placeholder'] = 'Digite o texto…';
$_['text_inline_editor_label'] = 'Editor visual de blocos do Blog';

// F9-23: block UX, searchable inserter, outline and structural actions
$_['text_block_add'] = 'Adicionar bloco';
$_['text_block_search'] = 'Buscar blocos…';
$_['text_block_no_results'] = 'Nenhum bloco encontrado.';
$_['text_block_category_content'] = 'Conteúdo';
$_['text_block_category_media'] = 'Mídia';
$_['text_block_category_layout'] = 'Layout';
$_['text_block_outline'] = 'Estrutura do documento';
$_['text_block_outline_empty'] = 'O documento ainda não tem blocos.';
$_['text_block_move_up'] = 'Mover para cima';
$_['text_block_move_down'] = 'Mover para baixo';
$_['text_block_duplicate'] = 'Duplicar';
$_['text_block_transform'] = 'Transformar bloco';
$_['text_block_remove'] = 'Remover bloco';
$_['text_block_add_after'] = 'Inserir depois deste bloco';
$_['text_block_drag'] = 'Arraste para reordenar; Alt+↑/↓ também move.';
$_['text_block_type_paragraph'] = 'Parágrafo';
$_['text_block_type_heading'] = 'Título';
$_['text_block_type_list'] = 'Lista';
$_['text_block_type_image'] = 'Imagem';
$_['text_block_type_quote'] = 'Citação';
$_['text_block_type_video'] = 'Vídeo';
$_['text_block_type_button'] = 'Botão';
$_['text_block_type_separator'] = 'Separador';
$_['text_block_type_group'] = 'Grupo';
$_['text_block_type_columns'] = 'Colunas';
$_['text_block_type_list_item'] = 'Item de lista';
$_['text_block_transform_hint'] = 'Transformação preserva somente conteúdo textual compatível.';
$_['text_block_insert_empty'] = 'Nenhum tipo de bloco pode ser inserido neste ponto.';

// F9-23: accessible action status
$_['text_block_status_inserted'] = 'Bloco inserido.';
$_['text_block_status_moved'] = 'Bloco movido.';
$_['text_block_status_duplicated'] = 'Bloco duplicado.';
$_['text_block_status_transformed'] = 'Bloco transformado.';
$_['text_block_status_removed'] = 'Bloco removido.';
$_['text_block_status_undo'] = 'Alteração desfeita.';
$_['text_block_status_redo'] = 'Alteração refeita.';
$_['text_block_type_column'] = 'Coluna';

// F9-23: localized canvas and history controls
$_['text_block_undo'] = 'Desfazer';
$_['text_block_redo'] = 'Refazer';
$_['text_block_preview_desktop'] = 'Desktop';
$_['text_block_preview_tablet'] = 'Tablet';
$_['text_block_preview_mobile'] = 'Celular';
$_['text_block_editor_tools'] = 'Ferramentas do editor de blocos';
$_['text_block_canvas'] = 'Canvas de conteúdo';
$_['text_block_canvas_empty'] = 'Escolha um bloco no inserter para começar.';
$_['text_block_inspector'] = 'Inspetor de bloco';
$_['text_block_inspector_empty'] = 'Selecione um bloco para editar seus atributos.';
$_['text_block_column_count'] = 'colunas · empilham em telas estreitas';

// F9-24: media blocks, responsive controls and built-in patterns
$_['text_block_type_gallery'] = 'Galeria';
$_['text_block_type_cover'] = 'Capa';

// BDREditor: structured table controls
$_['text_block_type_table'] = 'Tabela';
$_['text_block_table_header'] = 'Primeira linha é cabeçalho';
$_['text_block_table_add_row'] = 'Adicionar linha';
$_['text_block_table_remove_row'] = 'Remover última linha';
$_['text_block_table_add_column'] = 'Adicionar coluna';
$_['text_block_table_remove_column'] = 'Remover última coluna';
$_['text_block_table_toggle_header'] = 'Alternar cabeçalho';
$_['text_block_table_cell_label'] = 'Célula %1$d, %2$d';
$_['text_block_caption'] = 'Legenda';
$_['text_block_alignment'] = 'Alinhamento da imagem';
$_['text_block_image_size'] = 'Tamanho';
$_['text_block_mobile_size'] = 'Tamanho no celular';
$_['text_block_gallery_columns'] = 'Colunas da galeria';
$_['text_block_media_source'] = 'Caminho local da imagem';
$_['text_block_image_alt'] = 'Texto alternativo';
$_['text_block_image_title'] = 'Título da imagem';
$_['text_block_align_left'] = 'Esquerda';
$_['text_block_align_center'] = 'Centro';
$_['text_block_align_right'] = 'Direita';
$_['text_block_align_content'] = 'Conteúdo';
$_['text_block_align_wide'] = 'Largo';
$_['text_block_align_full'] = 'Largura total';
$_['text_block_size_small'] = 'Pequeno';
$_['text_block_size_medium'] = 'Médio';
$_['text_block_size_large'] = 'Grande';
$_['text_block_size_full'] = 'Largura total';
$_['text_block_mobile_full'] = 'Largura total';
$_['text_block_mobile_large'] = 'Grande';
$_['text_block_mobile_medium'] = 'Médio';
$_['text_block_video_preview'] = 'Prévia do vídeo';
$_['text_block_video_preview_hint'] = 'Prévia administrativa segura; a reprodução ocorre na página pública.';
$_['text_block_media_placeholder'] = 'Selecione uma imagem na biblioteca.';
$_['text_block_pattern_category'] = 'Padrões de layout';
$_['text_block_pattern_two_columns'] = 'Destaque em duas colunas';
$_['text_block_pattern_quote_cta'] = 'Citação e chamada';
$_['text_block_pattern_section_intro'] = 'Introdução de seção';
$_['text_block_pattern_cover_hero'] = 'Capa de destaque';
$_['text_block_pattern_hint'] = 'Padrões locais prontos para inserir; não são salvos como templates globais.';
$_['text_block_outline_label'] = 'Título na estrutura';
$_['text_block_initial_invalid'] = 'O documento inicial de blocos é inválido. A fonte foi preservada para revisão e a edição está bloqueada.';
$_['text_block_document_too_large'] = 'O documento de blocos ultrapassa o limite de 4 MiB.';

// BDREditor core/file — biblioteca de descargas local.
$_['text_block_type_file'] = 'Arquivo para download';
$_['text_block_file_source'] = 'Referência local do arquivo';
$_['text_block_file_choose'] = 'Escolher arquivo da biblioteca';
$_['text_download_library'] = 'Arquivos para download';
$_['text_download_empty'] = 'Nenhum documento disponível.';
$_['text_download_upload'] = 'Enviar documento';
$_['text_download_hint'] = 'PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV ou TXT; máximo de 2 MiB.';
$_['text_download_limit'] = 'Até 2 MiB por documento.';
$_['button_download_select'] = 'Usar este arquivo';
$_['button_download_upload'] = 'Enviar e usar arquivo';
$_['error_download_upload_no_file'] = 'Escolha um documento para enviar.';
$_['error_download_upload_size'] = 'O documento deve ter no máximo %s.';
$_['error_download_upload_type'] = 'Tipo de documento inválido ou conteúdo incompatível.';
$_['error_download_upload_move'] = 'Não foi possível salvar o documento. Verifique as permissões da pasta catalog.';
$_['error_download_generic'] = 'Não foi possível carregar a biblioteca de downloads.';

// BDREditor core/audio: local assets only; no upload/delete.
$_['text_block_type_audio'] = 'Áudio';
$_['text_block_audio_source'] = 'Referência local do áudio';
$_['text_block_audio_choose'] = 'Escolher áudio da biblioteca';
$_['text_audio_library'] = 'Biblioteca de áudio';
$_['text_audio_empty'] = 'Nenhum áudio compatível encontrado em catalog/view/audio/blog/.';
$_['text_audio_hint'] = 'Somente MP3, OGG ou WAV locais. Esta versão não permite envio nem exclusão de áudio.';
$_['button_audio_select'] = 'Usar este áudio';
$_['error_audio_generic'] = 'Não foi possível carregar a biblioteca de áudio.';

// BDREditor keyboard help
$_['text_block_keyboard_shortcuts_title'] = 'Atalhos de teclado';
$_['text_block_keyboard_shortcuts'] = 'Ctrl/⌘+B negrito · Ctrl/⌘+I itálico · Ctrl/⌘+U sublinhado · Ctrl/⌘+K link · Ctrl/⌘+Z desfazer · Ctrl/⌘+Shift+Z ou Ctrl+Y refazer · Alt+↑/↓ mover bloco · / inserir bloco';
