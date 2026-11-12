<?php
/**
 * Shared helpers: plugin state, condition list, visitor detection, templates.
 *
 * @package Stax\VisibilityLogic
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Resources extends Singleton {

	/**
	 * Per-request cache of the visitor detection.
	 *
	 * @var array
	 */
	private static $detected = [];

	/**
	 * Whether a plugin is active on this site or network wide.
	 *
	 * @param string $plugin_slug Plugin basename, e.g. `elementor/elementor.php`.
	 *
	 * @return bool
	 */
	public function is_plugin_active( $plugin_slug ) {
		$active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.

		if ( in_array( $plugin_slug, $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', [] );

			return isset( $network[ $plugin_slug ] );
		}

		return false;
	}

	/**
	 * Whether a plugin is installed.
	 *
	 * Checks the plugin file instead of `get_plugins()`, which reads the headers of every installed plugin and is
	 * not loaded on the front end.
	 *
	 * @param string $plugin_path Plugin basename.
	 *
	 * @return bool
	 */
	public function is_plugin_installed( $plugin_path ) {
		if ( $this->is_plugin_active( $plugin_path ) ) {
			return true;
		}

		$plugin_path = wp_normalize_path( (string) $plugin_path );

		if ( '' === $plugin_path || false !== strpos( $plugin_path, '..' ) ) {
			return false;
		}

		return file_exists( trailingslashit( WP_PLUGIN_DIR ) . $plugin_path );
	}

	/**
	 * Load template
	 *
	 * @param $name
	 * @param array $args
	 * @param bool  $echo
	 *
	 * @return false|string|void
	 */
	public static function load_template( $name, $args = [], $echo = true ) {
		if ( ! $name ) {
			return;
		}

		$file = STAX_VISIBILITY_PATH . trim( $name ) . '.php';

		if ( false !== strpos( $name, '..' ) || ! file_exists( $file ) ) {
			return;
		}

		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables.

		ob_start();
		include $file;

		if ( $echo ) {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- templates escape their output.
		} else {
			return ob_get_clean();
		}
	}

	/**
	 * Option holding the general settings of the free plugin.
	 */
	const SETTINGS_OPTION = 'stax_visibility_general';

	/**
	 * Default general settings: the 2.x behaviour. An update never changes how an existing site works; each
	 * newer behaviour is a setting in Settings > Visibility Logic.
	 *
	 * - `site_timezone`: read Date FROM/TO in the site time zone (2.x: UTC). Opt-in for every site.
	 * - `container_widgets`: "Hide when empty" counts widgets placed directly in a container (2.x skipped them,
	 *   so such a container was always hidden). On for new installs, opt-in for updated sites.
	 * - `element_cache`: keep Elementor's element cache on for pages with rules and render only the elements
	 *   with rules per request (2.x bypassed the cache for the whole page). On for new installs, opt-in for
	 *   updated sites.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return [
			'site_timezone'     => false,
			'container_widgets' => false,
			'element_cache'     => false,
		];
	}

	/**
	 * Settings of a new install (no Visibility Logic data on the site yet).
	 *
	 * @return array
	 */
	public static function new_install_settings() {
		return array_merge(
			self::default_settings(),
			[
				'container_widgets' => true,
				'element_cache'     => true,
			]
		);
	}

	/**
	 * Whether the site already has Visibility Logic data (rules in Elementor data or saved options).
	 *
	 * @return bool
	 */
	public static function site_has_existing_data() {
		global $wpdb;

		if ( false !== get_option( '_stax_visibility_disabled_options', false ) || false !== get_option( 'stax_visibility_settings', false ) ) {
			return true;
		}

		$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND ( meta_value LIKE %s OR meta_value LIKE %s ) LIMIT 1",
				'%' . $wpdb->esc_like( '"stax_visibility_' ) . '%',
				'%' . $wpdb->esc_like( '"ecl_enabled"' ) . '%'
			)
		);

		return ! empty( $found );
	}

	/**
	 * A general setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default when unknown.
	 *
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = wp_parse_args( (array) get_option( self::SETTINGS_OPTION, [] ), self::default_settings() );

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Visibility Logic Pro page on staxwp.com.
	 *
	 * @param string $context Where the link is shown (analytics only).
	 *
	 * @return string
	 */
	public static function pro_url( $context = 'panel' ) {
		$url = apply_filters( STAX_VISIBILITY_HOOK_PREFIX . 'admin_site_url', 'https://staxwp.com/go/visibility-logic' );

		return add_query_arg(
			[
				'utm_source'   => 'visibility-logic-free',
				'utm_medium'   => sanitize_key( $context ),
				'utm_campaign' => 'upgrade',
			],
			$url
		);
	}

	/**
	 * Get plugin slug
	 *
	 * @return string
	 */
	public static function get_slug() {
		return STAX_VISIBILITY_SLUG_PREFIX . 'addons';
	}

	/**
	 * Check if current page is
	 *
	 * @param $page
	 *
	 * @return bool
	 */
	public static function is_current_page( $page ) {
		$page = STAX_VISIBILITY_SLUG_PREFIX . $page;

		return self::current_page() === $page;
	}

	/**
	 * Current admin page slug (`page` query argument).
	 *
	 * @return string
	 */
	public static function current_page() {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only.
	}

	/**
	 * Get all visibility options
	 *
	 * @param boolean $with_pro
	 * @return array
	 */
	public static function get_all_widget_options( $with_pro = true ) {
		$options = [
			'user-role'    => [
				'name'  => __( 'User Role', 'visibility-logic-elementor' ),
				'pro'   => false,
				'class' => STAX_VISIBILITY_CORE_SETTINGS_PATH . 'UserRoleVisibility.php',
			],
			'user-meta'    => [
				'name'  => __( 'User Meta', 'visibility-logic-elementor' ),
				'pro'   => false,
				'class' => STAX_VISIBILITY_CORE_SETTINGS_PATH . 'UserMetaVisibility.php',
			],
			'date-time'    => [
				'name'  => __( 'Date & Time', 'visibility-logic-elementor' ),
				'pro'   => false,
				'class' => STAX_VISIBILITY_CORE_SETTINGS_PATH . 'DateTimeVisibility.php',
			],
			'browser-type' => [
				'name'  => __( 'Browser Type', 'visibility-logic-elementor' ),
				'pro'   => false,
				'class' => STAX_VISIBILITY_CORE_SETTINGS_PATH . 'BrowserTypeVisiblity.php',
			],
			'device-type'  => [
				'name'  => __( 'Device Type', 'visibility-logic-elementor' ),
				'pro'   => false,
				'class' => STAX_VISIBILITY_CORE_SETTINGS_PATH . 'DeviceTypeVisibility.php',
			],
			'acf'          => [
				'name'  => __( 'ACF Field', 'visibility-logic-elementor' ),
				'pro'   => false,
				'class' => STAX_VISIBILITY_CORE_SETTINGS_PATH . 'AcfVisibility.php',
			],

		];

		if ( $with_pro ) {
			$options = array_merge(
				$options,
				self::get_pro_widget_options()
			);
		}

		$options = apply_filters( 'stax/visibility/options', $options ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.

		$descriptions = self::get_option_descriptions();

		foreach ( $options as $slug => $option ) {
			if ( empty( $option['description'] ) && isset( $descriptions[ $slug ] ) ) {
				$options[ $slug ]['description'] = $descriptions[ $slug ];
			}
		}

		$disabled_options = (array) get_option( '_stax_visibility_disabled_options', [] );

		foreach ( $options as $slug => $option ) {
			$options[ $slug ]['status'] = ! isset( $disabled_options[ $slug ] );
		}

		return $options;
	}

	/**
	 * Conditions included in the free plugin.
	 */
	const FREE_CONDITIONS = [ 'user-role', 'user-meta', 'date-time', 'browser-type', 'device-type', 'acf' ];

	/**
	 * Whether a condition belongs to the free plugin.
	 *
	 * @param string $slug Condition slug.
	 *
	 * @return bool
	 */
	public static function is_free_condition( $slug ) {
		return in_array( $slug, self::FREE_CONDITIONS, true );
	}

	/**
	 * Short descriptions of the conditions, shown in Settings > Visibility Logic.
	 *
	 * @return array
	 */
	public static function get_option_descriptions() {
		return [
			'user-role'          => __( 'Guests, logged in users or specific roles.', 'visibility-logic-elementor' ),
			'user-meta'          => __( 'Compare a field or meta value of the logged in user.', 'visibility-logic-elementor' ),
			'date-time'          => __( 'Date range, time of day and days of the week.', 'visibility-logic-elementor' ),
			'browser-type'       => __( 'Chrome, Firefox, Safari, Edge and more.', 'visibility-logic-elementor' ),
			'device-type'        => __( 'Desktop, tablet or mobile, detected on the server.', 'visibility-logic-elementor' ),
			'acf'                => __( 'One ACF field of the current post or page.', 'visibility-logic-elementor' ),
			'ip-referrer'        => __( 'Visitor IP address and the referring site.', 'visibility-logic-elementor' ),
			'geo-location'       => __( 'Country or city of the visitor (MaxMind).', 'visibility-logic-elementor' ),
			'advanced-user-meta' => __( 'Several user meta rules combined.', 'visibility-logic-elementor' ),
			'post-page'          => __( 'Specific posts, pages, post types and terms.', 'visibility-logic-elementor' ),
			'archive'            => __( 'Category, tag, author and other archive pages.', 'visibility-logic-elementor' ),
			'conditional-tags'   => __( 'WordPress conditional tags such as is_front_page.', 'visibility-logic-elementor' ),
			'dynamic-conditions' => __( 'Any Elementor Pro dynamic tag value.', 'visibility-logic-elementor' ),
			'woocommerce-users'  => __( 'Customers who bought products or hold subscriptions.', 'visibility-logic-elementor' ),
			'edd-users'          => __( 'Customers who bought Easy Digital Downloads products.', 'visibility-logic-elementor' ),
			'fallback'           => __( 'Show text or a template in place of a hidden element.', 'visibility-logic-elementor' ),
			'query-string'       => __( 'URL parameters such as UTM tags or campaign codes.', 'visibility-logic-elementor' ),
			'language'           => __( 'Current language with WPML, Polylang or TranslatePress.', 'visibility-logic-elementor' ),
			'advanced-acf'       => __( 'Repeaters, options pages, user and term fields, AND/OR groups.', 'visibility-logic-elementor' ),
		];
	}

	/**
	 * Get pro widget options
	 *
	 * @return array
	 */
	public static function get_pro_widget_options() {
		return [
			'ip-referrer'        => [
				'name' => __( 'IP & Referrer', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'geo-location'       => [
				'name' => __( 'Geo Location', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'advanced-user-meta' => [
				'name' => __( 'Advanced User Meta', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'post-page'          => [
				'name' => __( 'Post & Page', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'archive'            => [
				'name' => __( 'Archive Pages', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'conditional-tags'   => [
				'name' => __( 'Conditional Tags', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'dynamic-conditions' => [
				'name' => __( 'Dynamic Conditions', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'woocommerce-users'  => [
				'name' => __( 'WooCommerce Users', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'edd-users'          => [
				'name' => __( 'EDD Users', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'fallback'           => [
				'name' => __( 'Fallback', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'query-string'       => [
				'name' => __( 'URL Parameters', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'language'           => [
				'name' => __( 'Language (WPML/Polylang)', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'advanced-acf'       => [
				'name' => __( 'Advanced ACF', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
		];
	}

	/**
	 * Get user roles
	 *
	 * @return array
	 */
	public static function get_user_roles() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- same as wp_roles().
		}
		$all_roles      = $wp_roles->roles;
		$editable_roles = apply_filters( 'editable_roles', $all_roles ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.

		$data = [
			'ecl-guest' => __( 'Guests', 'visibility-logic-elementor' ),
			'ecl-user'  => __( 'Logged in users', 'visibility-logic-elementor' ),
		];

		foreach ( $editable_roles as $k => $role ) {
			$data[ $k ] = $role['name'];
		}

		return $data;
	}

	/**
	 * Visitor User-Agent (sanitized).
	 *
	 * @return string
	 */
	private static function user_agent() {
		// Only matched against patterns, never printed or stored; read as 2.x did so detection stays identical.
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Forget the per-request visitor detection (tests).
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$detected = [];
	}

	/**
	 * Get device type based on User-Agent
	 *
	 * Filter `stax/visibility/device_type` can change the answer (for example from a CDN device header).
	 *
	 * @return string desktop|tablet|mobile
	 */
	public static function get_device_type() {
		if ( isset( self::$detected['device'] ) ) {
			return self::$detected['device'];
		}

		$ua     = self::user_agent();
		$device = 'desktop';

		if ( '' !== $ua ) {
			// Tablets first: some tablets contain "Mobile" in the UA. Android without "Mobile" is a tablet.
			$tablet = '/iPad|Android(?!.*Mobile)|Kindle|Silk|PlayBook|Tablet|Nexus 7|Nexus 10|KFAPWI|SM-T|GT-P/i';
			$mobile = '/Mobile|Android.*Mobile|iPhone|iPod|Opera Mini|Opera Mobi|Windows Phone|BlackBerry|BB10|webOS/i';

			if ( preg_match( $tablet, $ua ) ) {
				$device = 'tablet';
			} elseif ( preg_match( $mobile, $ua ) ) {
				$device = 'mobile';
			}
		}

		$device = (string) apply_filters( 'stax/visibility/device_type', $device, $ua ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.

		self::$detected['device'] = $device;

		return $device;
	}

	/**
	 * Get browser type
	 *
	 * @return string
	 */
	public static function get_browser_type() {
		if ( isset( self::$detected['browser'] ) ) {
			return self::$detected['browser'];
		}

		self::$detected['browser'] = self::detect_browser();

		return self::$detected['browser'];
	}

	/**
	 * Browser detection (WordPress globals from wp-includes/vars.php, Edge and Android first).
	 *
	 * @return string
	 */
	private static function detect_browser() {
		$ua = self::user_agent();

		if ( '' === $ua ) {
			return 'is_unknown';
		}

		// Check Edge first (contains Chrome/Safari in UA string)
		if ( stripos( $ua, 'Edge' ) !== false || stripos( $ua, 'Edg/' ) !== false ) {
			return 'is_edge';
		}

		global $is_lynx, $is_gecko, $is_IE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone; // phpcs:ignore WordPress.NamingConventions.ValidVariableName

		if ( $is_iphone ) {
			return 'is_iphone';
		}
		if ( stripos( $ua, 'Android' ) !== false ) {
			return 'is_android';
		}
		if ( $is_chrome ) {
			return 'is_chrome';
		}
		if ( $is_safari ) {
			return 'is_safari';
		}
		if ( $is_gecko ) {
			return 'is_gecko';
		}
		if ( $is_opera ) {
			return 'is_opera';
		}
		if ( $is_IE ) {
			return 'is_ie';
		}
		if ( $is_lynx ) {
			return 'is_lynx';
		}
		if ( $is_NS4 ) {
			return 'is_ns4';
		}

		return 'is_unknown';
	}
}
