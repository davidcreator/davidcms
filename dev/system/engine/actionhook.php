<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyryght (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

 namespace Reamur\System\Engine;

/** Class Actionhook */
class Actionhook {
    /** @var string */
    private string $id;

    /** @var string */
    private string $route;

    /** @var string */
    private string $method = 'index';

    /**
     * Constructor
     * @param string $route
     */
    public function __construct(string $route) {
        $this->id = $route;
        
        // Sanitização do caminho da rota
        $parts = explode('/', preg_replace('/[^a-zA-Z0-9_\/]/', '', $route));

        // Processamento da rota
        while (!empty($parts)) {
            $file = DIR_WEBHOOK . 'controller/' . implode('/', $parts) . '.php';

            if (is_file($file)) {
                $this->route = implode('/', $parts);
                break;
            } else {
                $this->method = array_pop($parts);
            }
        }
    }
    
    /** @return string */
    public function getId(): string {
        return $this->id;
    }

    /** 
     * Executa a ação da rota
     * @param object $registry
     * @param array $args
     * @return mixed
     */
    public function execute($registry, array $args = []): mixed {
        // Bloquear chamadas de métodos mágicos
        if (str_starts_with($this->method, '__')) {
            throw new \Exception('Error: Magic methods are not allowed!');
        }

        $file = DIR_WEBHOOK . 'controller/' . $this->route . '.php';
        $class = 'ControllerWebHook' . preg_replace('/[^a-zA-Z0-9]/', '', $this->route);

        // Inicializa a classe do controlador
        if (is_file($file)) {
            include_once $file;

            if (class_exists($class)) {
                $controller = new $class($registry);
            } else {
                throw new \Exception('Error: Controller class ' . $class . ' not found!');
            }
        } else {
            throw new \Exception('Error: Could not call route ' . $this->method . '!');
        }

        $reflection = new \ReflectionClass($class);

        if ($reflection->hasMethod($this->method) && $reflection->getMethod($this->method)->getNumberOfRequiredParameters() <= count($args)) {
            return call_user_func_array([$controller, $this->method], $args);
        } else {
            throw new \Exception('Error: Could not call route ' . $this->route . '/' . $this->method . '!');
        }
    }
}