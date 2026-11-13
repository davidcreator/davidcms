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
 *
 * EX-01 (ADR 0095): cada ouvinte tem uma prioridade (número menor roda antes;
 * empate segue a ordem de registro) e has() diz se alguém escuta um gatilho,
 * para que o núcleo não monte dados que ninguém vai ler. Esta classe NÃO
 * engole erro de ouvinte: quem isola as extensões é o Extension\Manager.
 */
namespace Reamur\System\Engine;

final class Event {
    /** @var array<string, list<array{0: int, 1: int, 2: callable}>> gatilho => [prioridade, sequência, ouvinte] */
    private array $handlers = [];

    /** @var array<string, bool> gatilhos cuja lista precisa ser reordenada antes do próximo disparo */
    private array $dirty = [];

    private int $sequence = 0;

    /** Registra um ouvinte. Prioridade menor roda antes; o padrão é 0. */
    public function register(string $trigger, callable $action, int $priority = 0): void {
        $this->handlers[$trigger][] = [$priority, $this->sequence++, $action];
        $this->dirty[$trigger]      = true;
    }

    /** Há ouvinte para este gatilho? */
    public function has(string $trigger): bool {
        return !empty($this->handlers[$trigger]);
    }

    /** Dispara os ouvintes do gatilho: por prioridade e, no empate, por ordem de registro. */
    public function trigger(string $trigger, array &$args = []): void {
        if (empty($this->handlers[$trigger])) {
            return;
        }

        if (isset($this->dirty[$trigger])) {
            usort($this->handlers[$trigger], static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            unset($this->dirty[$trigger]);
        }

        foreach ($this->handlers[$trigger] as $handler) {
            $handler[2]($args);
        }
    }
}
