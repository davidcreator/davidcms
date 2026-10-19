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
