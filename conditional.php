<?php
/**
 * Plugin Name: Visibility Logic for Elementor
 * Description: Hide or show Elementor widgets based on conditions
 * Plugin URI: https://wordpress.org/plugins/visibility-logic-elementor
 * Author URI: https://staxwp.com
 * Author: StaxWP
 * Version: 2.3.9
 *
 * Elementor tested up to: 3.25.5
 * Elementor Pro tested up to: 3.25.5
 *
 * Text Domain: visibility-logic-elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'STAX_VISIBILITY_VERSION', '2.3.9' );

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
 * Load gettext translate for Elementor text domain.
 *
 * @return void
 * @since 1.0.0
 */
function ecl_load_plugin_textdomain() {
    load_plugin_textdomain( 'visibility-logic-elementor' );
}

add_action( 'init', 'ecl_load_plugin_textdomain', 0 );


/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function appsero_init_tracker_visibility_logic_elementor() {

    if ( ! class_exists( 'Appsero\Client' ) ) {
      require_once __DIR__ . '/vendor/appsero/client/src/Client.php';
    }

    $client = new Appsero\Client( 'd40e3204-1270-4588-b9ff-37b420fad6b8', 'Visibility Logic for Elementor', __FILE__ );

    // Active insights
    $client->insights()->init();

}

add_action( 'init', 'appsero_init_tracker_visibility_logic_elementor', 1 );
