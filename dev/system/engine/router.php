<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyryght (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

namespace Reamur\System\Engine;

/** Class Router */
class Router {
    private $registry;
    private $pre_action = [];
    private $post_action = [];
    private $error;

    /**
     * Constructor
     *
     * @param    object $route
     */
	public function __construct($registry) {
		$this->registry = $registry;
	}

    /**
     * @param    object $pre_action
     */
	public function addPreAction(Action $pre_action) {
		$this->pre_action[] = $pre_action;
	}
	/**
     * @param    object $post_action
     */
	public function addPostAction(Action $post_action) {
		$this->post_action[] = $post_action;
	}

    /**
     * @param    object $action
     * @param    object $error
     */
	public function dispatch(Action $action, Action $error) {
		$this->error = $error;

		foreach ($this->pre_action as $pre_action) {
			$result = $this->execute($pre_action);

			if ($result instanceof Action) {
				$action = $result;

				break;
			}
		}

		while ($action instanceof Action) {
			$action = $this->execute($action);
		}

		foreach ($this->post_action as $post_action) {
			$result = $this->execute($post_action);
		}
	}

    /**
     * @param    object $action
     * @return    object
     */
	private function execute(Action $action) {
		$result = $action->execute($this->registry);

		if ($result instanceof Action) {
			return $result;
		}

		if ($result instanceof Exception) {
			$action = $this->error;

			$this->error = null;

			return $action;
		}
	}
}