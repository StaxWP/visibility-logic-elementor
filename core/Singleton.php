<?php
/**
 * Base class of the plugin's singletons and condition modules.
 *
 * @package Stax\VisibilityLogic
 */

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
		if ( self::columns_supported() ) {
			$this->elements[] = [
				'name'       => 'column',
				'section_id' => 'section_advanced',
				'prefix'     => self::SECTION_PREFIX,
			];
		}
	}

	/**
	 * Classic columns get the Visibility tab (3.0), also with Visibility Logic Pro 2.4 (free conditions only there).
	 *
	 * @return bool
	 */
	public static function columns_supported() {
		return true;
	}

	/**
	 * Whether Visibility Logic Pro registers its conditions on columns too (3.0+). With Pro 2.4 columns get the free
	 * conditions; rules on columns are evaluated without the Pro controls (Plugin::should_render()).
	 *
	 * @return bool
	 */
	public static function pro_supports_columns() {
		return ! self::pro_outdated();
	}

	/**
	 * Visibility Logic Pro is active but older than 3.0 (the Pro release that goes with free 3.0). Everything keeps
	 * working; Pro conditions are read-only in the rule builder until Pro is updated (UpdateNotice::render_pro_outdated()).
	 *
	 * @return bool
	 */
	public static function pro_outdated() {
		return defined( 'STAX_VISIBILITY_PRO_VERSION' ) && version_compare( STAX_VISIBILITY_PRO_VERSION, '3.0.0-dev', '<' );
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
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'visibility-logic-elementor' ), esc_html( STAX_VISIBILITY_VERSION ) );
	}

	/**
	 * Disables unserializing of the class.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	final public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'visibility-logic-elementor' ), esc_html( STAX_VISIBILITY_VERSION ) );
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
	 * Whether a visibility switch is on (`stax_visibility_<name>`), safe for missing keys.
	 *
	 * @param array  $settings Element settings.
	 * @param string $name     Setting name without the prefix.
	 *
	 * @return bool
	 */
	public static function flag( $settings, $name ) {
		return is_array( $settings ) && ! empty( $settings[ self::SECTION_PREFIX . $name ] );
	}

	/**
	 * A visibility setting (`stax_visibility_<name>`) or a default when it is missing or null.
	 *
	 * @param array  $settings Element settings.
	 * @param string $name     Setting name without the prefix.
	 * @param mixed  $default  Default.
	 *
	 * @return mixed
	 */
	public static function value( $settings, $name, $default = '' ) {
		if ( ! is_array( $settings ) || ! isset( $settings[ self::SECTION_PREFIX . $name ] ) ) {
			return $default;
		}

		return $settings[ self::SECTION_PREFIX . $name ];
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
