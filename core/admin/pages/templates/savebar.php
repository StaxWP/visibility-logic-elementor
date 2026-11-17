<?php
/**
 * Sticky save bar at the bottom of a settings card (settings.js marks it when the form has unsaved changes).
 *
 * @package Stax\VisibilityLogic
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="stax-vl-savebar">
	<span class="stax-vl-savebar__state" data-stax-vl-state data-dirty="<?php esc_attr_e( 'Unsaved changes', 'visibility-logic-elementor' ); ?>" aria-live="polite"></span>
	<button type="submit" class="stax-vl-btn stax-vl-btn--primary"><?php esc_html_e( 'Save changes', 'visibility-logic-elementor' ); ?></button>
</div>
