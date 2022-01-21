<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Settings {

	/**
	 * @var null
	 */
	public static $instance;

	/**
	 * @var string
	 */
	private $current_slug = '';

	/**
	 * @return Settings|null
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Settings constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 10 );
		add_action( 'admin_menu', [ $this, 'admin_menu_change_name' ], 200 );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( STAX_VISIBILITY_HOOK_PREFIX . 'panel_action', [ $this, 'main_panel' ] );
	}

	/**
	 * Register admin menu
	 */
	public function register_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'STAX Visibility - Dashboard', 'visibility-logic-elementor' ),
			__( 'Visibility Logic', 'visibility-logic-elementor' ),
			'manage_options',
			'stax-visibility-options',
			[ $this, 'settings_template' ]
		);
	}

	/**
	 * Change fist menu item name
	 */
	public function admin_menu_change_name() {
		global $submenu;

		if ( isset( $submenu[ Resources::get_slug() ] ) ) {
			$submenu[ Resources::get_slug() ][0][0] = __( 'Dashboard', 'visibility-logic-elementor' );
		}
	}

	/**
	 * Add body class when on plugin's settings page
	 *
	 * @param $classes
	 *
	 * @return string
	 */
	public function add_admin_body_class( $classes ) {
		if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'stax-visibility' ) !== false ) {
			$classes .= ' stax-visibility-admin-page';
		}

		return $classes;
	}

	/**
	 * Settings template
	 */
	public function settings_template() {
		$site_url      = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'admin_site_url', 'https://staxwp.com/go/visibility-logic)' );
		$wrapper_class = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'welcome_wrapper_class', [ $this->current_slug ] );
		$menu          = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'admin_menu', [] );

		if ( ! empty( $menu ) ) {
			usort(
				$menu,
				static function ( $a, $b ) {
					return $a['priority'] - $b['priority'];
				}
			);
		}

		Resources::load_template(
			'core/admin/layout',
			[
				'site_url'      => $site_url,
				'wrapper_class' => $wrapper_class,
				'menu'          => $menu,
			]
		);
	}

	/**
	 * Main template actions
	 */
	public function main_panel() {
		$current_slug = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'current_slug', $this->current_slug );

		Resources::load_template(
			'core/admin/actions',
			[
				'current_slug' => $current_slug,
			]
		);
	}

	/**
	 * Load scripts & styles
	 */
	public function admin_scripts() {
		if ( isset( $_GET['page'] ) && strpos( $_GET['page'], STAX_VISIBILITY_SLUG_PREFIX ) !== false ) {
			wp_register_style(
				'stax-visibility-tw',
				STAX_VISIBILITY_ASSETS_URL . 'css/admin.css',
				[],
				STAX_VISIBILITY_VERSION,
				'all'
			);

			wp_register_script(
				'stax-visibility-js',
				STAX_VISIBILITY_ASSETS_URL . 'js/admin.js',
				[ 'jquery' ],
				STAX_VISIBILITY_VERSION,
				true
			);

			wp_enqueue_style( 'stax-visibility-tw' );
			wp_enqueue_script( 'stax-visibility-js' );
		}
	}

}

Settings::instance();
