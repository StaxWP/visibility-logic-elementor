<?php
/**
 * Without Visibility Logic Pro: one discreet "More conditions in Pro" section at the end of the Visibility tab.
 *
 * @package Stax\VisibilityLogic
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

/**
 * Class ProVisibility
 */
class ProVisibility extends Singleton {

	/**
	 * ProVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		foreach ( $this->elements as $element ) {
			add_action( "elementor/element/{$element['name']}/{$element['section_id']}/after_section_end", [ $this, 'register_section' ] );
		}
	}

	/**
	 * One closed section with one line and one link (2.x added an empty section per Pro condition).
	 *
	 * @param \Elementor\Controls_Stack $element Element.
	 *
	 * @return void
	 */
	public function register_section( $element ) {
		$element->start_controls_section(
			self::SECTION_PREFIX . 'more_pro',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => Pro_Teaser::more_title(),
				'condition' => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'more_pro_text',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => Pro_Teaser::more_html( 'editor' ),
				'content_classes' => 'stax-vl-pro-more-wrap',
				'style_transfer'  => false,
			]
		);

		$element->end_controls_section();
	}
}

ProVisibility::instance();
