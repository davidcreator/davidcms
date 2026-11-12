<?php declare(strict_types=1);
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
        'user/profile',
        // F8-V (ADR 0047): "Minha aparência" é preferência PRÓPRIA (mesma classe
        // de rota de user/profile — qualquer usuário logado personaliza o seu
        // painel). A seção GLOBAL da tela verifica hasPermission('modify') no
        // controller — rota permitida ≠ persistência global liberada.
        'setting/appearance',
        'error/not_found',
        'error/permission',
    ];

    public function index(): void {
        $user = $this->registry->get('user');
        if (!$user instanceof \Reamur\System\Library\User || !$user->isLogged()) {
            return; // muralha de login já tratou
        }

        $route = (string)$this->config->get('_route', '');

        if ($route === '') {
            $route = $this->queryValue('route');
        }

        if ($route === '') {
            $route = (string)$this->config->get('action_default', 'common/dashboard');
        }

        // S1 (ADR 0087): a rota checada aqui é a MESMA que o dispatcher executa.
        // Antes este arquivo limpava a rota por conta própria (cortava no
        // primeiro '.') enquanto Action removia '..': 'user/us..er' era checada
        // como 'user/us' (arquivo inexistente → liberada) e executada como
        // 'user/user'. Agora as duas pontas leem a rota com Action::parse().
        //
        // Granularidade base-route (Adendo F2-6 do ADR 0005): 'user/user.add|3',
        // 'user/user.delete' etc. herdam a ACL do arquivo 'user/user'. Um grant
        // cobre TODOS os métodos do controller — métodos com efeito colateral
        // verificam adicionalmente hasPermission('modify', ...).
        $action = new \Reamur\System\Engine\Action($route);
        $route  = $action->getRoute();

        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        // Rota que o dispatcher NÃO consegue executar (vazia, malformada, sem
        // arquivo, método '_privado') não é negação de permissão: ele entrega o
        // 404. É seguro liberar aqui porque a pergunta é feita ao mesmo Action.
        if (!$action->isCallable()) {
            return;
        }

        if (!$user->hasPermission('access', $route)) {
            $this->response->redirect($this->url->link('error/permission'));
        }
    }
}
