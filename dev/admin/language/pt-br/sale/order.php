<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * sale/order — Idiomas do módulo Pedidos/Orçamentos (pt-BR; en-US canônico no ADR 0001).
 */

// Headings & texts
$_['heading_title']        = 'Pedidos e Orçamentos';
$_['text_list']            = 'Pedidos de serviço e solicitações de orçamento. Os totais são recalculados ao salvar (quantidade × preço por item).';
$_['text_no_results']      = 'Nenhum pedido ainda. Crie o primeiro.';
$_['text_confirm_delete']  = 'Excluir este pedido? Seus itens e o histórico de status serão removidos.';
$_['text_add']             = 'Adicionar Pedido';
$_['text_edit']            = 'Editar Pedido';
$_['text_customer']        = 'Cliente';
$_['text_products']        = 'Itens';
$_['text_data']            = 'Dados do Pedido';
$_['text_history']         = 'Histórico de Status';
$_['text_status_hint']     = 'Cada mudança de status é anexada ao histórico abaixo.';
$_['text_success_add']     = 'Pedido criado.';
$_['text_success_edit']    = 'Pedido atualizado.';
$_['text_success_delete']  = 'Pedido excluído.';
$_['text_no_products']     = 'Nenhum item ainda — adicione a primeira linha.';

// F8-04 — filtros, paginação e proteção CSRF
$_['text_filter'] = 'Filtrar pedidos';
$_['text_all_status'] = 'Todos os status';
$_['text_showing'] = 'Exibindo %s–%s de %s pedidos';
$_['text_no_results_filtered'] = 'Nenhum pedido corresponde a esses filtros.';
$_['text_close'] = 'Fechar';
$_['text_page_first'] = '« Primeiro';
$_['text_page_last'] = 'Último »';
$_['text_page_next'] = 'Próximo ›';
$_['text_page_prev'] = '‹ Anterior';
$_['entry_filter_order_id'] = 'Número do pedido';
$_['entry_filter_customer'] = 'Cliente';
$_['entry_filter_email'] = 'E-mail';
$_['button_filter'] = 'Aplicar filtros';
$_['button_reset'] = 'Limpar';
$_['error_csrf'] = 'O token de segurança é inválido ou expirou. Atualize a página e tente novamente.';

// Columns
$_['column_order']         = 'Pedido';
$_['column_customer']      = 'Cliente';
$_['column_email']         = 'E-mail';
$_['column_total']         = 'Total';
$_['column_status']        = 'Status';
$_['column_date_added']    = 'Adicionado';
$_['column_date_modified'] = 'Modificado';
$_['column_action']        = 'Ação';
$_['column_comment']       = 'Comentário';

// Entries
$_['entry_firstname']  = 'Nome';
$_['entry_lastname']   = 'Sobrenome';
$_['entry_email']      = 'E-mail';
$_['entry_telephone']  = 'Telefone / WhatsApp';
$_['entry_product']    = 'Item (serviço/projeto)';
$_['entry_quantity']   = 'Qtd';
$_['entry_price']      = 'Preço Unitário';
$_['entry_total']      = 'Total da Linha';
$_['entry_comment']    = 'Briefing / Observações';
$_['entry_status']     = 'Status';

// Buttons
$_['button_add']          = 'Adicionar';
$_['button_edit']         = 'Editar';
$_['button_delete']       = 'Excluir';
$_['button_mp_paylink'] = '💳 Link de pagamento MP'; // F5-03 (ADR 0035)
$_['button_save']         = 'Salvar';
$_['button_cancel']       = 'Cancelar';
$_['button_add_product']  = 'Adicionar Item';
$_['button_remove']       = 'Remover';

// Errors
$_['error_firstname']     = 'O nome é obrigatório (1 a 32 caracteres).';
$_['error_email']         = 'Um e-mail válido é obrigatório (até 96 caracteres).';
$_['error_product']       = 'Linha de item incompleta: preencha o nome do item ou limpe a linha.';
$_['error_status']        = 'Status selecionado inválido.';
$_['error_permission']    = 'Você não tem permissão para modificar os pedidos.';
