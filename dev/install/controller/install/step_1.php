<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 1: Licença (GPL-3.0).
 * Spec: docs/Analise-DavidAlmeida.md (licença → requisitos → banco → admin → concluir).
 * Rota padrão da aplicação install (Contrato-Bootstrap §4): install/step_1.
 */
namespace Reamur\Install\Controller\Install;

class Step_1 extends \Reamur\System\Engine\Controller {

    /** Exibe a licença; POST com aceite avança para o passo 2 */
    public function index(): void {
        $this->load->language('install/step_1');

        $this->document->setTitle($this->language->get('heading_title'));

        // Textos da view (regra de ouro: nenhum texto solto no template)
        $data['step']            = sprintf($this->language->get('text_step'), 1);
        $data['heading_title']   = $this->language->get('heading_title');
        $data['text_license']    = $this->language->get('text_license');
        $data['text_agree']      = $this->language->get('text_agree');
        $data['button_continue'] = $this->language->get('button_continue');

        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!empty($this->request->post['agree'])) {
                $this->response->redirect($this->url->link('install/step_2'));
                return;
            }

            $data['error'] = $this->language->get('error_agree');
        }

        // LICENSE fica na raiz do repositório (= DIR_REAMUR em dev):
        // __DIR__ = dev/install/controller/install → 4 níveis acima.
        $license = dirname(__DIR__, 4) . '/LICENSE';

        $data['license'] = is_file($license) ? (string)file_get_contents($license) : $this->language->get('error_license_missing');

        $data['action'] = $this->url->link('install/step_1');
        $data['agree']  = !empty($this->request->post['agree']);

        $this->response->setOutput($this->load->view('install/step_1', $data));
    }
}
