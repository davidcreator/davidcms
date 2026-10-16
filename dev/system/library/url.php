<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Construção de URLs da aplicação (Arquitetura-MVCL §6).
 * Base: HTTP_SERVER (definido pelo config.php gerado ou pelo Adendo F1-4).
 * Reescritores (SEO URL) se registram com addRewrite — Fase 2 para catalog.
 */
namespace Reamur\System\Library;

final class Url {
    /** @var array<int, callable> */
    private array $rewrite = [];

    public function __construct(private string $url) {}

    public function addRewrite(callable $rewrite): void {
        $this->rewrite[] = $rewrite;
    }

    /**
     * Monta index.php?route=grupo/modulo&args.
     *
     * @param string|array<string, mixed> $args Query string pronta ou array de parâmetros
     */
    public function link(string $route, string|array $args = '', bool $secure = false): string {
        $url = $this->url . 'index.php?route=' . $route;

        if ($args) {
            $url .= '&' . (is_array($args) ? http_build_query($args) : ltrim($args, '&'));
        }

        foreach ($this->rewrite as $rewrite) {
            $url = (string)$rewrite($url);
        }

        return $url;
    }
}
