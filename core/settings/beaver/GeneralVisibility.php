<?php

namespace Stax\VisibilityLogic\Beaver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeneralVisibility
 */
class GeneralVisibility extends BeaverWidgetBase {

	/**
	 * Register controls
	 *
	 * @return array
	 */
	public function register_controls() {
		return [
			'title'  => __( 'General', 'visibility-logic-elementor' ),
			'fields' => [
				self::SECTION_PREFIX . 'enabled'         => [
					'type'    => 'select',
					'label'   => __( 'Enable Visibility Logic', 'visibility-logic-elementor' ),
					'default' => '',
					'options' => [
						''    => __( 'No', 'visibility-logic-elementor' ),
						'yes' => __( 'Yes', 'visibility-logic-elementor' ),
					],
					'preview' => [
						'type' => 'none',
					],
					'toggle'  => [
						''    => [
							'fields' => [ self::SECTION_PREFIX . 'hide_when_empty' ],
						],
						'yes' => [
							'sections' => [
								'user-role',
								'user-meta',
							],
							'fields'   => [
								self::SECTION_PREFIX . 'show_hide',
								self::SECTION_PREFIX . 'condition_type',
								self::SECTION_PREFIX . 'keep_html',
							],
						],
					],
				],
				self::SECTION_PREFIX . 'hide_when_empty' => [
					'type'        => 'select',
					'label'       => __( 'Hide when empty', 'visibility-logic-elementor' ),
					'description' => __( 'This will hide the entire section if all the widgets inside it are hidden because of Visiblity conditions. Please note that this won\'t apply if any of the widgets inside the section is hidden via CSS.', 'visibility-logic-elementor' ),
					'default'     => 'yes',
					'options'     => [
						''    => __( 'No', 'visibility-logic-elementor' ),
						'yes' => __( 'Yes', 'visibility-logic-elementor' ),
					],
					'preview'     => [
						'type' => 'none',
					],
				],
				self::SECTION_PREFIX . 'show_hide'       => [
					'type'        => 'select',
					'label'       => __( 'Show/Hide action', 'visibility-logic-elementor' ),
					'description' => __( 'Determine if the element should be hidden or shown when the conditions are met.', 'visibility-logic-elementor' ),
					'default'     => 'yes',
					'options'     => [
						''    => __( 'No', 'visibility-logic-elementor' ),
						'yes' => __( 'Yes', 'visibility-logic-elementor' ),
					],
					'preview'     => [
						'type' => 'none',
					],
				],
				self::SECTION_PREFIX . 'condition_type'  => [
					'type'        => 'select',
					'label'       => __( 'Conditions Type', 'visibility-logic-elementor' ),
					'description' => __( 'If ALL conditions need to be met or JUST ONE in order to trigger the hide/show action.', 'visibility-logic-elementor' ),
					'default'     => 'all',
					'options'     => [
						'all' => __( 'All', 'visibility-logic-elementor' ),
						'one' => __( 'At least one', 'visibility-logic-elementor' ),
					],
					'preview'     => [
						'type' => 'none',
					],
				],
				self::SECTION_PREFIX . 'keep_html'       => [
					'type'        => 'select',
					'label'       => __( 'Keep HTML/Hide by CSS', 'visibility-logic-elementor' ),
					'description' => __( 'When the element is hidden, decide if you still want to render the HTML in the page but hidden using CSS.', 'visibility-logic-elementor' ),
					'default'     => 'yes',
					'options'     => [
						''    => __( 'No', 'visibility-logic-elementor' ),
						'yes' => __( 'Yes', 'visibility-logic-elementor' ),
					],
					'preview'     => [
						'type' => 'none',
					],
				],
			],
		];
	}
}
