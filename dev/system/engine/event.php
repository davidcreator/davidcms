<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Eventos internos (Arquitetura-MVCL §10): gatilhos antes/depois no Loader.
 * Sem handlers registrados, trigger() é no-op — o comportamento padrão não muda.
 */
namespace Reamur\System\Engine;

final class Event {
    /** @var array<string, array<int, callable>> */
    private array $handlers = [];

    public function register(string $trigger, callable $action): void {
        $this->handlers[$trigger][] = $action;
    }

    /** Dispara os handlers do gatilho, em ordem de registro */
    public function trigger(string $trigger, array &$args = []): void {
        foreach ($this->handlers[$trigger] ?? [] as $action) {
            $action($args);
        }
    }
}
