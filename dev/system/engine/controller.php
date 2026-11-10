<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Classe base de todo controller (Arquitetura-MVCL §6).
 * Serviços e models chegam via propriedade mágica a partir do Registry:
 * $this->request · $this->load · $this->model_grupo_modulo · ...
 */
namespace Reamur\System\Engine;

/**
 * @property \Reamur\System\Engine\Config  $config
 * @property \Reamur\System\Library\Request $request
 * @property \Reamur\System\Library\Session $session
 */
class Controller {
    public function __construct(protected Registry $registry) {}

    /** Acesso preguiçoso a qualquer serviço registrado (load, request, model_*...) */
    public function __get(string $key): mixed {
        return $this->registry->get($key);
    }

    /** Valida o token CSRF enviado via POST contra a sessão atual. */
    protected function csrfValid(): bool {
        $session = $this->registry->get('session');
        if (!$session instanceof \Reamur\System\Library\Session) {
            return false;
        }

        $request = $this->registry->get('request');
        $token = ($request instanceof \Reamur\System\Library\Request)
            ? ($request->post['csrf_token'] ?? '')
            : '';

        return \Reamur\System\Library\Security\Csrf::check(
            $session,
            is_scalar($token) ? (string)$token : ''
        );
    }

    /**
     * Lê e limpa a mensagem flash armazenada na sessão.
     *
     * @return array{type: string, text: string}
     */
    protected function flash(): array {
        $session = $this->registry->get('session');
        $data = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $flash = (array)$session->data['flash'];
            $data['type'] = (string)($flash['type'] ?? 'success');
            $data['text'] = (string)($flash['text'] ?? '');
            unset($session->data['flash']);
        }

        return $data;
    }

    /** Grava mensagem flash na sessão para a próxima renderização. */
    protected function setFlash(string $text, string $type = 'success'): void {
        $session = $this->registry->get('session');

        if ($session instanceof \Reamur\System\Library\Session) {
            $session->data['flash'] = ['type' => $type, 'text' => $text];
        }
    }

    /** Lê parâmetro escalar da query string (GET) normalizado com trim. */
    protected function queryValue(string $key): string {
        $request = $this->registry->get('request');
        $value = ($request instanceof \Reamur\System\Library\Request)
            ? ($request->get[$key] ?? '')
            : '';

        return is_scalar($value) ? trim((string)$value) : '';
    }

    /** Base URL pública do catálogo (converte sufixo admin/ em catalog/). */
    protected function catalogUrl(): string {
        $config = $this->registry->get('config');
        $base = ($config instanceof Config) ? (string)$config->get('site_url', '') : '';

        if (str_ends_with($base, 'admin/')) {
            return substr($base, 0, -strlen('admin/')) . 'catalog/';
        }

        return $base;
    }
}
