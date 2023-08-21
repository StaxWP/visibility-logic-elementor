<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Builders extends Base {

	/**
	 * Builders constructor.
	 */
	public function __construct() {
		$this->current_slug = 'builders';

		if ( Resources::is_current_page( $this->current_slug ) ) {
			add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'current_slug', [ $this, 'set_page_slug' ] );
			add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'welcome_wrapper_class', [ $this, 'set_wrapper_classes' ] );
			add_action( STAX_VISIBILITY_HOOK_PREFIX . $this->current_slug . '_page_content', [ $this, 'panel_content' ] );
		}

		add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'admin_menu', [ $this, 'add_menu_item' ] );
		add_action( 'admin_post_stax_visibility_builders_activation', [ $this, 'save_builders' ] );
	}

	/**
	 * Save builders
	 *
	 * @return void
	 */
	public function save_builders() {
		$is_safe = true;

		if ( ! current_user_can( 'manage_options' ) ) {
			$is_safe = false;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'stax_visibility_builders_activation' ) ) {
			$is_safe = false;
		}

		if ( ! isset( $_POST['action'] ) || 'stax_visibility_builders_activation' !== $_POST['action'] ) {
			$is_safe = false;
		}

		if ( ! $is_safe ) {
			wp_redirect( admin_url( 'admin.php?page=' . STAX_EL_SLUG_PREFIX . $this->current_slug ) );
			exit;
		}

		$disabled_builders = [];

		$builders = Resources::get_all_builders();

		foreach ( $builders as $slug => $builder ) {
			if ( ! isset( $_POST[ $slug ] ) ) {
				$disabled_builders[ $slug ] = true;
			}
		}

		update_option( '_stax_visibility_disabled_builders', $disabled_builders );

		wp_redirect( admin_url( 'options-general.php?page=' . STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug ) );
		exit();
	}

	/**
	 * Panel content
	 */
	public function panel_content() {
		Resources::load_template(
			'core/admin/pages/templates/builders',
			[
				'builders' => Resources::get_all_builders(),
			]
		);
	}

	/**
	 * Add menu item
	 *
	 * @param array $menu
	 * @return array
	 */
	public function add_menu_item( $menu ) {
		$menu[] = [
			'name'     => __( 'Builders', 'visibility-logic-elementor' ),
			'link'     => admin_url( 'options-general.php?page=' . STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug ),
			'query'    => STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug,
			'priority' => 2,
		];

		return $menu;
	}

}

Builders::instance();
