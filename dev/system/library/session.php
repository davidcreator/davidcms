<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Sessão com driver plugável (Arquitetura-MVCL §3: library/session/{db,file,redis}).
 * Estratégia: handler nativo do PHP via session_set_save_handler($adaptor) e
 * sincronização com $_SESSION no shutdown — controllers usam $this->session->data
 * (array próprio estável, sem proxy de referência frágil).
 */
namespace Reamur\System\Library;

final class Session {
    /** @var array<string, mixed> Dados da sessão (lidam/escrevem em memória) */
    public array $data = [];

    /**
     * @param object $adaptor Driver com métodos open/close/read/write/destroy/gc
     * @param array<string, mixed> $options name, path, domain, samesite, expire, probability, divisor
     */
    public function __construct(private object $adaptor, private array $options = []) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->data = $_SESSION ?? [];

            return;
        }

        session_name($options['name'] ?? 'RMSSESSION');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $options['path'] ?? '/',
            'domain'   => $options['domain'] ?? '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => $options['samesite'] ?? 'Lax',
        ]);

        ini_set('session.gc_maxlifetime', (string)($options['expire'] ?? 86400));
        ini_set('session.gc_probability', (string)($options['probability'] ?? 1));
        ini_set('session.gc_divisor', (string)($options['divisor'] ?? 100));
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_save_handler($this->adaptor, true);
        session_start();

        $this->data = $_SESSION ?? [];

        register_shutdown_function(function (): void {
            $this->close();
        });
    }

    public function getId(): string {
        return session_id();
    }

    /** Regrava os dados na sessão nativa e fecha (chamado no shutdown) */
    public function close(): void {
        $_SESSION = $this->data;

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /** Nova identidade (pós-login) preservando os dados */
    public function regenerateId(): void {
        $_SESSION = $this->data;
        session_regenerate_id(true);
    }

    public function destroy(): void {
        $_SESSION = $this->data = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
