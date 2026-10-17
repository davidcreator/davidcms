<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Chrome do wizard (ADR 0007 — paridade de UI com o instalador ReamurCMS):
 * <head> com Bootstrap/ReamurCSS/FontAwesome/jQuery, navbar com logo.
 * Assets relativos — o wizard só responde em index.php (sem URL limpa,
 * ADR 0006), então 'view/...' resolve na raiz da aplicação em qualquer host.
 */
namespace Reamur\Install\Controller\Common;

class Header extends \Reamur\System\Engine\Controller {

    /**
     * @param array<string, mixed> $args Aceita 'page_title' do passo chamador
     */
    public function index(array $args = []): string {
        $this->load->language('common/header');

        $code = (string)$this->config->get('language_directory', 'en-us');

        $data['lang']    = $code;
        $data['dir']     = 'ltr';
        $data['title']   = (string)($args['page_title'] ?? $this->language->get('heading_installer'));
        $data['home']    = $this->url->link('install/step_1');
        $data['version'] = defined('REAMUR_VERSION') ? REAMUR_VERSION : '1.0';

        return $this->load->view('common/header', $data);
    }
}
