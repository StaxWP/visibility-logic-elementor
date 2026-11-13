<?php
/**
 * Text fallback (free since 3.0, backlog B3): "When hidden: show a message" for Elementor elements and blocks.
 *
 * Visibility Logic Pro has its own Fallback section (text and template): this file only registers the Elementor
 * controls when Pro is not active, with the same setting names, so values saved with either are the same. The
 * fallback is printed by Plugin::content_change() (Elementor) and Blocks::render_block() (blocks).
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

/**
 * Class FallbackText
 */
class FallbackText extends Singleton {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->register_elementor_settings( 'fallback_section' );
	}

	/**
	 * Section.
	 *
	 * @param \Elementor\Element_Base $element Element.
	 *
	 * @return void
	 */
	public function register_section( $element ) {
		$element->start_controls_section(
			self::SECTION_PREFIX . 'fallback_section',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => __( 'Fallback', 'visibility-logic-elementor' ),
				'condition' => [ self::SECTION_PREFIX . 'enabled' => 'yes' ],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * Controls.
	 *
	 * @param \Elementor\Element_Base $element Element.
	 * @param array                   $args    Args.
	 *
	 * @return void
	 */
	public function register_controls( $element, $args ) {
		$element->add_control(
			self::SECTION_PREFIX . 'fallback_enabled',
			[
				'label'          => __( 'Show a message when hidden', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'return_value'   => 'yes',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'fallback_type',
			[
				'label'          => __( 'Content', 'visibility-logic-elementor' ),
				'type'           => 'stax_select',
				'options'        => [
					'text'     => __( 'Text', 'visibility-logic-elementor' ),
					'template' => Pro_Teaser::label( __( 'Elementor template', 'visibility-logic-elementor' ) ),
				],
				'locked_options' => [ 'template' ],
				'default'        => 'text',
				'condition'      => [ self::SECTION_PREFIX . 'fallback_enabled' => 'yes' ],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'fallback_text',
			[
				'label'          => __( 'Message', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::WYSIWYG,
				'default'        => '',
				'condition'      => [
					self::SECTION_PREFIX . 'fallback_enabled' => 'yes',
					self::SECTION_PREFIX . 'fallback_type'    => 'text',
				],
				'style_transfer' => false,
			]
		);
	}
}

FallbackText::instance();
