<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Resposta do meio de pagamento a "crie a cobrança" (ADR 0097).
 */
namespace Reamur\System\Library\Payment;

final class Charge {
    private function __construct(
        public readonly bool $ok,
        public readonly string $reference,
        public readonly string $url,
        public readonly float $fee,
        public readonly int $account,
        public readonly string $currency,
        public readonly string $error,
    ) {}

    /**
     * Cobrança criada.
     *
     * @param string $reference identificador da cobrança no provedor
     * @param string $url       endereço em que o cliente paga
     * @param float  $fee       parte do valor que fica com a plataforma (0 se não houver)
     * @param int    $account   conta do provedor que recebe (0 se o meio tem uma só)
     * @param string $currency  moeda cobrada (código de 3 letras)
     */
    public static function created(string $reference, string $url, float $fee = 0.0, int $account = 0, string $currency = 'BRL'): self {
        return new self(true, $reference, $url, $fee, $account, $currency, '');
    }

    /** Cobrança não criada. $error é o texto que a tela mostra a quem pediu o link. */
    public static function failed(string $error): self {
        return new self(false, '', '', 0.0, 0, '', $error);
    }
}
