<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyryght (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

namespace Reamur\System\Engine;
/** Class Proxy */
class Proxy {
    /** @var array */
    protected $data = [];

    /** 
     * __get
     * @param string $key
     * @return object|null
     */
    public function &__get(string $key): object|null {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        } else {
            throw new \Exception('Error: Could not call proxy key' . $key . '!');
        }
    }

    /**
     * __get
     * @param string $key
     * @param string $value
     * @return void
     */
    public function __set(string $key, object $value): void {
        $this->data[$key] = $value;
    }

    /**
     * __isset
     * @param string $key
     * @return void
     */
    public function __isset(string $key) {
        return isset($this->data[$key]);
    }

    /**
     * __unset
     * @param string $key
     * @return void
     */
    public function __unset(string $key) {
        unset($this->data[$key]);
    }

    /** 
     * __call
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    public function __call(string $method, array $args): mixed {
        // Hack for pass-by-reference
        foreach ($args as $key => &$value);

        if (isset($this->data[$method])) {
            return call_user_func_array($this->data[$method], $args);
        } else {
            $trace = debug_backtrace();

            throw new \Exception('<b>Notice</b>:  Undefined property: Proxy::' . $method . ' in <b>' . $trace[0]['file'] . '</b> on line <b>' . $trace[0]['line'] . '</b>');
        }
    }
}