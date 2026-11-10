<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 1: Licença (GPL-3.0). ADR 0007: aceite por continuação
 * (mesmo gesto do instalador ReamurCMS) — ler + clicar em Continuar implica
 * concordância; o POST segue aceito como atalho (retrocompatível com a
 * automação que já postava agree=1).
 */
namespace Reamur\Install\Controller\Install;

class Step_1 extends \Reamur\System\Engine\Controller {

    /** Exibe a licença; POST exige CSRF válido para avançar ao passo 2 */
    public function index(): void {
        $this->load->language('install/step_1');

        $data['error_csrf'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if ($this->csrfValid()) {
                $this->response->redirect($this->url->link('install/step_2'));

                return;
            }

            $data['error_csrf'] = $this->language->get('error_csrf');
        }

        $this->document->setTitle($this->language->get('heading_title'));

        // Textos da view (regra de ouro: nenhum texto solto no template)
        $data['heading_title']   = $this->language->get('heading_title');
        $data['text_step_1']     = $this->language->get('text_step_1');
        $data['button_continue'] = $this->language->get('button_continue');

        // LICENSE fica na raiz do repositório (= DIR_REAMUR em dev):
        // __DIR__ = dev/install/controller/install → 4 níveis acima.
        $license = dirname(__DIR__, 4) . '/LICENSE';

        $data['license'] = is_file($license) ? (string)file_get_contents($license) : $this->language->get('error_license_missing');

        $data['action']     = $this->url->link('install/step_1');
        $data['continue']   = $this->url->link('install/step_2');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);

        $this->installChrome($data);

        $this->response->setOutput($this->load->view('install/step_1', $data));
    }
}
