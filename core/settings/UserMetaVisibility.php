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
				'options'        => [
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
				],
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

		if ( ! (bool) $settings[ self::SECTION_PREFIX . 'user_meta_enabled' ] || ! is_array( $settings[ self::SECTION_PREFIX . 'user_meta_options' ] ) ) {
			return $options;
		}

		$current_user       = wp_get_current_user();
		$meta_is_consistent = true;
		$meta_check_type    = $settings[ self::SECTION_PREFIX . 'user_meta_status' ];
		$meta_check_value   = $settings[ self::SECTION_PREFIX . 'user_meta_value' ];

		foreach ( $settings[ self::SECTION_PREFIX . 'user_meta_options' ] as $meta ) {
			$user_meta = get_user_meta( $current_user->ID, $meta, true );

			if ( isset( $current_user->{$meta} ) ) {
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
					if ( $user_meta !== $meta_check_value ) {
						$meta_is_consistent = false;
					}
					break;
				case 'specific_value_multiple':
					$values = explode( ',', $meta_check_value );

					$value_found = false;

					foreach ( $values as $item ) {
						if ( $item === $user_meta ) {
							$value_found = true;
						}
					}

					$meta_is_consistent = $value_found;
					break;
				case 'not_specific_value':
					if ( $user_meta === $meta_check_value ) {
						$meta_is_consistent = false;
					}
					break;
				case 'contain':
					if ( strpos( $user_meta, $meta_check_value ) === false ) {
						$meta_is_consistent = false;
					}
					break;
				case 'not_contain':
					if ( strpos( $user_meta, $meta_check_value ) !== false ) {
						$meta_is_consistent = false;
					}
					break;
				case 'is_between':
					$meta_check_value_2 = $settings[ self::SECTION_PREFIX . 'user_meta_value_2' ];

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
					}

					$values = explode( ',', $meta_check_value );

					if ( empty( array_intersect( $user_meta, $values ) ) ) {
						$meta_is_consistent = false;
					}
					break;
				default:
			}
		}

		// If conditions are met for each meta selected, then alter the output.
		$options['user_meta'] = $meta_is_consistent;

		return $options;
	}

}

UserMetaVisibility::instance();
