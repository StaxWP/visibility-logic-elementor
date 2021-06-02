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

		add_action( 'elementor/element/common/_section_style/after_section_end', [ $this, 'register_section' ] );
		add_action( 'elementor/element/section/section_advanced/after_section_end', [ $this, 'register_section' ] );

		add_action( 'elementor/element/common/' . self::SECTION_PREFIX . 'user_meta_section/before_section_end', [ $this, 'register_controls' ], 10, 2 );
		add_action( 'elementor/element/section/' . self::SECTION_PREFIX . 'user_meta_section/before_section_end', [ $this, 'register_controls' ], 10, 2 );

		add_filter( 'stax/visibility/apply_conditions', [ $this, 'apply_conditions' ], 10, 2 );
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
				'label'        => __( 'Enable', 'visibility-logic-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'label_on'     => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'    => __( 'No', 'visibility-logic-elementor' ),
				'return_value' => 'yes',
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_options',
			[
				'type'        => Controls_Manager::SELECT2,
				'label'       => __( 'Select Meta:', 'visibility-logic-elementor' ),
				'options'     => Resources::get_user_metas(),
				'default'     => [],
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
				],
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_status',
			[
				'label'     => __( 'Meta Status', 'visibility-logic-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'empty'          => [
						'title' => __( 'Meta empty', 'visibility-logic-elementor' ),
						'icon'  => 'fa fa-circle-o',
					],
					'not_empty'      => [
						'title' => __( 'Meta not empty', 'visibility-logic-elementor' ),
						'icon'  => 'fa fa-dot-circle-o',
					],
					'specific_value' => [
						'title' => __( 'Specific meta value', 'visibility-logic-elementor' ),
						'icon'  => 'fa fa-circle',
					],
				],
				'default'   => 'exists',
				'toggle'    => false,
				'condition' => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
					self::SECTION_PREFIX . 'user_meta_options!' => '',
				],
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'user_meta_value',
			[
				'label'       => __( 'Meta Value', 'visibility-logic-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'The specific value of the User Meta', 'visibility-logic-elementor' ),
				'condition'   => [
					self::SECTION_PREFIX . 'user_meta_enabled' => 'yes',
					self::SECTION_PREFIX . 'user_meta_options!' => '',
					self::SECTION_PREFIX . 'user_meta_status' => 'specific_value',
				],
			]
		);
	}

	/**
	 * Apply conditions
	 *
	 * @param array $options
	 * @param array $settings
	 * @return array
	 */
	public function apply_conditions( $options, $settings ) {
		if ( (bool) $settings[ self::SECTION_PREFIX . 'user_meta_enabled' ] ) {
			$current_user = wp_get_current_user();

			$meta_check_type  = $settings[ self::SECTION_PREFIX . 'user_meta_status' ];
			$meta_check_value = $settings[ self::SECTION_PREFIX . 'user_meta_value' ];

			$meta_is_consistent = true;

			foreach ( $settings[ self::SECTION_PREFIX . 'user_meta_options' ] as $meta ) {
				$user_meta = get_user_meta( $current_user->ID, $meta, true );

				if ( 'empty' === $meta_check_type ) {
					if ( ! empty( $user_meta ) ) {
						$meta_is_consistent = false;
					}
				} elseif ( 'not_empty' === $meta_check_type ) {
					if ( empty( $user_meta ) ) {
						$meta_is_consistent = false;
					}
				} elseif ( 'specific_value' === $meta_check_type ) {
					if ( $user_meta !== $meta_check_value ) {
						$meta_is_consistent = false;
					}
				}
			}

			// If conditions are met for each meta selected, then alter the output.
			$options['user_meta'] = $meta_is_consistent;
		}

		return $options;
	}

}

UserMetaVisibility::instance();
