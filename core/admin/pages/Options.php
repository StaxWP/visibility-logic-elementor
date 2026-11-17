<?php
/**
 * Settings > Visibility Logic > Conditions: turn conditions on or off, free vs Pro overview. Also saves the Settings tab.
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

		/*
		 * One form per tab. Sections: `conditions` (Conditions tab), `settings` (Settings tab: Elementor cache),
		 * `current_behavior` (Settings tab: switch a pre-2.5.2 site to the current behavior), `all` (a 2.x form
		 * without a section: conditions and every general setting).
		 */
		$section  = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : 'all';
		$sections = [ 'conditions', 'settings', 'current_behavior', 'all' ];
		$section  = in_array( $section, $sections, true ) ? $section : 'all';

		if ( in_array( $section, [ 'conditions', 'all' ], true ) ) {
			$disabled_options = [];

			foreach ( Resources::get_all_widget_options( Plugin::instance()->has_pro() ) as $slug => $option ) {
				if ( ! isset( $_POST[ $slug ] ) ) {
					$disabled_options[ $slug ] = true;
				}
			}

			update_option( '_stax_visibility_disabled_options', $disabled_options );
		}

		if ( 'conditions' !== $section ) {
			$settings = wp_parse_args( (array) get_option( Resources::SETTINGS_OPTION, [] ), Resources::default_settings() );
			$previous = $settings;

			if ( 'current_behavior' === $section ) {
				$settings['site_timezone']     = true;
				$settings['container_widgets'] = true;
			} else {
				$keys = 'settings' === $section ? [ 'element_cache' ] : array_keys( Resources::default_settings() );

				foreach ( $keys as $key ) {
					$settings[ $key ] = ! empty( $_POST[ 'stax_visibility_' . $key ] );
				}
			}

			update_option( Resources::SETTINGS_OPTION, $settings );

			// Cached pages were built for the other cache mode or the old behavior.
			if ( $previous !== $settings ) {
				Plugin::flush_element_cache();
			}
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', Settings::url( in_array( $section, [ 'settings', 'current_behavior' ], true ) ? 'settings' : $this->current_slug ) ) );
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
