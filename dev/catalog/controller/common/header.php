<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Bloco compartilhado common/header (F2-4): <head> honesto (lang dinâmico,
 * título do document e styles do serviço document) + barra de marca com
 * switcher de idiomas (?language= — Adendo F2-3 do ADR 0001).
 * Chamado como sub-controller via $this->load->controller('common/header', $data)
 * e devolve HTML renderizado (padrão declarado em Loader::controller).
 */
namespace Reamur\Catalog\Controller\Common;

class Header extends \Reamur\System\Engine\Controller {

    /**
     * @param array<string, mixed> $data Dados passados pelo controller chamador
     * @return string HTML renderizado
     */
    public function index(array $data = []): string {
        $this->load->language('common/header');

        $data['locale'] = (string)$this->config->get('language_directory', 'en-us');
        $data['title']  = $this->document->getTitle();
        $data['styles'] = $this->document->getStyles();

        $data['home']           = (string)$this->config->get('site_url');
        $data['text_languages'] = $this->language->get('text_languages');
        $data['body_class']     = (string)($data['body_class'] ?? '');

        // Switcher: diretórios que EXISTEM são as opções (fonte da verdade = filesystem)
        $args  = $this->request->get;
        $route = (string)($args['route'] ?? '');

        unset($args['route'], $args['language']);

        if ($route === '') {
            $route = (string)$this->config->get('action_default', 'common/home');
        }

        $data['languages'] = [];

        foreach ((array)glob(DIR_APPLICATION . 'language/*', GLOB_ONLYDIR) as $dir) {
            $code            = basename((string)$dir);
            $langArgs        = $args;
            $langArgs['language'] = $code;

            $data['languages'][] = [
                'code'   => $code,
                'name'   => $this->language->get('text_lang_' . str_replace('-', '_', $code)),
                'active' => $code === $data['locale'],
                'href'   => $this->url->link($route, $langArgs),
            ];
        }

        return $this->load->view('common/header', $data);
    }
}
