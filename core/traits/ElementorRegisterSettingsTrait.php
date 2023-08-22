<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ElementorRegisterSettingsTrait {

	/**
	 * @var array
	 */
	public $elements = [
		[
			'name'       => 'common',
			'section_id' => '_section_style',
			'prefix'     => self::SECTION_PREFIX,
		],
		[
			'name'       => 'section',
			'section_id' => 'section_advanced',
			'prefix'     => self::SECTION_PREFIX,
		],
		[
			'name'       => 'container',
			'section_id' => 'section_layout',
			'prefix'     => self::SECTION_PREFIX,
		],
	];

	/**
	 * Register elementor settings
	 *
	 * @param $option_name
	 * @return void
	 */
	public function register_elementor_settings( $option_name ) {
		foreach ( $this->elements as $element ) {
			add_action( "elementor/element/{$element['name']}/{$element['section_id']}/after_section_end", [ $this, 'register_section' ], 20 );
			add_action(
				"elementor/element/{$element['name']}/{$element['prefix']}{$option_name}/before_section_end",
				[
					$this,
					'register_controls',
				],
				10,
				2
			);
		}
	}
}
