<?php

namespace Stax\VisibilityLogic\Beaver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Stax\VisibilityLogic\Resources;

/**
 * Class UserRoleVisibility
 */
class UserRoleVisibility extends BeaverWidgetBase {

	/**
	 * Register controls
	 *
	 * @return array
	 */
	public function register_controls() {
		return [
			'title'  => __( 'User Role', 'visibility-logic-elementor' ),
			'fields' => [
				self::SECTION_PREFIX . 'user_role_enabled' => [
					'type'    => 'select',
					'label'   => __( 'Enable', 'visibility-logic-elementor' ),
					'default' => '',
					'options' => [
						''    => __( 'No', 'visibility-logic-elementor' ),
						'yes' => __( 'Yes', 'visibility-logic-elementor' ),
					],
					'preview' => [
						'type' => 'none',
					],
					'toggle'  => [
						'yes' => [
							'fields' => [
								self::SECTION_PREFIX . 'user_role_conditions',
							],
						],
					],
				],
				self::SECTION_PREFIX . 'user_role_conditions' => [
					'type'         => 'select',
					'label'        => __( 'Enable', 'visibility-logic-elementor' ),
					'default'      => [],
					'options'      => Resources::get_user_roles(),
					'multi-select' => true,
					'preview'      => [
						'type' => 'none',
					],
				],
			],
		];
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
