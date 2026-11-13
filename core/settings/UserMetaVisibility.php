<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Stax\VisibilityLogic\Singleton;

/**
 * Class UserMetaVisibility
 */
class UserMetaVisibility extends Singleton {

	/**
	 * UserMetaVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->register_elementor_settings( 'user_meta_section' );

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
			self::SECTION_PREFIX . 'user_meta_section',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => __( 'User Meta', 'visibility-logic-elementor' ),
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
			self::SECTION_PREFIX . 'user_meta_enabled',
			[
				'label'          => __( 'Enable', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				'prefix_class'   => 'stax-user_meta_enabled-',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_options',
			[
				'type'           => 'stax_query',
				'label'          => __( 'Meta Name', 'visibility-logic-elementor' ),
				'query_type'     => 'fields',
				'object_type'    => 'user',
				'placeholder'    => __( 'Meta key or Name', 'visibility-logic-elementor' ),
				'label_block'    => true,
				'multiple'       => true,
				'condition'      => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_status',
			[
				'type'           => Controls_Manager::SELECT2,
				'label'          => __( 'Meta Condition', 'visibility-logic-elementor' ),
				'description'    => __( 'Select the condition that must be met by the meta', 'visibility-logic-elementor' ),
				'options'        => self::status_options( true ),
				'default'        => 'none',
				'label_block'    => true,
				'condition'      => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
					self::SECTION_PREFIX . 'user_meta_options!' => '',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_value',
			[
				'label'          => __( 'Condition Value', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::TEXTAREA,
				'label_block'    => true,
				'condition'      => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
					self::SECTION_PREFIX . 'user_meta_options!' => '',
					self::SECTION_PREFIX . 'user_meta_status' => [
						'specific_value',
						'specific_value_multiple',
						'not_specific_value',
						'contain',
						'not_contain',
						'is_between',
						'less_than',
						'greater_than',
						'is_array_and_contains',
					],
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_value_2',
			[
				'label'          => __( 'Condition Value 2', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::TEXTAREA,
				'label_block'    => true,
				'condition'      => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
					self::SECTION_PREFIX . 'user_meta_options!' => '',
					self::SECTION_PREFIX . 'user_meta_status' => 'is_between',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_notice_array',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'Type in comma separated strings.', 'visibility-logic-elementor' ),
				'content_classes' => 'stax-generic-notice',
				'condition'       => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
					self::SECTION_PREFIX . 'user_meta_options!' => '',
					self::SECTION_PREFIX . 'user_meta_status' => [
						'specific_value_multiple',
						'is_array_and_contains',
					],
				],
				'style_transfer'  => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_notice_numeric',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'The value of the selected meta and also the the values of the conditions must be numeric.', 'visibility-logic-elementor' ),
				'content_classes' => 'stax-generic-notice',
				'condition'       => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
					self::SECTION_PREFIX . 'user_meta_options!' => '',
					self::SECTION_PREFIX . 'user_meta_status' => [
						'is_between',
						'less_than',
						'greater_than',
					],
				],
				'style_transfer'  => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_notice_none',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'Setting the condition to "None" will have no effect.', 'visibility-logic-elementor' ),
				'content_classes' => 'stax-generic-notice',
				'condition'       => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
					self::SECTION_PREFIX . 'user_meta_options!' => '',
					self::SECTION_PREFIX . 'user_meta_status' => [
						'none',
					],
				],
				'style_transfer'  => false,
			]
		);
	}

	/**
	 * User meta conditions (value => label).
	 *
	 * @return array
	 */
	public static function status_options( $legacy = false ) {
		$options = [
			'none'                    => __( 'None', 'visibility-logic-elementor' ),
			'empty'                   => __( 'Empty', 'visibility-logic-elementor' ),
			'not_empty'               => __( 'Not empty', 'visibility-logic-elementor' ),
			'specific_value'          => __( 'Is equal to', 'visibility-logic-elementor' ),
			'specific_value_multiple' => __( 'Is equal to one of', 'visibility-logic-elementor' ),
			'not_specific_value'      => __( 'Not equal to', 'visibility-logic-elementor' ),
			'contain'                 => __( 'Contains', 'visibility-logic-elementor' ),
			'not_contain'             => __( 'Does not contain', 'visibility-logic-elementor' ),
			'is_between'              => __( 'Between', 'visibility-logic-elementor' ),
			'less_than'               => __( 'Less than', 'visibility-logic-elementor' ),
			'greater_than'            => __( 'Greater than', 'visibility-logic-elementor' ),
			'is_array'                => __( 'Is array', 'visibility-logic-elementor' ),
			'is_array_and_contains'   => __( 'Is array and contains', 'visibility-logic-elementor' ),
		];

		if ( ! $legacy ) {
			$options += self::new_status_options();
		}

		return $options;
	}

	/**
	 * Operators added in 3.0 (shared operator library), evaluated by StaxWP\Conditions\V1\Operators.
	 *
	 * @return array
	 */
	public static function new_status_options() {
		return [
			'equals_ci'     => __( 'Is equal to (ignore case)', 'visibility-logic-elementor' ),
			'not_equals_ci' => __( 'Not equal to (ignore case)', 'visibility-logic-elementor' ),
			'starts_with'   => __( 'Starts with', 'visibility-logic-elementor' ),
			'ends_with'     => __( 'Ends with', 'visibility-logic-elementor' ),
			'lte'           => __( 'Less than or equal to', 'visibility-logic-elementor' ),
			'gte'           => __( 'Greater than or equal to', 'visibility-logic-elementor' ),
			'not_in'        => __( 'Is not one of', 'visibility-logic-elementor' ),
		];
	}

	/**
	 * Fields of this condition for the block editor and Elementor 4 atomic elements (see Conditions).
	 *
	 * @param array $conditions Registered conditions.
	 *
	 * @return array
	 */
	public function condition_fields( $conditions ) {
		$conditions['user_meta'] = [
			'label'  => __( 'User Meta', 'visibility-logic-elementor' ),
			'switch' => 'user_meta_enabled',
			'fields' => [
				[
					'key'         => 'user_meta_options',
					'required'    => true,
					'type'        => 'list',
					'label'       => __( 'Meta keys', 'visibility-logic-elementor' ),
					'placeholder' => 'first_name, billing_country',
					'help'        => __( 'User fields or meta keys, separated by commas. Each one must meet the condition.', 'visibility-logic-elementor' ),
				],
				[
					'key'     => 'user_meta_status',
					'type'    => 'select',
					'label'   => __( 'Meta Condition', 'visibility-logic-elementor' ),
					'options'       => self::status_options(),
					'default'       => 'none',
					'options_since' => array_fill_keys( array_keys( self::new_status_options() ), '3.0.0' ),
				],
				[
					'key'     => 'user_meta_value',
					'type'    => 'textarea',
					'label'   => __( 'Condition Value', 'visibility-logic-elementor' ),
					'help'    => __( 'Comma separated for "Is equal to one of" and "Is array and contains".', 'visibility-logic-elementor' ),
					'show_if' => [ 'user_meta_status' => array_merge( [ 'specific_value', 'specific_value_multiple', 'not_specific_value', 'contain', 'not_contain', 'is_between', 'less_than', 'greater_than', 'is_array_and_contains' ], array_keys( self::new_status_options() ) ) ],
				],
				[
					'key'     => 'user_meta_value_2',
					'type'    => 'textarea',
					'label'   => __( 'Condition Value 2', 'visibility-logic-elementor' ),
					'show_if' => [ 'user_meta_status' => [ 'is_between' ] ],
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

		if ( ! self::flag( $settings, 'user_meta_enabled' ) || ! is_array( self::value( $settings, 'user_meta_options', null ) ) ) {
			return $options;
		}

		$current_user       = Context::user();
		$meta_is_consistent = true;
		$meta_check_type    = (string) self::value( $settings, 'user_meta_status', 'none' );
		$meta_check_raw     = self::value( $settings, 'user_meta_value', null ); // Strict comparisons use the saved value as 2.x did.
		$meta_check_value   = (string) $meta_check_raw;

		foreach ( self::value( $settings, 'user_meta_options', [] ) as $meta ) {
			$meta = (string) $meta;

			if ( '' !== $meta && isset( $current_user->{$meta} ) ) {
				$user_meta = $current_user->{$meta};
			} else {
				$user_meta = get_user_meta( $current_user->ID, $meta, true );
			}

			switch ( $meta_check_type ) {
				case 'empty':
					if ( ! empty( $user_meta ) ) {
						$meta_is_consistent = false;
					}
					break;
				case 'not_empty':
					if ( empty( $user_meta ) ) {
						$meta_is_consistent = false;
					}
					break;
				case 'specific_value':
					if ( $user_meta !== $meta_check_raw ) {
						$meta_is_consistent = false;
					}
					break;
				case 'specific_value_multiple':
					$values = explode( ',', $meta_check_value );

					$value_found = false;

					foreach ( $values as $value ) {
						if ( $value === $user_meta ) {
							$value_found = true;
						}
					}

					$meta_is_consistent = $value_found;
					break;
				case 'not_specific_value':
					if ( $user_meta === $meta_check_raw ) {
						$meta_is_consistent = false;
					}
					break;
				case 'contain':
					if ( ! is_string( $user_meta ) || strpos( $user_meta, $meta_check_value ) === false ) {
						$meta_is_consistent = false;
					}
					break;
				case 'not_contain':
					if ( is_string( $user_meta ) && strpos( $user_meta, $meta_check_value ) !== false ) {
						$meta_is_consistent = false;
					}
					break;
				case 'is_between':
					$meta_check_value_2 = (string) self::value( $settings, 'user_meta_value_2' );

					if ( ! is_numeric( $user_meta ) || ! is_numeric( $meta_check_value ) || ! is_numeric( $meta_check_value_2 ) ) {
						$meta_is_consistent = false;
					}

					if ( (int) $meta_check_value > (int) $user_meta || (int) $user_meta > (int) $meta_check_value_2 ) {
						$meta_is_consistent = false;
					}
					break;
				case 'less_than':
					if ( ! is_numeric( $user_meta ) || ! is_numeric( $meta_check_value ) ) {
						$meta_is_consistent = false;
					}

					if ( (int) $user_meta > (int) $meta_check_value ) {
						$meta_is_consistent = false;
					}
					break;
				case 'greater_than':
					if ( ! is_numeric( $user_meta ) || ! is_numeric( $meta_check_value ) ) {
						$meta_is_consistent = false;
					}

					if ( (int) $user_meta < (int) $meta_check_value ) {
						$meta_is_consistent = false;
					}
					break;
				case 'is_array':
					if ( ! is_array( $user_meta ) ) {
						$meta_is_consistent = false;
					}
					break;
				case 'is_array_and_contains':
					if ( ! is_array( $user_meta ) ) {
						$meta_is_consistent = false;
						break;
					}

					$values = explode( ',', $meta_check_value );

					if ( empty( array_intersect( $user_meta, $values ) ) ) {
						$meta_is_consistent = false;
					}
					break;
				default:
					// 3.0 operators from the shared library (2.x operators above keep their exact 2.x results).
					if ( array_key_exists( $meta_check_type, self::new_status_options() ) ) {
						list( $matches ) = \StaxWP\Conditions\V1\Operators::compare( $meta_check_type, $user_meta, $meta_check_value );

						if ( ! $matches ) {
							$meta_is_consistent = false;
						}
					}
			}
		}

		// If conditions are met for each meta selected, then alter the output.
		$options['user_meta'] = $meta_is_consistent;

		return $options;
	}
}

UserMetaVisibility::instance();
