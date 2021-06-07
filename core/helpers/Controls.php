<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Controls {

	/**
	 * Controls
	 *
	 * @var array
	 */
	public $controls = [];

	/**
	 * Group controls
	 *
	 * @var array
	 */
	public $group_controls = [];

	/**
	 * Controls namespace
	 *
	 * @var string
	 */
	public static $namespace = '\\Stax\\VisibilityLogic\\Controls\\';

	/**
	 * Controls constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init controls
	 *
	 * @return void
	 */
	public function init() {
		$this->controls = $this->get_controls();
	}

	/**
	 * Get controls
	 *
	 * @return array
	 */
	public function get_controls() {
		$controls['stax_query'] = 'Query';

		return $controls;
	}

	/**
	 * On controls registered action
	 */
	public function on_controls_registered() {
		$this->register_controls();
	}

	/**
	 * Register controls
	 */
	public function register_controls() {
		$controls_manager = \Elementor\Plugin::$instance->controls_manager;

		foreach ( $this->controls as $key => $name ) {
			$class = self::$namespace . $name;
			$controls_manager->register_control( $key, new $class() );
		}

	}

}
