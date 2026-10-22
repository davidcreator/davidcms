<?php
/**
 * blog/import — Importador WordPress (F7-03, ADR 0044) — pt-br
 */

// Heading
$_['heading_title']            = 'Importar do WordPress';

// Text
$_['text_form_intro']          = 'Lê a API pública do WP, baixa as imagens destacadas e materializa os posts no blog interno. Idempotente: slugs já importados viram "pular"; colisões de keyword ganham sufixo -2/-3. Âncora de idioma: todo post cai em en-US (o coalesce o torna visível nos três idiomas).';
$_['text_preview']             = 'Pré-visualizar (sem gravar)';
$_['text_run']                 = 'Executar importação';
$_['text_confirm']             = 'Importar de verdade e gravar os posts no banco?';
$_['text_summary_header']      = 'Relatório';
$_['text_summary_preview']     = 'Prévia: %d post(s) seriam importados (%d já pulados, %d com sufixo de keyword).';
$_['text_summary_run']         = "Importação: %d importados (%d com sufixo), %d pulados, %d erros.\n%s";
$_['text_map_written']         = 'Mapa 301 gravado (%d bytes) em system/storage/cache/wp-301-map.txt';
$_['text_map_failed']          = 'Atenção: o mapa de redirect não pôde ser gravado (permissões do storage).';
$_['text_actions']             = 'Importar';
$_['text_action_imported']     = 'Importado';
$_['text_action_import_with_suffix'] = 'Importar com sufixo';
$_['text_action_skip']         = 'Pular';
$_['text_action_error']        = 'Erro';
$_['text_log_header']          = 'Log da operação';
$_['text_no_rows']             = 'Nenhum post retornado nesta página.';
$_['text_imported_edit']       = 'editar';

// Entry
$_['entry_base_url']           = 'URL base do WordPress';
$_['entry_page']               = 'Página';
$_['entry_per_page']           = 'Por página';
$_['entry_public_url']         = 'URL pública do catálogo';

// Help
$_['help_base_url']            = 'O endpoint público /wp-json/ precisa estar acessível. Nenhuma credencial é enviada.';
$_['help_page']                = 'Paginação do WP REST (X-WP-TotalPages). Comece em 1.';
$_['help_per_page']            = 'No máximo 100 por chamada (limite duro da API do WP).';
$_['help_public_url']          = 'Base do mapa 301 (URLs absolutas do blog NOVO). Derivada do HTTP_SERVER; ajuste em topologias exóticas.';

// Column
$_['column_wp_id']             = 'ID WP';
$_['column_title']             = 'Título';
$_['column_date']              = 'Data';
$_['column_keyword']           = 'Keyword';
$_['column_action_taken']      = 'Ação';
$_['column_image']             = 'Imagem';
$_['column_status_label']      = 'Status';

// Button
$_['button_back_list']         = 'Voltar ao blog';
$_['button_submit']            = 'Ir';

// Error
$_['error_action']             = 'Ação desconhecida (esperado preview ou run).';
$_['error_base_url']           = 'A URL base é obrigatória.';
$_['error_fetch']              = 'Falha ao ler o endpoint do WordPress: %s';

// F8-02 (ADR 0046): cards
$_['text_source_card']     = 'Fonte';
$_['text_pagination_card'] = 'Janela da amostra';
