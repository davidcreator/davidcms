<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Rodapé do wizard (ADR 0007): links do projeto/documentação/suporte
 * + fechamento do documento. Sem marca d'água externa além dos links
 * oficiais — mesmos do instalador público.
 */
namespace Reamur\Install\Controller\Common;

class Footer extends \Reamur\System\Engine\Controller {

    public function index(array $args = []): string {
        $this->load->language('common/footer');

        $data['text_project']       = $this->language->get('text_project');
        $data['text_documentation'] = $this->language->get('text_documentation');
        $data['text_support']       = $this->language->get('text_support');

        return $this->load->view('common/footer', $data);
    }
}
