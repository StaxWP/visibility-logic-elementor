<?php

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
	 * UserRoleVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'elementor/element/common/_section_style/after_section_end', [ $this, 'register_section' ] );
		add_action( 'elementor/element/section/section_advanced/after_section_end', [ $this, 'register_section' ] );
	}

	/**
	 * Register section
	 *
	 * @param $element
	 * @return void
	 */
	public function register_section( $element ) {
		foreach ( Resources::get_pro_widget_options() as $slug => $option ) {
			$element->start_controls_section(
				self::SECTION_PREFIX . 'pro_' . str_replace( '-', '_', $slug ),
				[
					'tab'       => self::VISIBILITY_TAB,
					'label'     => $option['name'],
					'condition' => [
						self::SECTION_PREFIX . 'enabled' => 'yes',
					],
				]
			);

			$element->end_controls_section();
		}
	}

}

ProVisibility::instance();
