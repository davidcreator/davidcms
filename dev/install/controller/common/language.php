<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Seletor de idioma do wizard (ADR 0007): dropdown Bootstrap com bandeiras.
 * Troca via ?language=codigo — a pré-ação startup/language (Adendo F2-3 do
 * ADR 0001) já grava a sessão; aqui só montamos os hrefs preservando a rota.
 */
namespace Reamur\Install\Controller\Common;

class Language extends \Reamur\System\Engine\Controller {

    /** Rótulos nativos estáveis (o idioma atual pode ser outro — bandeira diz o resto) */
    private const NAMES = [
        'en-us' => 'English',
        'pt-br' => 'Português',
        'es-eu' => 'Español',
    ];

    public function index(array $args = []): string {
        $this->load->language('common/language');

        $code = (string)$this->config->get('language_directory', 'en-us');

        $data['code']          = $code;
        $data['text_language'] = $this->language->get('text_language');

        $route = (string)($this->request->get['route'] ?? 'install/step_1');

        $data['languages'] = [];

        foreach ((array)glob(DIR_APPLICATION . 'language/*', GLOB_ONLYDIR) as $dir) {
            $dirCode = basename((string)$dir);

            if (!is_file(DIR_APPLICATION . 'language/' . $dirCode . '/common/header.php')) {
                continue; // diretório sem idioma à vista (index.html solto) não entra
            }

            $data['languages'][] = [
                'code'   => $dirCode,
                'text'   => self::NAMES[$dirCode] ?? $dirCode,
                'flag'   => 'language/' . $dirCode . '/' . $dirCode . '.png',
                'active' => $dirCode === $code,
                'href'   => $this->url->link($route, ['language' => $dirCode]),
            ];
        }

        return $this->load->view('common/language', $data);
    }
}
