<?php
/**
 * Plugin Name: Visibility Logic for Elementor
 * Description: Show or hide Elementor widgets, sections, containers, Elementor 4 elements and blocks by user role, user meta, date and time, device, browser and ACF fields.
 * Plugin URI: https://wordpress.org/plugins/visibility-logic-elementor/
 * Author URI: https://staxwp.com
 * Author: StaxWP
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Elementor tested up to: 4.4.0
 * Elementor Pro tested up to: 4.4.0
 *
 * Text Domain: visibility-logic-elementor
 *
 * @package Stax\VisibilityLogic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'STAX_VISIBILITY_VERSION', '3.0.0' );

define( 'STAX_VISIBILITY_FILE', __FILE__ );
define( 'STAX_VISIBILITY_PLUGIN_BASE', plugin_basename( STAX_VISIBILITY_FILE ) );
define( 'STAX_VISIBILITY_PATH', plugin_dir_path( STAX_VISIBILITY_FILE ) );
define( 'STAX_VISIBILITY_BASE_URL', plugins_url( '/', STAX_VISIBILITY_FILE ) );
define( 'STAX_VISIBILITY_ASSETS_URL', STAX_VISIBILITY_BASE_URL . 'assets/' );
define( 'STAX_VISIBILITY_CORE_PATH', STAX_VISIBILITY_PATH . 'core/' );
define( 'STAX_VISIBILITY_CORE_HELPERS_PATH', STAX_VISIBILITY_CORE_PATH . 'helpers/' );
define( 'STAX_VISIBILITY_CORE_SETTINGS_PATH', STAX_VISIBILITY_CORE_PATH . 'settings/' );

define( 'STAX_VISIBILITY_HOOK_PREFIX', 'stax_visibility_' );
define( 'STAX_VISIBILITY_SLUG_PREFIX', 'stax-visibility-' );

require_once STAX_VISIBILITY_CORE_PATH . 'Singleton.php';
require_once STAX_VISIBILITY_CORE_PATH . 'Plugin.php';

/**
 * Load textdomain.
 *
 * Translations from translate.wordpress.org load on demand; this keeps translations placed in
 * wp-content/languages/plugins working on older setups.
 *
 * @return void
 * @since 1.0.0
 */
function ecl_load_plugin_textdomain() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- public since 1.0.
	load_plugin_textdomain( 'visibility-logic-elementor' );
}

add_action( 'init', 'ecl_load_plugin_textdomain', 0 );

/**
 * Activation.
 *
 * First activation on a site without Visibility Logic data: the 3.0 behaviours (see
 * Resources::default_settings()). A site that already has rules or saved options keeps the 2.x behaviour.
 *
 * @return void
 */
function stax_visibility_activate() {
	if ( false === get_option( 'stax_visibility_general', false ) ) {
		$settings = \Stax\VisibilityLogic\Resources::site_has_existing_data()
			? \Stax\VisibilityLogic\Resources::default_settings()
			: \Stax\VisibilityLogic\Resources::new_install_settings();

		add_option( 'stax_visibility_general', $settings );
	}

	// Elementor may have cached pages while the plugin was inactive: flush on the next request.
	delete_option( 'stax_visibility_version' );
}

register_activation_hook( __FILE__, 'stax_visibility_activate' );
