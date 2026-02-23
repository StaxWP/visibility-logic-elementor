<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

/**
 * Class DeviceTypeVisibility
 */
class DeviceTypeVisibility extends Singleton {

	/**
	 * DeviceTypeVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->register_elementor_settings( 'device_type_section' );

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
			self::SECTION_PREFIX . 'device_type_section',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => __( 'Device Type', 'visibility-logic-elementor' ),
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
			self::SECTION_PREFIX . 'device_type_enabled',
			[
				'label'          => __( 'Enable', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				'prefix_class'   => 'stax-device_type_enabled-',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'device_types',
			[
				'label'          => __( 'Device', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SELECT2,
				'options'        => [
					'desktop' => __( 'Desktop', 'visibility-logic-elementor' ),
					'tablet'  => __( 'Tablet', 'visibility-logic-elementor' ),
					'mobile'  => __( 'Mobile', 'visibility-logic-elementor' ),
				],
				'description'    => __( 'Trigger visibility for specific device types.', 'visibility-logic-elementor' ),
				'multiple'       => true,
				'condition'      => [
					self::SECTION_PREFIX . 'device_type_enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'device_type_info',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'Detection is based on the User-Agent header. The element HTML is completely removed for non-matching devices.', 'visibility-logic-elementor' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => [
					self::SECTION_PREFIX . 'device_type_enabled' => 'yes',
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

		if ( ! (bool) $settings[ self::SECTION_PREFIX . 'device_type_enabled' ] || empty( $settings[ self::SECTION_PREFIX . 'device_types' ] ) ) {
			return $options;
		}

		$current_device = Resources::get_device_type();
		$options['device_type'] = in_array( $current_device, $settings[ self::SECTION_PREFIX . 'device_types' ], true );

		return $options;
	}

}

DeviceTypeVisibility::instance();
