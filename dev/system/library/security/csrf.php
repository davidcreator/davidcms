<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * CSRF por sessão (ADR 0018): token estável guardado em $_SESSION e exigido
 * como campo oculto 'csrf_token' em todo formulário que altera estado.
 * Não substitui validação de input — é a barreira de origem da requisição.
 */
namespace Reamur\System\Library\Security;

final class Csrf {

    /** Token da sessão; cria lazily (32 bytes hex) se inexistente */
    public static function token(object $session): string {
        if (empty($session->data['csrf_token']) || !is_string($session->data['csrf_token'])) {
            $session->data['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$session->data['csrf_token'];
    }

    /** Comparação em tempo constante; token vazio/ausente nunca passa */
    public static function check(object $session, mixed $token): bool {
        $expected = (string)($session->data['csrf_token'] ?? '');

        return $expected !== ''
            && is_string($token)
            && hash_equals($expected, $token);
    }
}
