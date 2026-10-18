<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/webhook — idioma do CRUD de webhooks + log de entregas (pt-BR).
 */

// Headings & texts
$_['heading_title']        = 'Webhooks';
$_['text_list']            = 'Endpoints HTTPS notificados nos eventos do site (pedidos, contato, produtos). Desativar pausa o envio sem perder o histórico.';
$_['text_form']            = 'Cadastre o endpoint que receberá os eventos assinados (HMAC com o secret abaixo).';
$_['text_no_results']      = 'Nenhum webhook ainda. Crie o primeiro.';
$_['text_confirm_delete']  = 'Excluir este webhook? TODO o histórico de entregas dele é removido junto.';
$_['text_enabled']         = 'Ativo';
$_['text_disabled']        = 'Inativo';
$_['text_trigger_hint']    = 'Sintaxe de evento: order_add, order_edit, contact_add, product_* ... curingas casam prefixos de evento.';
$_['text_url_hint']        = 'Apenas HTTPS — o payload assinado viaja protegido.';
$_['text_secret_hint']     = 'Secret HMAC usado para assinar cada entrega (16 a 64 caracteres). Mantenha-o privado.';
$_['text_status_hint']     = 'Endpoints inativos mantêm o histórico, mas não recebem novas entregas.';
$_['text_logs_all']        = 'Todas as entregas';
$_['text_logs_of']         = 'Entregas de %s (%s)';
$_['text_logs_hint']       = 'Entregas mais recentes (até 200). 2xx = sucesso; fora disso = candidato à fila de retry.';
$_['text_no_logs']         = 'Nenhuma entrega ainda.';
$_['text_log_detail']      = 'Detalhe da entrega (somente leitura).';
$_['text_success_add']     = 'Webhook criado.';
$_['text_success_edit']    = 'Webhook atualizado.';
$_['text_success_delete']  = 'Webhook excluído.';

// Columns
$_['column_trigger']       = 'Evento';
$_['column_url']           = 'URL';
$_['column_secret']        = 'Secret';
$_['column_description']   = 'Descrição';
$_['column_status']        = 'Status';
$_['column_deliveries']    = 'Entregas';
$_['column_delivery']      = 'Entrega';
$_['column_http_code']     = 'HTTP';
$_['column_duration']      = 'Duração';
$_['column_attempt']       = 'Tentativa';
$_['column_date_added']    = 'Data';
$_['column_action']        = 'Ação';

// Entries
$_['entry_trigger']        = 'Evento (trigger)';
$_['entry_url']            = 'URL do endpoint';
$_['entry_secret']         = 'Secret HMAC';
$_['entry_description']    = 'Descrição';
$_['entry_status']         = 'Ativo';
$_['entry_delivery']       = 'ID da entrega';
$_['entry_http_code']      = 'Código HTTP';
$_['entry_duration']       = 'Duração';
$_['entry_attempt']        = 'Tentativa';
$_['entry_date_added']     = 'Entregue em';
$_['entry_payload']        = 'Payload';

// Buttons
$_['button_add']           = 'Adicionar';
$_['button_edit']          = 'Editar';
$_['button_delete']        = 'Excluir';
$_['button_save']          = 'Salvar';
$_['button_cancel']        = 'Cancelar';
$_['button_logs']          = 'Entregas';
$_['button_view']          = 'Ver';
$_['button_back']          = 'Voltar aos webhooks';
$_['button_back_logs']     = 'Voltar às entregas';
$_['button_generate']      = 'Gerar';

// Errors
$_['error_trigger']        = 'Evento inválido: use a sintaxe de evento em minúsculas (ex.: order_add, order_*, contact_add), até 64 caracteres.';
$_['error_url_https']      = 'A URL deve começar com https:// (até 255 caracteres).';
$_['error_secret']         = 'O secret deve ter de 16 a 64 caracteres.';
$_['error_description']    = 'A descrição é obrigatória (1 a 255 caracteres).';
$_['error_duplicate']      = 'Este par evento + URL já existe (o par precisa ser único).';
