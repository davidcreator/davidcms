<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * setting/appearance — F8-V (ADR 0047): Aparência do Painel.
 * Dois escopos numa mesma tela:
 *   · "Padrão do painel" (global, rms_setting) — exige modify setting/appearance;
 *   · "Minha aparência"    (rms_user.theme_prefs) — qualquer usuário logado
 *     (rota na allowlist do middleware, mesma classe de user/profile).
 * Accent custom: validado WCAG AA server-side com trava (≥4.5:1), com sugestão
 * automática de variante para o modo oposto.
 */
namespace Reamur\Admin\Controller\Setting;

class Appearance extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('setting/appearance');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/appearance');

        $data = $this->baseData();

        $data['can_edit_global'] = $this->user->hasPermission('modify', 'setting/appearance');

        // ── POST ──
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $scope = (string)($this->request->post['scope'] ?? 'user');

            $merged = $this->readFormTheme();
            $errors = $this->model_setting_appearance->validateAccentPair($merged['accent'], $merged['accent_dark']);

            if ($errors !== []) {
                $data['error'] = implode("\n", array_map(
                    fn(string $e): string => $this->language->get('error_' . $e),
                    $errors
                ));
            } else {
                $merged = $this->model_setting_appearance->sanitize($merged);

                if ($scope === 'global' && $data['can_edit_global']) {
                    $this->load->model('setting/setting');
                    $this->model_setting_setting->editSettings([
                        'config_admin_skin'            => $merged['skin'],
                        'config_admin_accent'          => $merged['accent'],
                        'config_admin_accent_dark'     => $merged['accent_dark'],
                        'config_admin_sidebar_variant' => $merged['sidebar'],
                        'config_admin_density'         => $merged['density'],
                        'config_admin_font_scale'      => $merged['font_scale'],
                        'config_admin_theme_mode'      => $merged['mode'],
                    ]);

                    // settings são cacheadas (CACHE_KEY setting.config; ver F8 setup)
                    if (method_exists($this->cache, 'delete')) {
                        $this->cache->delete('setting.config');
                    }

                    $this->setFlash($this->language->get('text_success_global'));
                } elseif ($scope === 'user') {
                    $prefs = $merged;
                    $prefs['override'] = (($this->request->post['user_override'] ?? '') === '1');
                    $this->user->saveThemePrefs($prefs);
                    $this->setFlash($this->language->get('text_success_user'));
                } else {
                    $data['error'] = $this->language->get('error_modify_denied');
                }
            }
        }

        // ── dados do form (pós-POST reflete o que veio; na 1a carga, o atual) ──
        $postedTheme = ($this->request->server['REQUEST_METHOD'] === 'POST') ? $this->readFormTheme() : null;

        $data['global_theme'] = $this->model_setting_appearance->resolveTheme([]);
        $data['user_prefs']   = $this->user->getThemePrefs();
        $data['user_theme']   = $this->model_setting_appearance->resolveTheme($data['user_prefs']);
        if ($postedTheme) {
            $data['user_theme'] = $this->model_setting_appearance->sanitize($postedTheme);
        }
        $data['user_override_on'] = !empty($data['user_prefs']['override']);

        $data['skins']   = $this->skinCards();
        $data['accents'] = \Reamur\Admin\Model\Setting\Appearance::CURATED_ACCENTS;

        $data['action']    = $this->url->link('setting/appearance');
        $data['text_flash'] = $this->flash();

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/appearance', $data));
    }

    /** Toggle da topbar salva o MODO do usuário (AJAX POST + CSRF) */
    public function saveMode(): void {
        $ok = false;
        $mode = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST'
            && $this->user instanceof \Reamur\System\Library\User && $this->user->isLogged()) {
            $csrfOk = \Reamur\System\Library\Security\Csrf::check(
                $this->session,
                (string)($this->request->post['csrf_token'] ?? ($this->request->post['csrf_token_ajax'] ?? ''))
            );

            $mode = (string)($this->request->post['mode'] ?? '');

            if ($csrfOk && in_array($mode, \Reamur\Admin\Model\Setting\Appearance::MODES, true)) {
                $prefs = $this->user->getThemePrefs();
                $prefs['mode'] = $mode;
                if (empty($prefs['override'])) {
                    $prefs['override'] = true; // tocar no modo já é uma preferência pessoal
                }
                $ok = $this->user->saveThemePrefs($prefs);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput((string)json_encode(['ok' => $ok, 'mode' => $mode]));
    }

    /**
     * Lê o tema postado no form (chaves únicas compartilhadas pelos dois escopos).
     *
     * @return array<string, string>
     */
    private function readFormTheme(): array {
        $p = $this->request->post;

        $accentChoice = (string)($p['accent_choice'] ?? ''); // '' | curated id | 'custom'
        $curated = \Reamur\Admin\Model\Setting\Appearance::CURATED_ACCENTS[$accentChoice] ?? null;

        if ($curated !== null) {
            $accent      = $curated['light'];
            $accent_dark = $curated['dark'];
        } else {
            $accent      = trim((string)($p['accent_custom'] ?? ''));
            $accent_dark = trim((string)($p['accent_custom_dark'] ?? ''));
            // normaliza #rgb → #rrggbb
            foreach (['accent', 'accent_dark'] as $k) {
                $v = ${$k};
                if (preg_match('/^#([0-9a-fA-F]{3})$/', $v, $m)) {
                    $h = $m[1];
                    $v = '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
                    ${$k} = $v;
                }
            }
        }

        return [
            'skin'       => (string)($p['skin'] ?? ''),
            'accent'     => strtolower($accent),
            'accent_dark' => strtolower($accent_dark),
            'sidebar'    => (string)($p['sidebar'] ?? ''),
            'density'    => (string)($p['density'] ?? ''),
            'font_scale' => (string)($p['font_scale'] ?? ''),
            'mode'       => (string)($p['mode'] ?? ''),
        ];
    }

    /** Cards de skin com swatches para o radio-group da tela */
    private function skinCards(): array {
        // swatches visuais por skin (tonalidade principal da sidebar+a accent)
        $preview = [
            'light'    => ['accent' => '#0d6efd', 'sidebar' => '#212529', 'surface' => '#ffffff'],
            'ocean'    => ['accent' => '#1677a8', 'sidebar' => '#0b2233', 'surface' => '#f4fafd'],
            'forest'   => ['accent' => '#1a7431', 'sidebar' => '#14261a', 'surface' => '#f3faf4'],
            'plum'     => ['accent' => '#7b4397', 'sidebar' => '#271730', 'surface' => '#fbf5fd'],
            'slate'    => ['accent' => '#46586b', 'sidebar' => '#2b3138', 'surface' => '#f7f8f9'],
            'contrast' => ['accent' => '#0039c9', 'sidebar' => '#000000', 'surface' => '#ffffff'],
        ];

        $skins = [];

        foreach (\Reamur\Admin\Model\Setting\Appearance::SKINS as $id) {
            $skins[] = [
                'id'    => $id,
                'label' => $this->language->get('skin_' . $id),
                'desc'  => $this->language->get('skin_' . $id . '_desc'),
                'preview' => $preview[$id] ?? $preview['light'],
            ];
        }

        return $skins;
    }

    private function baseData(): array {
        $keys = [
            'heading_title', 'text_intro', 'text_global_section', 'text_user_section',
            'text_global_locked', 'text_apply_override', 'text_override_hint',
            'text_skin_card', 'text_accent_card', 'text_layout_card',
            'text_mode', 'text_mode_system', 'text_mode_light', 'text_mode_dark',
            'text_sidebar', 'text_sidebar_auto', 'text_sidebar_light', 'text_sidebar_dark',
            'text_density', 'text_density_comfortable', 'text_density_compact',
            'text_font_scale', 'text_font_95', 'text_font_100', 'text_font_105',
            'text_accent_skin', 'text_accent_custom', 'text_accent_custom_dark',
            'text_accent_help', 'text_validating_contrast', 'text_contrast_fail',
            'button_save_global', 'button_save_user', 'button_reset_mine',
            'help_accent_dark',
        ];

        $data = [];

        foreach ($keys as $k) { $data[$k] = $this->language->get($k); }

        $data['error'] = '';

        // fallback i18n p/ o validador do cliente (appearance.js)
        $data['text_contrast_fail_js'] = $this->language->get('text_contrast_fail');

        return $data;
    }

    /** Leia-uma-vez do flash da sessão */
    private function flash(): array {
        $data = ['type' => '', 'text' => ''];

        if (!empty($this->session->data['flash'])) {
            $data         = (array)$this->session->data['flash'];
            $data['type'] = (string)($data['type'] ?? 'success');
            $data['text'] = (string)($data['text'] ?? '');

            unset($this->session->data['flash']);
        }

        return $data;
    }

    private function setFlash(string $text, string $type = 'success'): void {
        $this->session->data['flash'] = ['type' => $type, 'text' => $text];
    }

    /** Chrome compartilhado (F8-01) */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['header'] = $this->load->controller('common/header', $data);
        $data['footer'] = $this->load->controller('common/footer', $data);
    }
}
