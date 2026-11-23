<?php
/**
 * Visibility rules on WordPress menu items (Appearance > Menus) and classic widgets (Appearance > Widgets, the
 * Customizer, the Legacy Widget block). Same rule builder and conditions (free and Pro) as elements and blocks.
 *
 * Stored value: { enabled: bool, rules: tree } (`enabled` false = "Off (keep the rule)"):
 * - menu items: post meta `_stax_visibility_rules` of the `nav_menu_item` post;
 * - classic widgets: key `stax_visibility_rules` of the widget instance.
 *
 * A hidden menu item hides its submenu items too. Rules apply to everyone, administrators included, as on elements.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

use StaxWP\Conditions\V1\Tree;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Area_Rules
 */
class Area_Rules {

	/**
	 * Menu item post meta.
	 */
	const META = '_stax_visibility_rules';

	/**
	 * Widget instance key and form field name.
	 */
	const KEY = 'stax_visibility_rules';

	/**
	 * Menu item form field (array keyed by menu item id).
	 */
	const MENU_FIELD = 'stax_visibility_menu';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Menus.
		add_action( 'wp_nav_menu_item_custom_fields', [ __CLASS__, 'menu_item_field' ], 10, 2 );
		add_action( 'wp_update_nav_menu_item', [ __CLASS__, 'save_menu_item' ], 10, 2 );
		add_filter( 'wp_nav_menu_objects', [ __CLASS__, 'filter_menu' ], 10, 2 );

		// Classic widgets.
		add_action( 'in_widget_form', [ __CLASS__, 'widget_field' ], 10, 3 );
		add_filter( 'widget_update_callback', [ __CLASS__, 'save_widget' ], 10, 4 );
		add_filter( 'widget_display_callback', [ __CLASS__, 'filter_widget' ], 10, 3 );

		// Rule builder on the admin screens that show these forms.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_assets' ] );
		add_action( 'customize_controls_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	/**
	 * Clean a stored or submitted value.
	 *
	 * @param mixed $value Value (array or JSON string).
	 *
	 * @return array|null Null when there is no rule (nothing to store).
	 */
	public static function sanitize( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( wp_unslash( $value ), true );
		}

		if ( ! is_array( $value ) || empty( $value['rules'] ) || ! is_array( $value['rules'] ) ) {
			return null;
		}

		Rule_Tree::load_package();

		$rules = Tree::normalize( $value['rules'] );

		if ( Tree::is_empty( $rules ) && empty( $rules['draft'] ) ) {
			return null;
		}

		return [
			'enabled' => ! empty( $value['enabled'] ) && 'no' !== $value['enabled'],
			'rules'   => $rules,
		];
	}

	/**
	 * Whether a stored value hides its item for the current visitor.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $id    Item id (for the trace and the admin bar).
	 * @param string $name  Item name.
	 * @param string $kind  menu|widget.
	 *
	 * @return bool
	 */
	public static function hides( $value, $id, $name, $kind ) {
		$value = self::sanitize( $value );

		if ( ! $value || empty( $value['enabled'] ) ) {
			return false;
		}

		$result = Rule_Tree::evaluate( $value['rules'], new Condition_Item( [], $id, $name, $kind ), true, Rule_Tree::recording() );

		if ( Rule_Tree::recording() ) {
			Rule_Tree::add_trace( $id, $name, $value['rules'], $result );
		}

		return empty( $result['visible'] );
	}

	/**
	 * Rule of a menu item.
	 *
	 * @param int $item_id Menu item id.
	 *
	 * @return array|null
	 */
	public static function menu_value( $item_id ) {
		return self::sanitize( get_post_meta( (int) $item_id, self::META, true ) );
	}

	// ---- Menus ----

	/**
	 * Visibility field in a menu item (Appearance > Menus).
	 *
	 * @param int      $item_id Menu item id.
	 * @param \WP_Post $item    Menu item.
	 *
	 * @return void
	 */
	public static function menu_item_field( $item_id, $item ) {
		self::field( self::MENU_FIELD . '[' . (int) $item_id . ']', self::menu_value( $item_id ), 'menu' );
	}

	/**
	 * Save the rule of a menu item (Appearance > Menus; WordPress checked the menu nonce before this action).
	 *
	 * @param int $menu_id Menu id.
	 * @param int $item_id Menu item id.
	 *
	 * @return void
	 */
	public static function save_menu_item( $menu_id, $item_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nav-menus.php verified `update-nav_menu`.
		if ( ! isset( $_POST[ self::MENU_FIELD ][ $item_id ] ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by self::sanitize().
		$value = self::sanitize( (string) $_POST[ self::MENU_FIELD ][ $item_id ] );

		if ( $value ) {
			update_post_meta( (int) $item_id, self::META, $value );
		} else {
			delete_post_meta( (int) $item_id, self::META );
		}

		Rules::flush();
	}

	/**
	 * `wp_nav_menu_objects`: drop hidden items and every item below them.
	 *
	 * @param array $items Menu items.
	 * @param mixed $args  Menu arguments.
	 *
	 * @return array
	 */
	public static function filter_menu( $items, $args = null ) {
		if ( empty( $items ) || ! is_array( $items ) || is_admin() ) {
			return $items;
		}

		$hidden = [];

		foreach ( $items as $item ) {
			$id    = (int) $item->ID;
			$value = get_post_meta( $id, self::META, true );

			if ( $value && self::hides( $value, 'menu-' . $id, wp_strip_all_tags( (string) $item->title ), 'menu' ) ) {
				$hidden[ $id ] = true;
			}
		}

		if ( ! $hidden ) {
			return $items;
		}

		// Submenu items of a hidden item go too (repeat until no new parent is found: order is not guaranteed).
		do {
			$found = false;

			foreach ( $items as $item ) {
				$id = (int) $item->ID;

				if ( ! isset( $hidden[ $id ] ) && isset( $hidden[ (int) $item->menu_item_parent ] ) ) {
					$hidden[ $id ] = true;
					$found         = true;
				}
			}
		} while ( $found );

		return array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $hidden ) {
					return ! isset( $hidden[ (int) $item->ID ] );
				}
			)
		);
	}

	// ---- Classic widgets ----

	/**
	 * Visibility field at the end of a classic widget form.
	 *
	 * @param \WP_Widget $widget   Widget.
	 * @param mixed      $return   Form return value (passed by reference by WordPress, unused).
	 * @param array      $instance Widget settings.
	 *
	 * @return void
	 */
	public static function widget_field( $widget, $return, $instance ) {
		self::field( $widget->get_field_name( self::KEY ), self::sanitize( isset( $instance[ self::KEY ] ) ? $instance[ self::KEY ] : null ), 'widget' );
	}

	/**
	 * Save the rule with the widget settings.
	 *
	 * @param array      $instance     New settings (from the widget's own update()).
	 * @param array      $new_instance Submitted settings.
	 * @param array      $old_instance Previous settings.
	 * @param \WP_Widget $widget       Widget.
	 *
	 * @return array|false
	 */
	public static function save_widget( $instance, $new_instance, $old_instance, $widget ) {
		if ( ! is_array( $instance ) ) {
			return $instance;
		}

		if ( array_key_exists( self::KEY, (array) $new_instance ) ) {
			$value = self::sanitize( $new_instance[ self::KEY ] );
		} else {
			$value = self::sanitize( isset( $old_instance[ self::KEY ] ) ? $old_instance[ self::KEY ] : null );
		}

		if ( $value ) {
			$instance[ self::KEY ] = $value;
		} else {
			unset( $instance[ self::KEY ] );
		}

		Rules::flush();

		return $instance;
	}

	/**
	 * `widget_display_callback`: false hides the widget.
	 *
	 * @param array|false $instance Widget settings.
	 * @param \WP_Widget  $widget   Widget.
	 * @param array       $args     Sidebar arguments.
	 *
	 * @return array|false
	 */
	public static function filter_widget( $instance, $widget, $args ) {
		if ( ! is_array( $instance ) || empty( $instance[ self::KEY ] ) || is_admin() ) {
			return $instance;
		}

		return self::hides( $instance[ self::KEY ], (string) $widget->id, (string) $widget->name, 'widget' ) ? false : $instance;
	}

	// ---- Rules overview ----

	/**
	 * Menus and widget areas with rules, as documents of the Rules overview (one row per menu item or widget).
	 *
	 * @return array[]
	 */
	public static function overview_documents() {
		$documents = [];

		foreach ( wp_get_nav_menus() as $menu ) {
			$elements = [];

			foreach ( (array) wp_get_nav_menu_items( $menu->term_id, [ 'post_status' => 'any' ] ) as $item ) {
				$value = self::menu_value( $item->ID );

				if ( $value ) {
					$elements[] = self::overview_element( $value, (string) $item->ID, 'menu_item', wp_strip_all_tags( (string) $item->title ) );
				}
			}

			if ( $elements ) {
				$documents[] = self::overview_document( (int) $menu->term_id, $menu->name, 'menus', __( 'Menu', 'visibility-logic-elementor' ), $elements );
			}
		}

		global $wp_registered_widgets, $wp_registered_sidebars;

		foreach ( (array) wp_get_sidebars_widgets() as $sidebar => $widget_ids ) {
			$elements = [];

			foreach ( (array) $widget_ids as $widget_id ) {
				$instance = self::widget_instance( $widget_id );

				if ( empty( $instance[ self::KEY ] ) ) {
					continue;
				}

				$value = self::sanitize( $instance[ self::KEY ] );

				if ( $value ) {
					$label      = isset( $wp_registered_widgets[ $widget_id ]['name'] ) ? $wp_registered_widgets[ $widget_id ]['name'] : $widget_id;
					$title      = ! empty( $instance['title'] ) ? $label . ': ' . wp_strip_all_tags( (string) $instance['title'] ) : $label;
					$elements[] = self::overview_element( $value, (string) $widget_id, 'widget', $title );
				}
			}

			if ( $elements ) {
				$name        = isset( $wp_registered_sidebars[ $sidebar ]['name'] ) ? $wp_registered_sidebars[ $sidebar ]['name'] : ( 'wp_inactive_widgets' === $sidebar ? __( 'Inactive widgets', 'visibility-logic-elementor' ) : $sidebar );
				$documents[] = self::overview_document( 0, $name, 'widgets', __( 'Widgets', 'visibility-logic-elementor' ), $elements );
			}
		}

		return $documents;
	}

	/**
	 * Settings of a classic widget by its id (`text-3`).
	 *
	 * @param string $widget_id Widget id.
	 *
	 * @return array
	 */
	private static function widget_instance( $widget_id ) {
		if ( ! preg_match( '/^(.+)-(\d+)$/', (string) $widget_id, $m ) ) {
			return [];
		}

		$all = get_option( 'widget_' . $m[1], [] );

		return isset( $all[ (int) $m[2] ] ) && is_array( $all[ (int) $m[2] ] ) ? $all[ (int) $m[2] ] : [];
	}

	/**
	 * Overview row of one item.
	 *
	 * @param array  $value Stored value.
	 * @param string $id    Item id.
	 * @param string $type  menu_item|widget.
	 * @param string $name  Shown name.
	 *
	 * @return array
	 */
	private static function overview_element( array $value, $id, $type, $name ) {
		$off = empty( $value['enabled'] );

		return [
			'id'         => $id,
			'type'       => $type,
			'name'       => '' !== $name ? $name : '#' . $id,
			'action'     => isset( $value['rules']['action'] ) && 'hide' === $value['rules']['action'] ? 'hide' : 'show',
			'match'      => 'all',
			'conditions' => Page_Rules::condition_types( [ 'rules' => $value['rules'] ] ),
			'legacy'     => false,
			'keep_html'  => false,
			'fallback'   => false,
			'tree'       => $value['rules'],
			'summary'    => self::summary( $value ),
			'stored'     => true,
			'draft'      => ! $off && ! empty( $value['rules']['draft'] ),
		];
	}

	/**
	 * Overview document (a menu or a widget area).
	 *
	 * @param int    $id       Menu id (0 for widget areas).
	 * @param string $title    Title.
	 * @param string $builder  menus|widgets.
	 * @param string $kind     Shown kind.
	 * @param array  $elements Rows.
	 *
	 * @return array
	 */
	private static function overview_document( $id, $title, $builder, $kind, array $elements ) {
		return [
			'id'         => $id,
			'title'      => $title,
			'post_type'  => $builder,
			'template'   => '',
			'builder'    => $builder,
			'status'     => 'publish',
			'count'      => count( $elements ),
			'legacy'     => 0,
			'conditions' => array_values( array_unique( array_merge( ...array_column( $elements, 'conditions' ) ) ) ),
			'elements'   => $elements,
			'area'       => true,
			'kind_label' => $kind,
		];
	}

	// ---- Shared field and assets ----

	/**
	 * Field markup: the rule in words, an "Edit rules" button (rule builder in a dialog) and the value as JSON.
	 *
	 * @param string     $name  Input name.
	 * @param array|null $value Stored value.
	 * @param string     $kind  menu|widget.
	 *
	 * @return void
	 */
	public static function field( $name, $value, $kind ) {
		self::enqueue();

		$summary = self::summary( $value );
		?>
		<div class="stax-vl-area" data-kind="<?php echo esc_attr( $kind ); ?>">
			<p class="stax-vl-area__head">
				<span class="stax-vl-area__title"><?php esc_html_e( 'Visibility', 'visibility-logic-elementor' ); ?></span>
				<span class="stax-vl-area__summary<?php echo $value && $value['enabled'] ? ' is-on' : ''; ?>"><?php echo esc_html( $summary ); ?></span>
			</p>
			<button type="button" class="button stax-vl-area__edit"><?php esc_html_e( 'Edit visibility rules', 'visibility-logic-elementor' ); ?></button>
			<input type="hidden" class="stax-vl-area__value" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ? (string) wp_json_encode( $value ) : '' ); ?>">
		</div>
		<?php
	}

	/**
	 * The rule in words for the field.
	 *
	 * @param array|null $value Stored value.
	 *
	 * @return string
	 */
	public static function summary( $value ) {
		if ( ! $value ) {
			return __( 'Everyone sees it.', 'visibility-logic-elementor' );
		}

		$sentence = Rule_Tree::sentence( $value['rules'] );

		if ( empty( $value['enabled'] ) ) {
			/* translators: %s: the rule in words. */
			return sprintf( __( 'Off (rule kept): %s', 'visibility-logic-elementor' ), $sentence );
		}

		return $sentence;
	}

	/**
	 * Admin screens with menu or widget forms.
	 *
	 * @param string $hook Screen hook.
	 *
	 * @return void
	 */
	public static function admin_assets( $hook ) {
		if ( in_array( $hook, [ 'nav-menus.php', 'widgets.php' ], true ) ) {
			self::enqueue();
		}
	}

	/**
	 * Rule builder, dialog script and styles (once).
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( wp_script_is( 'stax-visibility-area-rules', 'enqueued' ) ) {
			return;
		}

		Rule_Tree::load_package();
		\StaxWP\Conditions\V1\Assets::register();

		wp_enqueue_style( 'stax-conditions' );
		wp_enqueue_style( 'stax-visibility-area-rules', STAX_VISIBILITY_ASSETS_URL . 'css/area-rules.css', [ 'stax-conditions' ], STAX_VISIBILITY_VERSION );
		wp_register_script( 'stax-visibility-rule-search', STAX_VISIBILITY_ASSETS_URL . 'js/rule-search.js', [], STAX_VISIBILITY_VERSION, true );
		wp_enqueue_script( 'stax-visibility-area-rules', STAX_VISIBILITY_ASSETS_URL . 'js/area-rules.js', [ 'stax-conditions', 'stax-visibility-rule-search' ], STAX_VISIBILITY_VERSION, true );
		wp_localize_script(
			'stax-visibility-area-rules',
			'staxVisibilityArea',
			[
				'rules'  => Rest::editor_data(),
				'labels' => [
					'title'    => __( 'Visibility rules', 'visibility-logic-elementor' ),
					'apply'    => __( 'Apply', 'visibility-logic-elementor' ),
					'cancel'   => __( 'Cancel', 'visibility-logic-elementor' ),
					'everyone' => __( 'Everyone sees it.', 'visibility-logic-elementor' ),
					/* translators: %s: the rule in words. */
					'off'      => __( 'Off (rule kept): %s', 'visibility-logic-elementor' ),
					'saveNote' => __( 'Applied to the form. Save the menu or widget to keep it.', 'visibility-logic-elementor' ),
				],
				'builder' => [
					'menu'   => [
						'when'      => __( 'this menu item when', 'visibility-logic-elementor' ),
						'draftHelp' => __( 'Ignores the conditions and keeps them for later. Nobody sees the item or its submenu.', 'visibility-logic-elementor' ),
					],
					'widget' => [
						'when'      => __( 'this widget when', 'visibility-logic-elementor' ),
						'draftHelp' => __( 'Ignores the conditions and keeps them for later. Nobody sees the widget.', 'visibility-logic-elementor' ),
					],
				],
			]
		);
	}
}

Area_Rules::init();
