<?php
/**
 * Elementor control hosting the page-level rule (Page_Rules) in the page settings: its value is the whole page
 * setting ( enabled, rules, hidden, message, ... ), edited by assets/js/page-rules.js.
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
 * Class Page_Rules_Control
 */
class Page_Rules_Control extends Base_Data_Control {

	/**
	 * Control type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'stax_page_rules';
	}

	/**
	 * Default value.
	 *
	 * @return array
	 */
	public function get_default_value() {
		return [];
	}

	/**
	 * Template: the builder mounts into the empty box.
	 *
	 * @return void
	 */
	public function content_template() {
		?>
		<div class="elementor-control-field stax-vl-rb-field">
			<div class="stax-vl-page-rb" data-setting="{{ data.name }}"></div>
		</div>
		<?php
	}
}
