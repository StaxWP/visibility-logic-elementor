<?php
/**
 * Settings > Visibility Logic: the one admin panel of Visibility Logic and Visibility Logic Pro.
 *
 * Tabs are added with the `stax_visibility_admin_menu` filter (items: name, link, query, priority) and filled with
 * the `stax_visibility_<slug>_page_content` action, where <slug> is the page slug without `stax-visibility-`.
 * A tab whose page (`query`) is not registered yet gets a hidden Settings page that renders this panel, so an
 * add-on only needs the filter and the action. Tabs registered by hand (2.x style) keep working.
 *
 * @package Stax\VisibilityLogic
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Instance.
	 *
	 * @var Settings|null
	 */
	public static $instance;

	/**
	 * Main page slug.
	 */
	const MAIN_PAGE = 'stax-visibility-options';

	/**
	 * Current tab slug (kept for 2.x compatibility, see current_slug()).
	 *
	 * @var string
	 */
	private $current_slug = '';

	/**
	 * Instance.
	 *
	 * @return Settings
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
		add_action( 'admin_menu', [ $this, 'register_extension_pages' ], 999 );
		add_filter( 'submenu_file', [ $this, 'highlight_menu' ] );
		add_action( 'current_screen', [ $this, 'set_page_title' ] );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'admin_init', [ $this, 'ensure_settings_option' ] );
		add_action( STAX_VISIBILITY_HOOK_PREFIX . 'panel_action', [ $this, 'main_panel' ] );
		add_filter( 'plugin_action_links_' . STAX_VISIBILITY_PLUGIN_BASE, [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Register admin menu
	 */
	public function register_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Visibility Logic for Elementor and the Block Editor', 'visibility-logic-elementor' ),
			__( 'Visibility Logic', 'visibility-logic-elementor' ),
			'manage_options',
			self::MAIN_PAGE,
			[ $this, 'settings_template' ]
		);
	}

	/**
	 * Change fist menu item name (2.x, kept for compatibility).
	 */
	public function admin_menu_change_name() {
		global $submenu;

		if ( isset( $submenu[ Resources::get_slug() ] ) ) {
			$submenu[ Resources::get_slug() ][0][0] = __( 'Dashboard', 'visibility-logic-elementor' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Hidden Settings pages for tabs whose page is not registered by their add-on.
	 */
	public function register_extension_pages() {
		global $_registered_pages;

		foreach ( $this->get_menu() as $item ) {
			$slug = $item['query'];

			if ( 0 !== strpos( $slug, STAX_VISIBILITY_SLUG_PREFIX ) ) {
				continue;
			}

			$hookname = get_plugin_page_hookname( $slug, 'options-general.php' );

			if ( ! empty( $_registered_pages[ $hookname ] ) ) {
				continue;
			}

			add_submenu_page( 'options-general.php', $item['name'], $item['name'], 'manage_options', $slug, [ $this, 'settings_template' ] );
			remove_submenu_page( 'options-general.php', $slug );
		}
	}

	/**
	 * Title of hidden tab pages (WordPress loses it once the submenu entry is removed).
	 */
	public function set_page_title() {
		global $title;

		if ( ! empty( $title ) || ! $this->is_panel_page() ) {
			return;
		}

		$current = Resources::current_page();

		foreach ( $this->get_menu() as $item ) {
			if ( $item['query'] === $current ) {
				/* translators: %s: tab name. */
				$title = sprintf( __( 'Visibility Logic: %s', 'visibility-logic-elementor' ), wp_strip_all_tags( $item['name'] ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

				return;
			}
		}

		$title = __( 'Visibility Logic', 'visibility-logic-elementor' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Keep Settings > Visibility Logic highlighted on every tab.
	 *
	 * @param string|null $submenu_file Submenu file.
	 *
	 * @return string|null
	 */
	public function highlight_menu( $submenu_file ) {
		if ( $this->is_panel_page() ) {
			return self::MAIN_PAGE;
		}

		return $submenu_file;
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'visibility-logic-elementor' ) . '</a>' );

		return $links;
	}

	/**
	 * Store the general settings once (autoloaded), so front-end requests read them from the options cache.
	 */
	public function ensure_settings_option() {
		if ( false === get_option( Resources::SETTINGS_OPTION, false ) ) {
			add_option( Resources::SETTINGS_OPTION, Resources::default_settings() );
		}
	}

	/**
	 * URL of a panel tab.
	 *
	 * @param string $slug Tab slug without the `stax-visibility-` prefix.
	 *
	 * @return string
	 */
	public static function url( $slug = 'options' ) {
		return admin_url( 'options-general.php?page=' . STAX_VISIBILITY_SLUG_PREFIX . sanitize_key( $slug ) );
	}

	/**
	 * Whether the current admin page is one of the panel's tabs.
	 *
	 * @return bool
	 */
	public function is_panel_page() {
		return 0 === strpos( Resources::current_page(), STAX_VISIBILITY_SLUG_PREFIX );
	}

	/**
	 * Add body class when on plugin's settings page
	 *
	 * @param string $classes Classes.
	 *
	 * @return string
	 */
	public function add_admin_body_class( $classes ) {
		if ( $this->is_panel_page() ) {
			$classes .= ' stax-visibility-admin-page';
		}

		return $classes;
	}

	/**
	 * Tabs, sorted by priority.
	 *
	 * @return array[]
	 */
	public function get_menu() {
		$menu = (array) apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'admin_menu', [] );
		$menu = array_values(
			array_filter(
				$menu,
				static function ( $item ) {
					return is_array( $item ) && ! empty( $item['name'] ) && ! empty( $item['query'] );
				}
			)
		);

		foreach ( $menu as $i => $item ) {
			$menu[ $i ] = wp_parse_args(
				$item,
				[
					'link'     => admin_url( 'options-general.php?page=' . $item['query'] ),
					'priority' => 10,
				]
			);
		}

		usort(
			$menu,
			static function ( $a, $b ) {
				return (int) $a['priority'] - (int) $b['priority'];
			}
		);

		return $menu;
	}

	/**
	 * Current tab slug: set by the tab (filter `stax_visibility_current_slug`, 2.x) or taken from the page slug.
	 *
	 * @return string
	 */
	public function current_slug() {
		$slug = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'current_slug', $this->current_slug );

		if ( '' === (string) $slug && $this->is_panel_page() ) {
			$slug = substr( Resources::current_page(), strlen( STAX_VISIBILITY_SLUG_PREFIX ) );
		}

		return sanitize_key( (string) $slug );
	}

	/**
	 * State of Visibility Logic Pro for the sidebar.
	 *
	 * Pro can answer through the `stax_visibility_pro_status` filter with an array:
	 * `licensed` (bool), `label` (string), `manage_url` (string).
	 *
	 * @return array{active:bool,licensed:bool|null,label:string,manage_url:string}
	 */
	public function pro_status() {
		$status = [
			'active'     => Plugin::instance()->has_pro(),
			'licensed'   => null,
			'label'      => '',
			'manage_url' => '',
		];

		if ( ! $status['active'] ) {
			return $status;
		}

		if ( class_exists( '\Stax\VisibilityLogicPro\License' ) && method_exists( '\Stax\VisibilityLogicPro\License', 'state' ) ) {
			$state                = \Stax\VisibilityLogicPro\License::state();
			$status['licensed']   = empty( $state['locked'] );
			$status['manage_url'] = admin_url( 'options-general.php?page=' . ( defined( '\Stax\VisibilityLogicPro\License::PAGE' ) ? \Stax\VisibilityLogicPro\License::PAGE : 'stax-visibility-license' ) );
		} elseif ( class_exists( '\Stax\VisibilityLogicPro\Plugin' ) && method_exists( \Stax\VisibilityLogicPro\Plugin::instance(), 'is_license_active' ) ) {
			$status['licensed']   = (bool) \Stax\VisibilityLogicPro\Plugin::instance()->is_license_active();
			$status['manage_url'] = admin_url( 'options-general.php?page=stax_wc_visibility_logic_activation' );
		}

		$filtered = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'pro_status', null );

		if ( is_array( $filtered ) ) {
			$status = array_merge( $status, array_intersect_key( $filtered, $status ) );
		}

		return $status;
	}

	/**
	 * Settings template (the panel).
	 */
	public function settings_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'visibility-logic-elementor' ), 403 );
		}

		$site_url      = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'admin_site_url', 'https://staxwp.com/go/visibility-logic' );
		$wrapper_class = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'welcome_wrapper_class', [ $this->current_slug() ] );

		Resources::load_template(
			'core/admin/layout',
			[
				'site_url'      => $site_url,
				'wrapper_class' => (array) $wrapper_class,
				'menu'          => $this->get_menu(),
				'current_page'  => Resources::current_page(),
				'pro'           => $this->pro_status(),
			]
		);
	}

	/**
	 * Main template actions
	 */
	public function main_panel() {
		Resources::load_template(
			'core/admin/actions',
			[
				'current_slug' => $this->current_slug(),
			]
		);
	}

	/**
	 * Load scripts & styles, only on the panel's pages.
	 */
	public function admin_scripts() {
		if ( ! $this->is_panel_page() ) {
			return;
		}

		wp_enqueue_style(
			'stax-visibility-settings',
			STAX_VISIBILITY_ASSETS_URL . 'css/settings.css',
			[],
			STAX_VISIBILITY_VERSION
		);

		// Utility classes (`ste-*`) used by tabs built for the 2.x panel, such as Visibility Logic Pro's settings.
		if ( ! in_array( $this->current_slug(), [ 'options', 'usage' ], true ) ) {
			wp_enqueue_style(
				'stax-visibility-tw',
				STAX_VISIBILITY_ASSETS_URL . 'css/admin.css',
				[],
				STAX_VISIBILITY_VERSION
			);
		}
	}
}

Settings::instance();
