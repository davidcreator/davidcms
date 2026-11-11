<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/logout — encerra a sessão do painel e volta ao login (rota pública
 * para não bloquear usuário com sessão morta).
 */
namespace Reamur\Admin\Controller\Common;

class Logout extends \Reamur\System\Engine\Controller {

    /** Destrói a sessão e volta à tela de acesso */
    public function index(): void {
        $user = $this->registry->get('user');
        if ($user instanceof \Reamur\System\Library\User) {
            $user->logout();
        }

        $this->response->redirect($this->url->link('common/login'));
    }
}
