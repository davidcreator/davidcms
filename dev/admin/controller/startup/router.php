<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Ação de rotas do ADMIN (config action_router, ADR 0006). Idêntica à do
 * catalog por contrato; única diferença: allowlist de aliases curto e estável
 * do painel:
 *   /login  → common/login    /logout  → common/logout
 * Demais caminhos limpos mapeiam direto para a rota (/common/dashboard), e a
 * muralha/ACL (startup/login + startup/permission) continua mandando na porta.
 */
namespace Reamur\Admin\Controller\Startup;

class Router extends \Reamur\System\Engine\Controller {

    /** @var array<string, string> caminho limpo curto → rota interna longa */
    private const ALIASES = [
        'login'  => 'common/login',
        'logout' => 'common/logout',
    ];

    /** Chamado pelo framework quando não há ?route= — resolve e prepara saída limpa */
    public function index(): string {
        $url = $this->registry->get('url');

        if ($url instanceof \Reamur\System\Library\Url) {
            $url->addRewrite([$this, 'rewrite']);
        }

        return $this->resolve();
    }

    /** Converte o REQUEST_URI do pedido em rota interna */
    private function resolve(): string {
        $uri  = (string)($this->request->server['REQUEST_URI'] ?? '');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '');

        // Base da app vem do config (site_url = HTTP_SERVER gerado pelo wizard):
        // fonte única e sensível ao deploy real — funciona em qualquer SAPI.
        $base = rtrim((string)(parse_url((string)$this->config->get('site_url', ''), PHP_URL_PATH) ?: ''), '/');

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = trim($path, '/');

        if ($path === '' || strtolower($path) === 'index.php') {
            return '';
        }

        if (array_key_exists($path, self::ALIASES)) {
            return self::ALIASES[$path];
        }

        return (string)preg_replace('/[^a-zA-Z0-9_\/]/', '', $path);
    }

    /**
     * Reescrita transparente de links internos (espelha o catalog):
     * 'index.php?route=grupo/modulo&x=y' → 'grupo/modulo?x=y' com aliases curtos.
     */
    public function rewrite(string $url): string {
        return (string)preg_replace_callback(
            '#index\.php\?route=([a-zA-Z0-9_/]+)(?:&(amp;)?(.*))?#',
            function (array $m): string {
                $route = (string)$m[1];

                foreach (self::ALIASES as $clean => $long) {
                    if ($route === $long) {
                        $route = $clean;

                        break;
                    }
                }

                $query = (isset($m[3]) && $m[3] !== '') ? '?' . str_replace('&amp;', '&', $m[3]) : '';

                return $route . $query;
            },
            $url
        );
    }
}
