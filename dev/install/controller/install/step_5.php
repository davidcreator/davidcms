<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Rota legada (fluxo pré-ADR 0007 era 5 passos): o "Concluído" mora agora no
 * passo 4, junto do instalador ReamurCMS. Mantemos redirect permanente por
 * uma versão para não quebrar links/automação antigos.
 */
namespace Reamur\Install\Controller\Install;

class Step_5 extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->response->redirect($this->url->link('install/step_4'));
    }
}
