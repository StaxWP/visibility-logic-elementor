<?php
/**
 * Plugin bootstrap and the render-time evaluation of the visibility rules.
 *
 * @package Stax\VisibilityLogic
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin extends Singleton {

	/**
	 * Minimum Elementor version.
	 *
	 * @var string
	 */
	public static $minimum_elementor_version = '2.0.0';

	/**
	 * Widget types hidden in this request (their styles are enqueued when a visible widget of the type follows).
	 *
	 * @var array
	 */
	public $initiated_widgets = [];

	/**
	 * Ids of widgets that are not printed because a parent is hidden.
	 *
	 * @var array
	 */
	public $excluded_widgets = [];

	/**
	 * Result of the rule evaluation per element object in this request.
	 *
	 * @var array
	 */
	private $results = [];

	/**
	 * Documents (by id) that contain an element whose rules cannot go through Elementor's element cache.
	 *
	 * @var array
	 */
	private $uncacheable_documents = [];

	/**
	 * Plugin constructor
	 */
	public function __construct() {

		require_once STAX_VISIBILITY_CORE_HELPERS_PATH . 'Resources.php';
		require_once STAX_VISIBILITY_CORE_HELPERS_PATH . 'Notices.php';
		require_once STAX_VISIBILITY_CORE_HELPERS_PATH . 'Pro_Teaser.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Conditions.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Condition_Item.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Rules.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Context.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Rule_Tree.php';
		require_once STAX_VISIBILITY_CORE_HELPERS_PATH . 'Cache_Check.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Rest.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Preview.php';

		Rule_Tree::load_package();

		// Builder-agnostic parts: conditions, admin panel, block editor, abilities, WP-CLI.
		if ( is_admin() ) {
			require_once STAX_VISIBILITY_CORE_PATH . 'Upgrades.php';
		}

		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Base.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Options.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Usage.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/Settings.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/UpdateNotice.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Abilities.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Blocks.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'Atomic.php';

		add_action( 'init', [ $this, 'load_settings' ], 0 );
		add_action( 'init', [ $this, 'maybe_upgrade' ], 1 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once STAX_VISIBILITY_CORE_PATH . 'Cli.php';
		}

		// Elementor parts, only when Elementor is active.
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}

		do_action( 'stax/visibility/pre_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.

		// Check for the minimum required Elementor version.
		if ( ! version_compare( ELEMENTOR_VERSION, self::$minimum_elementor_version, '>=' ) ) {
			add_action( 'admin_notices', [ Notices::instance(), 'minimum_elementor_version' ] );

			return;
		}

		add_action( 'elementor/init', [ $this, 'load_elementor_modules' ] );
		add_action( 'elementor/init', [ $this, 'register_visibility_tab' ] );

		add_filter( 'elementor/widget/render_content', [ $this, 'content_change' ], 999, 2 );
		add_filter( 'elementor/frontend/section/before_render', [ $this, 'section_content_change' ], 999 );
		add_filter( 'elementor/frontend/container/before_render', [ $this, 'section_content_change' ], 999 );
		if ( self::columns_supported() ) {
			add_filter( 'elementor/frontend/column/before_render', [ $this, 'section_content_change' ], 999 );
			}

		add_filter( 'elementor/frontend/section/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/container/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/column/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/widget/should_render', [ $this, 'item_should_render' ], 99999, 2 );
		add_filter( 'elementor/frontend/repeater/should_render', [ $this, 'item_should_render' ], 99999, 2 );

		// Elementor element cache (see the `element_cache` setting).
		add_filter( 'elementor/element/is_dynamic_content', [ $this, 'is_dynamic_content' ], 10, 2 );
		add_action( 'elementor/frontend/before_get_builder_content', [ $this, 'maybe_disable_document_caching' ], 99999, 2 );
		add_action( 'elementor/frontend/get_builder_content', [ $this, 're_enable_document_caching' ], 99999, 2 );

		add_action( 'elementor/preview/enqueue_styles', [ $this, 'editor_preview_styles' ] );

		Atomic::init( [ $this, 'item_should_render' ] );

		do_action( 'stax/visibility/after_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.
	}

	/**
	 * Register visibility tab
	 */
	public function register_visibility_tab() {
		\Elementor\Controls_Manager::add_tab(
			self::VISIBILITY_TAB,
			__( 'Visibility', 'visibility-logic-elementor' )
		);
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
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/controls/Select.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/controls/Rule_Builder.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'helpers/Controls.php';

		new Ajax();

		add_action( 'elementor/controls/register', [ new Controls(), 'on_controls_registered' ] );
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
			|| Resources::instance()->is_plugin_active( 'visibility-logic-elementor-pro/conditional.php' );
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
				$option['status'] && false === $option['pro'] && file_exists( $option['class'] ) ) {
				require_once $option['class'];
			}
		}

		if ( Pro_Teaser::visible() ) {
			require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'ProVisibility.php';
		}

		// Text fallback (free since 3.0): Pro registers its own Fallback section (text and template).
		if ( ! defined( 'STAX_VISIBILITY_PRO_VERSION' ) ) {
			require_once STAX_VISIBILITY_CORE_SETTINGS_PATH . 'FallbackText.php';
		}

		do_action( 'stax/visibility/after/load_settings' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.
	}

	/**
	 * Once per version: store the general settings of a site updating from 2.x (the 2.x behaviour, see
	 * Resources::default_settings()), and flush Elementor's element cache when the per-element cache mode is on.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( STAX_VISIBILITY_VERSION === get_option( 'stax_visibility_version' ) ) {
			return;
		}

		if ( false === get_option( Resources::SETTINGS_OPTION, false ) ) {
			add_option( Resources::SETTINGS_OPTION, Resources::default_settings() );

			// A site updating from 2.x: one admin notice about the opt-in behaviours (see UpdateNotice).
			add_option( 'stax_visibility_updated_from_2x', '1', '', false );
		}

		self::remove_appsero_data();

		if ( Resources::get_setting( 'element_cache' ) ) {
			self::flush_element_cache();
		}

		update_option( 'stax_visibility_version', STAX_VISIBILITY_VERSION, true );
	}

	/**
	 * Remove what the Appsero usage tracker (bundled until 2.5.1) stored on the site.
	 *
	 * @return void
	 */
	public static function remove_appsero_data() {
		foreach ( [ '_tracking_last_send', '_allow_tracking', '_tracking_notice', '_tracking_skipped' ] as $suffix ) {
			delete_option( 'visibility-logic-elementor' . $suffix );
		}

		wp_clear_scheduled_hook( 'visibility-logic-elementor_tracker_send_event' );
	}

	/**
	 * Delete Elementor's element cache of every document (it is rebuilt on the next view).
	 *
	 * @return void
	 */
	public static function flush_element_cache() {
		$key = defined( '\Elementor\Core\Base\Document::CACHE_META_KEY' ) ? \Elementor\Core\Base\Document::CACHE_META_KEY : '_elementor_element_cache';

		delete_post_meta_by_key( $key );
	}

	/**
	 * Render item or not based on conditions (sections and containers, before render).
	 *
	 * @param \Elementor\Element_Base $widget Element.
	 *
	 * @return void
	 */
	public function section_content_change( $widget ) {
		$this->content_change( '', $widget );
	}

	/**
	 * Replace the content of a hidden widget (fallback, hide by CSS) and keep track of hidden widget types.
	 *
	 * @param string                  $content Content.
	 * @param \Elementor\Element_Base $widget  Element.
	 *
	 * @return string
	 */
	public function content_change( $content, $widget ) {
		$visible = $this->should_render( $widget );

		if ( ! $visible ) {
			if ( $this->is_container_like( $widget ) ) {
				$not_rendered_widgets = $this->get_section_widgets_recursively( $widget );

				$this->initiated_widgets = array_merge( $this->initiated_widgets, $not_rendered_widgets );
				$this->excluded_widgets  = array_merge( $this->excluded_widgets, array_keys( $not_rendered_widgets ) );
			} else {
				$this->initiated_widgets[] = method_exists( $widget, 'get_group_name' ) ? $widget->get_group_name() : $widget->get_name();
			}
		}

		if ( ! $visible && ! $this->is_edit_mode() ) {
			$settings = $this->item_settings( $widget );

			if ( self::flag( $settings, 'enabled' ) ) {
				if ( self::flag( $settings, 'fallback_enabled' ) ) {
					$fallback_content = '';
					$fallback_type    = (string) self::value( $settings, 'fallback_type' );
					$fallback_type    = '' === $fallback_type ? 'text' : $fallback_type;

					if ( 'text' === $fallback_type ) {
						$fallback_content = wp_kses_post( (string) self::value( $settings, 'fallback_text' ) );
					} elseif ( 'template' === $fallback_type && self::value( $settings, 'fallback_template' ) && shortcode_exists( 'elementor-template' ) ) {
						$fallback_content = do_shortcode( '[elementor-template id="' . esc_attr( self::value( $settings, 'fallback_template' ) ) . '"]' );
					}

					if ( $fallback_content ) {
						return $fallback_content;
					}
				} elseif ( self::flag( $settings, 'keep_html' ) ) {
					$widget->add_render_attribute( '_wrapper', 'class', 'stax-visibility-hidden' );
					$widget->add_render_attribute( '_wrapper', 'style', 'display: none' );

					return $content;
				}
			}

			return '';
		}

		if ( ! empty( $this->initiated_widgets ) && ! in_array( $widget->get_name(), [ 'section', 'container', 'column' ], true ) ) {
			$needs_print = false;

			foreach ( $this->initiated_widgets as $k => $initiated_widget ) {
				if ( $initiated_widget === $widget->get_name() && ! in_array( $widget->get_id(), $this->excluded_widgets, true ) ) {
					$needs_print = true;
					unset( $this->initiated_widgets[ $k ] );
				}
			}

			if ( $needs_print ) {
				// Get widget styles.
				foreach ( (array) $widget->get_style_depends() as $style ) {
					wp_enqueue_style( $style );
				}
			}
		}

		return $content;
	}

	/**
	 * Check if item should render
	 *
	 * @param bool                    $should_render Elementor's answer.
	 * @param \Elementor\Element_Base $widget        Element.
	 *
	 * @return boolean
	 */
	public function item_should_render( $should_render, $widget ) {
		$settings = $this->item_settings( $widget );

		if ( ! $this->should_render( $widget, $should_render ) ) {

			// Keep HTML (hidden by CSS) or fallback content: the element is printed.
			if ( self::flag( $settings, 'keep_html' ) || self::flag( $settings, 'fallback_enabled' ) ) {
				return true;
			}

			return false;
		}

		$should_render = true;

		// Hide section if needed.
		if ( $this->is_container_like( $widget ) &&
			! self::flag( $settings, 'enabled' ) &&
			self::flag( $settings, 'hide_when_empty' ) ) {

			$should_render = ! empty( $this->check_for_empty_sections_recursively( $widget ) );
		}

		return $should_render;
	}

	/**
	 * Public check: does the element pass its visibility rules for the current visitor?
	 *
	 * The answer is cached per element object for the request (Elementor asks several times per element).
	 *
	 * @param \Elementor\Element_Base $item           Element.
	 * @param bool                    $default_render Answer when the element has no rules.
	 *
	 * @return bool
	 */
	public function is_visible( $item, $default_render = true ) {
		return $this->should_render( $item, $default_render );
	}

	/**
	 * Forget the cached answers (tests, long-running processes).
	 *
	 * @return void
	 */
	public function reset_cache() {
		$this->results           = [];
		$this->initiated_widgets = [];
		$this->excluded_widgets  = [];
	}

	/**
	 * Whether the element is a section or a container (the elements that can hide when empty).
	 *
	 * @param \Elementor\Element_Base $item Element.
	 *
	 * @return bool
	 */
	private function is_container_like( $item ) {
		$name = $item->get_name();

		return 'section' === $name || 'container' === $name;
	}

	/**
	 * Elements one level below the columns of a section, or below the child containers of a container.
	 *
	 * With the `container_widgets` setting (on since 2.5.2) a widget placed directly in a container counts itself;
	 * before 2.5.2 it was skipped (children of children only), so "Hide when empty" always hid such a container.
	 * Unchecking the setting restores that walk.
	 *
	 * @param \Elementor\Element_Base $item Element.
	 *
	 * @return \Elementor\Element_Base[]
	 */
	private function get_leaf_children( $item, $skip_hidden_columns = false ) {
		$children = [];
		$direct   = (bool) Resources::get_setting( 'container_widgets' );

		foreach ( $item->get_children() as $child ) {
			if ( $direct && 'widget' === $child->get_type() ) {
				$children[] = $child;
				continue;
			}

			// A column hidden by its own rule (3.0) hides everything in it.
			if ( $skip_hidden_columns && 'column' === $child->get_name() && ! $this->should_render( $child ) && ! self::flag( $this->item_settings( $child ), 'keep_html' ) ) {
				continue;
			}

			foreach ( $child->get_children() as $grand_child ) {
				$children[] = $grand_child;
			}
		}

		return $children;
	}

	/**
	 * Get section's widgets recursively
	 *
	 * @param object $item Element.
	 *
	 * @return array
	 */
	private function get_section_widgets_recursively( $item ) {
		$elements = [];

		foreach ( $this->get_leaf_children( $item ) as $widget ) {
			if ( $this->is_container_like( $widget ) ) {
				$elements = array_merge( $elements, $this->get_section_widgets_recursively( $widget ) );
			} else {
				$elements[ $widget->get_id() ] = method_exists( $widget, 'get_group_name' ) ? $widget->get_group_name() : $widget->get_name();
			}
		}

		return $elements;
	}

	/**
	 * Check for empty section recursively
	 *
	 * @param object $item Element.
	 *
	 * @return array
	 */
	private function check_for_empty_sections_recursively( $item ) {
		$elements = [];

		foreach ( $this->get_leaf_children( $item, true ) as $widget ) {
			$widget_settings = $this->item_settings( $widget );

			if ( ! $this->should_render( $widget ) && ! self::flag( $widget_settings, 'keep_html' ) ) {
				continue;
			}

			if ( $this->is_container_like( $widget ) &&
				! self::flag( $widget_settings, 'enabled' ) &&
				self::flag( $widget_settings, 'hide_when_empty' ) ) {
				$elements = array_merge( $elements, $this->check_for_empty_sections_recursively( $widget ) );
			} else {
				$elements[] = $widget->get_name();
			}
		}

		return $elements;
	}

	/**
	 * Check if conditions are matched
	 *
	 * @param \Elementor\Element_Base $item           Element.
	 * @param bool                    $default_render Answer when the element has no rules.
	 *
	 * @return boolean
	 */
	private function should_render( $item, $default_render = true ) {
		$settings = $this->item_settings( $item );

		// Fast path: no rules at all (most elements).
		if ( ! self::flag( $settings, 'enabled' ) && empty( $settings['ecl_enabled'] ) ) {
			return $default_render;
		}

		$atomic = Atomic::is_atomic( $item );

		// Atomic elements stay visible in the Elementor 4 editor canvas.
		if ( $atomic && $this->is_editor_render() ) {
			return $default_render;
		}

		$key = spl_object_id( $item ) . '|' . (int) get_the_ID() . '|' . ( $default_render ? 1 : 0 );

		if ( isset( $this->results[ $key ] ) && $this->results[ $key ][0] === $item->get_id() ) {
			return $this->results[ $key ][1];
		}

		$target = $atomic ? new Condition_Item( $settings, $item->get_id(), $item->get_name(), 'atomic' ) : $item;

		// Columns with Visibility Logic Pro 2.4 (its controls are not registered on columns): the switches of every
		// condition default to off, so Pro 2.4 modules read defined values.
		if ( ! $atomic && 'column' === $item->get_name() && ! self::pro_supports_columns() ) {
			$defaults = [];

			foreach ( array_keys( Resources::get_all_widget_options() ) as $slug ) {
				$defaults[ self::SECTION_PREFIX . ( 'post-page' === $slug ? 'post' : str_replace( '-', '_', $slug ) ) . '_enabled' ] = '';
			}

			$settings = array_merge( $defaults, $settings );
			$target   = new Condition_Item( $settings, $item->get_id(), $item->get_name(), 'element' );
		}
		$result = Conditions::evaluate( $settings, $target, $default_render );

		$this->results[ $key ] = [ $item->get_id(), $result ];

		return $result;
	}

	/**
	 * Visibility settings of an element: classic settings as saved, atomic (Elementor 4) props converted.
	 *
	 * @param object $item Element.
	 *
	 * @return array
	 */
	private function item_settings( $item ) {
		$settings = (array) $item->get_settings();

		if ( ! Atomic::is_atomic( $item ) ) {
			return $settings;
		}

		$values = [];
		$prefix = self::SECTION_PREFIX;

		foreach ( $settings as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, $prefix ) ) {
				$values[ substr( $key, strlen( $prefix ) ) ] = Conditions::unwrap( $value );
			}
		}

		return empty( $values ) ? [] : Conditions::to_settings( $values, 'atomic' );
	}

	/**
	 * Whether Elementor is rendering for its editor (edit mode or the preview iframe).
	 *
	 * @return bool
	 */
	private function is_editor_render() {
		if ( $this->is_edit_mode() ) {
			return true;
		}

		return isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode();
	}

	/**
	 * Whether Elementor renders the editor (hidden elements stay visible there).
	 *
	 * @return bool
	 */
	private function is_edit_mode() {
		return isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/**
	 * Before load panel assets
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
			wp_script_is( 'jquery-elementor-select2', 'registered' ) ? [ 'jquery', 'jquery-elementor-select2' ] : [ 'jquery' ],
			STAX_VISIBILITY_VERSION,
			false
		);

		$js_widgets = [];

		foreach ( array_keys( Resources::get_all_widget_options() ) as $type ) {
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

		self::enqueue_rule_builder( 'elementor' );

		// Select2: Elementor's own copy (handles `jquery-elementor-select2` / `elementor-select2` in Elementor 3.x and 4.x).
		// 2.x bundled a second Select2 build here.
	}

	/**
	 * Rule builder assets (shared package script and style, the 2.x mapping, the editor adapter).
	 *
	 * @param string $editor elementor|blocks.
	 *
	 * @return string Handle of the editor adapter script.
	 */
	public static function enqueue_rule_builder( $editor ) {
		\StaxWP\Conditions\V1\Assets::register();

		wp_enqueue_style( 'stax-conditions' );
		wp_register_script( 'stax-visibility-rule-map', STAX_VISIBILITY_ASSETS_URL . 'js/rule-map.js', [], STAX_VISIBILITY_VERSION, true );

		if ( 'elementor' === $editor ) {
			$handle = 'stax-visibility-rule-builder';
			wp_enqueue_script( $handle, STAX_VISIBILITY_ASSETS_URL . 'js/rule-builder-elementor.js', [ 'jquery', 'stax-conditions', 'stax-visibility-rule-map' ], STAX_VISIBILITY_VERSION, true );
			wp_localize_script( $handle, 'staxVisibilityRules', Rest::editor_data() );

			return $handle;
		}

		return 'stax-visibility-blocks';
	}

	/**
	 * Icon on elements with visibility rules, in the editor preview only.
	 *
	 * @return void
	 */
	public function editor_preview_styles() {
		wp_register_style( 'stax-visibility-preview', false, [], STAX_VISIBILITY_VERSION );
		wp_enqueue_style( 'stax-visibility-preview' );
		wp_add_inline_style( 'stax-visibility-preview', self::preview_css() );
	}

	/**
	 * Show icon in editor for widgets with setting enabled.
	 *
	 * Kept for backward compatibility (it was hooked to `wp_footer` before 3.0); the styles now load through
	 * `elementor/preview/enqueue_styles`.
	 *
	 * @return void
	 */
	public function editor_show_visibility_icon() {
		if ( ! class_exists( 'Elementor\Plugin' ) || ! \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return;
		}

		echo '<style>' . self::preview_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
	}

	/**
	 * CSS of the editor preview icon.
	 *
	 * @return string
	 */
	private static function preview_css() {
		return "body.elementor-editor-active .elementor-element.stax-condition-yes::before{content:'\\e8ed';color:#6E49CB;display:inline-block;font-family:eicons;font-size:15px;position:absolute;top:0;right:5px;z-index:1;}"
			// 3.0: short rule summary on the canvas (set by assets/js/rule-builder-elementor.js).
			. 'body.elementor-editor-active .elementor-element[data-stax-vl]::after{content:attr(data-stax-vl);position:absolute;top:0;right:22px;z-index:2;max-width:60%;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;padding:1px 6px;border-radius:0 0 4px 4px;background:#6E49CB;color:#fff;font:600 10px/16px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;pointer-events:none;}'
			. 'body.elementor-editor-active .elementor-element.stax-vl-ghost{opacity:.35;outline:1px dashed #6E49CB;}'
			. 'body.elementor-editor-active .elementor-element.stax-vl-ghost[data-stax-vl]::after{background:#3c434a;}';
	}

	/**
	 * Elementor element cache: an element with rules (or hiding when empty) must be rendered on every request.
	 *
	 * @param bool  $is_dynamic Elementor's answer.
	 * @param array $raw_data   Element data.
	 *
	 * @return bool
	 */
	public function is_dynamic_content( $is_dynamic, $raw_data = [] ) {
		if ( $is_dynamic || empty( $raw_data['settings'] ) || ! is_array( $raw_data['settings'] ) || ! Resources::get_setting( 'element_cache' ) ) {
			return $is_dynamic;
		}

		return Rules::has_rules( $raw_data['settings'] ) || self::flag( $raw_data['settings'], 'hide_when_empty' );
	}

	/**
	 * Disable Elementor's element cache for a document while it renders.
	 *
	 * 2.x behaviour (default for sites updating from 2.x): for every document with an element that has
	 * "Enable Visibility Logic" on. With the `element_cache` setting: only when an element with rules cannot be
	 * rendered per request through the element cache (Elementor before 3.22, or "Cache Settings" forced to
	 * active on that element).
	 *
	 * @param \Elementor\Core\Base\Document $document   Document.
	 * @param bool                          $is_excerpt Excerpt.
	 *
	 * @return void
	 */
	public function maybe_disable_document_caching( $document, $is_excerpt = false ) {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_elements_data' ) ) {
			return;
		}

		$id = method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;

		if ( ! isset( $this->uncacheable_documents[ $id ] ) || 0 === $id ) {
			$elements = (array) $document->get_elements_data();

			if ( Resources::get_setting( 'element_cache' ) ) {
				$this->uncacheable_documents[ $id ] = $this->needs_uncached_render( $elements, version_compare( ELEMENTOR_VERSION, '3.22.0', '<' ) );
			} else {
				$this->uncacheable_documents[ $id ] = $this->has_enabled_element( $elements );
			}
		}

		if ( $this->uncacheable_documents[ $id ] ) {
			add_filter( 'get_post_metadata', [ $this, 'filter_document_cache_meta' ], 9999, 5 );
		}
	}

	/**
	 * Re-enable the document cache after the document was rendered.
	 *
	 * @param \Elementor\Core\Base\Document $document   Document.
	 * @param bool                          $is_excerpt Excerpt.
	 *
	 * @return void
	 */
	public function re_enable_document_caching( $document, $is_excerpt = false ) {
		remove_filter( 'get_post_metadata', [ $this, 'filter_document_cache_meta' ], 9999 );
	}

	/**
	 * Hide the element cache meta from Elementor.
	 *
	 * @param mixed  $value     Value.
	 * @param int    $object_id Object id.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Single.
	 * @param string $meta_type Meta type.
	 *
	 * @return mixed
	 */
	public function filter_document_cache_meta( $value, $object_id, $meta_key, $single = false, $meta_type = 'post' ) {
		$cache_key = defined( '\Elementor\Core\Base\Document::CACHE_META_KEY' ) ? \Elementor\Core\Base\Document::CACHE_META_KEY : '_elementor_element_cache';

		if ( $meta_key === $cache_key ) {
			return false;
		}

		return $value;
	}

	/**
	 * 2.x check: an element (at any depth) with "Enable Visibility Logic" on.
	 *
	 * @param array $elements Elements data.
	 *
	 * @return bool
	 */
	private function has_enabled_element( array $elements ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['settings'] ) && Rules::is_enabled( $element['settings'] ) ) {
				return true;
			}

			if ( ! empty( $element['elements'] ) && $this->has_enabled_element( (array) $element['elements'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the document must skip Elementor's element cache.
	 *
	 * @param array $elements     Elements data.
	 * @param bool  $legacy_cache Elementor without per-element dynamic rendering.
	 *
	 * @return bool
	 */
	private function needs_uncached_render( array $elements, $legacy_cache ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : [];

			if ( Rules::has_rules( $settings ) || self::flag( $settings, 'hide_when_empty' ) ) {
				if ( $legacy_cache || ( isset( $settings['_element_cache'] ) && 'no' === $settings['_element_cache'] ) ) {
					return true;
				}
			}

			if ( ! empty( $element['elements'] ) && $this->needs_uncached_render( (array) $element['elements'], $legacy_cache ) ) {
				return true;
			}
		}

		return false;
	}
}

Plugin::instance();
