<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/login (ADR 0005): muralha de sessão do painel.
 * Monta o serviço user PREGUIÇOSAMENTE (o front controller é imutável por
 * app — Contrato §2) e redireciona tudo que exige login, exceto as rotas
 * públicas do próprio fluxo de autenticação.
 */
namespace Reamur\Admin\Controller\Startup;

class Login extends \Reamur\System\Engine\Controller {

    /** Rotas que não exigem sessão ativa */
    private const PUBLIC_ROUTES = ['common/login', 'common/logout'];

    /** Muralha: sem sessão válida → common/login (redireciona e encerra) */
    public function index(): void {
        if (!$this->registry->has('user')) {
            $this->registry->set('user', new \Reamur\System\Library\User($this->registry));
        }

        // Fonte única da rota (ADR 0006): _route (via config) cobre URL limpa;
        // ?route= dropdown para harnesses CLI/cron que pulam o framework
        $route = (string)$this->config->get('_route', '');

        if ($route === '') {
            $route = (string)($this->request->get['route'] ?? '');
        }

        if ($route === '') {
            $route = (string)$this->config->get('action_default', 'common/dashboard');
        }

        if (!in_array($route, self::PUBLIC_ROUTES, true) && !$this->user->isLogged()) {
            $this->response->redirect($this->url->link('common/login'));
        }

        // Anti-loop SOMENTE no formulário: common/logout PRECISA executar com sessão ativa
        if ($route === 'common/login' && $this->user->isLogged()) {
            $this->response->redirect($this->url->link('common/dashboard'));
        }
    }
}
