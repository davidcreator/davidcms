<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyryght (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

 namespace Reamur\System\Engine;

/** Class Webhook */
class Webhook {
    /** @var object */
    protected $registry;

    /** @var array */
    protected array $data = [];

    /**
     * Constructor
     *
     * @param object $registry
     */
    public function __construct(object $registry) {
        $this->registry = $registry;
    }

    /**
     * Registra um webhook
     *
     * @param string $trigger
     * @param object $action
     * @param int $priority
     */
    public function register(string $trigger, object $action, int $priority = 0): void {
        $this->data[] = [
            'trigger'  => $trigger,
            'action'   => $action,
            'priority' => $priority
        ];

        // Ordenação por prioridade
        usort($this->data, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Dispara um webhook
     *
     * @param string $event
     * @param array $args
     * @return mixed
     */
    public function trigger(string $event, array $args = []): mixed {
        foreach ($this->data as $value) {
            if (preg_match('/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($value['trigger'], '/')) . '/', $event)) {
                $result = $value['action']->execute($this->registry, $args);

                if ($result !== null && !($result instanceof \Exception)) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Remove um webhook específico
     *
     * @param string $trigger
     * @param string $route
     */
    public function unregister(string $trigger, string $route): void {
        foreach ($this->data as $key => $value) {
            if ($trigger === $value['trigger'] && method_exists($value['action'], 'getId') && $value['action']->getId() === $route) {
                unset($this->data[$key]);
            }
        }
    }

    /**
     * Limpa todos os webhooks de um determinado trigger
     *
     * @param string $trigger
     */
    public function clear(string $trigger): void {
        $this->data = array_filter($this->data, fn($value) => $value['trigger'] !== $trigger);
    }
}