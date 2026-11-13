<?php
/**
 * Condition registry: the condition types a plugin offers in the rule builder, their fields, summary templates,
 * categories and presets. One registry drives the builder UI (exported to JS), the summaries and the warnings.
 *
 * Type entry (all keys optional except label and fields):
 *   'user_role' => [
 *       'label'    => 'User role',
 *       'category' => 'who',                  // Categories::* slug, 'other' when missing
 *       'keywords' => [ 'member', 'login' ],  // picker search
 *       'fields'   => [ field, ... ],         // see below
 *       'summary'  => 'User role is {user_role_conditions|or}',   // template or [ 'field' => key, 'cases' => [ value => template, '*' => template ] ]
 *       'short'    => '{user_role_conditions}',                  // canvas / Navigator label, same format
 *       'pro'      => false,                  // shown with a small Pro mark
 *       'locked'   => false,                  // Pro without license / add-on missing: listed, cannot be added
 *       'disabled' => false,                  // turned off in the plugin settings: listed with a warning
 *       'visitor'  => true,                   // depends on the visitor (cache warning)
 *       'exclusive'=> 'user_role_conditions', // two of these in one group with no common value can never match
 *       'expires'  => [ 'field' => 'date_to', 'when' => [ 'date_time_type' => [ 'date' ] ] ],
 *       'starts'   => [ 'field' => 'date_from', 'when' => [ 'date_time_type' => [ 'date' ] ] ],
 *       'tree'     => true,                   // false: cannot be stored in a tree (only in a plugin's legacy format)
 *   ]
 *
 * Field: key, type (switch|select|multiselect|text|textarea|number|date|time|list|users|posts|repeater; users and posts are ids picked through a search callback), label, options
 * ( value => label ), locked_options ( values listed but disabled ), default, placeholder, help, show_if
 * ( [ other_key => [ values ] ] ), required, since (version that added the field), options_since ( value => version ),
 * fields (repeater rows), summary_options ( value => wording used in summaries ).
 *
 * @package StaxWP\Conditions
 */

namespace StaxWP\Conditions\V1;

/**
 * Class Registry
 */
class Registry {

	/**
	 * Types.
	 *
	 * @var array
	 */
	private $types = [];

	/**
	 * Categories ( slug => label ).
	 *
	 * @var array
	 */
	private $categories = [];

	/**
	 * Presets: slug => [ label, description, groups (tree groups), action ].
	 *
	 * @var array
	 */
	private $presets = [];

	/**
	 * Constructor.
	 *
	 * @param array $types      Types.
	 * @param array $categories Categories.
	 * @param array $presets    Presets.
	 */
	public function __construct( array $types = [], array $categories = [], array $presets = [] ) {
		foreach ( $types as $slug => $type ) {
			$this->add_type( $slug, $type );
		}

		$this->categories = $categories ? $categories : self::default_categories();
		$this->presets    = $presets;
	}

	/**
	 * Task-based categories (labels in English; plugins pass translated ones).
	 *
	 * @return array
	 */
	public static function default_categories() {
		return [
			'who'    => 'Who',
			'when'   => 'When',
			'device' => 'Device and browser',
			'page'   => 'Page and content',
			'shop'   => 'Shop',
			'where'  => 'Where from',
			'other'  => 'Other',
		];
	}

	/**
	 * Add or replace a type.
	 *
	 * @param string $slug Slug.
	 * @param array  $type Type.
	 *
	 * @return void
	 */
	public function add_type( $slug, array $type ) {
		$type += [
			'label'    => $slug,
			'category' => 'other',
			'keywords' => [],
			'fields'   => [],
			'summary'  => '',
			'short'    => '',
			'pro'      => false,
			'locked'   => false,
			'disabled' => false,
			'visitor'  => false,
			'tree'     => true,
		];

		$this->types[ (string) $slug ] = $type;
	}

	/**
	 * Type or null.
	 *
	 * @param string $slug Slug.
	 *
	 * @return array|null
	 */
	public function type( $slug ) {
		return isset( $this->types[ $slug ] ) ? $this->types[ $slug ] : null;
	}

	/**
	 * All types.
	 *
	 * @return array
	 */
	public function types() {
		return $this->types;
	}

	/**
	 * Categories.
	 *
	 * @return array
	 */
	public function categories() {
		return $this->categories;
	}

	/**
	 * Presets.
	 *
	 * @return array
	 */
	public function presets() {
		return $this->presets;
	}

	/**
	 * Field of a type, or null.
	 *
	 * @param string $type Type slug.
	 * @param string $key  Field key.
	 *
	 * @return array|null
	 */
	public function field( $type, $key ) {
		$entry = $this->type( $type );

		if ( ! $entry ) {
			return null;
		}

		foreach ( (array) $entry['fields'] as $field ) {
			if ( isset( $field['key'] ) && $field['key'] === $key ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Default values of a type's fields.
	 *
	 * @param string $type Type slug.
	 *
	 * @return array
	 */
	public function defaults( $type ) {
		$entry  = $this->type( $type );
		$values = [];

		foreach ( $entry ? (array) $entry['fields'] : [] as $field ) {
			if ( isset( $field['key'] ) ) {
				$values[ $field['key'] ] = self::field_default( $field );
			}
		}

		return $values;
	}

	/**
	 * Default of one field.
	 *
	 * @param array $field Field.
	 *
	 * @return mixed
	 */
	public static function field_default( array $field ) {
		if ( array_key_exists( 'default', $field ) ) {
			return $field['default'];
		}

		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		if ( 'switch' === $type ) {
			return false;
		}

		return in_array( $type, [ 'multiselect', 'list', 'users', 'posts', 'repeater' ], true ) ? [] : '';
	}

	/**
	 * Whether a field is shown for these values (show_if).
	 *
	 * @param array $field  Field.
	 * @param array $values Values of the condition.
	 * @param array $fields All fields of the type (defaults of missing keys).
	 *
	 * @return bool
	 */
	public static function shown( array $field, array $values, array $fields ) {
		if ( empty( $field['show_if'] ) ) {
			return true;
		}

		foreach ( (array) $field['show_if'] as $key => $allowed ) {
			if ( array_key_exists( $key, $values ) ) {
				$current = $values[ $key ];
			} else {
				$current = '';

				foreach ( $fields as $other ) {
					if ( isset( $other['key'] ) && $other['key'] === $key ) {
						$current = self::field_default( $other );
					}
				}
			}

			if ( is_bool( $current ) ) {
				$current = $current ? 'yes' : '';
			}

			if ( ! in_array( is_scalar( $current ) ? (string) $current : '', array_map( 'strval', (array) $allowed ), true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Registry for the JS builder: options as ordered lists, locked options disabled.
	 *
	 * @return array{types:array,categories:array,presets:array}
	 */
	public function to_array() {
		$types = [];

		foreach ( $this->types as $slug => $type ) {
			$type['slug']   = $slug;
			$type['fields'] = self::fields_for_js( (array) $type['fields'] );
			$types[]        = $type;
		}

		$categories = [];

		foreach ( $this->categories as $slug => $label ) {
			$categories[] = [
				'slug'  => (string) $slug,
				'label' => (string) $label,
			];
		}

		$presets = [];

		foreach ( $this->presets as $slug => $preset ) {
			$preset['slug'] = (string) $slug;
			$presets[]      = $preset;
		}

		return [
			'types'      => $types,
			'categories' => $categories,
			'presets'    => $presets,
		];
	}

	/**
	 * Fields with options as [ { value, label, disabled } ].
	 *
	 * @param array $fields Fields.
	 *
	 * @return array
	 */
	private static function fields_for_js( array $fields ) {
		$out = [];

		foreach ( $fields as $field ) {
			if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
				$locked = isset( $field['locked_options'] ) ? array_map( 'strval', (array) $field['locked_options'] ) : [];
				$list   = [];

				foreach ( $field['options'] as $value => $label ) {
					$item = [
						'value' => (string) $value,
						'label' => (string) $label,
					];

					if ( in_array( (string) $value, $locked, true ) ) {
						$item['disabled'] = true;
					}

					$list[] = $item;
				}

				$field['options'] = $list;
			}

			unset( $field['locked_options'] );

			if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$field['fields'] = self::fields_for_js( $field['fields'] );
			}

			$out[] = $field;
		}

		return $out;
	}
}
