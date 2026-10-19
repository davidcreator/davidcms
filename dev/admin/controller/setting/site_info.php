<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/site_info (ADMIN) — informações institucionais do site (F4-03, ADR 0026):
 *  · contato (email/whatsapp + link wa.me) — global;
 *  · endereço quanto-idioma (decisão do mantenedor);
 *  · copyright do rodapé — quanto-idioma (placeholder %s mantém contrato sprintf do footer);
 *  · hrefs das 6 redes sociais — globais.
 * · GET  → formulário efetivo (rms_setting > seeds do upgrade);
 * · POST → valida e persiste code='config' via setting/setting::editSettings
 *   (allowlist extendida nesta tarefa — camada 2/3 documentada na ADR 0025).
 * Degradação silenciosa (padrão ADR 0023): campo vazio NO BD cai no lang file.
 */
namespace Reamur\Admin\Controller\Setting;

class Site_info extends \Reamur\System\Engine\Controller {

    /** Idiomas que a tela permite administrar = status=1 (mesma governança F4-02) */
    private function activeLanguages(): array {
        $this->load->model('localization/language');

        return array_values(array_filter(
            $this->model_localization_language->getLanguages(),
            static fn (array $lang): bool => (int)($lang['status'] ?? 0) === 1
        ));
    }

    public function index(): void {
        $this->load->language('setting/site_info');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        $errors   = [];
        $settings = $this->model_setting_setting->getSettings();

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'setting/site_info')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $post   = $this->sanitize($this->request->post);
            $errors = $this->validate($post, $this->activeLanguages());

            if (!$errors) {
                $this->model_setting_setting->editSettings($post);

                $this->session->data['flash'] = ['type' => 'success', 'text' => $this->language->get('text_success')];

                $this->response->redirect($this->url->link('setting/site_info'));

                return;
            }

            // POST inválido: repovoa do próprio POST (não perde o digitado — padrão setting/setting)
            $settings = array_merge($settings, $post);
        }

        $data = [];

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_site_info'] = $this->language->get('text_site_info');
        $data['text_flash']    = $this->flash();
        $data['error']         = implode("\n", array_filter(array_map('strval', $errors)));

        // Seções
        $data['fieldset_contact']   = $this->language->get('fieldset_contact');
        $data['fieldset_address']   = $this->language->get('fieldset_address');
        $data['fieldset_copyright'] = $this->language->get('fieldset_copyright');
        $data['fieldset_social']    = $this->language->get('fieldset_social');

        // Labels genéricos dos campos globais
        foreach (['config_contact_email', 'config_contact_whatsapp_text', 'config_contact_whatsapp_href',
                  'config_social_facebook_href', 'config_social_instagram_href', 'config_social_pinterest_href',
                  'config_social_x_href', 'config_social_google_business_href', 'config_social_redbubble_href'] as $field) {
            $data['entry_' . $field] = $this->language->get('entry_' . $field);
            $data['help_' . $field]  = $this->language->get('help_' . $field);
        }

        $data['entry_contact_address'] = $this->language->get('entry_contact_address');
        $data['help_contact_address']  = $this->language->get('help_contact_address');
        $data['entry_copyright']       = $this->language->get('entry_copyright');
        $data['help_copyright']        = $this->language->get('help_copyright');
        $data['help_fallback']         = $this->language->get('help_fallback');

        // Idiomas ativos (campos por-idioma aderem à governança F4-02)
        $data['languages'] = $this->activeLanguages();

        // Valores efetivos
        foreach ([
            'config_contact_email', 'config_contact_whatsapp_text', 'config_contact_whatsapp_href',
            'config_social_facebook_href', 'config_social_instagram_href', 'config_social_pinterest_href',
            'config_social_x_href', 'config_social_google_business_href', 'config_social_redbubble_href',
        ] as $field) {
            $data['value_' . $field] = (string)($settings[$field] ?? '');
        }

        foreach ($data['languages'] as $lang) {
            $code = (string)$lang['code'];
            $data['value_contact_address'][$code] = (string)($settings['config_contact_address_' . $code] ?? '');
            $data['value_copyright'][$code]       = (string)($settings['config_copyright_' . $code] ?? '');
        }

        $data['button_save'] = $this->language->get('button_save');
        $data['button_back'] = $this->language->get('button_back');

        $data['action'] = $this->url->link('setting/site_info');
        $data['back']   = $this->url->link('common/dashboard');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/site_info', $data));
    }

    /**
     * @param array<string, mixed> $post
     * @param array<int, array<string, string>> $languages
     * @return array<string, string> field → mensagem
     */
    private function validate(array $post, array $languages): array {
        $errors = [];

        if ($post['config_contact_email'] === '') {
            $errors['config_contact_email'] = sprintf($this->language->get('error_required'), $this->language->get('entry_config_contact_email'));
        } elseif (!filter_var($post['config_contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['config_contact_email'] = sprintf($this->language->get('error_email'), $this->language->get('entry_config_contact_email'));
        }

        if ($post['config_contact_whatsapp_text'] === '') {
            $errors['config_contact_whatsapp_text'] = sprintf($this->language->get('error_required'), $this->language->get('entry_config_contact_whatsapp_text'));
        }

        if ($post['config_contact_whatsapp_href'] !== '' && !preg_match('/^\d{8,16}$/', $post['config_contact_whatsapp_href'])) {
            $errors['config_contact_whatsapp_href'] = $this->language->get('error_whatsapp_href');
        }

        foreach ($languages as $lang) {
            $code = (string)$lang['code'];

            $address = (string)($post['config_contact_address_' . $code] ?? '');
            if ($address === '') {
                $errors['config_contact_address_' . $code] = sprintf($this->language->get('error_required'), $this->language->get('entry_contact_address') . ' (' . $code . ')');
            }

            $copy = (string)($post['config_copyright_' . $code] ?? '');
            if ($copy === '') {
                $errors['config_copyright_' . $code] = sprintf($this->language->get('error_required'), $this->language->get('entry_copyright') . ' (' . $code . ')');
            } elseif (mb_strpos($copy, '%s') === false) {
                $errors['config_copyright_' . $code] = $this->language->get('error_copyright_year');
            }
        }

        // Redes sociais: vazio = "remover do site"; preenchido = URL http(s) válida
        foreach (['config_social_facebook_href', 'config_social_instagram_href', 'config_social_pinterest_href',
                  'config_social_x_href', 'config_social_google_business_href', 'config_social_redbubble_href'] as $field) {
            $v = (string)$post[$field];
            if ($v !== '' && !preg_match('#^https?://#i', $v)) {
                $errors[$field] = sprintf($this->language->get('error_url'), $this->language->get('entry_' . $field));
            }
        }

        return $errors;
    }

    /**
     * Sanitização trim + teto de tamanho (não valida — role separada).
     *
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function sanitize(array $post): array {
        $str   = static function (string $k, int $max = 255) use ($post): string {
            return mb_substr(trim((string)($post[$k] ?? '')), 0, $max);
        };
        $langs = $this->activeLanguages();

        $out = [
            'config_contact_email'                   => $str('config_contact_email', 96),
            'config_contact_whatsapp_text'           => $str('config_contact_whatsapp_text', 32),
            // link só recebe dígitos (wa.me) — descarte silencioso de não-dígitos
            'config_contact_whatsapp_href'           => preg_replace('/\D/', '', (string)($post['config_contact_whatsapp_href'] ?? '')),
            'config_social_facebook_href'            => $str('config_social_facebook_href'),
            'config_social_instagram_href'           => $str('config_social_instagram_href'),
            'config_social_pinterest_href'           => $str('config_social_pinterest_href'),
            'config_social_x_href'                   => $str('config_social_x_href'),
            'config_social_google_business_href'     => $str('config_social_google_business_href'),
            'config_social_redbubble_href'           => $str('config_social_redbubble_href'),
        ];

        foreach ($langs as $lang) {
            $code = (string)$lang['code'];
            $out['config_contact_address_' . $code] = $str('config_contact_address_' . $code, 128);
            $out['config_copyright_' . $code]       = $str('config_copyright_' . $code, 255);
        }

        return $out;
    }

    /** @return array{type: string, text: string} */
    private function flash(): array {
        $session = $this->session;

        $data = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $data         = (array)$session->data['flash'];
            $data['type'] = (string)($data['type'] ?? 'success');
            $data['text'] = (string)($data['text'] ?? '');

            unset($session->data['flash']);
        }

        return $data;
    }

    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
