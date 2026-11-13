<?php
/**
 * "Check my cache setup": page caches that serve one copy of a page to every visitor, which breaks rules that
 * depend on the visitor (role, device, time, location). Read only: nothing is changed on the site.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Check
 */
class Cache_Check {

	/**
	 * Per-request result.
	 *
	 * @var array|null
	 */
	private static $found = null;

	/**
	 * Known page caches: slug => [ name, detected (bool), help ].
	 *
	 * @return array[]
	 */
	public static function detect() {
		if ( null !== self::$found ) {
			return self::$found;
		}

		$known = [
			'wp-rocket'       => [ 'WP Rocket', defined( 'WP_ROCKET_VERSION' ), __( 'Settings > WP Rocket > Advanced Rules: add the pages with visitor rules to "Never Cache URL(s)". Logged-in users are not cached by default ("Enable caching for logged-in WordPress users" off).', 'visibility-logic-elementor' ) ],
			'litespeed'       => [ 'LiteSpeed Cache', defined( 'LSCWP_V' ), __( 'LiteSpeed Cache > Cache > Excludes: add the pages to "Do Not Cache URIs"; for role rules keep "Cache Logged-in Users" off or use private cache.', 'visibility-logic-elementor' ) ],
			'w3tc'            => [ 'W3 Total Cache', defined( 'W3TC' ), __( 'Performance > Page Cache: add the pages to "Never cache the following pages" and keep "Don\'t cache pages for logged in users" on.', 'visibility-logic-elementor' ) ],
			'wp-super-cache'  => [ 'WP Super Cache', defined( 'WPCACHEHOME' ), __( 'Settings > WP Super Cache > Advanced: add the pages to "Rejected URL Strings" and keep "Don\'t cache pages for known users" on.', 'visibility-logic-elementor' ) ],
			'wp-fastest'      => [ 'WP Fastest Cache', class_exists( 'WpFastestCache' ), __( 'WP Fastest Cache > Exclude: add an exclude rule for the pages with visitor rules.', 'visibility-logic-elementor' ) ],
			'cache-enabler'   => [ 'Cache Enabler', class_exists( 'Cache_Enabler' ), __( 'Settings > Cache Enabler: add the pages to "Cache Exclusions".', 'visibility-logic-elementor' ) ],
			'sg-optimizer'    => [ 'SiteGround Speed Optimizer', defined( 'SiteGround_Optimizer\VERSION' ), __( 'Speed Optimizer > Caching: add the pages to "Exclude URLs from Caching".', 'visibility-logic-elementor' ) ],
			'breeze'          => [ 'Breeze', defined( 'BREEZE_VERSION' ), __( 'Settings > Breeze > Advanced Options: add the pages to "Never Cache URL(s)".', 'visibility-logic-elementor' ) ],
			'hummingbird'     => [ 'Hummingbird', defined( 'WPHB_VERSION' ), __( 'Hummingbird > Caching > Page Caching: add the pages to the exclusions.', 'visibility-logic-elementor' ) ],
			'nitropack'       => [ 'NitroPack', defined( 'NITROPACK_VERSION' ), __( 'NitroPack dashboard > Cache > Excludes: add the pages with visitor rules.', 'visibility-logic-elementor' ) ],
			'wp-engine'       => [ 'WP Engine page cache', function_exists( 'is_wpe' ) || defined( 'WPE_APIKEY' ), __( 'WP Engine User Portal > Cache: add a cache exclusion for the pages (or ask WP Engine support).', 'visibility-logic-elementor' ) ],
			'kinsta'          => [ 'Kinsta page cache', defined( 'KINSTAMU_VERSION' ), __( 'MyKinsta > Tools > Cache: ask Kinsta support to bypass the cache for these pages.', 'visibility-logic-elementor' ) ],
			'cloudflare'      => [ 'Cloudflare', self::has_header( 'cf-ray' ) || defined( 'CLOUDFLARE_PLUGIN_DIR' ), __( 'Cloudflare: if APO or a "Cache Everything" rule is on, add a Cache Rule that bypasses the cache for these pages.', 'visibility-logic-elementor' ) ],
			'advanced-cache'  => [ __( 'A page cache drop-in (advanced-cache.php)', 'visibility-logic-elementor' ), defined( 'WP_CACHE' ) && WP_CACHE && file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ), __( 'WP_CACHE is on and wp-content/advanced-cache.php exists: a page cache is active. Exclude the pages with visitor rules in its settings.', 'visibility-logic-elementor' ) ],
		];

		$found = [];

		foreach ( $known as $slug => $item ) {
			if ( $item[1] ) {
				$found[ $slug ] = [
					'name' => $item[0],
					'help' => $item[2],
				];
			}
		}

		// A specific plugin was found: the generic drop-in line adds nothing.
		if ( count( $found ) > 1 ) {
			unset( $found['advanced-cache'] );
		}

		self::$found = (array) apply_filters( 'stax/visibility/page_caches', $found ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.

		return self::$found;
	}

	/**
	 * Whether any page cache was detected.
	 *
	 * @return bool
	 */
	public static function has_page_cache() {
		return ! empty( self::detect() );
	}

	/**
	 * Forget the result (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$found = null;
	}

	/**
	 * Whether the current request came with a header (proxies set it on the way in).
	 *
	 * @param string $name Header name.
	 *
	 * @return bool
	 */
	private static function has_header( $name ) {
		return isset( $_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) ) ] );
	}
}
