<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

/**
 * Class AcfVisibility
 */
class AcfVisibility extends Singleton {

	/**
	 * AcfVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->register_elementor_settings( 'acf_section' );

		add_filter( 'stax/visibility/apply_conditions', [ $this, 'apply_conditions' ], 10, 3 );
	}

	/**
	 * Register section
	 *
	 * @param $element
	 *
	 * @return void
	 */
	public function register_section( $element ) {
		$element->start_controls_section(
			self::SECTION_PREFIX . 'acf_section',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => __( 'ACF Field', 'visibility-logic-elementor' ),
				'condition' => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * @param $element \Elementor\Widget_Base
	 * @param $args
	 */
	public function register_controls( $element, $args ) {
		$element->add_control(
			self::SECTION_PREFIX . 'acf_enabled',
			[
				'label'          => __( 'Enable', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				'prefix_class'   => 'stax-acf_enabled-',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'acf_source',
			[
				'label'          => __( 'Field Source', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::HIDDEN,
				'default'        => 'post',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'acf_source_info',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'Checks ACF fields on the current post/page.', 'visibility-logic-elementor' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => [
					self::SECTION_PREFIX . 'acf_enabled' => 'yes',
				],
				'style_transfer'  => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'acf_field',
			[
				'label'          => __( 'Field Name', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::TEXT,
				'placeholder'    => __( 'e.g. my_custom_field', 'visibility-logic-elementor' ),
				'description'    => __( 'Enter the ACF field name or key.', 'visibility-logic-elementor' ),
				'label_block'    => true,
				'condition'      => [
					self::SECTION_PREFIX . 'acf_enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'acf_condition',
			[
				'label'          => __( 'Condition', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SELECT,
				'options'        => [
					'empty'      => __( 'Is empty', 'visibility-logic-elementor' ),
					'not_empty'  => __( 'Is not empty', 'visibility-logic-elementor' ),
					'equals'     => __( 'Equals', 'visibility-logic-elementor' ),
					'not_equals' => __( 'Not equals', 'visibility-logic-elementor' ),
					'contains'   => __( 'Contains', 'visibility-logic-elementor' ),
					'is_true'    => __( 'Is true', 'visibility-logic-elementor' ),
					'is_false'   => __( 'Is false', 'visibility-logic-elementor' ),
				],
				'default'        => 'not_empty',
				'condition'      => [
					self::SECTION_PREFIX . 'acf_enabled' => 'yes',
					self::SECTION_PREFIX . 'acf_field!'  => '',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'acf_value',
			[
				'label'          => __( 'Value', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::TEXT,
				'label_block'    => true,
				'condition'      => [
					self::SECTION_PREFIX . 'acf_enabled'   => 'yes',
					self::SECTION_PREFIX . 'acf_field!'    => '',
					self::SECTION_PREFIX . 'acf_condition' => [
						'equals',
						'not_equals',
						'contains',
					],
				],
				'style_transfer' => false,
			]
		);

		if ( ! defined( 'STAX_VISIBILITY_PRO_VERSION' ) ) {
			$element->add_control(
				self::SECTION_PREFIX . 'acf_pro_notice',
				[
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => sprintf(
						__( 'Need user fields, multiple ACF conditions with AND/OR logic, repeater fields, or options page support? %1$sUpgrade to Pro%2$s', 'visibility-logic-elementor' ),
						'<a href="https://staxwp.com/go/visibility-logic" target="_blank"><strong>',
						'</strong></a>'
					),
					'content_classes' => 'elementor-descriptor',
					'condition'       => [
						self::SECTION_PREFIX . 'acf_enabled' => 'yes',
					],
					'style_transfer'  => false,
				]
			);
		}

		if ( ! class_exists( 'ACF' ) && ! class_exists( 'acf' ) ) {
			$element->add_control(
				self::SECTION_PREFIX . 'acf_not_installed',
				[
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => __( 'Advanced Custom Fields plugin is not active. This condition will have no effect.', 'visibility-logic-elementor' ),
					'content_classes' => 'stax-generic-notice',
					'condition'       => [
						self::SECTION_PREFIX . 'acf_enabled' => 'yes',
					],
					'style_transfer'  => false,
				]
			);
		}
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

		if ( ! (bool) $settings[ self::SECTION_PREFIX . 'acf_enabled' ] ) {
			return $options;
		}

		// ACF must be active
		if ( ! function_exists( 'get_field' ) ) {
			return $options;
		}

		$field_name = $settings[ self::SECTION_PREFIX . 'acf_field' ];

		if ( empty( $field_name ) ) {
			return $options;
		}

		$condition     = isset( $settings[ self::SECTION_PREFIX . 'acf_condition' ] ) ? $settings[ self::SECTION_PREFIX . 'acf_condition' ] : 'not_empty';
		$compare_value = isset( $settings[ self::SECTION_PREFIX . 'acf_value' ] ) ? $settings[ self::SECTION_PREFIX . 'acf_value' ] : '';

		// Get field value from current post/page
		$value = get_field( $field_name );

		// Evaluate condition
		$result = false;

		switch ( $condition ) {
			case 'empty':
				$result = empty( $value );
				break;
			case 'not_empty':
				$result = ! empty( $value );
				break;
			case 'equals':
				if ( is_array( $value ) ) {
					$result = in_array( $compare_value, $value, false );
				} else {
					$result = (string) $value === $compare_value;
				}
				break;
			case 'not_equals':
				if ( is_array( $value ) ) {
					$result = ! in_array( $compare_value, $value, false );
				} else {
					$result = (string) $value !== $compare_value;
				}
				break;
			case 'contains':
				if ( is_array( $value ) ) {
					$result = in_array( $compare_value, $value, false );
				} elseif ( is_string( $value ) ) {
					$result = strpos( $value, $compare_value ) !== false;
				}
				break;
			case 'is_true':
				$result = ! empty( $value ) && $value !== '0' && $value !== 'false';
				break;
			case 'is_false':
				$result = empty( $value ) || $value === '0' || $value === 'false';
				break;
		}

		$options['acf'] = $result;

		return $options;
	}

}

AcfVisibility::instance();
