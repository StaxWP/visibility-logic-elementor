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
		do_action( 'stax/visibility/pre_init' );

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

		require_once STAX_VISIBILITY_CORE_PATH . 'Upgrades.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Base.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Widgets.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/Settings.php';

		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'load_panel_assets' ] );

		add_filter( 'elementor/widget/render_content', [ $this, 'content_change' ], 999, 2 );
		add_filter( 'elementor/section/render_content', [ $this, 'content_change' ], 999, 2 );

		add_filter( 'elementor/frontend/section/should_render', [ $this, 'item_should_render' ], 10, 2 );
		add_filter( 'elementor/frontend/widget/should_render', [ $this, 'item_should_render' ], 10, 2 );
		add_filter( 'elementor/frontend/repeater/should_render', [ $this, 'item_should_render' ], 10, 2 );

		\Elementor\Controls_Manager::add_tab(
			'stax-visibility',
			__( 'Stax Visibility', 'visibility-logic-elementor' )
		);

		add_action( 'wp_footer', [ $this, 'editor_show_visibility_icon' ] );

		$this->load_settings();

		do_action( 'stax/visibility/after_init' );
	}

	/**
	 * Check if plugin is PRO
	 *
	 * @return boolean
	 */
	public function has_pro() {
		return defined( 'STAX_VISIBILITY_PRO_VERSION' );
	}

	/**
	 * Load visibility settings
	 *
	 * @return void
	 */
	public function load_settings() {
		require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'OldVersionFallback.php';
		require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'GeneralVisibility.php';

		// Load active options.
		$widgets = Resources::get_all_widget_options();

		foreach ( $widgets as $slug => $option ) {
			if ( isset( $option['status'], $option['class'], $option['pro'] ) &&
				 $option['status'] && file_exists( $option['class'] ) && $option['pro'] === false ) {
				require_once $option['class'];
			}
		}

		if ( ! $this->has_pro() ) {
			require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'ProVisibility.php';
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
		$settings = $widget->get_settings();

		if ( ! $this->should_render( $settings ) && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			if ( (bool) $settings[ self::SECTION_PREFIX . 'enabled' ] ) {
				if ( isset( $settings[ self::SECTION_PREFIX . 'fallback_enabled' ] ) && (bool) $settings[ self::SECTION_PREFIX . 'fallback_enabled' ] ) {
					$fallback_content = '';
					if ( 'text' === $settings[ self::SECTION_PREFIX . 'fallback_type' ] ) {
						$fallback_content = esc_html( $settings[ self::SECTION_PREFIX . 'fallback_text' ] );
					} elseif ( 'template' === $settings[ self::SECTION_PREFIX . 'fallback_type' ] ) {
						if ( $settings[ self::SECTION_PREFIX . 'fallback_template' ] ) {
							$fallback_content = do_shortcode( '[elementor-template id="' . $settings[ self::SECTION_PREFIX . 'fallback_template' ] . '"]' );
						}
					}

					if ( $fallback_content ) {
						return $fallback_content;
					}
				} elseif ( isset( $settings[ self::SECTION_PREFIX . 'keep_html' ] ) && (bool) $settings[ self::SECTION_PREFIX . 'keep_html' ] ) {
					$widget->add_render_attribute( '_wrapper', 'class', 'stax-visibility-hidden' );
					$widget->add_render_attribute( '_wrapper', 'style', 'display: none' );

					return $content;
				}
			}

			return '';
		}

		return $content;
	}

	/**
	 * Check if item should render
	 *
	 * @param bool   $should_render
	 * @param object $section
	 *
	 * @return boolean
	 */
	public function item_should_render( $should_render, $section ) {
		$settings = $section->get_settings();

		if ( ! $this->should_render( $settings ) ) {
			if ( isset( $settings[ self::SECTION_PREFIX . 'fallback_enabled' ] ) &&
				( (bool) $settings[ self::SECTION_PREFIX . 'fallback_enabled' ] ||
				(bool) $settings[ self::SECTION_PREFIX . 'keep_html' ] ) ) {
				return true;
			}

			return false;
		}

		return $should_render;
	}

	/**
	 * Check if conditions are matched
	 *
	 * @param array $settings
	 *
	 * @return boolean
	 */
	private function should_render( $settings ) {
		if ( ! (bool) $settings[ self::SECTION_PREFIX . 'enabled' ] ) {
			return $this->version_fallback_render( $settings );
		}

		$options = apply_filters( 'stax/visibility/apply_conditions', [], $settings );

		if ( empty( $options ) ) {
			return true;
		}

		$should_render = false;

		$condition_type = isset( $settings[ self::SECTION_PREFIX . 'condition_type' ] ) ? $settings[ self::SECTION_PREFIX . 'condition_type' ] : 'all';

		if ( 'all' === $condition_type ) {
			$should_render = true;

			foreach ( $options as $status ) {
				if ( ! $status ) {
					$should_render = false;
				}
			}
		} elseif ( 'one' === $$condition_type ) {
			foreach ( $options as $status ) {
				if ( $status ) {
					$should_render = true;
				}
			}
		}

		if ( (bool) $settings[ self::SECTION_PREFIX . 'show_hide' ] ) {
			return $should_render;
		}

		return ! $should_render;
	}

	/**
	 * Version fallback render
	 *
	 * @param array $settings
	 * @return boolean
	 */
	private function version_fallback_render( $settings ) {
		if ( ! isset( $settings['ecl_enabled'] ) ) {
			return true;
		}

		$user_state = is_user_logged_in();

		if ( 'yes' === $settings['ecl_enabled'] ) {

			if ( ! empty( $settings['ecl_role_visible'] ) ) {
				if ( in_array( 'ecl-guest', $settings['ecl_role_visible'] ) ) {
					if ( true === $user_state ) {
						return false;
					}
				} elseif ( in_array( 'ecl-user', $settings['ecl_role_visible'] ) ) {
					if ( false === $user_state ) {
						return false;
					}
				} else {
					if ( false === $user_state ) {
						return false;
					}
					$user = wp_get_current_user();

					$has_role = false;
					foreach ( $settings['ecl_role_visible'] as $setting ) {
						if ( in_array( $setting, (array) $user->roles ) ) {
							$has_role = true;
						}
					}
					if ( false === $has_role ) {
						return false;
					}
				}
			} elseif ( ! empty( $settings['ecl_role_hidden'] ) ) {

				if ( false === $user_state && in_array( 'ecl-guest', $settings['ecl_role_hidden'], false ) ) {
					return false;
				} elseif ( true === $user_state && in_array( 'ecl-user', $settings['ecl_role_hidden'], false ) ) {
					return false;
				} else {
					if ( false === $user_state ) {
						return true;
					}
					$user = wp_get_current_user();

					foreach ( $settings['ecl_role_hidden'] as $setting ) {
						if ( in_array( $setting, (array) $user->roles, false ) ) {
							return false;
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * Load panel assets
	 *
	 * @return void
	 */
	public function load_panel_assets() {
		wp_enqueue_style(
			'stax-visibility-panel',
			STAX_VISIBILITY_ASSETS_URL . 'css/panel.css',
			[],
			STAX_VISIBILITY_VERSION
		);
	}

	/**
	 * Show icon in editor for widgets with setting enabled
	 */
	public function editor_show_visibility_icon() {
		if ( ! class_exists( 'Elementor\Plugin' ) || ! \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return;
		}
		?>
		<style>
			body.elementor-editor-active .elementor-element.stax-condition-yes::after {
				content: '\e8ed';
				color: #6E49CB;
				display: inline-block;
				font-family: eicons;
				font-size: 15px;
				position: absolute;
				top: 0;
				right: 5px;
			}
		</style>
		<?php
	}
}

Plugin::instance();
