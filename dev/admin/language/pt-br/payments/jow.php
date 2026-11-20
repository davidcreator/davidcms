<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * payments/jow — JOW-payments (ferramenta interna, KZ-35) + contas próprias multi-gateway.
 */

$_['heading_title']      = 'JOW Payments';
$_['text_home']          = 'Início';
$_['text_internal_note'] = 'Ferramenta INTERNA do ReamurCMS: visível somente no admin, nunca no catálogo público.';
$_['text_new_charge']   = 'Nova cobrança';
$_['text_store']        = 'Dados do recebedor (usados no PIX)';
$_['text_pix_keys']     = 'Chaves PIX';
$_['text_report']       = 'Relatório de recebimentos';
$_['text_charges']      = 'Cobranças (últimas 50)';
$_['entry_description'] = 'Descrição'; $_['entry_amount'] = 'Valor (BRL)'; $_['entry_method'] = 'Método';
$_['entry_payer_email'] = 'E-mail do pagador'; $_['entry_due_date'] = 'Vencimento';
$_['entry_store_name']  = 'Nome do recebedor'; $_['entry_city'] = 'Cidade'; $_['entry_key'] = 'Chave'; $_['entry_holder'] = 'Titular';
$_['opt_card'] = 'Cartão'; $_['opt_boleto'] = 'Boleto';
$_['help_pix'] = 'Cobrança via PIX gera o BR Code (Copia e Cola) automaticamente na listagem.';
$_['col_type']='Tipo'; $_['col_key']='Chave'; $_['col_holder']='Titular'; $_['col_status']='Status';
$_['col_method']='Método'; $_['col_count']='Qtd.'; $_['col_total']='Total'; $_['col_description']='Descrição';
$_['col_amount']='Valor'; $_['col_date']='Data';
$_['text_empty']='Nenhum registro.';
$_['button_charge']='Criar cobrança'; $_['button_save']='Salvar'; $_['button_add']='Adicionar';
$_['button_delete']='Excluir'; $_['button_update']='Atualizar';
$_['text_charge_added']='Cobrança criada (PIX gerado se aplicável).';
$_['text_charge_updated']='Status da cobrança atualizado.';
$_['text_pix_saved']='Chave PIX salva.'; $_['text_pix_deleted']='Chave PIX removida.';
$_['text_store_saved']='Dados do recebedor salvos.';
$_['error_amount']='Informe um valor BRL maior que zero, com até duas casas decimais.'; $_['error_pix_invalid']='Chave PIX inválida.';

// Integração aditiva de gateways via JOW-Payments MultiGateway (Mercado Pago, Stripe, PayPal).
$_['entry_provider'] = 'Provedor';
$_['opt_manual'] = 'Manual / local (fluxo atual)';
$_['help_provider'] = 'No checkout hospedado (Mercado Pago, Stripe ou PayPal), o pagador escolhe o meio de pagamento na página do provedor. As cobranças manuais e o PIX local continuam disponíveis.';
$_['text_gateway_config'] = 'Gateways — contas próprias da plataforma/Reamur';
$_['help_gateway_account_isolation'] = 'Estas credenciais pertencem às contas próprias do JOW: esta conta recebe o valor integral de cada cobrança. O JOW não divide pagamentos nem recebe em nome de terceiros.';
$_['text_gateway_not_configured'] = 'Conta ainda não configurada';
$_['text_gateway_enabled'] = 'Ativo';
$_['text_gateway_disabled'] = 'Desativado';
$_['entry_gateway_environment'] = 'Ambiente';
$_['text_sandbox'] = 'Sandbox / teste';
$_['text_live'] = 'Produção';
$_['entry_secret_clear'] = 'Remover o valor gravado';
$_['entry_gateway_enabled'] = 'Habilitar a conta para novas cobranças JOW';
$_['text_gateway_webhook_url'] = 'URL pública para configurar no app do provedor';
$_['text_gateway_migration_required'] = 'A fundação MultiGateway ainda não está instalada neste banco. Aplique, com autorização, tools/upgrade_jow_payments_multigateway_core.sql; esta integração não executa migrações automaticamente.';
$_['text_gateway_load_error'] = 'Não foi possível carregar a configuração MultiGateway. Verifique o log e a instalação do SQL aditivo.';
$_['text_gateway_credentials_locked'] = 'As credenciais gravadas não podem ser abertas com a chave atual do config.php. Restaure a chave correta ou informe novamente os segredos.';
$_['text_live_gate_blocked'] = 'Produção está bloqueada pelo padrão seguro. Para permitir cobranças reais, configure explicitamente REAMUR_PAYMENTS_ALLOW_LIVE=true no config.php.';
$_['text_gateway_account_saved'] = 'Configuração do gateway salva; novas cobranças continuam desativadas.';
$_['text_gateway_account_enabled'] = 'Conta do gateway habilitada para novas cobranças JOW.';
$_['text_gateway_link_ready'] = 'Link de checkout criado ou reutilizado. Consulte a coluna Checkout nas cobranças.';
$_['text_gateway_link_reused'] = 'O link de pagamento já existia para este pedido; o mesmo link foi mantido.';
$_['text_order_gateway_link'] = 'Link de pagamento de pedido (ledger novo)';
$_['help_order_gateway_link'] = 'Cria a tentativa de pagamento em rms_payment_transaction para o provedor escolhido. O cliente abre o link e paga no provedor; a situação volta pelo aviso assinado ou pela reconciliação.';
$_['text_no_orders'] = 'Nenhum pedido com valor aguardando pagamento.';
$_['text_order_already_paid'] = 'Este pedido já está pago; nenhum link novo foi criado.';
$_['error_order_without_total'] = 'O pedido não tem valor para cobrar.';
$_['text_open_checkout'] = 'Abrir link';
$_['text_no_checkout'] = 'Link ainda não gerado';
$_['col_checkout'] = 'Checkout';
$_['button_gateway_link'] = 'Gerar / reutilizar link';
$_['error_description'] = 'Informe a descrição da cobrança.';
$_['error_method'] = 'Provedor ou método de pagamento inválido.';
$_['error_payer_email'] = 'Informe um e-mail válido para o pagador.';
$_['error_gateway_credentials'] = 'Segredo inválido; confira o tamanho e remova espaços/caracteres de controle.';
$_['error_gateway_credentials_required'] = 'Para habilitar a conta, informe todos os campos de credencial do provedor.';
$_['error_gateway_account_save'] = 'Não foi possível salvar a conta do gateway. A configuração ficou desativada; confira o log, a chave de criptografia e o SQL.';
$_['error_gateway_migration'] = 'A fundação MultiGateway não está instalada. Nenhuma cobrança de gateway foi criada.';
$_['error_gateway_link_failed'] = 'Não foi possível gerar o checkout no provedor. Verifique a conta, o ambiente, a URL HTTPS e a configuração do webhook; a cobrança JOW permanece disponível para nova tentativa.';

// Mercado Pago (Orders API, conta própria).
$_['entry_mp_access_token'] = 'Access Token da conta própria';
$_['help_mp_access_token'] = 'O token fica cifrado no banco. Em Sandbox, use a credencial da conta de vendedor de teste (tokens TEST- não são aceitos pela Orders API); se informar e-mail do pagador, use um endereço @testuser.com. Nunca use a credencial da conta real no ambiente Sandbox. Deixe em branco para manter o valor atual.';
$_['entry_mp_gateway_webhook_secret'] = 'Segredo de assinatura Webhooks — Orders API';
$_['help_mp_gateway_webhook_secret'] = 'Segredo da aplicação que envia eventos Order do Checkout Pro Orders API. É independente de mp_webhook_secret e dos tokens dos vendedores.';

// Stripe (Checkout Session hospedado, conta própria).
$_['entry_stripe_secret_key'] = 'Secret Key da conta própria';
$_['help_stripe_secret_key'] = 'Chave secreta sk_... (recomenda-se restricted key com permissão de Checkout). Fica cifrada no banco; deixe em branco para manter o valor atual.';
$_['entry_stripe_webhook_secret'] = 'Segredo de assinatura Webhooks (whsec_...)';
$_['help_stripe_webhook_secret'] = 'Segredo do endpoint que recebe os eventos checkout.session.* e charge.refunded. Independente dos conectores Stripe Connect do Marketplace.';

// PayPal (Orders API v2, conta própria).
$_['entry_paypal_client_id'] = 'Client ID da conta própria';
$_['help_paypal_client_id'] = 'Client ID das credenciais REST do app. Fica cifrado no banco; deixe em branco para manter o valor atual.';
$_['entry_paypal_client_secret'] = 'Client Secret da conta própria';
$_['help_paypal_client_secret'] = 'Secret das credenciais REST do app, usado para obter o token OAuth2. Fica cifrado no banco.';
$_['entry_paypal_webhook_id'] = 'Webhook ID do app';
$_['help_paypal_webhook_id'] = 'Identificador do webhook cadastrado no app PayPal; a verificação da assinatura usa este ID. Fica cifrado no banco.';
