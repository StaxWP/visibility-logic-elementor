<?php
/**
 * Settings > Visibility Logic > General: how rules are read and rendered (saved by Options::toggle_option()).
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class General
 */
class General extends Base {

	/**
	 * General constructor.
	 */
	public function __construct() {
		$this->current_slug = 'general';

		if ( Resources::is_current_page( $this->current_slug ) ) {
			add_action( STAX_VISIBILITY_HOOK_PREFIX . $this->current_slug . '_page_content', [ $this, 'panel_content' ] );
		}

		add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'admin_menu', [ $this, 'add_menu_item' ] );
	}

	/**
	 * Panel content.
	 */
	public function panel_content() {
		Resources::load_template(
			'core/admin/pages/templates/general',
			[
				'settings' => wp_parse_args( (array) get_option( Resources::SETTINGS_OPTION, [] ), Resources::default_settings() ),
				'saved'    => isset( $_GET['updated'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			]
		);
	}

	/**
	 * Add menu item.
	 *
	 * @param array $menu Tabs.
	 *
	 * @return array
	 */
	public function add_menu_item( $menu ) {
		$menu[] = [
			'name'     => __( 'General', 'visibility-logic-elementor' ),
			'link'     => Settings::url( $this->current_slug ),
			'query'    => STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug,
			'priority' => 2.5,
		];

		return $menu;
	}
}

General::instance();
