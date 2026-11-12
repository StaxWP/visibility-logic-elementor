<?php
/**
 * Settings > Visibility Logic > Conditions: turn conditions on or off, general settings, free vs Pro overview.
 *
 * @package Stax\VisibilityLogic
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Options
 */
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
	 * Save: conditions on/off and general settings.
	 *
	 * @return void
	 */
	public function toggle_option() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'visibility-logic-elementor' ), 403 );
		}

		check_admin_referer( 'stax_visibility_options_activation' );

		$disabled_options = [];

		foreach ( Resources::get_all_widget_options( Plugin::instance()->has_pro() ) as $slug => $option ) {
			if ( ! isset( $_POST[ $slug ] ) ) {
				$disabled_options[ $slug ] = true;
			}
		}

		update_option( '_stax_visibility_disabled_options', $disabled_options );

		$settings                  = wp_parse_args( (array) get_option( Resources::SETTINGS_OPTION, [] ), Resources::default_settings() );
		$settings['site_timezone'] = ! empty( $_POST['stax_visibility_site_timezone'] );

		update_option( Resources::SETTINGS_OPTION, $settings );

		wp_safe_redirect( add_query_arg( 'updated', '1', Settings::url( $this->current_slug ) ) );
		exit;
	}

	/**
	 * Panel content
	 */
	public function panel_content() {
		$options = Resources::get_all_widget_options();
		$pro     = Plugin::instance()->has_pro();

		$included = [];
		$locked   = [];

		foreach ( $options as $slug => $option ) {
			if ( ! empty( $option['pro'] ) ) {
				$locked[ $slug ] = $option;
			} else {
				$included[ $slug ] = $option;
			}
		}

		Resources::load_template(
			'core/admin/pages/templates/options',
			[
				'options'  => $options,
				'included' => $included,
				'locked'   => $locked,
				'has_pro'  => $pro,
				'settings' => wp_parse_args( (array) get_option( Resources::SETTINGS_OPTION, [] ), Resources::default_settings() ),
				'saved'    => isset( $_GET['updated'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			]
		);
	}

	/**
	 * Add menu item
	 *
	 * @param array $menu Tabs.
	 *
	 * @return array
	 */
	public function add_menu_item( $menu ) {
		$menu[] = [
			'name'     => __( 'Conditions', 'visibility-logic-elementor' ),
			'link'     => Settings::url( $this->current_slug ),
			'query'    => STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug,
			'priority' => 2,
		];

		return $menu;
	}
}

Options::instance();
