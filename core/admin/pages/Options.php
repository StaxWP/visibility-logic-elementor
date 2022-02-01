<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Options extends Base {

	/**
	 * Options constructor.
	 */
	public function __construct() {
		$this->current_slug = 'options';

		if ( Resources::is_current_page( $this->current_slug ) ) {
			add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'current_slug', [ $this, 'set_page_slug' ] );
			add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'welcome_wrapper_class', [ $this, 'set_wrapper_classes' ] );
			add_action( STAX_VISIBILITY_HOOK_PREFIX . $this->current_slug . '_page_content', [ $this, 'panel_content' ] );
		}

		add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'admin_menu', [ $this, 'add_menu_item' ] );
		add_action( 'admin_post_stax_visibility_options_activation', [ $this, 'toggle_option' ] );
	}

	/**
	 * Toggle options visibility
	 *
	 * @return void
	 */
	public function toggle_option() {
		if ( ! isset( $_POST['action'] ) || 'stax_visibility_options_activation' !== $_POST['action'] ) {
			wp_redirect( admin_url( 'admin.php?page=' . STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug ) );
		}

		$disabled_options = [];

		$options = Resources::get_all_widget_options( Plugin::instance()->has_pro() );

		foreach ( $options as $slug => $option ) {
			if ( ! isset( $_POST[ $slug ] ) ) {
				$disabled_options[ $slug ] = true;
			}
		}

		update_option( '_stax_visibility_disabled_options', $disabled_options );

		wp_redirect( admin_url( 'admin.php?page=' . STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug ) );
		exit();
	}

	/**
	 * Panel content
	 */
	public function panel_content() {
		Resources::load_template(
			'core/admin/pages/templates/options',
			[
				'options' => Resources::get_all_widget_options(),
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
			'name'     => __( 'Options', 'visibility-logic-elementor' ),
			'link'     => admin_url( 'admin.php?page=' . STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug ),
			'query'    => STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug,
			'priority' => 2,
		];

		return $menu;
	}

}

Options::instance();
