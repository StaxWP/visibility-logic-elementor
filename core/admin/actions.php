<?php
/**
 * Current tab content: `stax_visibility_<slug>_page_content` with its _before and _after actions.
 *
 * @package Stax\VisibilityLogic
 *
 * @var string $current_slug Tab slug.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="stax-vl-panel stax-vl-panel--<?php echo esc_attr( $current_slug ); ?>">
	<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content_before' ); ?>

	<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content' ); ?>

	<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content_after' ); ?>
</div>
