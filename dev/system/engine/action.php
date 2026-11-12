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
     * Resolve a rota até um controller pronto para ser chamado, SEM chamá-lo.
     *
     * ADR 0088 (S4): separa as duas perguntas que antes davam a mesma resposta.
     * "Essa URL corresponde a uma tela?" é respondida aqui, e o não é sempre
     * um NotFoundException. "A tela funcionou?" só é respondida quando o
     * chamável devolvido roda. O Router usa essa fronteira para responder 404
     * num caso e 500 no outro.
     *
     * Erro de sintaxe no arquivo do controller ou exceção no construtor NÃO
     * são NotFoundException: a rota existe e está quebrada.
     *
     * @param array<int, mixed> $args Argumentos explícitos (têm prioridade sobre os '|' da rota)
     * @return \Closure(): mixed
     * @throws NotFoundException
     */
    public function prepare(Registry $registry, array $args = []): \Closure {
        if (!$this->isCallable()) {
            throw new NotFoundException(sprintf('Route not found: %s.%s', $this->route, $this->method));
        }

        $class = $this->getClass();

        if (!class_exists($class)) {
            throw new NotFoundException(sprintf('Controller class not found: %s (route "%s")', $class, $this->route));
        }

        $controller = new $class($registry);
        $method     = $this->method;

        if (!is_callable([$controller, $method])) {
            throw new NotFoundException(sprintf('Method not callable: %s::%s', $class, $method));
        }

        // Argumentos vindos da URL ('rota|a|b') são sempre texto. Se o método
        // exige mais argumentos, ou exige outro tipo, a URL não corresponde a
        // essa tela: 404, como já era (antes por um TypeError capturado).
        // Argumentos explícitos vêm do código (load->controller); ali um tipo
        // errado é defeito do sistema e segue como erro.
        if ($args === [] && !self::acceptsUrlArguments($controller, $method, $this->args)) {
            throw new NotFoundException(sprintf('Arguments do not match: %s::%s', $class, $method));
        }

        $call = $args ?: $this->args;

        return static fn (): mixed => $controller->{$method}(...$call);
    }

    /**
     * Instancia o controller com o MESMO registry compartilhado e chama o método.
     *
     * @param array<int, mixed> $args Argumentos explícitos (têm prioridade sobre os '|' da rota)
     * @throws NotFoundException quando a rota, o método ou os argumentos da URL não correspondem a nada chamável
     */
    public function execute(Registry $registry, array $args = []): mixed {
        return $this->prepare($registry, $args)();
    }

    /** @param array<int, string> $args */
    private static function acceptsUrlArguments(object $controller, string $method, array $args): bool {
        try {
            $reflection = new \ReflectionMethod($controller, $method);
        } catch (\ReflectionException $e) {
            return true; // método atendido por __call: não há assinatura para conferir
        }

        if (count($args) < $reflection->getNumberOfRequiredParameters()) {
            return false;
        }

        foreach ($reflection->getParameters() as $position => $parameter) {
            if (!$parameter->isVariadic() && !array_key_exists($position, $args)) {
                break;
            }

            if (count($args) > $position && !self::typeAcceptsString($parameter->getType())) {
                return false;
            }
        }

        return true;
    }

    private static function typeAcceptsString(?\ReflectionType $type): bool {
        if ($type === null) {
            return true;
        }

        if ($type instanceof \ReflectionNamedType) {
            return in_array(strtolower($type->getName()), ['string', 'mixed'], true);
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if (self::typeAcceptsString($member)) {
                    return true;
                }
            }
        }

        return false;
    }
}
