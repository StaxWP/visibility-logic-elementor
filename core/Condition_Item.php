<?php
/**
 * Element passed to the condition modules for atomic elements and blocks.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Element passed to the condition modules for atomic elements and blocks. Same reading methods as an Elementor
 * element, over a plain settings array.
 */
class Condition_Item {

	/**
	 * Settings (Elementor-style keys).
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Name (block name or element type).
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Builder type: block or atomic.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Constructor.
	 *
	 * @param array  $settings Settings.
	 * @param string $id       Id.
	 * @param string $name     Name.
	 * @param string $type     Type.
	 */
	public function __construct( array $settings, $id = '', $name = '', $type = 'block' ) {
		$this->settings = $settings;
		$this->id       = (string) $id;
		$this->name     = (string) $name;
		$this->type     = (string) $type;
	}

	/**
	 * Settings.
	 *
	 * @param string|null $key Key.
	 *
	 * @return mixed
	 */
	public function get_settings( $key = null ) {
		if ( null === $key ) {
			return $this->settings;
		}

		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null;
	}

	/**
	 * Settings for display (no dynamic tags outside Elementor classic).
	 *
	 * @param string|null $key Key.
	 *
	 * @return mixed
	 */
	public function get_settings_for_display( $key = null ) {
		return $this->get_settings( $key );
	}

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Type: block or atomic.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}
}
