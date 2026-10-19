<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/language (Adendo F2-3 do ADR 0001): idioma da requisição.
 * Idêntica às irmãs (catalog/install) por contrato — roda ANTES da muralha
 * (startup/login) para que login/erros já saiam no idioma do visitante.
 * Ordem: ?language=codigo validado → sessão → padrão da app (en-us).
 */
namespace Reamur\Admin\Controller\Startup;

class Language extends \Reamur\System\Engine\Controller {

    /**
     * Escolhe e grava o idioma ativo (silent fallback — nunca explode erro).
     * F4-02 (ADR 0025): gate = status=1 + dir existe (governado pela tela
     * localization/language); default = config_language_admin do painel
     * (rms_setting injetada pela pre-ação startup/setting, que vem antes).
     */
    public function index(): void {
        $this->load->model('localization/language');

        $enabled = $this->model_localization_language->getEnabledCodes();

        $default = (string)$this->config->get(
            'config_language_admin',
            (string)$this->config->get('language_directory', 'en-us')
        );

        if (!in_array($default, $enabled, true)) {
            $default = (string)($enabled[0] ?? 'en-us');
        }

        $session = $this->registry->get('session');
        $stored  = ($session instanceof \Reamur\System\Library\Session)
            ? (string)($session->data['language'] ?? '')
            : '';

        $requested = strtolower(preg_replace(
            '/[^a-zA-Z-]/',
            '',
            (string)($this->request->get['language'] ?? '')
        ));

        if ($requested !== '' && in_array($requested, $enabled, true)) {
            $code = $requested;
        } elseif ($stored !== '' && in_array($stored, $enabled, true)) {
            $code = $stored;
        } else {
            $code = $default;
        }

        if ($session instanceof \Reamur\System\Library\Session && $stored !== $code) {
            $session->data['language'] = $code;
        }

        // Substitui o idioma montado no framework pelo escolhido para a requisição
        $this->registry->set('language', new \Reamur\System\Library\Language($code));

        $this->config->set('language_directory', $code);
    }
}
