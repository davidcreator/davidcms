<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Página 404 do catalog (rota de erro do Router: error/not_found — Contrato §4).
 * Recebe o dispatch por fallback, marca o status HTTP honesto e devolve a
 * página estilizada com retorno para a home da aplicação.
 */
namespace Reamur\Catalog\Controller\Error;

class Not_found extends \Reamur\System\Engine\Controller {

    /** Renderiza a 404 (status 404) */
    public function index(): void {
        $this->load->language('error/not_found');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->response->addHeader('HTTP/1.1 404 Not Found');

        $data['heading_title']   = $this->language->get('heading_title');
        $data['text_code']       = $this->language->get('text_code');
        $data['text_error']      = $this->language->get('text_error');
        $data['text_suggestion'] = $this->language->get('text_suggestion');
        $data['button_continue'] = $this->language->get('button_continue');

        $data['home'] = $this->config->get('site_url');

        // Cena Three.js configurável pelo painel; o fallback permanece se desativada.
        $this->load->model('setting/setting');
        $threeJs = $this->three_scene_config->runtime($this->model_setting_setting->getSettings());
        $base = rtrim((string)$this->config->get('site_url', ''), '/') . '/';

        $data['has_3d']     = $threeJs['not_found_enabled'];
        $data['three_runtime_base'] = $base . $this->three_asset_manifest->publicBasePath();
        $data['nf3d']       = $threeJs['not_found'];

        // Chrome compartilhado (F2-4)
        $this->document->addStyle($this->config->get('site_url') . 'view/css/common.css');

        $data['body_class'] = 'rms-404';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('error/not_found', $data));
    }
}
