<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/extension (EX-01, ADR 0095): põe para rodar, no admin, as
 * extensões ligadas que pedem "admin" no manifesto. Vem depois do login e da
 * permissão, e tem duas travas próprias:
 *
 *  - só para usuário logado: a tela de login nunca executa código de extensão;
 *  - nunca na própria tela de extensões nem na saída: se uma extensão quebrar
 *    o admin, a tela que a desliga continua abrindo.
 *
 * A outra saída de emergência é `define('REAMUR_EXTENSIONS', false);` no
 * config.php, que o Manager respeita.
 */
namespace Reamur\Admin\Controller\Startup;

class Extension extends \Reamur\System\Engine\Controller {

    /** Rotas em que nenhuma extensão roda. */
    public const QUIET_ROUTES = ['extension/extension', 'common/login', 'common/logout', 'error/permission'];

    public function index(): void {
        $manager = $this->registry->get('extensions');
        $user    = $this->registry->get('user');

        if (!$manager instanceof \Reamur\System\Library\Extension\Manager
            || !is_object($user) || !method_exists($user, 'isLogged') || $user->isLogged() !== true) {
            return;
        }

        // Mesma leitura de rota do dispatcher (ADR 0087): 'extension/extension.save|x'
        // é a tela de extensões, e uma rota malformada não vira outra coisa.
        $route = (string)$this->config->get('_route', '');

        if ($route === '') {
            $route = (string)$this->config->get('action_default', 'common/dashboard');
        }

        if (in_array((new \Reamur\System\Engine\Action($route))->getRoute(), self::QUIET_ROUTES, true)) {
            return;
        }

        $manager->boot('admin');
    }
}
