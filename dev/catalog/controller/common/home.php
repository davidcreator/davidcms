<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Primeira página pública do catalog (rota padrão: common/home — Contrato §4).
 * Hero de marca mínimo em MVCL puro: nenhum texto solto no template e nenhuma
 * config inventada (§5.2) — textos 100% via language, versão via REAMUR_VERSION.
 */
namespace Reamur\Catalog\Controller\Common;

class Home extends \Reamur\System\Engine\Controller {

    /** Renderiza o hero institucional (200) */
    public function index(): void {
        $this->load->language('common/home');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('text_tagline'));

        $data['heading_title']      = $this->language->get('heading_title');
        $data['text_tagline']       = $this->language->get('text_tagline');
        $data['text_lead']          = $this->language->get('text_lead');
        $data['text_feature_1']     = $this->language->get('text_feature_1');
        $data['text_feature_2']     = $this->language->get('text_feature_2');
        $data['text_feature_3']     = $this->language->get('text_feature_3');
        $data['text_feature_1_alt'] = $this->language->get('text_feature_1_alt');
        $data['text_feature_2_alt'] = $this->language->get('text_feature_2_alt');
        $data['text_feature_3_alt'] = $this->language->get('text_feature_3_alt');
        $data['button_admin']       = $this->language->get('button_admin');

        $data['admin'] = $this->config->get('site_url') . 'admin/';

        // Chrome compartilhado (F2-4): css via document + header/footer por sub-controller
        $this->document->addStyle($this->config->get('site_url') . 'view/css/common.css');

        $data['body_class'] = 'rms-home';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('common/home', $data));
    }
}
