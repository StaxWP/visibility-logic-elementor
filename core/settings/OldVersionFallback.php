<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

/**
 * Class OldVersionFallback
 */
class OldVersionFallback extends Singleton {

	/**
	 * OldVersionFallback constructor
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'elementor/element/common/_section_style/after_section_end', [ $this, 'register_section' ] );
		add_action( 'elementor/element/section/section_advanced/after_section_end', [ $this, 'register_section' ] );

		add_action(
			'elementor/element/common/' . self::SECTION_PREFIX . 'old_version_section/before_section_end',
			[
				$this,
				'register_controls',
			],
			10,
			2
		);
		add_action(
			'elementor/element/section/' . self::SECTION_PREFIX . 'old_version_section/before_section_end',
			[
				$this,
				'register_controls',
			],
			10,
			2
		);
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
			self::SECTION_PREFIX . 'old_version_section',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => __( 'Old Version (Deprecated)', 'visibility-logic-elementor' ),
				'condition' => [
					self::SECTION_PREFIX . 'enabled' => '',
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
			'ecl_enabled',
			[
				'label'        => __( 'Enable Conditions', 'visibility-logic-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'label_on'     => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'    => __( 'No', 'visibility-logic-elementor' ),
				'return_value' => 'yes',
			]
		);

		$element->add_control(
			'ecl_role_visible',
			[
				'type'        => Controls_Manager::SELECT2,
				'label'       => __( 'Visible for:', 'visibility-logic-elementor' ),
				'options'     => Resources::get_user_roles(),
				'default'     => [],
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [
					'ecl_enabled'     => 'yes',
					'ecl_role_hidden' => [],
				],
			]
		);

		$element->add_control(
			'ecl_role_hidden',
			[
				'type'        => Controls_Manager::SELECT2,
				'label'       => __( 'Hidden for:', 'visibility-logic-elementor' ),
				'options'     => Resources::get_user_roles(),
				'default'     => [],
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [
					'ecl_enabled'      => 'yes',
					'ecl_role_visible' => [],
				],
			]
		);

		$element->add_control(
			'old_version_note',
			[
				'label'           => __( 'Important Notice', 'visibility-logic-elementor' ),
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'This is a backwards compatibility for version 1.2.0. If you want to use the new version please enable the option in General tab.', 'visibility-logic-elementor' ),
				'content_classes' => 'visibility-old-version-notice',
			]
		);
	}

}

OldVersionFallback::instance();
