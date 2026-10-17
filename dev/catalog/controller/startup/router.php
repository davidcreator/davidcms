<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Ação de rotas do catalog (framework executa quando não veio ?route= na URL
 * — config action_router, ADR 0006). Contrato: devolve 'grupo/modulo' ou ''
 * (→ rota padrão da app).
 * Convenções (ADR 0006):
 *  - caminho limpo = rota direta (ex.: /common/home · /error/not_found); a
 *    validação do arquivo cabe ao dispatcher → 404 honesto em rota inexistente;
 *  - aliases curtos declarados em self::ALIASES (hoje: nenhum — a home é
 *    atendida pela rota padrão; caminho explícito continua valendo);
 *  - traversal e caracteres fora do alfabeto de rotas são removidos (mesma
 *    sanção da Action);
 *  - links internos SAEM limpos: reescrita transparente em Url::addRewrite
 *    (decisão reversível na tabela rms_seo_url — Fase 3).
 */
namespace Reamur\Catalog\Controller\Startup;

class Router extends \Reamur\System\Engine\Controller {

    /** @var array<string, string> caminho limpo curto → rota interna longa */
    private const ALIASES = [];

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
        // fonte única e sensível ao deploy real — funciona em qualquer SAPI
        // (php -S dev-server altera SCRIPT_NAME por requisição; dele NÃO depende).
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
     * Reescrita transparente de links internos:
     * 'index.php?route=grupo/modulo&x=y' → 'grupo/modulo?x=y' (aliás curto quando houver).
     * Chamada pelo serviço Url em cada link montado (só desta requisição).
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
