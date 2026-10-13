<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyryght (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

 namespace Reamur\System\Engine;
 /** Class Controller */
 class Controller {
	/**
	 * @var object|\Reamur\System\Engine\Registry
	 */
	protected $registry;

	/**
	 * Constructor
	 *
	 * @param    object  $registry
	 */
	public function __construct(\Reamur\System\Engine\Registry $registry) {
		$this->registry = $registry;
	}

	/**
	 * __get
	 *
	 * @param	string	$key
	 *
	 * @return object
	 */	
	public function __get(string $key): object {
		if ($this->registry->has($key)) {
			return $this->registry->get($key);
		} else {
			throw new \Exception('Error: Could not call registry key ' . $key . '!');
		}
	}

	/**
	 * __set
	 *
	 * @param	string	$key
	 * @param	object	$value
	 *
	 * @return void
	 */
	public function __set(string $key, object $value): void {
		$this->registry->set($key, $value);
	}
}