<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Contrato de meio de pagamento (ADR 0097, EX-03).
 *
 * Um meio de pagamento responde a três perguntas e mais nada:
 *
 *   1. blockedBy()      Está pronto para cobrar?
 *   2. charge()         Crie a cobrança deste pedido.
 *   3. notification()   Este aviso é verdadeiro? De qual pagamento ele fala?
 *
 * O que vale para qualquer meio fica no Checkout, uma vez só: a chave do
 * link do cliente, o reaproveitamento de link pendente, o aviso repetido, a
 * mudança de situação do pedido, o histórico e o evento de pedido pago. Um
 * meio não grava em pedido, não escreve histórico e não responde HTTP.
 */
namespace Reamur\System\Library\Payment;

interface Method {
    /**
     * Nome curto e fixo do meio: letras minúsculas, dígitos e `_`.
     * Aparece no começo das linhas de log ("<code> notify: ...").
     */
    public function code(): string;

    /**
     * Pronto para cobrar? '' = sim. Senão, o motivo em uma palavra-chave do
     * próprio meio (ex.: `no_seller`). A tela do meio traduz a palavra-chave;
     * o Checkout só a repassa e não chama charge().
     */
    public function blockedBy(): string;

    /**
     * Cria a cobrança no provedor. Não grava nada no banco do CMS.
     * Falha (rede, recusa do provedor) volta como Charge::failed(), sem exceção.
     */
    public function charge(ChargeRequest $request): Charge;

    /**
     * Lê um aviso recebido do provedor e diz o que ele vale. A decisão é do
     * meio: conferir assinatura, consultar o provedor, achar o pedido. O
     * corpo do aviso, sozinho, nunca é prova de pagamento.
     */
    public function notification(Notification $notification): Verdict;
}
