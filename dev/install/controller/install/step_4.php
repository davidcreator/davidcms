<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 4: Concluído (ADR 0007). Só exibe quando ESTA sessão
 * concluiu a instalação (session install_done gravada pelo passo 3).
 * Desde a ADR 0091 é chamado pelo passo 3 (mesma resposta); pela URL, com o
 * site instalado, a rota responde o 403 da trava como todo o instalador.
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

        // ADR 0091 (S12): a trava (install.lock) é gravada pelo passo 3, junto
        // com a instalação, e não mais aqui. Antes ela dependia de esta página
        // ser aberta e de a gravação dar certo, sem aviso quando falhava.
        // Esta página é renderizada pelo próprio passo 3, na mesma resposta.

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

        // Painel: gravado pelo passo 3 (regra irmãos: catalog/ → admin/).
        // Fallback para sessões antigas: aplica a mesma regra sobre $site.
        $admin = (string)($this->session->data['install_admin'] ?? '');

        if ($admin === '' && $site !== '') {
            $admin = str_ends_with($site, 'catalog/')
                ? substr($site, 0, -strlen('catalog/')) . 'admin/'
                : $site . 'admin/';
        }

        $isHttp = static fn(string $u): bool => $u !== ''
            && in_array(strtolower((string)parse_url($u, PHP_URL_SCHEME)), ['http', 'https'], true)
            && filter_var($u, FILTER_VALIDATE_URL) !== false;

        $data['catalog'] = $isHttp($site) ? $site : '#';
        $data['admin']   = $isHttp($admin) ? $admin : '#';

        // Sessão efêmera do wizard: encerra antes da despedida (links já montados)
        unset($this->session->data['install_db'], $this->session->data['install_site'], $this->session->data['install_admin'], $this->session->data['install_done']);

        $this->installChrome($data);

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
}
