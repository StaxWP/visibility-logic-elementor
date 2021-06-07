<?php

namespace Stax\VisibilityLogic\Controls;

use \Elementor\Control_Select2;
use \Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Query extends Control_Select2 {

	/**
	 * Get control type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'stax_query';
	}

	/**
	 * Retrieve the default settings of the text control. Used to return the
	 * default settings while initializing the text control.
	 *
	 * @return array Control default settings.
	 */
	protected function get_default_settings() {
		return [
			'dynamic' => [
				'categories' => [
					TagsModule::BASE_GROUP,
					TagsModule::TEXT_CATEGORY,
					TagsModule::NUMBER_CATEGORY,
				],
			],
		];
	}

	/**
	 * Used to generate the control HTML in the editor using Underscore JS
	 * template. The variables for the class are available using `data` JS
	 * object.
	 */
	public function content_template() {
		$control_uid = $this->get_control_uid(); ?>
		<div class="elementor-control-field">
			<# if ( data.label ) {#>
				<label for="<?php echo $control_uid; ?>" class="elementor-control-title">{{{ data.label }}}</label>
			<# } #>
			<div class="elementor-control-input-wrapper elementor-control-stax-selector-wrapper">
				<# var multiple = ( data.multiple ) ? 'multiple' : ''; #>
				<select id="<?php echo $control_uid; ?>" class="elementor-select2 elementor-control-tag-area" type="select2" {{ multiple }} data-setting="{{ data.name }}">
					<# _.each( data.options, function( option_title, option_value ) {
						var value = data.controlValue;
						if ( typeof value == 'string' ) {
							var selected = ( option_value === value ) ? 'selected' : '';
						} else if ( null !== value ) {
							var value = _.values( value );
							var selected = ( -1 !== value.indexOf( option_value ) ) ? 'selected' : '';
						}
						#>
					<option {{ selected }} value="{{ option_value }}">{{{ option_title }}}</option>
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
