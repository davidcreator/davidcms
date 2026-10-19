<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/setting: carrega rms_setting code='config' (F4-01,
 * ADR 0024) e sobrescreve o Config da request corrente. Efeito imediato:
 * meta title/description do document, mail_* (quando a engine mail ganhar
 * consumidor), demais chaves operacionais. Sem isso, o que o painel grava
 * ficaria inerte. Chaves sensíveis (sessão/db) NÃO são importadas — allowlist.
 */
namespace Reamur\Catalog\Controller\Startup;

class Setting extends \Reamur\System\Engine\Controller {

    /** Chaves que o painel pode injetar no boot — sessão/db/cache-path ficam de fora por segurança */
    private const ALLOWED = [
        'config_name', 'config_meta_title', 'config_meta_description',
        'mail_engine', 'mail_smtp_hostname', 'mail_smtp_username',
        'mail_smtp_password', 'mail_smtp_port', 'mail_smtp_timeout',
        'mail_reply_to',
        'cache_engine', 'cache_expire',
        // F4-02 (ADR 0025): injeta também os idiomas governados pela tela
        // localization/language — catalog usa o default de catálogo
        'config_language_catalog',

        // F4-03 (ADR 0026): informações do site (tela setting/site_info)
        'config_contact_email', 'config_contact_whatsapp_text', 'config_contact_whatsapp_href',
        'config_contact_address_en-us', 'config_contact_address_pt-br', 'config_contact_address_es-eu',
        'config_copyright_en-us', 'config_copyright_pt-br', 'config_copyright_es-eu',
        'config_social_facebook_href', 'config_social_instagram_href', 'config_social_pinterest_href',
        'config_social_x_href', 'config_social_google_business_href', 'config_social_redbubble_href',
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
                continue; // default do arquivo permanece em vez de apagar
            }

            $this->config->set($key, $value);
        }
    }
}
