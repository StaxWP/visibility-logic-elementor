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

	public $elements = [
		[
			'name'       => 'common',
			'section_id' => '_section_style',
			'prefix'     => self::SECTION_PREFIX,
		],
		[
			'name'       => 'section',
			'section_id' => 'section_advanced',
			'prefix'     => self::SECTION_PREFIX,
		],
		[
			'name'       => 'container',
			'section_id' => 'section_layout',
			'prefix'     => self::SECTION_PREFIX,
		],
	];

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

	/**
	 * Register elementor settings
	 *
	 * @param $option_name
	 * @return void
	 */
	public function register_elementor_settings( $option_name ) {
		foreach ( $this->elements as $element ) {
			add_action( "elementor/element/{$element['name']}/{$element['section_id']}/after_section_end", [ $this, 'register_section' ] );
			add_action(
				"elementor/element/{$element['name']}/{$element['prefix']}{$option_name}/before_section_end",
				[
					$this,
					'register_controls',
				],
				10,
				2
			);
		}
	}

}
