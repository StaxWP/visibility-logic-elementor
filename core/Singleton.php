<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Singleton
 *
 * @package Stax\VisibilityLogic
 */
class Singleton {

	/**
	 * @var array
	 */
	public static $instances = [];

	const VISIBILITY_TAB = 'stax-visibility';
	const SECTION_PREFIX = 'stax_visibility_';

	/**
	 * Singleton constructor.
	 */
	protected function __construct() {
	}

	/**
	 * Disables class cloning and throw an error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object. Therefore, we don't want the object to be cloned.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	final public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'visibility-logic-elementor' ), STAX_VISIBILITY_VERSION );
	}

	/**
	 * Disables unserializing of the class.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	final public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'visibility-logic-elementor' ), STAX_VISIBILITY_VERSION );
	}

	/**
	 * Get instance
	 *
	 * @return mixed
	 */
	public static function instance() {
		$class = static::class;

		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class();
		}

		return self::$instances[ $class ];
	}

}
