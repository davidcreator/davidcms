<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyryght (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */
namespace Reamur\System\Engine;
/** Clas Event
 * https://github.com/reamurcms/reamurcms/wiki/Events-(script-notifications)-1.x.x.x
 */
class Event {
	/**
	 * @var \Reamur\System\Engine\Registry
	 */
	protected $registry;
	/**
	 * @var array
	 */
	protected array $data = [];
	
	/**
	 * Constructor
	 *
	 * @param	object	$route
 	*/
	public function __construct(\Reamur\System\Engine\Registry $registry) {
		$this->registry = $registry;
	}
	
	/**
	 * 
	 *
	 * @param	string	$trigger
	 * @param	object	$action
	 * @param	int		$priority
 	*/	
	public function register(string $trigger, \Reamur\System\Engine\Action $action, int $priority = 0): void {
		$this->data[] = [
			'trigger'  => $trigger,
			'action'   => $action,
			'priority' => $priority
		];
		
		$sort_order = [];

		foreach ($this->data as $key => $value) {
			$sort_order[$key] = $value['priority'];
		}

		array_multisort($sort_order, SORT_ASC, $this->data);	
	}
	
	/**
	 * 
	 *
	 * @param	string	$event
	 * @param	array	$args
 	*/		
	public function trigger(string $event, array $args = []): mixed {
		foreach ($this->data as $value) {
			if (preg_match('/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($value['trigger'], '/')) . '/', $event)) {
				$result = $value['action']->execute($this->registry, $args);

				if (!is_null($result) && !($result instanceof \Exception)) {
					return $result;
				}
			}
		}

		return '';
	}
	
	/**
	 * 
	 *
	 * @param	string	$trigger
	 * @param	string	$route
 	*/	
	public function unregister(string $trigger, string $route): void {
		foreach ($this->data as $key => $value) {
			if ($trigger == $value['trigger'] && $value['action']->getId() == $route) {
				unset($this->data[$key]);
			}
		}			
	}
	
	/**
	 * 
	 *
	 * @param	string	$trigger
 	*/		
	public function clear(string $trigger): void {
		foreach ($this->data as $key => $value) {
			if ($trigger == $value['trigger']) {
				unset($this->data[$key]);
			}
		}
	}	
}