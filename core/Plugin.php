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
	 * @var array
	 */
	public $initiated_widgets = [];

	/**
	 * @var array
	 */
	public $excluded_widgets = [];

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
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Options.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/Settings.php';

		add_action( 'elementor/init', [ $this, 'load_elementor_modules' ] );

		add_filter( 'elementor/widget/render_content', [ $this, 'content_change' ], 999, 2 );
		add_filter( 'elementor/frontend/section/before_render', [ $this, 'section_content_change' ], 999 );

		add_action(
			'elementor/element/print_template',
			function ( $template, $widget ) {
				return $template;
			},
			10,
			2
		);

		add_filter( 'elementor/frontend/section/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/widget/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/repeater/should_render', [ $this, 'item_should_render' ], 99999, 2 );

		\Elementor\Controls_Manager::add_tab(
			'stax-visibility',
			__( 'Stax Visibility', 'visibility-logic-elementor' )
		);

		add_action( 'wp_footer', [ $this, 'editor_show_visibility_icon' ] );

		$this->load_settings();

		do_action( 'stax/visibility/after_init' );
	}

	/**
	 * Load Elementor modules
	 *
	 * @return void
	 */
	public function load_elementor_modules() {
		// Traits.
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/traits/Meta_Trait.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/traits/Wp_Trait.php';

		// Query helpers.
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/Ajax.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/FunctionCaller.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/controls/Query.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/Controls.php';

		new Ajax();

		add_action( 'elementor/controls/controls_registered', [ new Controls(), 'on_controls_registered' ] );
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/modules/QueryControl.php';

		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'before_load_panel_assets' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'after_load_panel_assets' ] );
	}

	/**
	 * Check if plugin is PRO
	 *
	 * @return boolean
	 */
	public function has_pro() {
		return defined( 'STAX_VISIBILITY_PRO_VERSION' )
		       || in_array( 'visibility-logic-elementor-pro/conditional.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
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
	 * @param string $content
	 * @param \Elementor\Widget_Base $widget
	 *
	 * @return string
	 */
	public function section_content_change( $widget ) {
		$this->content_change( '', $widget );
	}

	/**
	 * Render item or not based on conditions
	 *
	 * @param string $content
	 * @param \Elementor\Widget_Base $widget
	 *
	 * @return string
	 */
	public function content_change( $content, $widget ) {
		if ( ! $this->should_render( $widget ) ) {
			if ( 'section' === $widget->get_name() ) {
				$not_rendered_widgets = $this->get_section_widgets_recursively( $widget );

				$this->initiated_widgets = array_merge( $this->initiated_widgets, $not_rendered_widgets );
				$this->excluded_widgets  = array_merge( $this->excluded_widgets, array_keys( $not_rendered_widgets ) );
			} else {
				$this->initiated_widgets[] = method_exists( $widget, 'get_group_name' ) ? $widget->get_group_name() : $widget->get_name();
			}
		}

		if ( ! $this->should_render( $widget ) && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$settings = $widget->get_settings();

			if ( (bool) $settings[ self::SECTION_PREFIX . 'enabled' ] ) {
				if ( isset( $settings[ self::SECTION_PREFIX . 'fallback_enabled' ] ) && (bool) $settings[ self::SECTION_PREFIX . 'fallback_enabled' ] ) {
					$fallback_content = '';
					if ( 'text' === $settings[ self::SECTION_PREFIX . 'fallback_type' ] ) {
						$fallback_content = wp_kses_post( $settings[ self::SECTION_PREFIX . 'fallback_text' ] );
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

		if ( ! in_array( $widget->get_name(), [ 'section', 'column' ] ) ) {
			if ( method_exists( $widget, 'get_css_config' ) && method_exists( $widget, 'get_group_name' ) ) {
				$needs_print = false;

				foreach ( $this->initiated_widgets as $k => $initiated_widget ) {
					if ( $initiated_widget === $widget->get_group_name() && ! in_array( $widget->get_id(), $this->excluded_widgets ) ) {
						$needs_print = true;
						unset( $this->initiated_widgets[ $k ] );
					}
				}

				if ( $needs_print ) {
					$config = $widget->get_css_config();

					if ( file_exists( $config['file_path'] ) ) {
						$css_manager = new \Elementor\Core\Page_Assets\Data_Managers\Widgets_Css();
						$css         = $css_manager->get_asset_data_from_config( $config );

						$content .= $css;
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Check if item should render
	 *
	 * @param bool $should_render
	 * @param object $widget
	 *
	 * @return boolean
	 */
	public function item_should_render( $should_render, $widget ) {
		$settings = $widget->get_settings();

		if ( ! $this->should_render( $widget ) ) {
			if ( (bool) $settings[ self::SECTION_PREFIX . 'keep_html' ] ) {
				return true;
			}

			if ( isset( $settings[ self::SECTION_PREFIX . 'fallback_enabled' ] ) &&
			     (bool) $settings[ self::SECTION_PREFIX . 'fallback_enabled' ] ) {
				return true;
			}

			return false;
		}

		// Hide section if needed
		if ( 'section' === $widget->get_name() &&
		     ! (bool) $settings[ self::SECTION_PREFIX . 'enabled' ] &&
		     isset( $settings[ self::SECTION_PREFIX . 'hide_when_empty' ] ) &&
		     (bool) $settings[ self::SECTION_PREFIX . 'hide_when_empty' ] ) {

			$should_render = ! empty( $this->check_for_empty_sections_recursively( $widget ) );
		}

		return $should_render;
	}

	/**
	 * Get section's widgets recursively
	 *
	 * @param object $item
	 *
	 * @return array
	 */
	private function get_section_widgets_recursively( $item ) {
		$elements = [];

		foreach ( $item->get_children() as $column ) {
			foreach ( $column->get_children() as $widget ) {
				if ( 'section' === $widget->get_name() ) {
					$elements = array_merge( $elements, $this->get_section_widgets_recursively( $widget ) );
				} else {
					$elements[ $widget->get_id() ] = method_exists( $widget, 'get_group_name' ) ? $widget->get_group_name() : $widget->get_name();
				}
			}
		}

		return $elements;
	}

	/**
	 * Check for empty section recursively
	 *
	 * @param object $item
	 *
	 * @return array
	 */
	private function check_for_empty_sections_recursively( $item ) {
		$elements = [];

		foreach ( $item->get_children() as $column ) {
			foreach ( $column->get_children() as $widget ) {
				$widget_settings = $widget->get_settings();

				if ( $this->should_render( $widget ) ) {
					if ( 'section' === $widget->get_name() &&
					     ! (bool) $widget_settings[ self::SECTION_PREFIX . 'enabled' ] &&
					     isset( $widget_settings[ self::SECTION_PREFIX . 'hide_when_empty' ] ) &&
					     (bool) $widget_settings[ self::SECTION_PREFIX . 'hide_when_empty' ] ) {
						$elements = array_merge( $elements, $this->check_for_empty_sections_recursively( $widget ) );
					} else {
						$elements[] = $widget->get_name();
					}
				} elseif ( ! $this->should_render( $widget ) && (bool) $widget_settings[ self::SECTION_PREFIX . 'keep_html' ] ) {
					if ( 'section' === $widget->get_name() &&
					     ! (bool) $widget_settings[ self::SECTION_PREFIX . 'enabled' ] &&
					     isset( $widget_settings[ self::SECTION_PREFIX . 'hide_when_empty' ] ) &&
					     (bool) $widget_settings[ self::SECTION_PREFIX . 'hide_when_empty' ] ) {
						$elements = array_merge( $elements, $this->check_for_empty_sections_recursively( $widget ) );
					} else {
						$elements[] = $widget->get_name();
					}
				}
			}
		}

		return $elements;
	}

	/**
	 * Check if conditions are matched
	 *
	 * @param \Elementor\Element_Base $settings
	 *
	 * @return boolean
	 */
	private function should_render( $item ) {
		$settings = $item->get_settings();

		if ( ! (bool) $settings[ self::SECTION_PREFIX . 'enabled' ] ) {
			return $this->version_fallback_render( $settings );
		}

		$options = apply_filters( 'stax/visibility/apply_conditions', [], $settings, $item );

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
					break;
				}
			}
		} elseif ( 'one' === $condition_type ) {
			foreach ( $options as $status ) {
				if ( $status ) {
					$should_render = true;
					break;
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
	 *
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
	 * Bbefore load panel assets
	 *
	 * @return void
	 */
	public function before_load_panel_assets() {
		wp_enqueue_style(
			'stax-visibility-panel',
			STAX_VISIBILITY_ASSETS_URL . 'css/panel.css',
			[],
			STAX_VISIBILITY_VERSION
		);
	}

	/**
	 * After load panel assets
	 *
	 * @return void
	 */
	public function after_load_panel_assets() {
		wp_enqueue_script(
			'stax-visibility-script-editor',
			STAX_VISIBILITY_ASSETS_URL . 'js/editor.js',
			[],
			STAX_VISIBILITY_VERSION,
			false
		);

		$widgets    = Resources::get_all_widget_options();
		$js_widgets = [];

		foreach ( $widgets as $type => $data ) {
			if ( 'post-page' === $type ) {
				$type = 'post';
			}

			$js_widgets[] = [
				'name'   => str_replace( '-', '_', $type ),
				'status' => 'inactive',
			];
		}

		wp_localize_script(
			'stax-visibility-script-editor',
			'visibility_widgets',
			$js_widgets
		);

		// Enqueue Select2 assets.
		wp_enqueue_style(
			'stax-visibility-select2',
			STAX_VISIBILITY_ASSETS_URL . 'libs/select2/select2.min.css',
			[],
			STAX_VISIBILITY_VERSION
		);

		wp_enqueue_script(
			'stax-visibility-select2',
			STAX_VISIBILITY_ASSETS_URL . 'libs/select2/select2.full.min.js',
			[ 'jquery' ],
			STAX_VISIBILITY_VERSION,
			true
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
            body.elementor-editor-active .elementor-element.stax-condition-yes::before {
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
