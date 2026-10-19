<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/setting do ADMIN: idem catalog (F4-01, ADR 0024) — o painel
 *mostra os valores configurados sem precisar reler a tabela em cada controller.
 */
namespace Reamur\Admin\Controller\Startup;

class Setting extends \Reamur\System\Engine\Controller {

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
                continue;
            }

            $this->config->set($key, $value);
        }
    }
}
