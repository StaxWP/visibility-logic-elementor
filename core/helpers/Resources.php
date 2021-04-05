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
	 * @return array
	 */
	public static function get_all_widget_options() {
		$options = [
			'user-role' => [
				'name' => __( 'User Role', 'visibility-logic-elementor' ),
				'pro'  => false,
			],
		];

		if ( ! defined( 'STAX_VISIBILITY_PRO_PATH' ) ) {
			$options = array_merge(
				$options,
				[
					'user-meta'        => [
						'name' => __( 'User Meta', 'visibility-logic-elementor' ),
						'pro'  => true,
					],
					'ip-referrer'      => [
						'name' => __( 'IP & Referrer', 'visibility-logic-elementor' ),
						'pro'  => true,
					],
					'browser-type'     => [
						'name' => __( 'Browser Type', 'visibility-logic-elementor' ),
						'pro'  => true,
					],
					'browser-type'     => [
						'name' => __( 'Browser Type', 'visibility-logic-elementor' ),
						'pro'  => true,
					],
					'date-time'        => [
						'name' => __( 'Date & Time', 'visibility-logic-elementor' ),
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
				]
			);
		}

		$options = apply_filters( 'stax/visibility/options', $options );

		$disabled_options = get_option( '_stax_visibility_disabled_options', [] );

		foreach ( $options as $slug => $option ) {
			$options[ $slug ]['status'] = ! isset( $disabled_options[ $slug ] );
		}

		return $options;
	}
}
