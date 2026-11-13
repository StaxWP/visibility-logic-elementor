<?php
/**
 * Select control whose Pro options are listed but disabled (see Pro_Teaser).
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic\Controls;

use Elementor\Control_Select;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor select with a `locked_options` argument: those values are shown disabled (they cannot be picked, so
 * they are never saved). Same markup and behaviour as Elementor's select otherwise.
 */
class Select extends Control_Select {

	/**
	 * Control type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'stax_select';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	protected function get_default_settings() {
		return array_merge(
			parent::get_default_settings(),
			[
				'locked_options' => [],
			]
		);
	}

	/**
	 * Control template.
	 *
	 * @return void
	 */
	public function content_template() {
		?>
		<div class="elementor-control-field {{ data.content_classes }}">
			<# if ( data.label ) {#>
				<label for="<?php $this->print_control_uid(); ?>" class="elementor-control-title">{{{ data.label }}}</label>
			<# } #>
			<div class="elementor-control-input-wrapper elementor-control-unit-5">
				<select id="<?php $this->print_control_uid(); ?>" data-setting="{{ data.name }}">
				<# var locked = _.map( data.locked_options || [], String );
				_.each( data.options, function( option_title, option_value ) {
					var isLocked = -1 !== locked.indexOf( String( option_value ) ); #>
					<option value="{{ option_value }}" <# if ( isLocked ) { #>disabled<# } #>>{{{ option_title }}}</option>
				<# } ); #>
				</select>
			</div>
		</div>
		<# if ( data.description ) { #>
			<div class="elementor-control-field-description">{{{ data.description }}}</div>
		<# } #>
		<?php
	}
}
