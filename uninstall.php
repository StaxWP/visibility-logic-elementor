<?php
/**
 * Uninstall: remove the options of the free plugin and what the Appsero tracker (bundled until 2.5.1) stored.
 *
 * Kept: the visibility rules saved in pages, templates and blocks (part of the content), and
 * `_stax_visibility_disabled_options` / `stax_visibility_settings`, which Visibility Logic Pro also reads.
 *
 * @package Stax\VisibilityLogic
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( [ 'stax_visibility_general', 'stax_visibility_version', 'stax_visibility_updated_from_2x', 'stax_visibility_db_version', 'stax_visibility_pro_notice' ] as $stax_visibility_option ) {
	delete_option( $stax_visibility_option );
}

foreach ( [ '_tracking_last_send', '_allow_tracking', '_tracking_notice', '_tracking_skipped' ] as $stax_visibility_suffix ) {
	delete_option( 'visibility-logic-elementor' . $stax_visibility_suffix );
}

delete_transient( 'stax_visibility_rules_index' );
wp_clear_scheduled_hook( 'visibility-logic-elementor_tracker_send_event' );
delete_metadata( 'user', 0, 'stax_visibility_dismissed_3_0_notice', '', true );
