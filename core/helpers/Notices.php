<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Notices extends Singleton {

	/**
	 * Elementor not installed notice
	 */
	public function elementor_notice() {
		$class = 'notice notice-warning';
		/* translators: %s: html tags */
		$message = sprintf( __( '%1$sVisibility Logic %2$s requires %1$sElementor%2$s plugin installed & activated.', 'visibility-logic-elementor' ), '<strong>', '</strong>' );

		$plugin = 'elementor/elementor.php';

		if ( Resources::instance()->is_plugin_installed( $plugin ) ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$action_url   = wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . $plugin . '&plugin_status=all&paged=1' ), 'activate-plugin_' . $plugin );
			$button_label = __( 'Activate Elementor', 'visibility-logic-elementor' );

		} else {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return;
			}

			$action_url   = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );
			$button_label = __( 'Install Elementor', 'visibility-logic-elementor' );
		}

		printf(
			'<div class="%1$s"><p>%2$s</p><p><a href="%3$s" class="button-primary">%4$s</a></p></div>',
			esc_attr( $class ),
			wp_kses( $message, [ 'strong' => [] ] ),
			esc_url( $action_url ),
			esc_html( $button_label )
		);
	}

	/**
	 * Displays notice on the admin dashboard if Elementor version is lower than the
	 * required minimum.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function minimum_elementor_version() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		if ( isset( $_GET['activate'] ) ) { // WPCS: CSRF ok, input var ok.
			unset( $_GET['activate'] ); // WPCS: input var ok.
		}

		$message = sprintf(
			'<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">'
			/* translators: 1: Plugin name 2: Elementor */
			. esc_html__( '%1$s requires version %3$s or greater of %2$s plugin.', 'visibility-logic-elementor' )
			. '</span>',
			'<strong>' . __( 'Visibility Logic', 'visibility-logic-elementor' ) . '</strong>',
			'<strong>' . __( 'Elementor', 'visibility-logic-elementor' ) . '</strong>',
			Plugin::$minimum_elementor_version
		);

		$file_path   = 'elementor/elementor.php';
		$update_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );

		$message .= sprintf(
			'<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">' .
			'<a class="button-primary" href="%1$s">%2$s</a></span>',
			esc_url( $update_link ),
			esc_html__( 'Update Elementor Now', 'visibility-logic-elementor' )
		);

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', wp_kses_post( $message ) );
	}
}
