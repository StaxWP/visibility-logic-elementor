<?php
/**
 * Plugin Name: Visibility Logic for Elementor
 * Description: Hide or show Elementor widgets based on user conditions
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

define( 'ELEMENTOR_VCE', '1.2.0' );

define( 'ELEMENTOR_VCE_FILE__', __FILE__ );
define( 'ELEMENTOR_VCE_PLUGIN_BASE', plugin_basename( ELEMENTOR_VCE_FILE__ ) );
define( 'ELEMENTOR_VCE_PATH', plugin_dir_path( ELEMENTOR_VCE_FILE__ ) );

add_action( 'plugins_loaded', 'ecl_load_plugin_textdomain' );

/**
 * Load textdomain.
 *
 * Load gettext translate for Elementor text domain.
 *
 * @return void
 * @since 1.0.0
 *
 */
function ecl_load_plugin_textdomain() {
	load_plugin_textdomain( 'visibility-logic-elementor' );
}

require_once ELEMENTOR_VCE_PATH . 'Elementor_Visibility_Control.php';
