<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/setting do ADMIN: idem catalog (F4-01, ADR 0024) — o painel
 * mostra os valores configurados sem precisar reler a tabela em cada controller.
 * mail_* alimentam a Mail\Factory (F4-05, ADR 0029 — teste de envio).
 */
namespace Reamur\Admin\Controller\Startup;

class Setting extends \Reamur\System\Engine\Controller {

    private const ALLOWED = [
        'config_name', 'config_meta_title', 'config_meta_description',
        'mail_engine', 'mail_smtp_hostname', 'mail_smtp_username',
        'mail_smtp_password', 'mail_smtp_port', 'mail_smtp_timeout',
        'mail_reply_to',
        'cache_engine', 'cache_expire',

        // F4-02 (ADR 0025): injeta também os idiomas governados pela tela
        // localization/language — tela edita os dois aqui
        'config_language_catalog', 'config_language_admin',

        // F4-03 (ADR 0026): informações do site (tela setting/site_info)
        'config_contact_email', 'config_contact_whatsapp_text', 'config_contact_whatsapp_href',
        'config_contact_address_en-us', 'config_contact_address_pt-br', 'config_contact_address_es-eu',
        'config_copyright_en-us', 'config_copyright_pt-br', 'config_copyright_es-eu',
        'config_social_facebook_href', 'config_social_instagram_href', 'config_social_pinterest_href',
        'config_social_x_href', 'config_social_google_business_href', 'config_social_redbubble_href',

        // F8-V (ADR 0047): aparência do painel (padrão global; o override por
        // usuário NÃO entra aqui — fica em rms_user.theme_prefs)
        'config_admin_skin', 'config_admin_accent', 'config_admin_accent_dark',
        'config_admin_sidebar_variant', 'config_admin_density',
        'config_admin_font_scale', 'config_admin_theme_mode',
    ];

    public function index(): void {
        $this->load->model('setting/setting');

        foreach ($this->model_setting_setting->getSettings() as $key => $value) {
            $key = (string)$key;

            if (!in_array($key, self::ALLOWED, true)) {
                continue;
            }

            $value = (string)$value;

            if ($value === '') {
                continue;
            }

            $this->config->set($key, $value);
        }
    }
}
