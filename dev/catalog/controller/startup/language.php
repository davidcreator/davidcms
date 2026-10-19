<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/language (Adendo F2-3 do ADR 0001): idioma da requisição.
 * Ordem de decisão: ?language=codigo validado contra os diretórios existentes
 * → persiste na sessão; senão sessão; senão o padrão da app (en-us).
 * Substitui o serviço language montado pelo framework (contrato estável).
 * Não toca no banco: a tabela rms_language entra na governança da UI de
 * idiomas (tarefa futura), aqui o filesystem é a fonte da verdade.
 */
namespace Reamur\Catalog\Controller\Startup;

class Language extends \Reamur\System\Engine\Controller {

    /**
     * Escolhe e grava o idioma ativo (silent fallback — nunca explode erro).
     *
     * F4-02 (ADR 0025): o gate deixa de ser “dir existe” para ser
     * “status=1 na rms_language E dir existe” — a tela localization/language
     * governa o que é publicável. O default também deixa de ser só o
     * language_directory do configfile: primeiro vale config_language_catalog
     * (persistido em rms_setting via F4-01 + injetado no Config pela pre-ação
     * startup/setting, que agora roda ANTES desta).
     */
    public function index(): void {
        $this->load->model('localization/language');

        $enabled = $this->model_localization_language->getEnabledCodes();

        // Default resolvido: config gravada no painel → cair p/ o configfile
        $default = (string)$this->config->get(
            'config_language_catalog',
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
            $code = $stored; // sessão antiga vale, mas só se o idioma continuou ativo
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
