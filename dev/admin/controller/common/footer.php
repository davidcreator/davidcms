<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Bloco compartilhado common/footer do ADMIN (F2-4 → F8-01, ADR 0045):
 * fecha o shell e injeta os scripts self-hosted (Bootstrap bundle + admin.js).
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

        // dados do shell (F8-01) — necessários se logado OU não (scripts do auth)
        $data['logged']   = $this->user instanceof \Reamur\System\Library\User && $this->user->isLogged();
        $data['site_url'] = (string)$this->config->get('site_url');
        $data['asset_v']  = \Reamur\Admin\Controller\Common\Header::ASSET_V;

        return $this->load->view('common/footer', $data);
    }
}
