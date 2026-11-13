<?php
/**
 * The visitor and moment the conditions are evaluated for.
 *
 * Normally the real request (current user, current time, User-Agent). "Preview as" (editor preview, see Preview)
 * sets a simulated context for the Visibility Logic evaluation only: the current user, capabilities and content of
 * the request are never changed.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Context
 */
class Context {

	/**
	 * Simulation: user (int id, 0 = guest), roles (string[] for a role preview), time (timestamp), device, browser,
	 * country, language. Null when not simulating.
	 *
	 * @var array|null
	 */
	private static $simulation = null;

	/**
	 * Start simulating (keys left out keep the real value).
	 *
	 * @param array $simulation Simulation.
	 *
	 * @return void
	 */
	public static function simulate( array $simulation ) {
		self::$simulation = $simulation;
		Resources::reset_cache();
	}

	/**
	 * Stop simulating.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$simulation = null;
		Resources::reset_cache();
	}

	/**
	 * Current simulation or null.
	 *
	 * @return array|null
	 */
	public static function simulation() {
		return self::$simulation;
	}

	/**
	 * Whether a key is simulated.
	 *
	 * @param string $key Key.
	 *
	 * @return bool
	 */
	public static function has( $key ) {
		return is_array( self::$simulation ) && array_key_exists( $key, self::$simulation );
	}

	/**
	 * Simulated value.
	 *
	 * @param string $key Key.
	 *
	 * @return mixed
	 */
	public static function get( $key ) {
		return self::has( $key ) ? self::$simulation[ $key ] : null;
	}

	/**
	 * Id of the visitor (0 for guests).
	 *
	 * @return int
	 */
	public static function user_id() {
		if ( self::has( 'roles' ) ) {
			return empty( self::$simulation['roles'] ) ? 0 : -1; // A role preview: a logged in visitor with no account.
		}

		return self::has( 'user' ) ? (int) self::$simulation['user'] : get_current_user_id();
	}

	/**
	 * Whether the visitor is logged in.
	 *
	 * @return bool
	 */
	public static function logged_in() {
		return 0 !== self::user_id();
	}

	/**
	 * The visitor as a WP_User (an empty user for guests; a role preview gets an unsaved user with those roles).
	 *
	 * @return \WP_User
	 */
	public static function user() {
		if ( self::has( 'roles' ) ) {
			$user        = new \WP_User();
			$user->roles = array_values( (array) self::$simulation['roles'] );

			foreach ( $user->roles as $role ) {
				$object = get_role( $role );

				if ( $object ) {
					$user->allcaps = array_merge( $user->allcaps, (array) $object->capabilities );
				}
			}

			return $user;
		}

		if ( self::has( 'user' ) ) {
			$user = get_userdata( (int) self::$simulation['user'] );

			return $user ? $user : new \WP_User();
		}

		return wp_get_current_user();
	}

	/**
	 * Roles of the visitor.
	 *
	 * @return string[]
	 */
	public static function roles() {
		return self::logged_in() ? (array) self::user()->roles : [];
	}

	/**
	 * Whether the visitor has a capability.
	 *
	 * @param string $capability Capability.
	 *
	 * @return bool
	 */
	public static function can( $capability ) {
		if ( ! self::logged_in() ) {
			return false;
		}

		$user = self::user();

		if ( self::has( 'roles' ) ) {
			return ! empty( $user->allcaps[ $capability ] );
		}

		return user_can( $user, $capability );
	}

	/**
	 * Now (Unix timestamp).
	 *
	 * @return int
	 */
	public static function timestamp() {
		return self::has( 'time' ) ? (int) self::$simulation['time'] : time();
	}

	/**
	 * Now, formatted in the site time zone (same as current_time( $format ) when not simulating).
	 *
	 * @param string $format Date format.
	 *
	 * @return string
	 */
	public static function local( $format ) {
		if ( ! self::has( 'time' ) ) {
			return current_time( $format );
		}

		return wp_date( $format, self::timestamp() );
	}

	/**
	 * Device: desktop, tablet or mobile.
	 *
	 * @return string
	 */
	public static function device() {
		return self::has( 'device' ) && '' !== (string) self::$simulation['device'] ? (string) self::$simulation['device'] : Resources::get_device_type();
	}

	/**
	 * Browser (Resources::get_browser_type() values).
	 *
	 * @return string
	 */
	public static function browser() {
		return self::has( 'browser' ) && '' !== (string) self::$simulation['browser'] ? (string) self::$simulation['browser'] : Resources::get_browser_type();
	}
}
