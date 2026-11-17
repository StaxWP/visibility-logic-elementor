<?php
/**
 * Settings > Visibility Logic > Settings: Elementor cache and, on sites upgraded from before 2.5.2, a one-time
 * switch to the current behavior. Visibility Logic Pro shows its settings in the same tab (same `settings` slug,
 * `stax_visibility_settings_page_content` action). Saved by Options::toggle_option().
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Settings_Tab
 */
class Settings_Tab extends Base {

	/**
	 * Settings_Tab constructor.
	 */
	public function __construct() {
		$this->current_slug = 'settings';

		if ( Resources::is_current_page( $this->current_slug ) ) {
			// Priority 5: before the settings of add-ons (Pro) on the same tab.
			add_action( STAX_VISIBILITY_HOOK_PREFIX . $this->current_slug . '_page_content', [ $this, 'panel_content' ], 5 );
		}

		add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'admin_menu', [ $this, 'add_menu_item' ] );
	}

	/**
	 * Settings that still use the behavior from before 2.5.2 (stored off).
	 *
	 * @param array $settings General settings.
	 *
	 * @return string[] Keys.
	 */
	public static function old_behaviors( array $settings ) {
		return array_values(
			array_filter(
				[ 'site_timezone', 'container_widgets' ],
				static function ( $key ) use ( $settings ) {
					return empty( $settings[ $key ] );
				}
			)
		);
	}

	/**
	 * Panel content.
	 */
	public function panel_content() {
		$settings = wp_parse_args( (array) get_option( Resources::SETTINGS_OPTION, [] ), Resources::default_settings() );

		Resources::load_template(
			'core/admin/pages/templates/settings',
			[
				'settings' => $settings,
				'old'      => self::old_behaviors( $settings ),
				'saved'    => isset( $_GET['updated'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			]
		);
	}

	/**
	 * Add menu item (Settings::get_menu() keeps one tab per page, so Pro's own Settings item merges into this one).
	 *
	 * @param array $menu Tabs.
	 *
	 * @return array
	 */
	public function add_menu_item( $menu ) {
		$menu[] = [
			'name'     => __( 'Settings', 'visibility-logic-elementor' ),
			'link'     => Settings::url( $this->current_slug ),
			'query'    => STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug,
			'priority' => 3,
		];

		return $menu;
	}
}

Settings_Tab::instance();
