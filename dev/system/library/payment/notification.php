<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Aviso recebido de um provedor de pagamento, como chegou (ADR 0097).
 * Nada aqui é confiável: é o meio de pagamento que confere.
 */
namespace Reamur\System\Library\Payment;

final class Notification {
    /**
     * @param array<array-key, mixed> $get    parâmetros do endereço
     * @param array<array-key, mixed> $post   campos de formulário
     * @param array<array-key, mixed> $server variáveis do servidor (cabeçalhos em HTTP_*)
     * @param string                  $body   corpo bruto da requisição
     */
    public function __construct(
        public readonly array $get = [],
        public readonly array $post = [],
        public readonly array $server = [],
        public readonly string $body = '',
    ) {}

    /** Cabeçalho pelo nome ("x-signature"); '' quando ausente ou não for texto. */
    public function header(string $name): string {
        $value = $this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '';

        return is_scalar($value) ? (string)$value : '';
    }
}
