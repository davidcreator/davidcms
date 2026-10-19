<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Bloco compartilhado common/footer (F2-4 · F3-01): assinatura do núcleo +
 * páginas legais dinâmicas (só renderiza se o seed existir — honesto, sem 404)
 * + redes sociais (idioma é a fonte dos hrefs).
 */
namespace Reamur\Catalog\Controller\Common;

class Footer extends \Reamur\System\Engine\Controller {

    /**
     * @param array<string, mixed> $data Dados passados pelo controller chamador
     * @return string HTML renderizado
     */
    public function index(array $data = []): string {
        $this->load->language('common/footer');
        $this->load->language('common/home'); // redes sociais (F3-01)

        $data['text_powered']   = sprintf($this->language->get('text_powered'), REAMUR_VERSION);

        // F4-03 (ADR 0026): copyright institucional por idioma — banco governa
        // quando a chave EXISTE; ausência cai no lang file (degradação silenciosa ADR 0023)
        $this->load->model('setting/setting');

        $settings  = $this->model_setting_setting->getSettings();
        $langCode  = (string)($this->session->data['language'] ?? 'en-us');
        $copyKey   = 'config_copyright_' . $langCode;
        $copy      = array_key_exists($copyKey, $settings)
            ? (string)$settings[$copyKey]
            : $this->language->get('text_copyright');

        $data['text_copyright'] = sprintf($copy, date('Y'));
        $data['text_legal']     = $this->language->get('text_legal');

        // Páginas legais (F3-01): aparecem só se as keywords existirem para o
        // idioma ativo (coalesce no model seo_url já faz fallback → en-US).
        $this->load->model('catalog/page');

        $legal = [];

        foreach (['cookies', 'politicas-internas'] as $kw) {
            $page = $this->model_catalog_page->getPageByKeyword($kw);

            if ($page) {
                $legal[] = [
                    'name' => (string)$page['title'],
                    'href' => $this->url->link('cms/page.info|' . (int)$page['page_id']),
                ];
            }
        }

        $data['legal'] = $legal;

        // Redes sociais (F4-03: banco governa — chave existente + vazia = esconder;
        // chave ausente = fallback p/ o lang file — mesmo contrato do hero/home)
        $socials = [];

        foreach (['facebook', 'instagram', 'pinterest', 'x', 'google_business', 'redbubble'] as $net) {
            $key  = 'config_social_' . $net . '_href';
            $href = array_key_exists($key, $settings)
                ? (string)$settings[$key]
                : (string)$this->language->get('text_social_' . $net . '_href');

            if ($href !== '') {
                $socials[] = [
                    'name' => (string)$this->language->get('text_social_' . $net),
                    'href' => $href,
                ];
            }
        }

        $data['socials'] = $socials;

        return $this->load->view('common/footer', $data);
    }
}
