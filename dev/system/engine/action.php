<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Resolve rota em classe+método executáveis (Arquitetura-MVCL §6).
 *
 * Gramática: "grupo/modulo" · "grupo/modulo.metodo" · "grupo/modulo.metodo|arg1|arg2"
 * ex.: 'install/step_3.save|draft' → Reamur\Install\Controller\Install\Step_3::save('draft')
 */
namespace Reamur\System\Engine;

final class Action {
    private string $route;
    private string $method;

    /** @var array<int, string> */
    private array $args;

    public function __construct(string $route) {
        $parsed = self::parse($route);

        $this->route  = $parsed['route'];
        $this->method = $parsed['method'];
        $this->args   = $parsed['args'];
    }

    /**
     * Fonte ÚNICA da leitura de uma rota (ADR 0087, S1): quem decide permissão
     * e quem executa precisam chegar ao MESMO controller. Qualquer checagem
     * por rota deve usar `(new Action($rota))->getRoute()`, nunca uma limpeza
     * própria.
     *
     * Entrada com '..' NÃO é consertada: antes 'user/us..er' virava
     * 'user/user' e uma checagem que cortasse no primeiro '.' via 'user/us'.
     * Agora a rota fica vazia, `isCallable()` devolve false e o dispatcher
     * entrega o 404 da aplicação.
     *
     * @return array{route: string, method: string, args: array<int, string>}
     */
    public static function parse(string $route): array {
        $route = trim($route);

        if (str_contains($route, '..')) {
            return ['route' => '', 'method' => 'index', 'args' => []];
        }

        $parts       = explode('|', $route);
        $routeMethod = explode('.', (string)array_shift($parts), 2);

        return [
            'route'  => (string)preg_replace('/[^a-zA-Z0-9_\/]/', '', $routeMethod[0]),
            'method' => (string)($routeMethod[1] ?? 'index'),
            'args'   => array_values($parts),
        ];
    }

    public function getRoute(): string {
        return $this->route;
    }

    public function getMethod(): string {
        return $this->method;
    }

    /** @return array<int, string> */
    public function getArgs(): array {
        return $this->args;
    }

    /** 'install/step_1' → 'Reamur\Install\Controller\Install\Step_1' (underscores preservados) */
    public function getClass(): string {
        return 'Reamur\\' . APPLICATION . '\\Controller\\' . implode(
            '\\',
            array_map('ucfirst', explode('/', $this->route))
        );
    }

    public function getFile(): string {
        return DIR_APPLICATION . 'controller/' . $this->route . '.php';
    }

    /** Arquivo existe e o método não é privado-convencionado (prefixo _) */
    public function isCallable(): bool {
        return $this->route !== '' && is_file($this->getFile()) && !str_starts_with($this->method, '_');
    }

    /**
     * Instancia o controller com o MESMO registry compartilhado e chama o método.
     *
     * @param array<int, mixed> $args Argumentos explícitos (têm prioridade sobre os '|' da rota)
     */
    public function execute(Registry $registry, array $args = []): mixed {
        if (!$this->isCallable()) {
            throw new \Exception(sprintf('Route not found: %s.%s', $this->route, $this->method));
        }

        $class = $this->getClass();

        if (!class_exists($class)) {
            throw new \Exception(sprintf('Controller class not found: %s (route "%s")', $class, $this->route));
        }

        $controller = new $class($registry);

        if (!is_callable([$controller, $this->method])) {
            throw new \Exception(sprintf('Method not callable: %s::%s', $class, $this->method));
        }

        return $controller->{$this->method}(...($args ?: $this->args));
    }
}
