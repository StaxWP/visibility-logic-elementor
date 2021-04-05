<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin extends Singleton {

	/**
	 * @var string
	 */
	public static $minimum_elementor_version = '2.0.0';

	/**
	 * Plugin constructor
	 */
	public function __construct() {
		require_once STAX_VISIBILITY_CORE_HELPERS_PATH . 'Resources.php';
		require_once STAX_VISIBILITY_CORE_HELPERS_PATH . 'Notices.php';

		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			add_action( 'admin_notices', [ Notices::instance(), 'elementor_notice' ] );

			return;
		}

		// Check for the minimum required Elementor version.
		if ( ! version_compare( ELEMENTOR_VERSION, self::$minimum_elementor_version, '>=' ) ) {
			add_action( 'admin_notices', [ Notices::instance(), 'minimum_elementor_version' ] );

			return;
		}

		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Base.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Widgets.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/Settings.php';

		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'load_assets' ] );

		add_filter( 'elementor/widget/render_content', [ $this, 'content_change' ], 999, 2 );
		add_filter( 'elementor/section/render_content', [ $this, 'content_change' ], 999, 2 );

		add_filter( 'elementor/frontend/section/should_render', [ $this, 'item_should_render' ], 10, 2 );
		add_filter( 'elementor/frontend/widget/should_render', [ $this, 'item_should_render' ], 10, 2 );
		add_filter( 'elementor/frontend/repeater/should_render', [ $this, 'item_should_render' ], 10, 2 );

		\Elementor\Controls_Manager::add_tab(
			'stax-visibility',
			__( 'Stax Visibility', 'visibility-logic-elementor' )
		);

		$this->load_settings();
	}

	/**
	 * Load visibility settings
	 *
	 * @return void
	 */
	public function load_settings() {
		require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'GeneralVisibility.php';
		require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'UserRoleVisibility.php';

		if ( file_exists( STAX_VISIBILITY_PATH . 'pro/loader.php' ) ) {
			require_once STAX_VISIBILITY_PATH . 'pro/loader.php';
		}

		do_action( 'stax/visibility/after/load_settings' );
	}

	/**
	 * Render item or not based on conditions
	 *
	 * @param string                 $content
	 * @param \Elementor\Widget_Base $widget
	 *
	 * @return string
	 */
	public function content_change( $content, $widget ) {
		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
			return $content;
		}

		$settings = $widget->get_settings();

		if ( ! $this->should_render( $settings ) ) {
			return '';
		}

		return $content;
	}

	/**
	 * Check if item should render
	 *
	 * @param bool   $should_render
	 * @param object $section
	 * @return boolean
	 */
	public function item_should_render( $should_render, $section ) {
		$settings = $section->get_settings();

		if ( ! $this->should_render( $settings ) ) {
			return false;
		}

		return $should_render;
	}

	/**
	 * Check if conditions are matched
	 *
	 * @param array $settings
	 * @return boolean
	 */
	private function should_render( $settings ) {
		$output = true;

		if ( ! (bool) $settings[ self::SECTION_PREFIX . 'enabled' ] ) {
			return $output;
		}

		$options = apply_filters( 'stax/visibility/apply_conditions', [], $settings );

		$should_render = false;

		if ( 'all' === $settings[ self::SECTION_PREFIX . 'condition_type' ] ) {
			$should_render = true;

			foreach ( $options as $status ) {
				if ( ! $status ) {
					$should_render = false;
				}
			}
		} elseif ( 'one' === $settings[ self::SECTION_PREFIX . 'condition_type' ] ) {
			foreach ( $options as $status ) {
				if ( $status ) {
					$should_render = true;
				}
			}
		}

		if ( (bool) $settings[ self::SECTION_PREFIX . 'show_hide' ] ) {
			return $should_render;
		} else {
			if ( $should_render ) {
				return false;
			}

			return true;
		}
	}

	/**
	 * Load assets
	 *
	 * @return void
	 */
	public function load_assets() {
		wp_enqueue_style(
			'stax-visibility-panel',
			STAX_VISIBILITY_ASSETS_URL . 'css/panel.css',
			[],
			STAX_VISIBILITY_VERSION
		);
	}
}

Plugin::instance();
