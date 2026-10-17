<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/permission (ADR 0005): ACL por grupo (ção/deny por rota).
 * Roda APÓS startup/login, que garante sessão válida (ou já redirecionou).
 * Regra: rota liberada = rota no ADR-allowlist de bootstrap OU rota presente
 * no JSON permission["access"] do user_group; caso contrário → error/permission (403).
 */
namespace Reamur\Admin\Controller\Startup;

class Permission extends \Reamur\System\Engine\Controller {

    /**
     * Allowlist mínima de bootstrap (ADR 0005 §Regra): sem ela um painel com
     * permissões zeradas (seed inicial) trava o próprio login e a home padrão.
     */
    private const ALLOWED_ROUTES = [
        'common/login',
        'common/logout',
        'common/dashboard',
        'error/not_found',
        'error/permission',
    ];

    public function index(): void {
        if (!$this->user instanceof \Reamur\System\Library\User || !$this->user->isLogged()) {
            return; // muralha de login já tratou
        }

        $route = (string)$this->config->get('_route', '');

        if ($route === '') {
            $route = (string)($this->request->get['route'] ?? '');
        }

        if ($route === '') {
            $route = (string)$this->config->get('action_default', 'common/dashboard');
        }

        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        // Granularidade base-route (Adendo F2-6 do ADR 0005): 'user/user.add|3',
        // 'user/user.delete' etc. herdam a ACL do arquivo 'user/user'. Um grant
        // cobre TODOS os métodos do controller — métodos com efeito colateral
        // verificam adicionalmente hasPermission('modify', ...).
        $route = (string)(preg_split('/[.|]/', $route, 2)[0] ?? $route);

        if ($route === '' || in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        // Rota inexistente NÃO é negação de permissão: deixa o dispatcher entregar o 404
        if (!is_file(DIR_APPLICATION . 'controller/' . $route . '.php')) {
            return;
        }

        if (!$this->user->hasPermission('access', $route)) {
            $this->response->redirect($this->url->link('error/permission'));
        }
    }
}
