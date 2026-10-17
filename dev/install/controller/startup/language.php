<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/language (Adendo F2-3 do ADR 0001): idioma do wizard.
 * Idêntica às irmãs (catalog/admin) por contrato; aqui NUNCA encosta no banco
 * (db_autostart=false deliberado nesta app — o wizard ainda não viu instalação).
 * Ordem: ?language=codigo validado → sessão → padrão (en-us).
 */
namespace Reamur\Install\Controller\Startup;

class Language extends \Reamur\System\Engine\Controller {

    /** Escolhe e grava o idioma ativo (silent fallback — nunca explode erro) */
    public function index(): void {
        $default = (string)$this->config->get('language_directory', 'en-us');

        $session = $this->registry->get('session');
        $stored  = ($session instanceof \Reamur\System\Library\Session)
            ? (string)($session->data['language'] ?? '')
            : '';

        $requested = strtolower(preg_replace(
            '/[^a-zA-Z-]/',
            '',
            (string)($this->request->get['language'] ?? '')
        ));

        if ($requested !== '' && is_dir(DIR_APPLICATION . 'language/' . $requested)) {
            $code = $requested;
        } else {
            $code = ($stored !== '') ? $stored : $default;
        }

        if ($session instanceof \Reamur\System\Library\Session && $stored !== $code) {
            $session->data['language'] = $code;
        }

        // Substitui o idioma montado no framework pelo escolhido para a requisição
        $this->registry->set('language', new \Reamur\System\Library\Language($code));

        $this->config->set('language_directory', $code);
    }
}
