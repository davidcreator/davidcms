<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Extensão "Google Analytics 4" (EX-02, ADR 0096).
 *
 * Declara `consent: ["analytics"]` no manifesto: o site só carrega este
 * arquivo para o visitante que aceitou cookies de medição. Por isso o código
 * abaixo não confere a escolha: quando ele roda, ela já foi conferida.
 *
 * Consent Mode: medição liberada; armazenamento e dados de publicidade
 * negados, e os recursos de publicidade do GA4 desligados (esta extensão não
 * declara a categoria "marketing" nem as origens que eles usariam).
 */
namespace Reamur\Extension\GoogleAnalytics;

use Reamur\System\Library\Extension\PageOutput;

final class Extension extends \Reamur\System\Library\Extension\Extension {

    /** Aviso de formulário enviado => valor de lead_source no evento generate_lead. */
    public const LEADS = [
        PageOutput::SIGNAL_CONTACT => 'contact',
        PageOutput::SIGNAL_QUOTE   => 'quote',
    ];

    /** Evento page/output: uma vez por página do site público. */
    public function onPageOutput(PageOutput $page): void {
        $id = (string)$this->setting('measurement_id');

        if (preg_match('/^G-[A-Z0-9]{4,20}$/D', $id) !== 1) {
            return;
        }

        $code = 'window.dataLayer=window.dataLayer||[];'
            . 'function gtag(){dataLayer.push(arguments);}'
            . "gtag('consent','default',{analytics_storage:'granted',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});"
            . "gtag('js',new Date());"
            . "gtag('config'," . PageOutput::js($id) . ',{allow_google_signals:false,allow_ad_personalization_signals:false});';

        if ($this->setting('form_events', true) === true) {
            foreach (self::LEADS as $signal => $source) {
                if ($page->happened($signal)) {
                    $code .= "gtag('event','generate_lead',{lead_source:" . PageOutput::js($source) . '});';
                }
            }
        }

        $page->script($code);
        $page->scriptSrc('https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($id), ['async' => true]);
    }
}
