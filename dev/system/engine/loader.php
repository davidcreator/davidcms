<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Loader unificado (Arquitetura-MVCL §5): model, view, language, controller, helper, config.
 * Dispara gatilhos before/after via Event — sem handlers, o comportamento é o padrão.
 */
namespace Reamur\System\Engine;

final class Loader {
    public function __construct(protected Registry $registry) {}

    /**
     * Registra o Proxy preguiçoso do model: 'cms/portfolio' → $this->model_cms_portfolio.
     * Não instancia nada — a classe real nasce no primeiro método chamado.
     */
    public function model(string $route): void {
        $route = $this->sanitize($route);

        $args = [$route];
        $this->fire('model/' . $route . '/before', $args);

        $key = 'model_' . str_replace('/', '_', $route);

        if (!$this->registry->has($key)) {
            $this->registry->set($key, new Proxy($this->registry, $route));
        }

        $this->fire('model/' . $route . '/after', $args);
    }

    /**
     * Renderiza um template via serviço template (engine ativa = config template_engine).
     *
     * @param array<string, mixed> $data
     */
    public function view(string $route, array $data = []): string {
        $route = $this->sanitize($route);

        $args = [$route, &$data];
        $this->fire('view/' . $route . '/before', $args);

        $output = (string)$this->registry->get('template')->render($route, $data);

        $this->fire('view/' . $route . '/after', $args);

        return $output;
    }

    /**
     * Carrega um arquivo de language no serviço language e devolve as chaves mescladas.
     *
     * @return array<string, mixed>
     */
    public function language(string $route, string $code = ''): array {
        $route = $this->sanitize($route);

        $args = [$route, $code];
        $this->fire('language/' . $route . '/before', $args);

        $result = (array)$this->registry->get('language')->load($route, $code);

        $this->fire('language/' . $route . '/after', $args);

        return $result;
    }

    /**
     * Executa outro controller no MESMO registry (header/footer/blocos compartilhados).
     * Convenção: o sub-controller devolve o HTML renderizado (string).
     */
    public function controller(string $route, mixed ...$args): mixed {
        return (new Action($route))->execute($this->registry, $args);
    }

    /** Inclui uma vez dev/system/helper/{route}.php */
    public function helper(string $route): void {
        $file = DIR_SYSTEM . 'helper/' . $this->sanitize($route) . '.php';

        if (is_file($file)) {
            require_once($file);
        }
    }

    /** Mescla config/{route}.php por cima do config atual */
    public function config(string $route): void {
        $this->registry->get('config')->load($this->sanitize($route));
    }

    /** Bloqueia traversal e mantém só o alfabeto de rotas */
    private function sanitize(string $route): string {
        return (string)preg_replace(
            '/[^a-zA-Z0-9_\/]/',
            '',
            str_replace(['../', '..\\', '..'], '', trim($route))
        );
    }

    /** Dispara gatilho de evento se o serviço existir (6a: opcional; framework monta em 6b) */
    private function fire(string $trigger, array &$args): void {
        $event = $this->registry->get('event');

        if ($event instanceof Event) {
            $event->trigger($trigger, $args);
        }
    }
}
