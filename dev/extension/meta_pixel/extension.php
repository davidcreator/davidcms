<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Extensão "Pixel da Meta" (EX-02, ADR 0096).
 *
 * Declara `consent: ["marketing"]` no manifesto: o site só carrega este
 * arquivo para o visitante que aceitou cookies de publicidade.
 *
 * Só o pixel do navegador. A API de Conversões (envio pelo servidor) não faz
 * parte desta extensão. Nenhum dado do visitante é passado em `init`
 * (sem "advanced matching").
 */
namespace Reamur\Extension\MetaPixel;

use Reamur\System\Library\Extension\PageOutput;

final class Extension extends \Reamur\System\Library\Extension\Extension {

    /** Aviso de formulário enviado => evento padrão da Meta. */
    public const EVENTS = [
        PageOutput::SIGNAL_CONTACT => 'Contact',
        PageOutput::SIGNAL_QUOTE   => 'Lead',
    ];

    /** Evento page/output: uma vez por página do site público. */
    public function onPageOutput(PageOutput $page): void {
        $id = (string)$this->setting('pixel_id');

        if (preg_match('/^[0-9]{8,20}$/D', $id) !== 1) {
            return;
        }

        // A fila `fbq` do código-base da Meta; a biblioteca é pedida logo abaixo.
        $code = '(function(f){if(f.fbq){return;}var n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments);};'
            . "if(!f._fbq){f._fbq=n;}n.push=n;n.loaded=true;n.version='2.0';n.queue=[];})(window);";

        if ($this->setting('auto_config', false) !== true) {
            $code .= "fbq('set','autoConfig',false," . PageOutput::js($id) . ');';
        }

        $code .= "fbq('init'," . PageOutput::js($id) . ");fbq('track','PageView');";

        if ($this->setting('form_events', true) === true) {
            foreach (self::EVENTS as $signal => $event) {
                if ($page->happened($signal)) {
                    $code .= "fbq('track'," . PageOutput::js($event) . ');';
                }
            }
        }

        $page->script($code);
        $page->scriptSrc('https://connect.facebook.net/en_US/fbevents.js', ['async' => true]);
    }
}
