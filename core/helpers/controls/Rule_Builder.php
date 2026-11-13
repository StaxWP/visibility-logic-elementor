<?php
/**
 * Elementor control hosting the rule builder (StaxWP Conditions) in the Visibility tab.
 *
 * Its own value is the stored rule tree (`stax_visibility_rules`), set only for rules that the 2.x settings cannot
 * express; other rules are written to the 2.x keys by the editor script (assets/js/rule-builder-elementor.js).
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic\Controls;

use Elementor\Base_Data_Control;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rule_Builder
 */
class Rule_Builder extends Base_Data_Control {

	/**
	 * Control type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'stax_rule_builder';
	}

	/**
	 * Default value: no stored tree.
	 *
	 * @return string
	 */
	public function get_default_value() {
		return '';
	}

	/**
	 * Template: the builder mounts into the empty box.
	 *
	 * @return void
	 */
	public function content_template() {
		?>
		<div class="elementor-control-field stax-vl-rb-field">
			<div class="stax-vl-rb" data-setting="{{ data.name }}"></div>
		</div>
		<?php
	}
}
