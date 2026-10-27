<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/setting (ADMIN) — chaves operacionais persistidas em rms_setting
 * (F4-01, ADR 0024): code='config'. Sobrescrevem, no boot, os defaults de
 * system/config + config.php gerado. Sem inventar tabela nova.
 */
namespace Reamur\Admin\Model\Setting;

class Setting extends \Reamur\System\Engine\Model {

    private const CACHE_KEY = 'setting.config';
    private const CODE      = 'config';

    /**
     * Todas as chaves do code 'config' (mapa key=>value pronto para merge).
     *
     * @return array<string, string>
     */
    public function getSettings(): array {
        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $rows  = $this->db->query(
            "SELECT `key`, `value`, `serialized` FROM `" . $this->t('setting') . "` WHERE `code` = '" . self::CODE . "'"
        );

        $map = [];

        if ($rows instanceof \stdClass && !empty($rows->rows)) {
            foreach ($rows->rows as $row) {
                $row = (array)$row;
                $map[(string)$row['key']] = $this->unserialize((string)$row['value'], (int)$row['serialized']);
            }
        }

        $this->cache->set(self::CACHE_KEY, $map, 3600);

        return $map;
    }

    /**
     * @param array<string, string> $data Novo mapa completo (keys são validadas pela ALLOWLIST)
     */
    public function editSettings(array $data): void {
        // Allowlist: sem espaço p/ chave arbitrária (SQL/engine nunca vê chave estranha)
        $allow = [
            'config_name', 'config_meta_title', 'config_meta_description',
            'mail_engine', 'mail_smtp_hostname', 'mail_smtp_username',
            'mail_smtp_password', 'mail_smtp_port', 'mail_smtp_timeout',
            'mail_reply_to',
            'cache_engine', 'cache_expire',
            // F4-02 (ADR 0025): idioma padrão por aplicação
            'config_language_catalog', 'config_language_admin',

            // F4-03 (ADR 0026): informações do site (tela setting/site_info)
            'config_contact_email', 'config_contact_whatsapp_text', 'config_contact_whatsapp_href',
            'config_contact_address_en-us', 'config_contact_address_pt-br', 'config_contact_address_es-eu',
            'config_copyright_en-us', 'config_copyright_pt-br', 'config_copyright_es-eu',
            'config_social_facebook_href', 'config_social_instagram_href', 'config_social_pinterest_href',
            'config_social_x_href', 'config_social_google_business_href', 'config_social_redbubble_href',
            // F5-02 (ADR 0034): app do Mercado Pago Marketplace — credenciais gravadas
            // no banco via painel, NUNCA versionadas no git
            'mp_app_id', 'mp_client_secret', 'mp_env', 'mp_redirect_uri',
            // costuras de ambiente (ADR 0034 §4): não expostas em tela; existem p/
            // baterias e2e e manutenção apontarem rotas alternativas (mock/sandbox)
            'mp_token_url', 'mp_users_me_url',
            // F8-V (ADR 0047): aparência do painel (padrão global; override por usuário em rms_user.theme_prefs)
            'config_admin_skin', 'config_admin_accent', 'config_admin_accent_dark',
            'config_admin_sidebar_variant', 'config_admin_density',
            'config_admin_font_scale', 'config_admin_theme_mode',

            // Three.js (ADRs 0072+): configuração declarativa allowlisted das cenas nativas
            'three_hero_enabled', 'three_hero_particle_count', 'three_hero_color', 'three_hero_speed',
            'three_not_found_enabled', 'three_portfolio_enabled', 'three_portfolio_frame_limit', 'three_tools_enabled',
        ];

        foreach ($data as $key => $value) {
            $key = (string)$key;

            if (!in_array($key, $allow, true)) {
                continue;
            }

            $serialized = is_array($value) ? 1 : 0;
            $v = $this->db->escape($serialized ? serialize($value) : (string)$value);

            $this->db->query(
                "UPDATE `" . $this->t('setting') . "`
                    SET `value` = '" . $v . "',
                        `serialized` = '" . $serialized . "'
                  WHERE `code` = '" . self::CODE . "' AND `key` = '" . $this->db->escape($key) . "'"
            );

            // INSERT ignorando duplicata: idempotente contra upgrade parcial
            $this->db->query(
                "INSERT INTO `" . $this->t('setting') . "` (`code`, `key`, `value`, `serialized`)
                 SELECT '" . self::CODE . "', '" . $this->db->escape($key) . "', '" . $v . "', '" . $serialized . "'
                 WHERE NOT EXISTS (
                    SELECT 1 FROM `" . $this->t('setting') . "` WHERE `code` = '" . self::CODE . "' AND `key` = '" . $this->db->escape($key) . "'
                 )"
            );
        }

        $this->cache->delete(self::CACHE_KEY);
    }

    private function unserialize(string $value, int $serialized): string {
        return $serialized
            ? (string)unserialize($value, ['allowed_classes' => false])
            : $value;
    }

    /** Tabela com prefixo (mesmo helper dos demais models F2) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
