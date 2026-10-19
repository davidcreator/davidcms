<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/setting (ADMIN) — ecrã operacional do painel (F4-01, ADR 0024):
 *  · GET  → formulário com os valores efetivos (settings > config default)
 *  · POST → valida e persiste em rms_setting code='config' (via model)
 *  · Botão "Clear cache" separado: método clearCache (POST, modify-only)
 * Sem fakear: mail_* persiste hoje; o consumo real chega quando a engine de
 * envio for acoplada (Fase 5). cache_engine/expire idem (framework monta o
 * cache antes de pré-ações). Declarado na ADR 0024.
 */
namespace Reamur\Admin\Controller\Setting;

class Setting extends \Reamur\System\Engine\Controller {

    /** Tela de configurações (200) */
    public function index(): void {
        $this->load->language('setting/setting');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        [$data, $errors] = $this->hydrate();

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'setting/setting')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $post = $this->sanitize($this->request->post);

            $errors = $this->validate($post);

            if (!$errors) {
                $this->model_setting_setting->editSettings($post);

                $this->session->data['flash'] = ['type' => 'success', 'text' => $this->language->get('text_success')];

                $this->response->redirect($this->url->link('setting/setting'));

                return;
            }

            // POST inválido → repovoa do próprio POST (não perde o digitado)
            $data = array_merge($data, $post);
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_settings'] = $this->language->get('text_settings');
        $data['text_edit']     = $this->language->get('text_edit');
        $data['text_success']  = $this->language->get('text_success');
        $data['text_flash']    = $this->flash();
        // Padrão da casa (testimonial): erros de validação agregados no alerta do topo
        $data['error']         = implode("\n", array_filter(array_map('strval', $errors)));

        // Seções
        $data['fieldset_store']    = $this->language->get('fieldset_store');
        $data['fieldset_mail']     = $this->language->get('fieldset_mail');
        $data['fieldset_cache']    = $this->language->get('fieldset_cache');

        // Campos
        foreach ([
            'config_name', 'config_meta_title', 'config_meta_description',
            'mail_engine', 'mail_smtp_hostname', 'mail_smtp_username',
            'mail_smtp_password', 'mail_smtp_port', 'mail_smtp_timeout',
            'mail_reply_to', 'cache_engine', 'cache_expire',
        ] as $field) {
            $data['entry_' . $field]  = $this->language->get('entry_' . $field);
            $data['help_' . $field]   = $this->language->get('help_' . $field);
            $data['error_' . $field]  = (string)($errors[$field] ?? '');
        }

        // Opções das engines (mesmos valores da lib — Guia PHP8 §13)
        $data['mail_engines']  = ['mail' => 'mail()', 'smtp' => 'SMTP'];
        $data['cache_engines'] = ['file' => 'file (disco)', 'apc' => 'apc', 'memcached' => 'memcached', 'redis' => 'redis'];

        $data['button_save']        = $this->language->get('button_save');
        $data['button_clear_cache'] = $this->language->get('button_clear_cache');
        $data['button_back']        = $this->language->get('button_back');

        $data['action']      = $this->url->link('setting/setting');
        $data['clear_cache'] = $this->url->link('setting/setting.clearCache');
        $data['back']        = $this->url->link('common/dashboard');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/setting', $data));
    }

    /** POST dedicado: limpa TODO o cache (driver ativo) — botão próprio da tela */
    public function clearCache(): void {
        $this->load->language('setting/setting');

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->response->redirect($this->url->link('setting/setting'));

            return;
        }

        if (!$this->user->hasPermission('modify', 'setting/setting')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->cache->clear();

        $this->session->data['flash'] = ['type' => 'success', 'text' => $this->language->get('text_cache_cleared')];

        $this->response->redirect($this->url->link('setting/setting'));
    }

    /**
     * Valor efetivo atual: rms_setting > config default (config.php). P/ password,
     * máscara manual: não-trunc'a por segurança — devolve o valor real ao form
     * (não vazia-value), assim salvar não-cadastra "mantém valor" inválido.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function hydrate(): array {
        $settings = $this->model_setting_setting->getSettings();

        $fields = [
            'config_name', 'config_meta_title', 'config_meta_description',
            'mail_engine', 'mail_smtp_hostname', 'mail_smtp_username',
            'mail_smtp_password', 'mail_smtp_port', 'mail_smtp_timeout',
            'mail_reply_to', 'cache_engine', 'cache_expire',
        ];

        $out = [];

        foreach ($fields as $f) {
            $out[$f] = array_key_exists($f, $settings)
                ? (string)$settings[$f]
                : (string)$this->config->get($f, '');
        }

        return [$out, []];
    }

    /**
     * Sanitização: trim + cast + coerção numérica onde convém. Não valida aqui
     * (validate é separado para o template renderizar erro por campo).
     *
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function sanitize(array $post): array {
        $str = static function (string $k, int $max = 255) use ($post): string {
            return mb_substr(trim((string)($post[$k] ?? '')), 0, $max);
        };

        return [
            'config_name'             => $str('config_name', 64),
            'config_meta_title'       => $str('config_meta_title', 128),
            'config_meta_description' => $str('config_meta_description', 255),
            'mail_engine'             => $str('mail_engine', 32),
            'mail_smtp_hostname'      => $str('mail_smtp_hostname', 128),
            'mail_smtp_username'      => $str('mail_smtp_username', 128),
            'mail_smtp_password'      => (string)($post['mail_smtp_password'] ?? ''), // sem trim (espaços fazem parte)
            'mail_smtp_port'          => $str('mail_smtp_port', 5),
            'mail_smtp_timeout'       => $str('mail_smtp_timeout', 3),
            'mail_reply_to'           => $str('mail_reply_to', 96),
            'cache_engine'            => $str('cache_engine', 16),
            'cache_expire'            => $str('cache_expire', 10),
        ];
    }

    /**
     * @param array<string, string> $post
     * @return array<string, string> field → mensagem de erro
     */
    private function validate(array $post): array {
        $errors = [];

        if ($post['config_name'] === '') {
            $errors['config_name'] = $this->language->get('error_config_name');
        }

        if (!in_array($post['mail_engine'], ['mail', 'smtp'], true)) {
            $errors['mail_engine'] = $this->language->get('error_mail_engine');
        }

        if ($post['mail_engine'] === 'smtp') {
            if ($post['mail_smtp_hostname'] === '') {
                $errors['mail_smtp_hostname'] = $this->language->get('error_smtp_hostname_required');
            }

            if (!in_array((int)$post['mail_smtp_port'], [25, 465, 587, 2525], true)) {
                $errors['mail_smtp_port'] = $this->language->get('error_smtp_port');
            }

            if ((int)$post['mail_smtp_timeout'] < 1 || (int)$post['mail_smtp_timeout'] > 60) {
                $errors['mail_smtp_timeout'] = $this->language->get('error_smtp_timeout');
            }
        }

        if ($post['mail_reply_to'] !== '' && !filter_var($post['mail_reply_to'], FILTER_VALIDATE_EMAIL)) {
            $errors['mail_reply_to'] = $this->language->get('error_mail_reply_to');
        }

        if (!in_array($post['cache_engine'], ['file', 'apc', 'memcached', 'redis'], true)) {
            $errors['cache_engine'] = $this->language->get('error_cache_engine');
        }

        if ((int)$post['cache_expire'] < 60 || (int)$post['cache_expire'] > 86400 * 30) {
            $errors['cache_expire'] = $this->language->get('error_cache_expire');
        }

        return $errors;
    }

    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }

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
}
