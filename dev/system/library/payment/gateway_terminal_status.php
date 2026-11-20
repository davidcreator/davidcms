<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * ADR 0124: transição de estado que o provedor exige, mesmo quando o ledger
 * ainda não passou pelo estado intermediário.
 *
 * O caso real: o dinheiro entrou e voltou dentro da janela de reconciliação
 * (estorno total logo depois do pagamento, comum em teste de cartão e em
 * cobrança duplicada cancelada no mesmo dia). A tentativa ainda está em
 * `requires_action` no ledger — o aviso de `paid` nunca chegou — e o provedor
 * afirma `refunded`.
 *
 * Recusar por `unsafe_status_transition` deixaria a tentativa nesse estado
 * **para sempre**: a fila aberta a consultaria a cada ciclo, o provedor
 * responderia `refunded`, e nada seria gravado. O dinheiro já voltou; o que
 * falta é o registro.
 *
 * Isto abre **só** o caminho `→ refunded`. Estorno parcial continua exigindo
 * `paid` (parcial de pagamento que o CMS nunca viu pago não tem como ser
 * conciliado contra um valor que não está registrado), e nada aqui move
 * dinheiro: é a mesma transição que o painel do provedor já executou.
 */
final class GatewayTerminalStatus {
    /** Estado em que a tentativa é dada por encerrada, sem volta possível. */
    public const SETTLED = GatewayPaymentStatus::REFUNDED;

    public static function canTransition(string $from, string $to): bool {
        if (GatewayPaymentStatus::canTransition($from, $to)) {
            return true;
        }

        if ($to !== self::SETTLED) {
            return false;
        }

        // Um estorno total é fato consumado no provedor. Os estados daqui são
        // os que ainda admitem `paid` como próximo passo: se o pagamento não
        // chegou a ser registrado, ele chegou a acontecer.
        return in_array($from, [
            GatewayPaymentStatus::PENDING,
            GatewayPaymentStatus::REQUIRES_ACTION,
            GatewayPaymentStatus::AUTHORIZED,
        ], true);
    }
}
