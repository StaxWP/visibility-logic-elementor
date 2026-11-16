<?php
/**
 * Builder-agnostic conditions: one field registry and one evaluator for Elementor (classic and atomic elements)
 * and the block editor.
 *
 * Every builder stores the same settings (the `stax_visibility_*` keys of Elementor's classic controls). Classic
 * Elementor elements pass themselves to the evaluator; atomic elements and blocks pass a Condition_Item built from
 * their stored values. Condition modules (free and Pro) only implement the `stax/visibility/apply_conditions`
 * filter and read `$item->get_settings_for_display()`, so they work for every builder.
 *
 * Field registry (`stax/visibility/condition_fields` filter): drives the block editor panel and the Visibility
 * section of atomic elements. Each entry:
 *   'user_role' => [
 *       'label'  => 'User Role',
 *       'switch' => 'user_role_enabled',            // Setting that turns the condition on.
 *       'pro'    => false,
 *       'fields' => [
 *           [ 'key' => 'user_role_conditions', 'type' => 'multiselect', 'label' => 'Roles', 'options' => [...] ],
 *       ],
 *   ]
 * Field keys are setting names without the `stax_visibility_` prefix. Types: switch, select, multiselect, text,
 * textarea, date (Y-m-d H:i), time (HH:mm), list (comma separated, stored as an array), users (user ids), repeater
 * (rows of `fields`, rule builder only). Optional: default,
 * placeholder, help, show_if ( [ 'other_key' => [ values ] ] ), builders ( [ 'blocks', 'atomic' ] ), locked_options
 * (select values listed but disabled: Pro options without Pro, see Pro_Teaser).
 * Condition-level, optional: `builders` (only these builders, e.g. [ 'rules' ]), `locked` (bool, an add-on without a license: the editors show the condition read-only,
 * saved values are kept and still apply) and `locked_notice` ( [ 'title', 'text', 'actions' => [ [ 'label', 'url' ] ] ] ).
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Conditions
 */
class Conditions {

	/**
	 * Registered fields per request.
	 *
	 * @var array|null
	 */
	private static $fields = null;

	/**
	 * Conditions added in code with Conditions::register() (developer API, backlog B19).
	 *
	 * @var array slug => entry (registry keys + `evaluate` callback).
	 */
	private static $custom = [];

	/**
	 * Registry keys a registered condition may set (everything else is ignored).
	 *
	 * @var string[]
	 */
	const REGISTER_KEYS = [ 'label', 'category', 'keywords', 'fields', 'summary', 'short', 'pro', 'visitor', 'exclusive', 'expires', 'starts', 'since', 'builders', 'locked', 'locked_notice' ];

	/**
	 * Hook the conditions added with register() into the registry and the evaluator. Called once by the plugin.
	 *
	 * @return void
	 */
	public static function boot() {
		// Late priority: built-in slugs are known when a registration is checked for a clash.
		add_filter( 'stax/visibility/condition_fields', [ __CLASS__, 'registered_fields' ], 50 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
		add_filter( 'stax/visibility/apply_conditions', [ __CLASS__, 'apply_registered' ], 10, 3 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.
	}

	/**
	 * Add a condition in code (supported developer API since 3.0). It shows in the rule builder (picker, summaries,
	 * Navigator and canvas labels, Rules overview, Preview as, "why hidden"), the block editor and Elementor 4
	 * elements, with no editor code. Rules that use it are stored as rule trees, so Visibility Logic 2.x (which
	 * does not know the condition) fails closed: administrators only.
	 *
	 * Call it on the `stax/visibility/register_conditions` action (or any time before `wp_loaded`).
	 *
	 *   Conditions::register( 'acme_member', [
	 *       'label'    => 'ACME membership',
	 *       'category' => 'who',
	 *       'fields'   => [ [ 'key' => 'acme_member_level', 'type' => 'select', 'label' => 'Level', 'options' => [ 'gold' => 'Gold' ] ] ],
	 *       'summary'  => 'ACME level is {acme_member_level}',
	 *       'evaluate' => function ( array $values, array $context ) { return acme_level() === $values['acme_member_level']; },
	 *   ] );
	 *
	 * `evaluate` receives the condition's field values (switches as bool, lists as arrays) and the context
	 * ( id, name, kind = element|block|atomic ). It returns true (met), false (not met) or null (cannot apply here:
	 * skipped, like a condition of an inactive plugin). An exception counts as "not met". Read the visitor through
	 * Context (user_id(), roles(), timestamp(), device()...), so "Preview as" works for the condition too.
	 *
	 * @param string $slug Lowercase letters, digits and underscores; the switch is `<slug>_enabled`, field keys
	 *                     should start with the slug.
	 * @param array  $args label (required), evaluate (callable, required), fields, category (who|when|device|page|
	 *                     shop|where|other), keywords, summary, short, visitor (depends on the visitor: cache
	 *                     warning), since, builders, pro.
	 *
	 * @return bool False when the slug or arguments are invalid or the slug is taken.
	 */
	public static function register( $slug, array $args ) {
		$slug  = (string) $slug;
		$error = '';

		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $slug ) ) {
			$error = 'The slug must use lowercase letters, digits and underscores.';
		} elseif ( empty( $args['label'] ) || ! isset( $args['evaluate'] ) || ! is_callable( $args['evaluate'] ) ) {
			$error = 'A label and a callable `evaluate` are required.';
		} elseif ( isset( self::$custom[ $slug ] ) || self::is_builtin( $slug, ! empty( $args['pro'] ) ) ) {
			$error = sprintf( 'The condition "%s" already exists.', $slug );
		}

		if ( '' !== $error ) {
			_doing_it_wrong( __METHOD__, esc_html( $error ), '3.0.0' );

			return false;
		}

		$entry = array_intersect_key( $args, array_flip( self::REGISTER_KEYS ) );

		$entry['switch']   = $slug . '_enabled';
		$entry['fields']   = isset( $entry['fields'] ) ? array_values( (array) $entry['fields'] ) : [];
		$entry['since']    = isset( $entry['since'] ) ? (string) $entry['since'] : '3.0.0';
		$entry['custom']   = true;
		$entry['evaluate'] = $args['evaluate'];

		if ( ! $entry['fields'] ) {
			// A condition without settings gets "is true / is not true" (the registry lists conditions with fields).
			$label           = wp_strip_all_tags( (string) $entry['label'] );
			$entry['fields'] = [
				[
					'key'     => $slug . '_is',
					'type'    => 'select',
					'label'   => $label,
					'options' => [
						'yes' => __( 'Is true', 'visibility-logic-elementor' ),
						'no'  => __( 'Is not true', 'visibility-logic-elementor' ),
					],
					'default' => 'yes',
				],
			];
			$entry['negate']  = $slug . '_is';
			$entry['summary'] = isset( $entry['summary'] ) ? $entry['summary'] : [
				'field' => $slug . '_is',
				'cases' => [
					/* translators: %s: condition name. */
					'no' => sprintf( __( 'Not: %s', 'visibility-logic-elementor' ), $label ),
					'*'  => $label,
				],
			];
		}

		self::$custom[ $slug ] = $entry;
		self::reset();

		return true;
	}

	/**
	 * Remove a condition added with register() (tests, add-ons that turn a condition off).
	 *
	 * @param string $slug Slug.
	 *
	 * @return void
	 */
	public static function unregister( $slug ) {
		unset( self::$custom[ (string) $slug ] );
		self::reset();
	}

	/**
	 * Conditions added with register().
	 *
	 * @return array slug => entry.
	 */
	public static function registered() {
		return self::$custom;
	}

	/**
	 * Whether a slug is taken: a condition of this plugin, a reserved setting name, or a condition of Visibility
	 * Logic Pro (2.x list) when the caller is not Pro (`pro` => true).
	 *
	 * @param string $slug Slug.
	 * @param bool   $pro  Registered by Visibility Logic Pro.
	 *
	 * @return bool
	 */
	private static function is_builtin( $slug, $pro = false ) {
		if ( in_array( $slug, [ 'enabled', 'rules', 'show_hide', 'condition_type', 'keep_html', 'fallback', 'page' ], true ) ) {
			return true;
		}

		foreach ( Resources::get_all_widget_options() as $option => $entry ) {
			if ( ( 'post-page' === $option ? 'post' : str_replace( '-', '_', $option ) ) === $slug ) {
				return ! $pro || ( isset( $entry['pro'] ) && false === $entry['pro'] );
			}
		}

		return false;
	}

	/**
	 * `stax/visibility/condition_fields`: add the registered conditions (an existing entry with the same slug wins).
	 *
	 * @param array $conditions Conditions.
	 *
	 * @return array
	 */
	public static function registered_fields( $conditions ) {
		foreach ( self::$custom as $slug => $entry ) {
			if ( ! isset( $conditions[ $slug ] ) ) {
				unset( $entry['evaluate'], $entry['negate'] );
				$conditions[ $slug ] = $entry;
			}
		}

		return $conditions;
	}

	/**
	 * `stax/visibility/apply_conditions`: answer for every registered condition switched on in the settings.
	 *
	 * @param array  $options  Results.
	 * @param array  $settings Settings.
	 * @param object $item     Element.
	 *
	 * @return array
	 */
	public static function apply_registered( $options, $settings, $item ) {
		if ( ! self::$custom ) {
			return $options;
		}

		$settings = is_object( $item ) && method_exists( $item, 'get_settings_for_display' ) ? (array) $item->get_settings_for_display() : (array) $settings;

		foreach ( self::$custom as $slug => $entry ) {
			if ( ! Singleton::flag( $settings, $entry['switch'] ) ) {
				continue;
			}

			$answer = self::run_registered( $entry, self::registered_values( $entry, $settings ), $item );

			if ( null !== $answer && isset( $entry['negate'] ) && 'no' === Singleton::value( $settings, $entry['negate'], 'yes' ) ) {
				$answer = ! $answer;
			}

			if ( null !== $answer ) {
				$options[ $slug ] = $answer;
			}
		}

		return $options;
	}

	/**
	 * Field values of a registered condition, unprefixed (switches as bool).
	 *
	 * @param array $entry    Entry.
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	private static function registered_values( array $entry, array $settings ) {
		$values = [];

		foreach ( $entry['fields'] as $field ) {
			if ( empty( $field['key'] ) || ( isset( $entry['negate'] ) && $entry['negate'] === $field['key'] ) ) {
				continue;
			}

			$value = Singleton::value( $settings, $field['key'], isset( $field['default'] ) ? $field['default'] : '' );

			$values[ $field['key'] ] = 'switch' === ( isset( $field['type'] ) ? $field['type'] : '' ) ? ! empty( $value ) : $value;
		}

		return $values;
	}

	/**
	 * Call the `evaluate` callback of a registered condition.
	 *
	 * @param array  $entry  Entry.
	 * @param array  $values Values.
	 * @param object $item   Element.
	 *
	 * @return bool|null
	 */
	private static function run_registered( array $entry, array $values, $item ) {
		$context = [
			'id'   => is_object( $item ) && method_exists( $item, 'get_id' ) ? (string) $item->get_id() : '',
			'name' => is_object( $item ) && method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
			'kind' => $item instanceof Condition_Item ? $item->get_type() : 'element',
		];

		try {
			$answer = call_user_func( $entry['evaluate'], $values, $context );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Visibility Logic: condition "' . substr( $entry['switch'], 0, -8 ) . '" failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug only.
			}

			return false;
		}

		return null === $answer ? null : (bool) $answer;
	}

	/**
	 * Fields of the General section (shared by every builder).
	 *
	 * @return array
	 */
	public static function general_fields() {
		return [
			[
				'key'   => 'enabled',
				'type'  => 'switch',
				'label' => __( 'Enable Visibility Logic', 'visibility-logic-elementor' ),
			],
			[
				'key'     => 'show_hide',
				'type'    => 'select',
				'label'   => __( 'When the conditions are met', 'visibility-logic-elementor' ),
				'options' => [
					''    => __( 'Hide', 'visibility-logic-elementor' ),
					'yes' => __( 'Show', 'visibility-logic-elementor' ),
				],
				'default' => '',
			],
			[
				'key'     => 'condition_type',
				'type'    => 'select',
				'label'   => __( 'Conditions to meet', 'visibility-logic-elementor' ),
				'options' => [
					'all' => __( 'All', 'visibility-logic-elementor' ),
					'one' => __( 'At least one', 'visibility-logic-elementor' ),
				],
				'default' => 'all',
			],
			[
				'key'      => 'keep_html',
				'type'     => 'switch',
				'label'    => __( 'Keep HTML, hide with CSS', 'visibility-logic-elementor' ),
				'help'     => __( 'The hidden element stays in the page source with display: none.', 'visibility-logic-elementor' ),
				'builders' => [ 'blocks' ],
			],
		];
	}

	/**
	 * Conditions with their fields, filtered by `stax/visibility/condition_fields`.
	 *
	 * @param string $builder Only fields for this builder (blocks|atomic), empty for all.
	 *
	 * @return array
	 */
	public static function fields( $builder = '' ) {
		// Cached once every module had the chance to register (wp_loaded); earlier calls read the filter again.
		$registered = null !== self::$fields ? self::$fields : (array) apply_filters( 'stax/visibility/condition_fields', [] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the `stax/visibility/` namespace of the 2.x hooks.

		if ( null === self::$fields && did_action( 'wp_loaded' ) ) {
			self::$fields = $registered;
		}

		$out = [];

		foreach ( $registered as $slug => $condition ) {
			if ( ! is_array( $condition ) || empty( $condition['switch'] ) || empty( $condition['fields'] ) ) {
				continue;
			}

			// Condition-level `builders`: offered only there (e.g. [ 'rules' ]: the rule builder, repeater fields).
			if ( '' !== $builder && ! empty( $condition['builders'] ) && ! in_array( $builder, (array) $condition['builders'], true ) ) {
				continue;
			}

			$condition['fields'] = self::for_builder( (array) $condition['fields'], $builder );
			$out[ $slug ]        = $condition;
		}

		return $out;
	}

	/**
	 * Keep the fields that apply to a builder.
	 *
	 * @param array  $fields  Fields.
	 * @param string $builder Builder.
	 *
	 * @return array
	 */
	public static function for_builder( array $fields, $builder ) {
		if ( '' === $builder ) {
			return array_values( $fields );
		}

		return array_values(
			array_filter(
				$fields,
				static function ( $field ) use ( $builder ) {
					return empty( $field['builders'] ) || in_array( $builder, (array) $field['builders'], true );
				}
			)
		);
	}

	/**
	 * Lock notice of a locked condition, plain text and http(s) URLs only, or null when the condition is not locked.
	 *
	 * @param array $condition Registered condition.
	 *
	 * @return array|null title, text, actions ( [ label, url ] ).
	 */
	public static function lock_notice( array $condition ) {
		if ( empty( $condition['locked'] ) ) {
			return null;
		}

		$notice  = isset( $condition['locked_notice'] ) && is_array( $condition['locked_notice'] ) ? $condition['locked_notice'] : [];
		$actions = [];

		foreach ( isset( $notice['actions'] ) ? (array) $notice['actions'] : [] as $action ) {
			if ( is_array( $action ) && ! empty( $action['label'] ) && ! empty( $action['url'] ) ) {
				$actions[] = [
					'label' => wp_strip_all_tags( (string) $action['label'] ),
					'url'   => esc_url_raw( (string) $action['url'], [ 'http', 'https' ] ),
				];
			}
		}

		return [
			'title'   => wp_strip_all_tags( isset( $notice['title'] ) ? (string) $notice['title'] : __( 'This condition is locked.', 'visibility-logic-elementor' ) ),
			'text'    => wp_strip_all_tags( isset( $notice['text'] ) ? (string) $notice['text'] : '' ),
			'actions' => $actions,
		];
	}

	/**
	 * Every field (general, switches, condition fields), flat.
	 *
	 * @param string $builder Builder.
	 *
	 * @return array[]
	 */
	public static function all_fields( $builder = '' ) {
		$fields = self::for_builder( self::general_fields(), $builder );

		foreach ( self::fields( $builder ) as $condition ) {
			$fields[] = [
				'key'   => $condition['switch'],
				'type'  => 'switch',
				'label' => $condition['label'],
			];

			foreach ( $condition['fields'] as $field ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Forget the registry (tests, after modules load late).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$fields = null;

		if ( class_exists( __NAMESPACE__ . '\\Rule_Tree' ) ) {
			Rule_Tree::reset();
		}
	}

	/**
	 * Saved values of a block or atomic element (unprefixed keys, editor types) to Elementor-style settings.
	 *
	 * Switches become 'yes' or '', lists become arrays, dates use a space instead of the HTML "T", defaults fill
	 * missing keys (like Elementor does for classic controls).
	 *
	 * @param array  $values  Values.
	 * @param string $builder Builder (fields filter).
	 *
	 * @return array
	 */
	public static function to_settings( array $values, $builder = '' ) {
		$settings = [];

		foreach ( self::all_fields( $builder ) as $field ) {
			$key   = $field['key'];
			$value = array_key_exists( $key, $values ) ? $values[ $key ] : ( isset( $field['default'] ) ? $field['default'] : null );

			switch ( $field['type'] ) {
				case 'switch':
					$value = ( true === $value || 'yes' === $value || 1 === $value || '1' === $value ) ? 'yes' : '';
					break;
				case 'multiselect':
					$value = is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : ( null === $value || '' === $value ? [] : [ (string) $value ] );
					break;
				case 'list':
				case 'users':
				case 'posts':
					$value = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' );
					$value = array_values( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
					break;
				case 'repeater':
					$value = is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : [];
					break;
				case 'date':
					$value = null === $value ? '' : str_replace( 'T', ' ', (string) $value );
					break;
				default:
					$value = null === $value ? '' : ( is_scalar( $value ) ? (string) $value : '' );
			}

			$settings[ Singleton::SECTION_PREFIX . $key ] = $value;
		}

		// Values of conditions that did not register fields (yet): passed through, so add-on conditions evaluate
		// with the settings they read. `*_enabled` keys become 'yes' or ''.
		foreach ( $values as $key => $value ) {
			$name = Singleton::SECTION_PREFIX . $key;

			if ( ! is_string( $key ) || '' === $key || array_key_exists( $name, $settings ) ) {
				continue;
			}

			if ( '_enabled' === substr( $key, -8 ) ) {
				$value = ( true === $value || 'yes' === $value || 1 === $value || '1' === $value ) ? 'yes' : '';
			}

			$settings[ $name ] = $value;
		}

		// Switches of every known condition (Pro ones too), like Elementor's control defaults: condition modules
		// read them without isset().
		foreach ( array_keys( Resources::get_all_widget_options() ) as $slug ) {
			$switch = Singleton::SECTION_PREFIX . ( 'post-page' === $slug ? 'post' : str_replace( '-', '_', $slug ) ) . '_enabled';

			if ( ! isset( $settings[ $switch ] ) ) {
				$settings[ $switch ] = array_key_exists( substr( $switch, strlen( Singleton::SECTION_PREFIX ) ), $values ) ? ( empty( $values[ substr( $switch, strlen( Singleton::SECTION_PREFIX ) ) ] ) ? '' : 'yes' ) : '';
			}
		}

		return $settings;
	}

	/**
	 * Atomic (Elementor 4) props to plain values: `{ $$type, value }` wrappers removed, recursively.
	 *
	 * @param mixed $value Prop value.
	 *
	 * @return mixed
	 */
	public static function unwrap( $value ) {
		if ( is_array( $value ) && array_key_exists( '$$type', $value ) && array_key_exists( 'value', $value ) ) {
			return self::unwrap( $value['value'] );
		}

		if ( is_array( $value ) ) {
			return array_map( [ __CLASS__, 'unwrap' ], $value );
		}

		return $value;
	}

	/**
	 * Evaluate the rules of a settings array (Elementor-style keys) for the current visitor.
	 *
	 * Same logic as 2.x: settings saved by 1.x (`ecl_*`) when "Enable Visibility Logic" is off, otherwise every
	 * `stax/visibility/apply_conditions` result combined with All / At least one, then Show / Hide.
	 *
	 * @param array  $settings       Settings.
	 * @param object $item           Element (Elementor element or Condition_Item).
	 * @param bool   $default_render Answer when no rule applies.
	 *
	 * @return bool
	 */
	public static function evaluate( array $settings, $item, $default_render = true ) {
		if ( ! Singleton::flag( $settings, 'enabled' ) ) {
			if ( empty( $settings['ecl_enabled'] ) ) {
				return $default_render;
			}

			return self::version_fallback_render( $settings, $default_render );
		}

		// Rule built in the 3.0 rule builder and stored as a tree (groups, draft, 3.0 options).
		$tree = Rule_Tree::stored( $settings );

		if ( $tree ) {
			$record = Rule_Tree::recording();
			$result = Rule_Tree::evaluate( $tree, $item, $default_render, $record );

			if ( $record ) {
				Rule_Tree::add_trace( is_object( $item ) && method_exists( $item, 'get_id' ) ? $item->get_id() : '', is_object( $item ) && method_exists( $item, 'get_name' ) ? $item->get_name() : '', $tree, $result );
			}

			return $result['visible'];
		}

		if ( Rule_Tree::recording() ) {
			// Same answer as below (the equivalence suite checks it), with the details for "why hidden".
			$legacy = Rule_Tree::legacy_to_tree( $settings );
			$result = Rule_Tree::evaluate( $legacy, $item, $default_render, true );
			Rule_Tree::add_trace( is_object( $item ) && method_exists( $item, 'get_id' ) ? $item->get_id() : '', is_object( $item ) && method_exists( $item, 'get_name' ) ? $item->get_name() : '', $legacy, $result );
		}

		$options = apply_filters( 'stax/visibility/apply_conditions', [], $settings, $item ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.

		if ( empty( $options ) ) {
			return $default_render;
		}

		$should_render  = false;
		$condition_type = Singleton::value( $settings, 'condition_type', 'all' );

		if ( 'all' === $condition_type ) {
			$should_render = ! in_array( false, array_map( 'boolval', $options ), true );
		} elseif ( 'one' === $condition_type ) {
			$should_render = in_array( true, $options, true );
		}

		if ( Singleton::flag( $settings, 'show_hide' ) ) {
			return $should_render;
		}

		return ! $should_render;
	}

	/**
	 * Settings saved by version 1.x (`ecl_*` keys).
	 *
	 * @param array $settings       Settings.
	 * @param bool  $default_render Default answer.
	 *
	 * @return bool
	 */
	public static function version_fallback_render( $settings, $default_render ) {
		if ( ! isset( $settings['ecl_enabled'] ) ) {
			return $default_render;
		}

		$user_state = is_user_logged_in();

		if ( 'yes' === $settings['ecl_enabled'] ) {

			if ( ! empty( $settings['ecl_role_visible'] ) ) {
				$visible = (array) $settings['ecl_role_visible'];

				if ( in_array( 'ecl-guest', $visible, true ) ) {
					if ( true === $user_state ) {
						return false;
					}
				} elseif ( in_array( 'ecl-user', $visible, true ) ) {
					if ( false === $user_state ) {
						return false;
					}
				} else {
					if ( false === $user_state ) {
						return false;
					}

					if ( ! array_intersect( $visible, (array) wp_get_current_user()->roles ) ) {
						return false;
					}
				}
			} elseif ( ! empty( $settings['ecl_role_hidden'] ) ) {
				$hidden = (array) $settings['ecl_role_hidden'];

				if ( false === $user_state && in_array( 'ecl-guest', $hidden, true ) ) {
					return false;
				} elseif ( true === $user_state && in_array( 'ecl-user', $hidden, true ) ) {
					return false;
				} else {
					if ( false === $user_state ) {
						return true;
					}

					if ( array_intersect( $hidden, (array) wp_get_current_user()->roles ) ) {
						return false;
					}
				}
			}
		}

		return $default_render;
	}

	/**
	 * Options as [ [ value, label ] ] (editor UIs). Locked values (Pro options without Pro, see Pro_Teaser) get
	 * `disabled`.
	 *
	 * @param array $options Value => label.
	 * @param array $locked  Locked values.
	 *
	 * @return array[]
	 */
	public static function option_list( array $options, array $locked = [] ) {
		$list   = [];
		$locked = array_map( 'strval', $locked );

		foreach ( $options as $value => $label ) {
			$item = [
				'value' => (string) $value,
				'label' => wp_strip_all_tags( (string) $label ),
			];

			if ( in_array( (string) $value, $locked, true ) ) {
				$item['disabled'] = true;
			}

			$list[] = $item;
		}

		return $list;
	}
}
