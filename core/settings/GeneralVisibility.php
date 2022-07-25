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
	 * GeneralVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->register_elementor_settings( 'general_section' );
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
				'label'          => __( 'Enable Visibility Logic', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				'prefix_class'   => 'stax-condition-',
				'style_transfer' => false,
			]
		);

		if ( 'section' === $element->get_type() || 'container' === $element->get_type() ) {
			$element->add_control(
				self::SECTION_PREFIX . 'hide_when_empty',
				[
					'label'          => __( 'Hide when empty', 'visibility-logic-elementor' ),
					'description'    => __( 'This will hide the entire section if all the widgets inside it are hidden because of Visiblity conditions. Please note that this won\'t apply if any of the widgets inside the section is hidden via CSS.', 'visibility-logic-elementor' ),
					'type'           => Controls_Manager::SWITCHER,
					'default'        => '',
					'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
					'label_off'      => __( 'No', 'visibility-logic-elementor' ),
					'return_value'   => 'yes',
					'separator'      => 'before',
					'condition'      => [
						self::SECTION_PREFIX . 'enabled' => '',
					],
					'style_transfer' => false,
				]
			);
		}

		$element->add_control(
			self::SECTION_PREFIX . 'show_hide',
			[
				'label'          => __( 'Show/Hide action', 'visibility-logic-elementor' ),
				'description'    => __( 'Determine if the element should be hidden or shown when the conditions are met.', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '',
				'options'        => [
					''    => __( 'Hide', 'visibility-logic-elementor' ),
					'yes' => __( 'Show', 'visibility-logic-elementor' ),
				],
				'condition'      => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'condition_type',
			[
				'label'          => __( 'Conditions Type', 'visibility-logic-elementor' ),
				'description'    => __( 'If ALL conditions need to be met or JUST ONE in order to trigger the hide/show action.', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SELECT,
				'options'        => [
					'all' => __( 'All', 'visibility-logic-elementor' ),
					'one' => __( 'At least one', 'visibility-logic-elementor' ),
				],
				'default'        => 'all',
				'condition'      => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'keep_html',
			[
				'label'          => __( 'Keep HTML/Hide by CSS', 'visibility-logic-elementor' ),
				'description'    => __( 'When the element is hidden, decide if you still want to render the HTML in the page but hidden using CSS.', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				'condition'      => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);

	}

}

GeneralVisibility::instance();
