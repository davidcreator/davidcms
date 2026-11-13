<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Preferências de cookies do visitante (EX-02, ADR 0096).
 *
 * GET  mostra a página de preferências.
 * POST grava a escolha (aviso do cabeçalho ou esta página) no cookie
 *      rms_consent e volta para a página de onde o visitante veio. Na página
 *      seguinte, startup/extension lê o cookie e só põe para rodar as
 *      extensões das categorias aceitas.
 *
 * A escolha fica só no navegador: nada é gravado no banco.
 */
declare(strict_types=1);

namespace Reamur\Catalog\Controller\Common;

use Reamur\System\Library\Consent as Choice;
use Reamur\System\Library\Extension\Manager;
use Reamur\System\Library\Extension\Manifest;

class Consent extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('common/consent');

        $error = '';

        if (($this->request->server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if ($this->csrfValid()) {
                $this->save();

                return;
            }

            $error = $this->language->get('error_csrf');
        }

        $this->document->setTitle($this->language->get('heading_title'));

        $in_use  = $this->categoriesInUse();
        $current = $this->current();

        $data = [
            'error'          => $error,
            'text_flash'     => $this->flash(),
            'action'         => $this->url->link('common/consent'),
            'csrf_token'     => \Reamur\System\Library\Security\Csrf::token($this->session),
            'home'           => (string)$this->config->get('site_url', ''),
            'categories'     => $this->categoryRows($in_use, $current),
            'has_categories' => $in_use !== [],
            'text_status'    => $current->time() > 0
                ? sprintf($this->language->get('text_choice_date'), date($this->language->get('date_format_choice'), $current->time()))
                : $this->language->get('text_no_choice'),
        ];

        foreach ([
            'heading_title', 'text_lead', 'text_necessary', 'text_necessary_help', 'text_always_on',
            'text_services', 'text_cookies', 'text_none_in_use', 'button_save', 'button_accept_all',
            'button_reject_all', 'button_back_home',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $this->catalogChrome($data, 'page-consent', ['view/css/consent.css']);

        $this->response->setOutput($this->load->view('common/consent', $data));
    }

    /**
     * Grava a escolha e volta para a página de origem.
     *
     * `decision`:
     *  - accept / reject: respondem às categorias que ainda esperam resposta
     *    (os dois botões do aviso); o que o visitante já tinha decidido fica.
     *  - accept_all / reject_all: valem para todas as categorias em uso.
     *  - custom: uma caixa por categoria em uso (página de preferências).
     */
    private function save(): void {
        $in_use   = $this->categoriesInUse();
        $current  = $this->current();
        $decision = $this->postScalar('decision');

        // Ponto de partida: o que já estava decidido, inclusive de categoria
        // que hoje nenhuma extensão usa (a resposta do visitante continua valendo).
        $choices = [];

        foreach (Choice::CATEGORIES as $category) {
            if ($current->decided($category)) {
                $choices[$category] = $current->allows($category);
            }
        }

        $posted = $this->request->post['category'] ?? [];
        $posted = is_array($posted) ? $posted : [];

        foreach ($in_use as $category) {
            $pending = !$current->decided($category);

            switch ($decision) {
                case 'accept_all':
                    $choices[$category] = true;
                    break;
                case 'accept':
                    if ($pending) {
                        $choices[$category] = true;
                    }
                    break;
                case 'custom':
                    $choices[$category] = ($posted[$category] ?? '') === '1';
                    break;
                case 'reject':
                    if ($pending) {
                        $choices[$category] = false;
                    }
                    break;
                default:
                    // reject_all e qualquer valor desconhecido: a resposta segura é não.
                    $choices[$category] = false;
            }
        }

        $new    = Choice::of($choices, time());
        $secure = strtolower((string)parse_url((string)$this->config->get('site_url', ''), PHP_URL_SCHEME)) === 'https';

        $this->response->addCookie($new->cookieLine($this->config->get('config_consent_days'), $secure));

        // Retirada: os cookies que as extensões agora recusadas declararam são
        // apagados do navegador. Só os deste site: cookie de outro domínio
        // (o da própria plataforma de anúncios) o site não alcança.
        $manager = $this->registry->get('extensions');

        if ($manager instanceof Manager) {
            $host = (string)(parse_url((string)$this->config->get('site_url', ''), PHP_URL_HOST) ?: '');
            $keep = [(string)$this->config->get('session_name', 'RMSSESSION')];

            foreach ($manager->staleCookieLines($new, array_keys($this->request->cookie), $host, $keep) as $line) {
                $this->response->addCookie($line);
            }
        }

        $own    = str_replace('&amp;', '&', $this->url->link('common/consent'));
        $target = $this->returnTarget($this->postScalar('return'), $own);

        // A confirmação por escrito só é guardada quando a volta é para esta tela,
        // que a mostra na hora. Vindo do aviso (volta para a página onde o visitante
        // estava), o sumiço do aviso já é a confirmação; a mensagem ficaria esperando
        // na sessão e apareceria mais tarde dentro do formulário de contato ou de
        // orçamento, parecendo resposta a um envio que não houve.
        if ($target === $own) {
            $this->setFlash($this->language->get('text_success'));
        }

        // 303: o navegador volta com GET, e o F5 não reenvia o formulário.
        $this->response->redirect($target, 303);
    }

    /**
     * Para onde voltar depois de gravar. Só um caminho DESTE site: começa com
     * uma única barra, sem esquema, sem host, sem barra invertida. Qualquer
     * outra coisa cai na página de preferências.
     */
    private function returnTarget(string $return, string $fallback): string {
        if ($return === '' || strlen($return) > 2000 || $return[0] !== '/') {
            return $fallback;
        }

        if (preg_match('#^/(?![/\\\\])[^\\\\\x00-\x20\x7f]*$#D', $return) !== 1) {
            return $fallback;
        }

        return $return;
    }

    private function current(): Choice {
        $consent = $this->registry->get('consent');

        return $consent instanceof Choice ? $consent : Choice::none();
    }

    /** @return list<string> */
    private function categoriesInUse(): array {
        $manager = $this->registry->get('extensions');

        return $manager instanceof Manager ? $manager->consentCategories() : [];
    }

    /** @return array<string, array{name: array<string, string>, categories: list<string>, cookies: list<string>, running: bool}> */
    private function consented(): array {
        $manager = $this->registry->get('extensions');

        return $manager instanceof Manager ? $manager->consented() : [];
    }

    /**
     * @param list<string> $in_use
     * @return list<array{code: string, name: string, help: string, checked: bool, services: string, cookies: string}>
     */
    private function categoryRows(array $in_use, Choice $current): array {
        $language = (string)$this->config->get('language_directory', 'en-us');
        $rows     = [];

        foreach ($in_use as $category) {
            $services = [];
            $cookies  = [];

            foreach ($this->consented() as $entry) {
                if (in_array($category, $entry['categories'], true)) {
                    $services[] = Manifest::pick($entry['name'], $language);
                    $cookies    = array_merge($cookies, $entry['cookies']);
                }
            }

            $rows[] = [
                'code'     => $category,
                'name'     => $this->language->get('text_' . $category),
                'help'     => $this->language->get('text_' . $category . '_help'),
                'checked'  => $current->allows($category),
                'services' => implode(', ', array_values(array_unique($services))),
                'cookies'  => implode(', ', array_values(array_unique($cookies))),
            ];
        }

        return $rows;
    }
}
