<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Extensão "Google Tag Manager" (EX-02, ADR 0096).
 *
 * Declara `consent: ["analytics", "marketing"]`: o site só carrega este
 * arquivo para o visitante que aceitou pelo menos uma das duas categorias, e
 * o contêiner fica sabendo qual pelo Consent Mode. Respeitar o Consent Mode é
 * tarefa de cada etiqueta do contêiner: as do Google já o fazem; nas demais,
 * quem monta o contêiner precisa exigir o consentimento na própria etiqueta.
 *
 * Duas formas de carregar:
 *  - padrão: `<script src>` comum. Só rodam etiquetas vindas de origens que a
 *    política do site permite; script escrito dentro do contêiner é barrado.
 *  - "HTML personalizado" ligado: o carregador do Google que repassa o nonce
 *    da página ao contêiner. A partir daí, script escrito no contêiner roda.
 *
 * Variáveis de JavaScript personalizado não funcionam em nenhum dos casos:
 * dependem de `unsafe-eval`, que o site não libera (ADR 0093).
 */
namespace Reamur\Extension\GoogleTagManager;

use Reamur\System\Library\Extension\PageOutput;

final class Extension extends \Reamur\System\Library\Extension\Extension {

    /** Aviso de formulário enviado => valor de lead_source no evento generate_lead. */
    public const LEADS = [
        PageOutput::SIGNAL_CONTACT => 'contact',
        PageOutput::SIGNAL_QUOTE   => 'quote',
    ];

    /** Evento page/output: uma vez por página do site público. */
    public function onPageOutput(PageOutput $page): void {
        $id = (string)$this->setting('container_id');

        if (preg_match('/^GTM-[A-Z0-9]{4,12}$/D', $id) !== 1) {
            return;
        }

        $analytics = $page->allows('analytics') ? 'granted' : 'denied';
        $marketing = $page->allows('marketing') ? 'granted' : 'denied';

        $code = 'window.dataLayer=window.dataLayer||[];'
            . 'function gtag(){dataLayer.push(arguments);}'
            . "gtag('consent','default',{analytics_storage:'" . $analytics . "',ad_storage:'" . $marketing
            . "',ad_user_data:'" . $marketing . "',ad_personalization:'" . $marketing . "'});";

        if ($this->setting('form_events', true) === true) {
            foreach (self::LEADS as $signal => $source) {
                if ($page->happened($signal)) {
                    $code .= "dataLayer.push({event:'generate_lead',lead_source:" . PageOutput::js($source) . '});';
                }
            }
        }

        $code .= "dataLayer.push({'gtm.start':new Date().getTime(),event:'gtm.js'});";

        $src = 'https://www.googletagmanager.com/gtm.js?id=' . rawurlencode($id);

        if ($this->setting('custom_html', false) !== true) {
            $page->script($code);
            $page->scriptSrc($src, ['async' => true]);

            return;
        }

        // Carregador que repassa o nonce (o modelo do guia de CSP do Google):
        // o contêiner o copia para os scripts que ele mesmo cria.
        $code .= '(function(d){var f=d.getElementsByTagName("script")[0],j=d.createElement("script"),n=d.querySelector("script[nonce]");'
            . 'j.async=true;j.src=' . PageOutput::js($src) . ';'
            . 'if(n){j.setAttribute("nonce",n.nonce||n.getAttribute("nonce"));}'
            . 'f.parentNode.insertBefore(j,f);})(document);';

        $page->script($code);
    }
}
