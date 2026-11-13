<?php
/**
 * Plain-language summaries of rules, from the registry's templates. The JS twin (js/src/summary.js) implements the
 * same template language; tests/fixtures/summary.json is checked by both test suites.
 *
 * Template language:
 *   {key}        value of the field, formatted (option labels; lists joined with ", "; long lists "A, B, C and 2 more")
 *   {key|or}     lists joined with " or "
 *   {key|lower}  first letter lower case
 *   [ ... ]      optional part, dropped when a placeholder inside is empty
 *   Cases: [ 'field' => 'date_time_type', 'cases' => [ 'date' => '...', '*' => '...' ] ]
 *
 * @package StaxWP\Conditions
 */

namespace StaxWP\Conditions\V1;

/**
 * Class Summary
 */
class Summary {

	/**
	 * Default wording (plugins pass translated strings with the same keys).
	 *
	 * @return array
	 */
	public static function default_labels() {
		return [
			'show'       => 'Shown only when %s',
			'hide'       => 'Hidden when %s',
			'and'        => ' and ',
			'or'         => ' or ',
			'draft'      => 'Hidden on the live site (draft)',
			'none'       => 'No conditions yet',
			'more'       => '%1$s and %2$d more',
			'yes'        => 'yes',
			'no'         => 'no',
			'short_show' => '%s',
			'short_hide' => 'Hidden: %s',
			'short_more' => '%1$s +%2$d',
			'users'      => '%d users',
			'rows'       => '%d rules',
		];
	}

	/**
	 * Full sentence of a tree.
	 *
	 * @param array    $tree       Tree.
	 * @param Registry $registry   Registry.
	 * @param array    $labels     Wording.
	 * @param array    $formatters type => callable( $value, $field ): string (e.g. user names).
	 *
	 * @return string
	 */
	public static function sentence( array $tree, Registry $registry, array $labels = [], array $formatters = [] ) {
		$labels = $labels + self::default_labels();

		if ( ! empty( $tree['draft'] ) ) {
			return $labels['draft'];
		}

		$groups = Tree::filled_groups( $tree );

		if ( ! $groups ) {
			return $labels['none'];
		}

		$parts = [];

		foreach ( $groups as $group ) {
			$texts = [];

			foreach ( $group['conditions'] as $condition ) {
				$texts[] = self::condition( $condition, $registry, $labels, $formatters );
			}

			$text    = implode( $labels['and'], $texts );
			$parts[] = count( $groups ) > 1 && count( $texts ) > 1 ? '(' . $text . ')' : $text;
		}

		return sprintf( 'hide' === $tree['action'] ? $labels['hide'] : $labels['show'], implode( $labels['or'], $parts ) );
	}

	/**
	 * Short label (canvas, Navigator, List View).
	 *
	 * @param array    $tree       Tree.
	 * @param Registry $registry   Registry.
	 * @param array    $labels     Wording.
	 * @param array    $formatters Formatters.
	 *
	 * @return string
	 */
	public static function short( array $tree, Registry $registry, array $labels = [], array $formatters = [] ) {
		$labels = $labels + self::default_labels();

		if ( ! empty( $tree['draft'] ) ) {
			return $labels['draft'];
		}

		$all = Tree::conditions( $tree );

		if ( ! $all ) {
			return $labels['none'];
		}

		$type  = $registry->type( $all[0]['type'] );
		$first = self::render( $type && $type['short'] ? $type['short'] : ( $type ? $type['summary'] : '' ), $all[0], $registry, $labels, $formatters );

		if ( '' === $first ) {
			$first = $type ? (string) $type['label'] : $all[0]['type'];
		}

		if ( count( $all ) > 1 ) {
			$first = sprintf( $labels['short_more'], $first, count( $all ) - 1 );
		}

		return sprintf( 'hide' === $tree['action'] ? $labels['short_hide'] : $labels['short_show'], $first );
	}

	/**
	 * Sentence of one condition.
	 *
	 * @param array    $condition  Condition.
	 * @param Registry $registry   Registry.
	 * @param array    $labels     Wording.
	 * @param array    $formatters Formatters.
	 *
	 * @return string
	 */
	public static function condition( array $condition, Registry $registry, array $labels = [], array $formatters = [] ) {
		$labels = $labels + self::default_labels();
		$type   = $registry->type( $condition['type'] );

		if ( ! $type ) {
			return ucfirst( str_replace( '_', ' ', $condition['type'] ) );
		}

		$text = self::render( $type['summary'], $condition, $registry, $labels, $formatters );

		return '' !== $text ? $text : (string) $type['label'];
	}

	/**
	 * Render a template (or cases) for a condition.
	 *
	 * @param string|array $template   Template.
	 * @param array        $condition  Condition.
	 * @param Registry     $registry   Registry.
	 * @param array        $labels     Wording.
	 * @param array        $formatters Formatters.
	 *
	 * @return string
	 */
	public static function render( $template, array $condition, Registry $registry, array $labels, array $formatters = [] ) {
		$values = isset( $condition['values'] ) && is_array( $condition['values'] ) ? $condition['values'] + $registry->defaults( $condition['type'] ) : $registry->defaults( $condition['type'] );

		if ( is_array( $template ) ) {
			$key   = isset( $template['field'] ) ? (string) $template['field'] : '';
			$cases = isset( $template['cases'] ) ? (array) $template['cases'] : [];
			$value = isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ? (string) $values[ $key ] : '';

			$template = isset( $cases[ $value ] ) ? $cases[ $value ] : ( isset( $cases['*'] ) ? $cases['*'] : '' );
		}

		$template = (string) $template;

		// Optional parts first: [ ... ] dropped when a placeholder inside is empty.
		$template = preg_replace_callback(
			'/\[([^\[\]]*)\]/',
			static function ( $m ) use ( $condition, $registry, $values, $labels, $formatters ) {
				$empty = false;
				$text  = self::placeholders( $m[1], $condition['type'], $registry, $values, $labels, $formatters, $empty );

				return $empty ? '' : $text;
			},
			$template
		);

		$empty = false;

		return trim( preg_replace( '/\s+/', ' ', self::placeholders( $template, $condition['type'], $registry, $values, $labels, $formatters, $empty ) ) );
	}

	/**
	 * Replace {key} placeholders.
	 *
	 * @param string   $text       Text.
	 * @param string   $type       Type slug.
	 * @param Registry $registry   Registry.
	 * @param array    $values     Values.
	 * @param array    $labels     Wording.
	 * @param array    $formatters Formatters.
	 * @param bool     $empty      Set to true when a placeholder is empty.
	 *
	 * @return string
	 */
	private static function placeholders( $text, $type, Registry $registry, array $values, array $labels, array $formatters, &$empty ) {
		return preg_replace_callback(
			'/\{([a-z0-9_]+)(?:\|([a-z]+))?\}/',
			static function ( $m ) use ( $type, $registry, $values, $labels, $formatters, &$empty ) {
				$field = $registry->field( $type, $m[1] );
				$value = array_key_exists( $m[1], $values ) ? $values[ $m[1] ] : null;
				$text  = self::format( $value, $field ? $field : [ 'type' => 'text' ], $labels, $formatters, isset( $m[2] ) ? $m[2] : '' );

				if ( '' === $text ) {
					$empty = true;
				}

				return $text;
			},
			$text
		);
	}

	/**
	 * Display text of a value.
	 *
	 * @param mixed  $value      Value.
	 * @param array  $field      Field.
	 * @param array  $labels     Wording.
	 * @param array  $formatters Formatters.
	 * @param string $modifier   or|lower|''.
	 *
	 * @return string
	 */
	public static function format( $value, array $field, array $labels, array $formatters = [], $modifier = '' ) {
		$labels = $labels + self::default_labels();
		$type   = isset( $field['type'] ) ? $field['type'] : 'text';

		if ( isset( $formatters[ $type ] ) && is_callable( $formatters[ $type ] ) ) {
			$text = (string) call_user_func( $formatters[ $type ], $value, $field );

			return 'lower' === $modifier ? self::lcfirst( $text ) : $text;
		}

		switch ( $type ) {
			case 'switch':
				$text = ( true === $value || 'yes' === $value || 1 === $value || '1' === $value ) ? $labels['yes'] : '';
				break;

			case 'users':
				$count = is_array( $value ) ? count( $value ) : 0;
				$text  = $count ? sprintf( $labels['users'], $count ) : '';
				break;

			case 'repeater':
				$count = is_array( $value ) ? count( $value ) : 0;
				$text  = $count ? sprintf( $labels['rows'], $count ) : '';
				break;

			case 'multiselect':
			case 'list':
				$items = is_array( $value ) ? $value : Operators::to_list( $value );
				$items = array_map(
					static function ( $item ) use ( $field ) {
						return self::option_label( $item, $field );
					},
					array_values( array_filter( array_map( 'strval', array_filter( $items, 'is_scalar' ) ), 'strlen' ) )
				);
				$text  = self::join( $items, 'or' === $modifier ? $labels['or'] : ', ', $labels );
				break;

			case 'select':
				$text = is_scalar( $value ) && '' !== (string) $value ? self::option_label( (string) $value, $field ) : '';
				break;

			default:
				$text = is_scalar( $value ) ? trim( str_replace( 'T', ' ', (string) $value ) ) : '';

				if ( 'date' !== $type ) {
					$text = is_scalar( $value ) ? trim( (string) $value ) : '';
				}
		}

		return 'lower' === $modifier ? self::lcfirst( $text ) : $text;
	}

	/**
	 * Label of an option (summary_options wording first).
	 *
	 * @param string $value Value.
	 * @param array  $field Field.
	 *
	 * @return string
	 */
	private static function option_label( $value, array $field ) {
		if ( isset( $field['summary_options'][ $value ] ) ) {
			return (string) $field['summary_options'][ $value ];
		}

		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			foreach ( $field['options'] as $key => $label ) {
				if ( is_array( $label ) && isset( $label['value'] ) && (string) $label['value'] === $value ) {
					return (string) $label['label'];
				}

				if ( ! is_array( $label ) && (string) $key === $value ) {
					return (string) $label;
				}
			}
		}

		return $value;
	}

	/**
	 * Join a list, long lists shortened.
	 *
	 * @param string[] $items  Items.
	 * @param string   $glue   Glue.
	 * @param array    $labels Wording.
	 *
	 * @return string
	 */
	private static function join( array $items, $glue, array $labels ) {
		if ( count( $items ) > 3 ) {
			return sprintf( $labels['more'], implode( ', ', array_slice( $items, 0, 3 ) ), count( $items ) - 3 );
		}

		return implode( $glue, $items );
	}

	/**
	 * First letter lower case unless the word is an acronym.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	private static function lcfirst( $text ) {
		if ( strlen( $text ) > 1 && ctype_upper( substr( $text, 1, 1 ) ) ) {
			return $text;
		}

		return lcfirst( $text );
	}
}
