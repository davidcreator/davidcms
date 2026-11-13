<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/extension (EX-01, ADR 0095): põe para rodar, no site
 * público, as extensões que o admin ligou. É a última pré-ação: configurações,
 * tema e idioma já estão resolvidos. Todo o trabalho (e todo o cuidado para
 * uma extensão com defeito não derrubar a página) está em
 * Library\Extension\Manager::boot(), que só age na primeira chamada.
 *
 * EX-02 (ADR 0096): também põe no registro o serviço `consent`, a escolha do
 * visitante sobre cookies, lida do cookie do pedido.
 */
namespace Reamur\Catalog\Controller\Startup;

class Extension extends \Reamur\System\Engine\Controller {

    public function index(): void {
        // EX-02 (ADR 0096): a escolha do visitante sobre cookies é lida aqui,
        // antes de qualquer extensão: a que declara uma categoria só entra em
        // funcionamento se ele a aceitou.
        $request = $this->registry->get('request');
        $cookies = $request instanceof \Reamur\System\Library\Request ? $request->cookie : [];

        $consent = \Reamur\System\Library\Consent::fromCookie(
            $cookies[\Reamur\System\Library\Consent::COOKIE] ?? null,
            time(),
            $this->config->get('config_consent_days')
        );

        $this->registry->set('consent', $consent);

        $manager = $this->registry->get('extensions');

        if (!$manager instanceof \Reamur\System\Library\Extension\Manager) {
            return;
        }

        $manager->boot('catalog');

        // Cookie de etiqueta recusada que ainda chega no pedido é apagado de
        // novo (ver Manager::staleCookieLines). Sem extensão que dependa de
        // aceitação, nada é conferido.
        if ($manager->consented() !== [] && $cookies !== []) {
            $host = (string)(parse_url((string)$this->config->get('site_url', ''), PHP_URL_HOST) ?: '');
            $keep = [(string)$this->config->get('session_name', 'RMSSESSION')];

            foreach ($manager->staleCookieLines($consent, array_keys($cookies), $host, $keep) as $line) {
                $this->response->addCookie($line);
            }
        }
    }
}
