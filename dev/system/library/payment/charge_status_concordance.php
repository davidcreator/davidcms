<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * ADR 0121 (R1) — as duas fontes de situação de uma cobrança.
 *
 * `jow_charge.status` é o que o operador digitou no painel. O ledger
 * (`payment_transaction.status`) é o que o provedor informou. São vocabulários
 * diferentes, com propósitos diferentes, e a tela mostrava só o segundo para
 * cobrança de gateway — então o que o operador digitava desaparecia sem aviso.
 *
 * Esta classe não traduz um vocabulário no outro: ela responde apenas "as duas
 * frases podem descrever o mesmo fato?". Divergir não é erro — o operador pode
 * ter marcado `partial` porque estornou parte no painel do provedor. O que não
 * pode é a divergência existir em silêncio.
 *
 * Nada aqui move dinheiro nem altera registro: é leitura.
 */
final class ChargeStatusConcordance {
    /**
     * Para cada situação do provedor, as palavras do painel que não a
     * contradizem. Lista fechada de propósito: situação desconhecida no
     * provedor nunca "concorda" com nada, e a tela avisa.
     *
     * @var array<string, list<string>>
     */
    private const CONCORDANT = [
        'pending'            => ['pending'],
        'requires_action'    => ['pending'],
        'authorized'         => ['pending', 'paid'],
        'paid'               => ['paid'],
        'partially_refunded' => ['partial', 'refunded'],
        'refunded'           => ['refunded'],
        'disputed'           => ['disputed'],
        'failed'             => ['canceled', 'expired'],
        'cancelled'          => ['canceled', 'expired'],
        'expired'            => ['expired', 'canceled'],
    ];

    /**
     * Verdadeiro quando há divergência a mostrar. Sem uma das duas situações
     * não há o que comparar: cobrança manual não tem situação de provedor, e
     * cobrança de gateway recém-criada pode ainda não ter nenhuma.
     */
    public static function diverges(string $operatorStatus, string $providerStatus): bool {
        if ($providerStatus === '' || $operatorStatus === '') {
            return false;
        }

        return !in_array($operatorStatus, self::CONCORDANT[$providerStatus] ?? [], true);
    }

    /** A situação do provedor que a tela deve exibir: a do provedor, ou a digitada. */
    public static function displayed(string $operatorStatus, string $providerStatus): string {
        return $providerStatus !== '' ? $providerStatus : $operatorStatus;
    }
}
