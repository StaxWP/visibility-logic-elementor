<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Stax\VisibilityLogic\ElementorRegisterSettingsTrait;
use Stax\VisibilityLogic\Resources;

class ElementorBuilder extends Singleton {
	use ElementorRegisterSettingsTrait;

	/**
	 * @var string
	 */
	public static $minimum_version = '2.0.0';

	/**
	 * @var array
	 */
	public $initiated_widgets = [];

	/**
	 * @var array
	 */
	public $excluded_widgets = [];

	/**
	 * ElementorBuilder constructor
	 */
	public function __construct() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			add_action( 'admin_notices', [ $this, 'not_installed_notice' ] );

			return;
		}

		// Check for the minimum required Elementor version.
		if ( ! version_compare( ELEMENTOR_VERSION, self::$minimum_version, '>=' ) ) {
			add_action( 'admin_notices', [ $this, 'required_min_version_notice' ] );

			return;
		}
	}

	/**
	 * Load settings
	 *
	 * @return void
	 */
	public function load_settings() {
		add_action( 'elementor/init', [ $this, 'load_modules' ] );

		add_filter( 'elementor/widget/render_content', [ $this, 'content_change' ], 999, 2 );
		add_filter( 'elementor/frontend/section/before_render', [ $this, 'section_content_change' ], 999 );
		add_filter( 'elementor/frontend/container/before_render', [ $this, 'section_content_change' ], 999 );

		add_action(
			'elementor/element/print_template',
			function ( $template, $widget ) {
				return $template;
			},
			10,
			2
		);

		add_filter( 'elementor/frontend/section/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/container/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/widget/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/repeater/should_render', [ $this, 'item_should_render' ], 99999, 2 );

		\Elementor\Controls_Manager::add_tab(
			'stax-visibility',
			__( 'Stax Visibility', 'visibility-logic-elementor' )
		);

		add_action( 'wp_footer', [ $this, 'editor_show_visibility_icon' ] );

		require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'elementor/OldVersionFallback.php';
		require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'elementor/GeneralVisibility.php';

		// Load active options.
		foreach ( Resources::get_all_widget_options() as $slug => $option ) {
			if ( ! isset( $option['status'], $option['class'], $option['pro'] ) ||
				! $option['status'] ||
				false !== $option['pro'] ) {
				continue;
			}

			$class_path = STAX_VISIBILITY_CORE_SETTINGS_PATH . "elementor/{$option['class']}.php";

			if ( ! file_exists( $class_path ) ) {
				continue;
			}

			require_once $class_path;
		}

		if ( ! stax_vle_is_pro() ) {
			foreach ( $this->elements as $element ) {
				add_action( "elementor/element/{$element['name']}/{$element['section_id']}/after_section_end", [ $this, 'register_section' ] );
			}
		}

		do_action( 'stax/visibility/after/elementor/load_settings' );
	}

	/**
	 * Load modules
	 *
	 * @return void
	 */
	public function load_modules() {
		// Traits.
		require_once STAX_VISIBILITY_CORE_PATH . 'traits/MetaTrait.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'traits/WpTrait.php';

		// Query helpers.
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/elementor/Ajax.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/elementor/FunctionCaller.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/elementor/controls/Query.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/elementor/Controls.php';

		new \Stax\VisibilityLogic\Elementor\Ajax();

		add_action( 'elementor/controls/register', [ new \Stax\VisibilityLogic\Elementor\Controls(), 'on_controls_registered' ] );

		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'before_load_panel_assets' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'after_load_panel_assets' ] );
	}

	/**
	 * Render item or not based on conditions
	 *
	 * @param string                 $content
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
	 * @param string                 $content
	 * @param \Elementor\Widget_Base $widget
	 *
	 * @return string
	 */
	public function content_change( $content, $widget ) {
		if ( ! $this->should_render( $widget ) ) {
			if ( 'section' === $widget->get_name() || 'container' === $widget->get_name() ) {
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

		if ( ! in_array( $widget->get_name(), [ 'section', 'container', 'column' ] ) ) {
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
	 * @param bool   $should_render
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
		if ( ( 'section' === $widget->get_name() || 'container' === $widget->get_name() ) &&
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
				if ( 'section' === $widget->get_name() || 'container' === $widget->get_name() ) {
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
					if ( ( 'section' === $widget->get_name() || 'container' === $widget->get_name() ) &&
						 ! (bool) $widget_settings[ self::SECTION_PREFIX . 'enabled' ] &&
						 isset( $widget_settings[ self::SECTION_PREFIX . 'hide_when_empty' ] ) &&
						 (bool) $widget_settings[ self::SECTION_PREFIX . 'hide_when_empty' ] ) {
						$elements = array_merge( $elements, $this->check_for_empty_sections_recursively( $widget ) );
					} else {
						$elements[] = $widget->get_name();
					}
				} elseif ( ! $this->should_render( $widget ) && (bool) $widget_settings[ self::SECTION_PREFIX . 'keep_html' ] ) {
					if ( ( 'section' === $widget->get_name() || 'container' === $widget->get_name() ) &&
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

		$js_widgets = [];

		foreach ( Resources::get_all_widget_options() as $type => $data ) {
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

	/**
	 * Register section
	 *
	 * @param $element
	 * @return void
	 */
	public function register_section( $element ) {
		foreach ( Resources::get_pro_widget_options() as $slug => $option ) {
			$element->start_controls_section(
				self::SECTION_PREFIX . 'pro_' . str_replace( '-', '_', $slug ),
				[
					'tab'       => self::VISIBILITY_TAB,
					'label'     => $option['name'],
					'condition' => [
						self::SECTION_PREFIX . 'enabled' => 'yes',
					],
				]
			);

			$element->end_controls_section();
		}
	}

	/**
	 * Not installed notice
	 *
	 * @return void
	 */
	public function not_installed_notice() {
		$class = 'notice notice-warning';
		/* translators: %s: html tags */
		$message = sprintf( __( '%1$sVisibility Logic%2$s is enabled for %1$sElementor%2$s but the %1$sElementor%2$s plugin installed & activated.', 'visibility-logic-elementor' ), '<strong>', '</strong>' );

		$plugin = 'elementor/elementor.php';

		if ( Resources::instance()->is_plugin_installed( $plugin ) ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$action_url   = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );
			$button_label = __( 'Activate Elementor', 'visibility-logic-elementor' );

		} else {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return;
			}

			$action_url   = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );
			$button_label = __( 'Install Elementor', 'visibility-logic-elementor' );
		}

		$button = '<p><a href="' . $action_url . '" class="button-primary">' . $button_label . '</a></p><p></p>';

		printf( '<div class="%1$s"><p>%2$s</p>%3$s</div>', esc_attr( $class ), $message, $button );
	}

	/**
	 * Required minimum version notice
	 *
	 * @return void
	 */
	public function required_min_version_notice() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		if ( isset( $_GET['activate'] ) ) { // WPCS: CSRF ok, input var ok.
			unset( $_GET['activate'] ); // WPCS: input var ok.
		}

		$message = sprintf(
			'<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">'
			/* translators: 1: Plugin name 2: Elementor */
			. esc_html__( '%1$s is enabled for %2$s but it requires version %3$s or greater of %2$s plugin.', 'visibility-logic-elementor' )
			. '</span>',
			'<strong>' . __( 'Visibility Logic', 'visibility-logic-elementor' ) . '</strong>',
			'<strong>' . __( 'Elementor', 'visibility-logic-elementor' ) . '</strong>',
			self::$minimum_version
		);

		$file_path   = 'elementor/elementor.php';
		$update_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );

		$message .= sprintf(
			'<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">' .
			'<a class="button-primary" href="%1$s">%2$s</a></span>',
			$update_link,
			__( 'Update Elementor Now', 'visibility-logic-elementor' )
		);

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', $message );
	}
}
