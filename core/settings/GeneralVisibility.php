<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

/**
 * Class GeneralVisibility
 */
class GeneralVisibility extends Singleton {

	/**
	 * UserRoleVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'elementor/element/common/_section_style/after_section_end', [ $this, 'register_section' ] );
		add_action( 'elementor/element/section/section_advanced/after_section_end', [ $this, 'register_section' ] );

		add_action( 'elementor/element/common/' . self::SECTION_PREFIX . 'general_section/before_section_end', [ $this, 'register_controls' ], 10, 2 );
		add_action( 'elementor/element/section/' . self::SECTION_PREFIX . 'general_section/before_section_end', [ $this, 'register_controls' ], 10, 2 );
	}

	/**
	 * Register section
	 *
	 * @param $element
	 * @return void
	 */
	public function register_section( $element ) {
		$element->start_controls_section(
			self::SECTION_PREFIX . 'general_section',
			[
				'tab'   => self::VISIBILITY_TAB,
				'label' => __( 'General', 'visibility-logic-elementor' ),
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
			self::SECTION_PREFIX . 'enabled',
			[
				'label'        => __( 'Enable', 'visibility-logic-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'label_on'     => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'    => __( 'No', 'visibility-logic-elementor' ),
				'return_value' => 'yes',
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'condition_type',
			[
				'label'       => __( 'Condition Type', 'visibility-logic-elementor' ),
				'description' => __( 'All - All conditions must be true / One - At least one condition must be true', 'visibility-logic-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => [
					'all' => __( 'All conditions must be true', 'visibility-logic-elementor' ),
					'one' => __( 'At least one condition must be true', 'visibility-logic-elementor' ),
				],
				'default'     => 'all',
				'condition'   => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'show_hide',
			[
				'label'        => __( 'Action', 'visibility-logic-elementor' ),
				'description'  => __( 'If the conditions are met, determine if the element should be visible or not.', 'visibility-logic-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'label_on'     => __( 'Show', 'visibility-logic-elementor' ),
				'label_off'    => __( 'Hide', 'visibility-logic-elementor' ),
				'return_value' => 'yes',
				'condition'    => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'keep_html',
			[
				'label'        => __( 'Keep HTML', 'visibility-logic-elementor' ),
				'description'  => __( 'When the element is hidden, decide if you still want to render the HTML but hidden.', 'visibility-logic-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'label_on'     => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'    => __( 'No', 'visibility-logic-elementor' ),
				'return_value' => 'yes',
				'condition'    => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
			]
		);
	}

}

GeneralVisibility::instance();
