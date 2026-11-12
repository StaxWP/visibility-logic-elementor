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

		$this->register_elementor_settings( 'old_version_section' );
	}

	/**
	 * Register section
	 *
	 * @param $element
	 *
	 * @return void
	 */
	public function register_section( $element ) {
		if ( $this->document_has_old_data() ) {
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
	}

	/**
	 * Whether the edited document has 1.x settings (checked once per request, it used to run per element).
	 *
	 * @return bool
	 */
	private function document_has_old_data() {
		static $cache = [];

		$post_id = (int) get_the_ID();

		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		$meta = $post_id ? get_post_meta( $post_id, '_elementor_data', true ) : '';

		if ( is_array( $meta ) ) {
			$data = $meta;
		} else {
			$data = $meta ? json_decode( (string) $meta, true ) : null;
		}

		$has_old_data = false;

		if ( is_array( $data ) ) {
			foreach ( $data as $data_item ) {
				if ( ! is_array( $data_item ) ) {
					continue;
				}
				$item_data    = $this->check_for_old_data( $data_item, $has_old_data );
				$has_old_data = $item_data['has_old_data'];
			}
		}

		$cache[ $post_id ] = $has_old_data;

		return $has_old_data;
	}

	/**
	 * Check for old data
	 *
	 * @param array $item
	 * @param bool  $has_old_data
	 *
	 * @return array
	 */
	private function check_for_old_data( $item, $has_old_data ) {
		if ( isset( $item['elType'] ) && 'column' !== $item['elType'] ) {
			if ( isset( $item['settings']['ecl_enabled'] ) ||
				isset( $item['settings']['ecl_role_visible'] ) ||
				isset( $item['settings']['ecl_role_hidden'] ) ) {
				$has_old_data = true;
			}
		}

		if ( isset( $item['elements'] ) && ! empty( $item['elements'] ) ) {
			foreach ( $item['elements'] as &$sub_item ) {
				$sub_item_data = $this->check_for_old_data( $sub_item, $has_old_data );

				$sub_item     = $sub_item_data['item'];
				$has_old_data = $sub_item_data['has_old_data'];
			}
		}

		return [
			'item'         => $item,
			'has_old_data' => $has_old_data,
		];
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
				'label'          => __( 'Enable Conditions', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			'ecl_role_visible',
			[
				'type'           => Controls_Manager::SELECT2,
				'label'          => __( 'Visible for:', 'visibility-logic-elementor' ),
				'options'        => Resources::get_user_roles(),
				'default'        => [],
				'multiple'       => true,
				'label_block'    => true,
				'condition'      => [
					'ecl_enabled'     => 'yes',
					'ecl_role_hidden' => [],
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			'ecl_role_hidden',
			[
				'type'           => Controls_Manager::SELECT2,
				'label'          => __( 'Hidden for:', 'visibility-logic-elementor' ),
				'options'        => Resources::get_user_roles(),
				'default'        => [],
				'multiple'       => true,
				'label_block'    => true,
				'condition'      => [
					'ecl_enabled'      => 'yes',
					'ecl_role_visible' => [],
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			'old_version_note',
			[
				'label'           => __( 'Important Notice', 'visibility-logic-elementor' ),
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'This is a backwards compatibility for version 1.2.0. If you want to use the new version please enable the option in General tab.', 'visibility-logic-elementor' ),
				'content_classes' => 'visibility-old-version-notice',
				'style_transfer'  => false,
			]
		);
	}
}

OldVersionFallback::instance();
