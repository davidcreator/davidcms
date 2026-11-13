<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Extensão "Tag do Pinterest" (EX-02, ADR 0096).
 *
 * Declara `consent: ["marketing"]` no manifesto: o site só carrega este
 * arquivo para o visitante que aceitou cookies de publicidade. Nenhum dado do
 * visitante é passado em `load` (sem "enhanced match").
 */
namespace Reamur\Extension\PinterestTag;

use Reamur\System\Library\Extension\PageOutput;

final class Extension extends \Reamur\System\Library\Extension\Extension {

    /** Aviso de formulário enviado => valor de lead_type no evento lead. */
    public const LEADS = [
        PageOutput::SIGNAL_CONTACT => 'contact',
        PageOutput::SIGNAL_QUOTE   => 'quote',
    ];

    /** Evento page/output: uma vez por página do site público. */
    public function onPageOutput(PageOutput $page): void {
        $id = (string)$this->setting('tag_id');

        if (preg_match('/^[0-9]{10,20}$/D', $id) !== 1) {
            return;
        }

        // A fila `pintrk` do código-base do Pinterest; a biblioteca é pedida logo abaixo.
        $code = '(function(w){if(w.pintrk){return;}w.pintrk=function(){w.pintrk.queue.push(Array.prototype.slice.call(arguments));};'
            . "w.pintrk.queue=[];w.pintrk.version='3.0';})(window);"
            . "pintrk('load'," . PageOutput::js($id) . ");pintrk('page');";

        if ($this->setting('form_events', true) === true) {
            foreach (self::LEADS as $signal => $type) {
                if ($page->happened($signal)) {
                    $code .= "pintrk('track','lead',{lead_type:" . PageOutput::js($type) . '});';
                }
            }
        }

        $page->script($code);
        $page->scriptSrc('https://s.pinimg.com/ct/core.js', ['async' => true]);
    }
}
