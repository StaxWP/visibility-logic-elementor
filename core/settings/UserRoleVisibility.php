<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

/**
 * Class UserRoleVisibility
 */
class UserRoleVisibility extends Singleton {

	/**
	 * UserRoleVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->register_elementor_settings( 'user_role_section' );

		add_filter( 'stax/visibility/apply_conditions', [ $this, 'apply_conditions' ], 10, 3 );
	}

	/**
	 * Register section
	 *
	 * @param $element
	 * @return void
	 */
	public function register_section( $element ) {
		$element->start_controls_section(
			self::SECTION_PREFIX . 'user_role_section',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => __( 'User Role', 'visibility-logic-elementor' ),
				'condition' => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * @param $element \Elementor\Widget_Base
	 * @param $section_id
	 * @param $args
	 */
	public function register_controls( $element, $args ) {
		$element->add_control(
			self::SECTION_PREFIX . 'user_role_enabled',
			[
				'label'          => __( 'Enable', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				'prefix_class'   => 'stax-user_role_enabled-',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_role_conditions',
			[
				'type'           => Controls_Manager::SELECT2,
				'label'          => __( 'Roles', 'visibility-logic-elementor' ),
				'options'        => Resources::get_user_roles(),
				'default'        => [],
				'multiple'       => true,
				'label_block'    => true,
				'condition'      => [
					self::SECTION_PREFIX . 'user_role_enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);
	}

	/**
	 * Apply conditions
	 *
	 * @param array                   $options
	 * @param array                   $settings
	 * @param \Elementor\Element_Base $item
	 *
	 * @return array
	 */
	public function apply_conditions( $options, $settings, $item ) {
		$settings = $item->get_settings_for_display();

		if ( ! (bool) $settings[ self::SECTION_PREFIX . 'user_role_enabled' ] || empty( $settings[ self::SECTION_PREFIX . 'user_role_conditions' ] ) ) {
			return $options;
		}

		$user = wp_get_current_user();

		$is_guest     = false;
		$is_logged_in = false;
		$has_role     = false;

		if ( in_array( 'ecl-guest', $settings[ self::SECTION_PREFIX . 'user_role_conditions' ] ) ) {
			$is_guest = ! $user->ID;
		}

		if ( in_array( 'ecl-user', $settings[ self::SECTION_PREFIX . 'user_role_conditions' ] ) ) {
			$is_logged_in = $user->ID;
		}

		foreach ( $settings[ self::SECTION_PREFIX . 'user_role_conditions' ] as $setting ) {
			if ( in_array( $setting, (array) $user->roles ) ) {
				$has_role = true;
				break;
			}
		}

		$options['user_role'] = $is_guest || $is_logged_in || $has_role;

		return $options;
	}

}

UserRoleVisibility::instance();
