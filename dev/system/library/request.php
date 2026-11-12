<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Snapshot da requisição HTTP (Arquitetura-MVCL §4).
 * Uso: $this->request->get['route'] ?? '' · $this->request->post['campo'] ?? ''
 */
namespace Reamur\System\Library;

final class Request {
    /** @var array<string, mixed> */
    public array $get    = [];
    /** @var array<string, mixed> */
    public array $post   = [];
    /** @var array<string, mixed> */
    public array $cookie = [];
    /** @var array<string, mixed> */
    public array $files  = [];
    /** @var array<string, mixed> */
    public array $server = [];

    /** @var string|array<int, mixed> */
    private string|array $proxyTrusted = '';
    private int $proxyHops = 0;

    public function __construct() {
        $this->get    = $_GET;
        $this->post   = $_POST;
        $this->cookie = $_COOKIE;
        $this->files  = $_FILES;
        $this->server = $_SERVER;
    }

    /**
     * ADR 0089 (S5): em quem a instalação confia para dizer o IP do visitante
     * e o esquema da conexão. O framework chama isto uma vez, com os valores
     * de REAMUR_TRUSTED_PROXIES e REAMUR_TRUSTED_PROXY_HOPS. Sem a chamada,
     * vale a conexão direta.
     *
     * @param string|array<int, mixed> $trusted Endereços e faixas dos proxies
     * @param int                      $hops    Quantos proxies ficam na frente (só sem lista)
     */
    public function setProxyTrust(string|array $trusted, int $hops = 0): void {
        $this->proxyTrusted = $trusted;
        $this->proxyHops    = $hops;
    }

    /**
     * Leitura da conexão a partir de $this->server (lido na hora: testes e
     * pré-ações podem ter alterado o array).
     */
    public function connection(): Security\ClientAddress {
        return Security\ClientAddress::resolve($this->server, $this->proxyTrusted, $this->proxyHops);
    }

    /**
     * IP do visitante. Use SEMPRE isto no lugar de server['REMOTE_ADDR']:
     * atrás de CDN ou proxy, REMOTE_ADDR é o IP do proxy, igual para todos.
     */
    public function ip(): string {
        return $this->connection()->ip;
    }

    /** A conexão do visitante é HTTPS (direta ou informada por proxy confiável)? */
    public function isHttps(): bool {
        return $this->connection()->https;
    }
}
