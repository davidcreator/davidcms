<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 4: Concluído (ADR 0007). Só exibe quando ESTA sessão
 * concluiu a instalação (session install_done gravada pelo passo 3).
 * Conteúdo exclusivo do ReamurCMS público (módulos blog/chat/mooc, loja de
 * extensões, promotion) NÃO faz parte deste projeto — decisão do mantenedor.
 */
namespace Reamur\Install\Controller\Install;

class Step_4 extends \Reamur\System\Engine\Controller {

    /** Página de conclusão: links do site/painel + alerta para remover o install */
    public function index(): void {
        if (empty($this->session->data['install_done'])) {
            $this->response->redirect($this->url->link('install/step_1'));

            return;
        }

        $this->load->language('install/step_4');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['heading_title']      = $this->language->get('heading_title');
        $data['text_step_4']        = $this->language->get('text_step_4');
        $data['text_success']       = $this->language->get('text_success');
        $data['text_delete_folder'] = $this->language->get('text_delete_folder');
        $data['text_catalog']       = $this->language->get('text_catalog');
        $data['text_admin']         = $this->language->get('text_admin');
        $data['text_installed']     = $this->language->get('text_installed');

        // Base pública real: gravada pelo passo 3 a partir do site_url informado
        // (fallback honesto: lê o define já escrito, nunca SCRIPT_NAME — F2-5).
        $site = (string)($this->session->data['install_site'] ?? '');

        if ($site === '') {
            $site = $this->siteFromConfig();
        }

        $data['catalog'] = $site !== '' ? $site : '#';
        $data['admin']   = $site !== '' ? $site . 'admin/' : '#';

        // Sessão efêmera do wizard: encerra antes da despedida (links já montados)
        unset($this->session->data['install_db'], $this->session->data['install_site'], $this->session->data['install_done']);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('install/step_4', $data));
    }

    /** HTTP_SERVER do catalog recém-escrito ('' se o arquivo nem existir) */
    private function siteFromConfig(): string {
        $config = dirname(__DIR__, 3) . '/catalog/config.php'; // dev/catalog/config.php

        if (is_file($config)
            && preg_match("/define\\('HTTP_SERVER',\\s*'([^']+)'\\)/", (string)file_get_contents($config), $m)) {
            return (string)$m[1];
        }

        return '';
    }

    /** Chrome compartilhado do wizard (header + seletor de idiomas + footer) */
    private function chrome(array &$data): void {
        $data['page_title'] = $this->language->get('heading_title');
        $data['header']     = $this->load->controller('common/header', $data);
        $data['language']   = $this->load->controller('common/language');
        $data['footer']     = $this->load->controller('common/footer');
    }
}
