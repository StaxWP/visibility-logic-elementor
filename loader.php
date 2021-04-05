<?php
/**
 * Plugin Name: Visibility Logic for Elementor
 * Description: Hide or show Elementor widgets based on conditions
 * Plugin URI: https://wordpress.org/plugins/visibility-logic-elementor
 * Author URI: https://seventhqueen.com
 * Author: SeventhQueen
 * Version: 1.2.0
 *
 * Text Domain: visibility-logic-elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'STAX_VISIBILITY_VERSION', '1.2.0' );

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

add_action( 'plugins_loaded', 'ecl_load_plugin_textdomain' );

/**
 * Load textdomain.
 *
 * Load gettext translate for Elementor text domain.
 *
 * @return void
 * @since 1.0.0
 */
function ecl_load_plugin_textdomain() {
	load_plugin_textdomain( 'visibility-logic-elementor' );
}

require_once STAX_VISIBILITY_CORE_PATH . 'Singleton.php';
require_once STAX_VISIBILITY_CORE_PATH . 'Plugin.php';
