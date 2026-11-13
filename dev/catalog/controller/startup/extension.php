<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/extension (EX-01, ADR 0095): põe para rodar, no site
 * público, as extensões que o admin ligou. É a última pré-ação: configurações,
 * tema e idioma já estão resolvidos. Todo o trabalho (e todo o cuidado para
 * uma extensão com defeito não derrubar a página) está em
 * Library\Extension\Manager::boot(), que só age na primeira chamada.
 */
namespace Reamur\Catalog\Controller\Startup;

class Extension extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $manager = $this->registry->get('extensions');

        if ($manager instanceof \Reamur\System\Library\Extension\Manager) {
            $manager->boot('catalog');
        }
    }
}
