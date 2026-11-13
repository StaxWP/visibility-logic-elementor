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
 * textarea, date (Y-m-d H:i), time (HH:mm), list (comma separated, stored as an array). Optional: default,
 * placeholder, help, show_if ( [ 'other_key' => [ values ] ] ), builders ( [ 'blocks', 'atomic' ] ), locked_options
 * (select values listed but disabled: Pro options without Pro, see Pro_Teaser).
 * Condition-level, optional: `locked` (bool, an add-on without a license: the editors show the condition read-only,
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
					$value = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' );
					$value = array_values( array_map( 'strval', $value ) );
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
