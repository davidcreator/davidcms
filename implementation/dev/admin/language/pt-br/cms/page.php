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

// Fluxo de edição em página exclusiva para conteúdo BDREditor.
$_['text_page_context_page'] = 'Página';
$_['text_page_context_language'] = 'Idioma';
$_['text_page_context_untitled'] = 'Página sem título';
$_['text_page_context_save_hint'] = 'Salvar aqui usa o formulário e as validações existentes.';
$_['text_page_context_readonly_hint'] = 'Modo de consulta: esta página não pode ser salva por este usuário.';
$_['text_content_summary_note'] = 'Resumo do conteúdo. Para editar texto e blocos, abra o editor dedicado.';
$_['text_content_empty'] = 'Ainda não há conteúdo nesta página.';
$_['text_content_save_first'] = 'Salve os dados gerais para liberar o editor.';
$_['text_content_block_count'] = '%d blocos de conteúdo';
$_['text_page_content_editor'] = 'Editor de conteúdo da página';
$_['text_content_editor_help'] = 'Edite o conteúdo por idioma. Os dados gerais, SEO e status permanecem no formulário da página.';
$_['button_edit_content'] = 'Editar conteúdo';
$_['button_save_content'] = 'Salvar conteúdo';
$_['text_content_invalid'] = 'O conteúdo armazenado precisa ser restaurado antes de editar.';
$_['text_editor_home'] = 'Início';
$_['text_editor_insert'] = 'Inserir';
$_['text_editor_view'] = 'Exibir';
$_['text_editor_clipboard'] = 'Área de transferência';
$_['text_editor_text_format'] = 'Formatação de texto';
$_['text_editor_paragraph_alignment'] = 'Alinhamento';
$_['text_editor_group_insert'] = 'Inserir conteúdo';
$_['text_editor_group_panels'] = 'Painéis';
$_['text_editor_group_preview'] = 'Pré-visualização';
$_['text_editor_close_panel'] = 'Fechar painel';
$_['text_editor_align_left'] = 'Alinhar à esquerda';
$_['text_editor_align_center'] = 'Centralizar';
$_['text_editor_align_right'] = 'Alinhar à direita';
$_['text_editor_import_file'] = 'Importar arquivo';
$_['text_editor_import_success'] = 'Conteúdo importado para o rascunho local: ';
$_['text_editor_preview_title'] = 'Prévia do conteúdo';
$_['text_editor_preview_mode'] = 'Pré-visualizar';
$_['text_editor_edit_mode'] = 'Voltar à edição';
$_['text_editor_import_hint'] = 'Importação local; nenhum arquivo é enviado ao servidor. Mídias só são referenciadas se já estiverem na biblioteca permitida.';
$_['text_editor_import_unavailable'] = 'Importador indisponível.';
$_['text_editor_import_audio_library'] = 'MP3, OGG e WAV não são importados como documentos. Insira um bloco Áudio e selecione ou envie o áudio pela biblioteca do Blog.';
$_['text_editor_import_error'] = 'Falha ao importar arquivo.';
$_['text_editor_preview_video_link'] = 'Vídeo incorporado';
$_['text_editor_preview_video_open'] = 'Abrir em nova aba';

$_['text_block_type_video_file'] = 'Vídeo local';

$_['text_block_video_file_source'] = 'Caminho local do vídeo';

$_['text_block_video_file_choose'] = 'Selecionar ou enviar vídeo';

$_['text_editor_import_video_library'] = 'MP4, WebM e OGV não são importados como documentos. Insira um bloco Vídeo local e selecione ou envie o vídeo pela biblioteca do Blog.';

$_['text_block_type_threejs_thumb'] = 'Thumb 3D (Three.js)';

$_['text_block_threejs_label'] = 'Rótulo acessível';

$_['text_block_threejs_scene_config'] = 'Configuração da cena (JSON)';

$_['text_block_threejs_config_hint'] = 'Cena em JSON declarativo, com limites rígidos. JavaScript personalizado opcional fica no campo separado e roda em sandbox isolado. Não use URLs, texturas ou recursos externos; Three.js é local.';

$_['text_block_threejs_preview_hint'] = 'Na Prévia completa do editor e na thumb pública, scripts personalizados rodam somente em iframe sandbox opaco e isolado. O cartão do bloco não os executa.';

$_['text_block_threejs_objects'] = 'Formas na cena';

$_['text_block_threejs_lights'] = 'Iluminação';

$_['text_block_threejs_addShape'] = 'Adicionar forma';

$_['text_block_threejs_addLight'] = 'Adicionar luz';

$_['text_block_threejs_remove'] = 'Remover';

$_['text_block_threejs_color'] = 'Cor';

$_['text_block_threejs_animation'] = 'Animação';

$_['text_block_threejs_intensity'] = 'Intensidade';

$_['text_block_threejs_invalidScene'] = 'JSON inválido ou fora dos limites. Corrija a configuração para editar a cena.';

$_['text_block_threejs_sceneConfig'] = 'JSON da cena (avançado)';

$_['text_block_threejs_scriptLabel'] = 'JavaScript personalizado (sandbox)';

$_['text_block_threejs_scriptHint'] = 'Cole somente JavaScript puro, sem as tags <script>. O código executa numa iframe isolada, sem acesso ao DOM/cookies do CMS e com CSP bloqueando rede/recursos externos. API: THREE, scene, camera, renderer, canvas e onFrame(fn). Limite: 8 KiB. Código pode travar esta thumb; use apenas scripts confiáveis. Exemplo: const mesh = new THREE.Mesh(new THREE.TorusGeometry(.8,.2,16,48), new THREE.MeshStandardMaterial({color:0xff66aa})); scene.add(mesh); onFrame(({delta}) => { mesh.rotation.y += delta; });';

$_['text_block_threejs_editInline'] = 'Editar thumb e script';

$_['text_block_threejs_closeInline'] = 'Fechar edição';

$_['text_block_threejs_deleteScript'] = 'Excluir script';

$_['text_block_threejs_scriptDeleted'] = 'Script da thumb excluído. Use Desfazer para restaurá-lo.';

$_['text_block_threejs_scriptPlaceholder'] = 'Adicione JavaScript para modificar a cena Three.js…';

$_['text_block_threejs_addScript'] = 'Editar thumb / adicionar script';

// CMS Pages private draft preview.
$_['text_page_preview_button'] = 'Abrir prévia';
$_['text_page_preview_title'] = 'Prévia privada da página';
$_['text_page_preview_notice'] = 'Prévia privada do conteúdo ainda não salvo. Esta visualização isolada não inclui o cabeçalho, rodapé ou campos personalizados completos do Catalog.';
$_['text_page_preview_untitled'] = 'Página sem título';
$_['error_page_preview_permission'] = 'Você não tem permissão para visualizar Páginas do CMS.';
$_['error_page_preview_method'] = 'A prévia exige uma requisição POST.';
$_['error_page_preview_csrf'] = 'Token CSRF inválido ou expirado.';
$_['error_page_preview_document'] = 'O documento de blocos é inválido ou excede o tamanho permitido.';
$_['error_page_preview_unavailable'] = 'A prévia não está disponível.';
// CMS Pages image library (block media only; no Page entity image field).
$_['text_page_media_library'] = 'Biblioteca de imagens das Páginas';
$_['text_page_media_empty'] = 'Nenhuma imagem encontrada.';
$_['text_page_media_loading'] = 'Carregando imagens…';
$_['text_page_media_search'] = 'Buscar por nome';
$_['text_page_media_upload_hint'] = 'JPEG, PNG, WebP ou GIF; máximo 2 MiB. Biblioteca exclusiva de Páginas.';
$_['text_page_media_uploading'] = 'Enviando imagem…';
$_['text_page_media_select'] = 'Selecionar';
$_['text_page_media_page'] = 'Página %1 de %2';
$_['text_page_media_upload'] = 'Enviar imagem';
$_['text_page_media_error'] = 'Não foi possível concluir a operação de imagem.';
$_['error_page_media_permission'] = 'Você não tem permissão para acessar a biblioteca de imagens de Páginas.';
$_['error_page_media_method'] = 'Método HTTP inválido.';
$_['error_page_media_csrf'] = 'Token CSRF inválido ou expirado.';
$_['error_page_media_list'] = 'Não foi possível listar as imagens.';
$_['error_page_media_no_file'] = 'Selecione uma imagem para enviar.';
$_['error_page_media_size'] = 'A imagem deve ter no máximo 2 MiB.';
$_['error_page_media_type'] = 'Formato inválido. Use JPEG, PNG, WebP ou GIF.';
$_['error_page_media_root'] = 'A pasta exclusiva de imagens de Páginas não está disponível.';
$_['error_page_media_upload'] = 'Falha ao enviar a imagem.';

// F10-01 — páginas públicas do Catalog vinculadas ao CMS.
$_['text_system_page'] = 'Página do Catalog';
$_['text_system_page_help'] = 'Esta página está vinculada a uma rota pública do Catalog. A chave da rota é fixa; edite o conteúdo e os metadados aqui.';
$_['error_system_page_delete'] = 'Esta página do sistema não pode ser excluída. Você pode desativá-la ou editar seu conteúdo.';
