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
		add_filter( 'stax/visibility/condition_fields', [ $this, 'condition_fields' ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
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
	 * Fields of this condition for the block editor and Elementor 4 atomic elements (see Conditions).
	 *
	 * @param array $conditions Registered conditions.
	 *
	 * @return array
	 */
	public function condition_fields( $conditions ) {
		$conditions['user_role'] = [
			'label'  => __( 'User Role', 'visibility-logic-elementor' ),
			'switch' => 'user_role_enabled',
			'fields' => [
				[
					'key'           => 'user_role_by',
					'type'          => 'select',
					'label'         => __( 'Check', 'visibility-logic-elementor' ),
					'options'       => [
						'roles'      => __( 'Roles', 'visibility-logic-elementor' ),
						'users'      => __( 'Specific users', 'visibility-logic-elementor' ),
						'capability' => __( 'Capability', 'visibility-logic-elementor' ),
					],
					'default'       => 'roles',
					'options_since' => [
						'users'      => '3.0.0',
						'capability' => '3.0.0',
					],
				],
				[
					'key'      => 'user_role_conditions',
					'type'     => 'multiselect',
					'label'    => __( 'Roles', 'visibility-logic-elementor' ),
					'options'  => Resources::get_user_roles(),
					'show_if'  => [ 'user_role_by' => [ 'roles' ] ],
					'required' => true,
				],
				[
					'key'           => 'user_role_match',
					'type'          => 'select',
					'label'         => __( 'Visitor must have', 'visibility-logic-elementor' ),
					'options'       => [
						'any' => __( 'Any of the selected roles', 'visibility-logic-elementor' ),
						'all' => __( 'All of the selected roles', 'visibility-logic-elementor' ),
					],
					'default'       => 'any',
					'options_since' => [ 'all' => '3.0.0' ],
					'show_if'       => [ 'user_role_by' => [ 'roles' ] ],
				],
				[
					'key'      => 'user_role_users',
					'type'     => 'users',
					'label'    => __( 'Users', 'visibility-logic-elementor' ),
					'help'     => __( 'Saved as user IDs. Guests never match.', 'visibility-logic-elementor' ),
					'show_if'  => [ 'user_role_by' => [ 'users' ] ],
					'since'    => '3.0.0',
					'required' => true,
				],
				[
					'key'         => 'user_role_caps',
					'type'        => 'list',
					'label'       => __( 'Capabilities', 'visibility-logic-elementor' ),
					'placeholder' => 'edit_posts',
					'help'        => __( 'The visitor needs at least one of them. Guests never match.', 'visibility-logic-elementor' ),
					'show_if'     => [ 'user_role_by' => [ 'capability' ] ],
					'since'       => '3.0.0',
					'required'    => true,
				],
			],
		];

		return $conditions;
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

		if ( ! self::flag( $settings, 'user_role_enabled' ) ) {
			return $options;
		}

		$by = (string) self::value( $settings, 'user_role_by', 'roles' );

		if ( 'users' === $by ) {
			$ids = array_filter( array_map( 'intval', (array) self::value( $settings, 'user_role_users', [] ) ) );

			if ( $ids ) {
				$options['user_role'] = Context::logged_in() && in_array( Context::user_id(), $ids, true );
			}

			return $options;
		}

		if ( 'capability' === $by ) {
			$caps = array_filter( array_map( 'trim', array_map( 'strval', (array) self::value( $settings, 'user_role_caps', [] ) ) ), 'strlen' );

			if ( $caps ) {
				$options['user_role'] = false;

				foreach ( $caps as $cap ) {
					if ( Context::can( $cap ) ) {
						$options['user_role'] = true;
						break;
					}
				}
			}

			return $options;
		}

		$roles = self::value( $settings, 'user_role_conditions', [] );

		if ( empty( $roles ) ) {
			return $options;
		}

		$roles   = (array) $roles;
		$user_id = Context::user_id();
		$current = $user_id ? (array) Context::user()->roles : [];

		if ( 'all' === self::value( $settings, 'user_role_match', 'any' ) ) {
			// Every selected entry must be true: Guests (not logged in), Logged in users, each role.
			$all = true;

			foreach ( $roles as $role ) {
				if ( 'ecl-guest' === $role ) {
					$all = $all && ! $user_id;
				} elseif ( 'ecl-user' === $role ) {
					$all = $all && $user_id;
				} else {
					$all = $all && $user_id && in_array( $role, $current, true );
				}
			}

			$options['user_role'] = (bool) $all;

			return $options;
		}

		$is_guest     = in_array( 'ecl-guest', $roles, true ) && ! $user_id;
		$is_logged_in = in_array( 'ecl-user', $roles, true ) && $user_id;
		$has_role     = $user_id && array_intersect( $roles, $current );

		$options['user_role'] = $is_guest || $is_logged_in || $has_role;

		return $options;
	}
}

UserRoleVisibility::instance();
