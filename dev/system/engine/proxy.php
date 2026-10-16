<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Proxy de lazy loading de models (Arquitetura-MVCL §7).
 * $this->load->model('grupo/modulo') registra este Proxy no Registry;
 * a classe real Reamur\{APP}\Model\{Grupo}\{Modulo} só é instanciada
 * na primeira chamada de método (__call).
 *
 * REGRA: métodos de model NÃO usam parâmetros por referência — &ref não
 * atravessa __call(). Devolva arrays/estado (ex.: getError()).
 */
namespace Reamur\System\Engine;

class Proxy {
    private object|null $instance = null;

    public function __construct(private Registry $registry, private string $route) {}

    public function __call(string $method, array $args): mixed {
        return call_user_func_array([$this->getInstance(), $method], $args);
    }

    /** Instancia (uma única vez) o model real: route 'cms/portfolio' → Model\Cms\Portfolio */
    public function getInstance(): object {
        if ($this->instance === null) {
            $class = 'Reamur\\' . APPLICATION . '\\Model\\' . implode(
                '\\',
                array_map('ucfirst', explode('/', $this->route))
            );

            if (!class_exists($class)) {
                throw new \Exception(sprintf('Model class not found: %s (route "%s")', $class, $this->route));
            }

            $this->instance = new $class($this->registry);
        }

        return $this->instance;
    }
}
