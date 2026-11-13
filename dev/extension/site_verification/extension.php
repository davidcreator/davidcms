<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Extensão "Verificação de site" (EX-01, ADR 0095): primeira extensão real e
 * exemplo mínimo para quem vai escrever a sua (docs/guias/Guia-Extensoes.md).
 *
 * Põe no <head> das páginas públicas as meta tags com que Google Search
 * Console, Bing Webmaster Tools, Pinterest e Meta confirmam quem é o dono do
 * site. Cada código vem do formulário gerado a partir do extension.json, que
 * só aceita letras, dígitos, `_` e `-`. Não carrega script, não grava cookie
 * e não faz pedido a outro site: por isso não declara nenhuma origem de fora.
 */
namespace Reamur\Extension\SiteVerification;

use Reamur\System\Library\Extension\PageOutput;

final class Extension extends \Reamur\System\Library\Extension\Extension {

    /** campo de configuração => nome da meta tag */
    public const TAGS = [
        'google'    => 'google-site-verification',
        'bing'      => 'msvalidate.01',
        'pinterest' => 'p:domain_verify',
        'meta'      => 'facebook-domain-verification',
    ];

    /** Evento page/output: uma vez por página do site público. */
    public function onPageOutput(PageOutput $page): void {
        foreach (self::TAGS as $setting => $name) {
            $code = (string)$this->setting($setting);

            if ($code !== '') {
                $page->meta($name, $code);
            }
        }
    }
}
