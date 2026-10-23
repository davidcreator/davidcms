<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * communication/contact_message — idioma da caixa de contato (pt-BR).
 * Semântica do status conforme Dicionário-de-Dados §9: 0 novo / 1 lido / 2 respondido.
 */

// Headings & texts
$_['heading_title']         = 'Mensagens de Contato';
$_['text_list']             = 'Mensagens enviadas pelo formulário de contato do site. As respostas saem por e-mail — marque a mensagem como Respondido quando concluir.';
$_['text_no_results']       = 'Nenhuma mensagem ainda.';
$_['text_empty_filter']     = 'Nenhuma mensagem com este status.';
$_['text_confirm_delete']   = 'Excluir esta mensagem permanentemente?';
$_['text_message_detail']   = 'Conteúdo somente leitura. O botão Ler da lista marca mensagens novas como Lido quando há permissão de modificar.';
$_['text_actions']          = 'Ações';
$_['text_all']              = 'Todas';
$_['text_new']              = 'Novo';
$_['text_read']             = 'Lido';
$_['text_replied']          = 'Respondido';
$_['text_success_status']   = 'Status da mensagem atualizado.';
$_['text_success_delete']   = 'Mensagem excluída.';

// Columns
$_['column_name']           = 'Nome';
$_['column_email']          = 'E-mail';
$_['column_subject']        = 'Assunto';
$_['column_status']         = 'Status';
$_['column_date_added']     = 'Recebida';
$_['column_action']         = 'Ação';

// Entries (read view)
$_['entry_name']            = 'Nome';
$_['entry_email']           = 'E-mail';
$_['entry_telephone']       = 'Telefone';
$_['entry_subject']         = 'Assunto';
$_['entry_message']         = 'Mensagem';
$_['entry_status']          = 'Status';
$_['entry_ip']              = 'IP';
$_['entry_date_added']      = 'Recebida em';

// Buttons
$_['button_read']           = 'Ler';
$_['button_delete']         = 'Excluir';
$_['button_back']           = 'Voltar à lista';
$_['button_reply']          = 'Responder por e-mail';
$_['button_mark_replied']   = 'Marcar como Respondido';
$_['button_mark_read']      = 'Marcar como Lido';
$_['button_mark_new']       = 'Marcar como Novo';

// Errors
$_['error_status']          = 'Status inválido (permitidos: Novo, Lido, Respondido).';

// Fase 8 / Tarefa 10 — caixa, busca/paginação e leitura POST+ACL+CSRF.
$_['text_empty_title'] = 'Nenhuma mensagem encontrada';
$_['text_empty_hint'] = 'Ajuste a busca ou o filtro de status.';
$_['text_search_placeholder'] = 'Buscar nome, e-mail ou assunto…';
$_['text_showing'] = 'Exibindo %d–%d de %d mensagens';
$_['text_readonly_notice'] = 'Você pode visualizar; alterar status ou excluir exige permissão de modificar.';
$_['button_filter'] = 'Filtrar';
$_['button_reset'] = 'Limpar';
$_['button_close'] = 'Fechar';
$_['button_first_page'] = '« Primeira';
$_['button_last_page'] = 'Última »';
$_['button_next_page'] = 'Próxima ›';
$_['button_previous_page'] = '‹ Anterior';
$_['error_csrf'] = 'Token de sessão inválido. Recarregue a lista e tente novamente.';
