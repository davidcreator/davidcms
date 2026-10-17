<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Bloco compartilhado common/footer do ADMIN (F2-4): assinatura do núcleo.
 */
namespace Reamur\Admin\Controller\Common;

class Footer extends \Reamur\System\Engine\Controller {

    /**
     * @param array<string, mixed> $data Dados passados pelo controller chamador
     * @return string HTML renderizado
     */
    public function index(array $data = []): string {
        $this->load->language('common/footer');

        $data['text_powered'] = sprintf($this->language->get('text_powered'), REAMUR_VERSION);

        return $this->load->view('common/footer', $data);
    }
}
