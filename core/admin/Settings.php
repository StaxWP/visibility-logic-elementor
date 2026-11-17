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
	 * Page that Settings > Visibility Logic opens (3.0: the Rules overview, the daily screen).
	 */
	const RULES_PAGE = 'stax-visibility-usage';

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
			self::RULES_PAGE,
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
			return self::RULES_PAGE;
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
		array_unshift( $links, '<a href="' . esc_url( self::url( 'usage' ) ) . '">' . esc_html__( 'Settings', 'visibility-logic-elementor' ) . '</a>' );

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
	 * Inline SVG icons of the panel (24px grid, stroke drawn in currentColor).
	 */
	const ICONS = [
		'eye'     => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'list'    => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
		'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
		'gear'    => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
		'key'     => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="M21 2l-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
		'stack'   => '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
		'alert'   => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
		'user'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'file'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
		'clock'   => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'phone'   => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
		'globe'   => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>',
		'cart'    => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
		'db'      => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.7-4 3-9 3s-9-1.3-9-3M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/>',
		'link'    => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
		'lock'    => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'check'   => '<path d="M20 6 9 17l-5-5"/>',
		'search'  => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'edit'    => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		'ext'     => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/>',
		'book'    => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
		'chat'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'star'    => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
		'box'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/>',
		'blocks'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
		'zap'     => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
		'shuffle' => '<path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>',
		'code'    => '<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',
		'refresh' => '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/>',
	];

	/**
	 * Print a panel icon.
	 *
	 * @param string $name  Key of self::ICONS.
	 * @param string $class Extra class.
	 *
	 * @return void
	 */
	public static function icon( $name, $class = '' ) {
		$paths = isset( self::ICONS[ $name ] ) ? self::ICONS[ $name ] : self::ICONS['box'];

		echo '<svg class="stax-vl-icon ' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup from self::ICONS.
	}

	/**
	 * Icon of a tab, by its page slug (add-on tabs can set `icon` on their menu item).
	 *
	 * @param array $item Menu item.
	 *
	 * @return string
	 */
	public static function tab_icon( $item ) {
		if ( ! empty( $item['icon'] ) && isset( self::ICONS[ $item['icon'] ] ) ) {
			return $item['icon'];
		}

		$icons = [
			'usage'     => 'list',
			'options'   => 'sliders',
						'rule-sets' => 'stack',
			'settings'  => 'gear',
			'license'   => 'key',
		];
		$slug  = substr( (string) $item['query'], strlen( STAX_VISIBILITY_SLUG_PREFIX ) );

		foreach ( $icons as $key => $icon ) {
			if ( false !== strpos( $slug, $key ) ) {
				return $icon;
			}
		}

		return 'box';
	}

	/**
	 * Rules that need attention, from the cached index only (no scan on other tabs).
	 *
	 * @return int
	 */
	public static function attention_count() {
		$index = get_transient( Rules::CACHE_KEY );

		if ( ! is_array( $index ) || empty( $index['documents'] ) ) {
			return 0;
		}

		$count = 0;

		foreach ( Rules::overview_rows( $index ) as $row ) {
			if ( array_diff( $row['codes'], [ 'cache', 'draft' ] ) ) {
				++$count;
			}
		}

		return $count;
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

		// One tab per page: Visibility Logic Pro's own "Settings" item shows its settings in the free Settings tab.
		$seen = [];
		$menu = array_values(
			array_filter(
				$menu,
				static function ( $item ) use ( &$seen ) {
					if ( isset( $seen[ $item['query'] ] ) ) {
						return false;
					}

					$seen[ $item['query'] ] = true;

					return true;
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
	 * Asset version: plugin version plus file time, so a changed file is never served from the browser cache.
	 *
	 * @param string $file Path inside assets/.
	 *
	 * @return string
	 */
	private static function asset_version( $file ) {
		$time = @filemtime( STAX_VISIBILITY_PATH . 'assets/' . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file only drops the suffix.

		return STAX_VISIBILITY_VERSION . ( $time ? '.' . $time : '' );
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
			self::asset_version( 'css/settings.css' )
		);

		wp_enqueue_script(
			'stax-visibility-settings',
			STAX_VISIBILITY_ASSETS_URL . 'js/settings.js',
			[],
			self::asset_version( 'js/settings.js' ),
			true
		);

		// Utility classes (`ste-*`) used by tabs built for the 2.x panel, such as Visibility Logic Pro's settings.
		if ( ! in_array( $this->current_slug(), [ 'options', 'usage', 'settings' ], true ) ) {
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
