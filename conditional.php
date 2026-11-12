<?php
/**
 * Plugin Name: Visibility Logic for Elementor
 * Description: Show or hide Elementor widgets, sections and containers by user role, user meta, date and time, device, browser and ACF fields.
 * Plugin URI: https://wordpress.org/plugins/visibility-logic-elementor/
 * Author URI: https://staxwp.com
 * Author: StaxWP
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: elementor
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

require_once __DIR__ . '/vendor/autoload.php';
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
 * New installs read Date FROM/TO in the site time zone; updated sites keep the 2.x reading (UTC) until the
 * setting is switched on in Settings > Visibility Logic.
 *
 * @return void
 */
function stax_visibility_activate() {
	if ( false === get_option( 'stax_visibility_general', false ) ) {
		add_option( 'stax_visibility_general', [ 'site_timezone' => true ] );
	}

	// Elementor may have cached pages while the plugin was inactive: flush on the next request.
	delete_option( 'stax_visibility_version' );
}

register_activation_hook( __FILE__, 'stax_visibility_activate' );

/**
 * Initialize the plugin tracker (Appsero, opt-in usage data). Loaded only in the admin and in cron, the only
 * places where it does anything.
 *
 * @return void
 */
function appsero_init_tracker_visibility_logic_elementor() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- public since 2.x.
	if ( ! is_admin() && ! wp_doing_cron() ) {
		return;
	}

	if ( ! class_exists( 'Appsero\Client' ) ) {
		require_once __DIR__ . '/vendor/appsero/client/src/Client.php';
	}

	$client = new Appsero\Client( 'd40e3204-1270-4588-b9ff-37b420fad6b8', 'Visibility Logic for Elementor', __FILE__ );

	// Active insights.
	$client->insights()->init();
}

add_action( 'init', 'appsero_init_tracker_visibility_logic_elementor', 1 );
