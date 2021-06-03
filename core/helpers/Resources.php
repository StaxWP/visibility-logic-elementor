<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Resources extends Singleton {

	/**
	 * @param $plugin_slug
	 *
	 * @return bool
	 */
	public function is_plugin_active( $plugin_slug ) {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

		foreach ( $active_plugins as $plugin ) {
			if ( $plugin === $plugin_slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $plugin_path
	 *
	 * @return bool
	 */
	public function is_plugin_installed( $plugin_path ) {
		$plugins = get_plugins();

		return isset( $plugins[ $plugin_path ] );
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

		extract( $args );

		ob_start();
		include STAX_VISIBILITY_PATH . trim( $name ) . '.php';

		if ( $echo ) {
			echo ob_get_clean();
		} else {
			return ob_get_clean();
		}
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

		return isset( $_GET['page'] ) && $_GET['page'] === $page;
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

		];

		if ( $with_pro ) {
			$options = array_merge(
				$options,
				self::get_pro_widget_options()
			);
		}

		$options = apply_filters( 'stax/visibility/options', $options );

		$disabled_options = get_option( '_stax_visibility_disabled_options', [] );

		foreach ( $options as $slug => $option ) {
			$options[ $slug ]['status'] = ! isset( $disabled_options[ $slug ] );
		}

		return $options;
	}

	/**
	 * Get pro widget options
	 *
	 * @return array
	 */
	public static function get_pro_widget_options() {
		return [
			'ip-referrer'      => [
				'name' => __( 'IP & Referrer', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'post-page'        => [
				'name' => __( 'Post & Page', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'conditional-tags' => [
				'name' => __( 'Conditional Tags', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
			'fallback'         => [
				'name' => __( 'Fallback', 'visibility-logic-elementor' ),
				'pro'  => true,
			],
		];
	}

	/**
	 * Get user roles
	 *
	 * @return void
	 */
	public static function get_user_roles() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}
		$all_roles      = $wp_roles->roles;
		$editable_roles = apply_filters( 'editable_roles', $all_roles );

		$data = [
			'ecl-guest' => 'Guests',
			'ecl-user'  => 'Logged in users',
		];

		foreach ( $editable_roles as $k => $role ) {
			$data[ $k ] = $role['name'];
		}

		return $data;
	}

	/**
	 * Get user metas
	 *
	 * @param boolean $grouped
	 * @return array
	 */
	public static function get_user_metas( $grouped = false ) {
		global $wpdb;

		$user_metas         = [];
		$user_metas_grouped = [];

		$query   = 'SELECT DISTINCT meta_key FROM ' . $wpdb->prefix . 'usermeta ORDER BY meta_key';
		$results = $wpdb->get_results( $query );

		if ( ! empty( $results ) ) {
			$metas = [];
			foreach ( $results as $key => $user ) {
				$metas[ $user->meta_key ] = $user->meta_key;
			}

			$manual_metas = $metas;
			foreach ( $manual_metas as $meta ) {
				if ( substr( $meta, 0, 1 ) == '_' ) {
					$meta = $tmp = substr( $meta, 1 );
					if ( in_array( $tmp, $manual_metas ) ) {
						continue;
					}
				}
				if ( ! isset( $post_metas[ $meta ] ) ) {
					$user_metas[ $meta ]                   = $meta;
					$user_metas_grouped['NATIVE'][ $meta ] = $meta;
				}
			}
		}

		if ( $grouped ) {
			return $user_metas_grouped;
		}

		return $user_metas;
	}

	/**
	 * Get borwser type
	 *
	 * @return string
	 */
	public static function get_browser_type() {
		global $is_lynx, $is_gecko, $is_IE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone, $is_android;

		$android = stripos( $_SERVER['HTTP_USER_AGENT'], 'Android' );

		if ( $is_lynx ) {
			return 'is_lynx';
		} elseif ( $is_gecko ) {
			return 'is_gecko';
		} elseif ( $is_opera ) {
			return 'is_opera';
		} elseif ( $is_NS4 ) {
			return 'is_ns4';
		} elseif ( $is_safari ) {
			return 'is_safari';
		} elseif ( $is_chrome ) {
			return 'is_chrome';
		} elseif ( $is_IE ) {
			return 'is_ie';
		} else {
			return 'is_unknown';
		}

		if ( $is_iphone ) {
			return 'is_ios';
		}

		if ( $android ) {
			return 'is_android';
		}

		return 'is_unknown';
	}
}
