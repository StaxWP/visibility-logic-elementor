<?php
/**
 * Shared operator library: one set of comparisons used by every condition that compares a value (user meta, ACF,
 * post meta, request values, dynamic tags). A condition exposes the subset that makes sense for it.
 *
 * Rules:
 * - numeric operators (lt, lte, gt, gte, between) only match numeric values (non-numeric = no match, with a reason);
 * - regex errors never throw: no match, with a reason;
 * - list operators (in, not_in) take a comma separated list or an array;
 * - array values: equals / contains / in check the items (any item), not_* check that no item matches.
 *
 * @package StaxWP\Conditions
 */

namespace StaxWP\Conditions\V1;

/**
 * Class Operators
 */
class Operators {

	/**
	 * Operator definitions: key => [ label, arity (0, 1, 2 values), kind (any|text|number|list|date|regex) ].
	 *
	 * Labels are plain English; plugins translate them through Registry options (they pass their own labels).
	 *
	 * @return array
	 */
	public static function definitions() {
		return [
			'empty'          => [ 'is empty', 0, 'any' ],
			'not_empty'      => [ 'is not empty', 0, 'any' ],
			'equals'         => [ 'is', 1, 'text' ],
			'equals_ci'      => [ 'is (ignore case)', 1, 'text' ],
			'not_equals'     => [ 'is not', 1, 'text' ],
			'not_equals_ci'  => [ 'is not (ignore case)', 1, 'text' ],
			'contains'       => [ 'contains', 1, 'text' ],
			'not_contains'   => [ 'does not contain', 1, 'text' ],
			'starts_with'    => [ 'starts with', 1, 'text' ],
			'ends_with'      => [ 'ends with', 1, 'text' ],
			'lt'             => [ 'is less than', 1, 'number' ],
			'lte'            => [ 'is at most', 1, 'number' ],
			'gt'             => [ 'is greater than', 1, 'number' ],
			'gte'            => [ 'is at least', 1, 'number' ],
			'between'        => [ 'is between', 2, 'number' ],
			'in'             => [ 'is one of', 1, 'list' ],
			'not_in'         => [ 'is not one of', 1, 'list' ],
			'array_contains' => [ 'has the item', 1, 'list' ],
			'regex'          => [ 'matches the pattern', 1, 'regex' ],
			'date_before'    => [ 'is a date before', 1, 'date' ],
			'date_after'     => [ 'is a date after', 1, 'date' ],
		];
	}

	/**
	 * Number of values an operator takes (0, 1 or 2), 1 for unknown operators.
	 *
	 * @param string $operator Operator.
	 *
	 * @return int
	 */
	public static function arity( $operator ) {
		$defs = self::definitions();

		return isset( $defs[ $operator ] ) ? (int) $defs[ $operator ][1] : 1;
	}

	/**
	 * Whether the operator exists.
	 *
	 * @param string $operator Operator.
	 *
	 * @return bool
	 */
	public static function exists( $operator ) {
		return array_key_exists( (string) $operator, self::definitions() );
	}

	/**
	 * Compare a value.
	 *
	 * @param string $operator Operator key.
	 * @param mixed  $actual   Value read from the site (meta, field, request).
	 * @param mixed  $value    First value typed in the rule.
	 * @param mixed  $value2   Second value (between).
	 *
	 * @return array{0:bool,1:string} Result and a short reason when the comparison could not run ('' otherwise).
	 */
	public static function compare( $operator, $actual, $value = '', $value2 = '' ) {
		switch ( (string) $operator ) {
			case 'empty':
				return [ self::is_empty( $actual ), '' ];

			case 'not_empty':
				return [ ! self::is_empty( $actual ), '' ];

			case 'equals':
				return [ self::any_item( $actual, static function ( $item ) use ( $value ) {
					return (string) $item === (string) $value;
				} ), '' ];

			case 'equals_ci':
				return [ self::any_item( $actual, static function ( $item ) use ( $value ) {
					return self::lower( $item ) === self::lower( $value );
				} ), '' ];

			case 'not_equals':
				return [ ! self::any_item( $actual, static function ( $item ) use ( $value ) {
					return (string) $item === (string) $value;
				} ), '' ];

			case 'not_equals_ci':
				return [ ! self::any_item( $actual, static function ( $item ) use ( $value ) {
					return self::lower( $item ) === self::lower( $value );
				} ), '' ];

			case 'contains':
				return [ self::any_item( $actual, static function ( $item ) use ( $value ) {
					return '' !== (string) $value && false !== strpos( (string) $item, (string) $value );
				} ), '' ];

			case 'not_contains':
				return [ ! self::any_item( $actual, static function ( $item ) use ( $value ) {
					return '' !== (string) $value && false !== strpos( (string) $item, (string) $value );
				} ), '' ];

			case 'starts_with':
				return [ self::any_item( $actual, static function ( $item ) use ( $value ) {
					return '' !== (string) $value && 0 === strpos( (string) $item, (string) $value );
				} ), '' ];

			case 'ends_with':
				return [ self::any_item( $actual, static function ( $item ) use ( $value ) {
					$item  = (string) $item;
					$value = (string) $value;

					return '' !== $value && strlen( $item ) >= strlen( $value ) && substr( $item, -strlen( $value ) ) === $value;
				} ), '' ];

			case 'lt':
			case 'lte':
			case 'gt':
			case 'gte':
				if ( ! is_scalar( $actual ) || ! is_numeric( $actual ) || ! is_numeric( $value ) ) {
					return [ false, 'not a number' ];
				}

				$a = (float) $actual;
				$b = (float) $value;

				return [ 'lt' === $operator ? $a < $b : ( 'lte' === $operator ? $a <= $b : ( 'gt' === $operator ? $a > $b : $a >= $b ) ), '' ];

			case 'between':
				if ( ! is_scalar( $actual ) || ! is_numeric( $actual ) || ! is_numeric( $value ) || ! is_numeric( $value2 ) ) {
					return [ false, 'not a number' ];
				}

				return [ (float) $actual >= (float) $value && (float) $actual <= (float) $value2, '' ];

			case 'in':
				$list = self::to_list( $value );

				return [ self::any_item( $actual, static function ( $item ) use ( $list ) {
					return in_array( (string) $item, $list, true );
				} ), '' ];

			case 'not_in':
				$list = self::to_list( $value );

				return [ ! self::any_item( $actual, static function ( $item ) use ( $list ) {
					return in_array( (string) $item, $list, true );
				} ), '' ];

			case 'array_contains':
				if ( ! is_array( $actual ) ) {
					return [ false, 'not a list' ];
				}

				return [ (bool) array_intersect( array_map( 'strval', array_filter( $actual, 'is_scalar' ) ), self::to_list( $value ) ), '' ];

			case 'regex':
				return self::regex( $actual, (string) $value );

			case 'date_before':
			case 'date_after':
				$a = is_scalar( $actual ) && '' !== (string) $actual ? strtotime( (string) $actual ) : false;
				$b = '' !== (string) $value ? strtotime( (string) $value ) : false;

				if ( false === $a || false === $b ) {
					return [ false, 'not a date' ];
				}

				return [ 'date_before' === $operator ? $a < $b : $a > $b, '' ];
		}

		return [ false, 'unknown operator' ];
	}

	/**
	 * Empty: null, '', [], false, '0' (PHP empty()) but not 0.0 as a string "0.0".
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	public static function is_empty( $value ) {
		if ( is_array( $value ) ) {
			return 0 === count( array_filter( $value, static function ( $item ) {
				return null !== $item && '' !== $item && [] !== $item;
			} ) );
		}

		return empty( $value );
	}

	/**
	 * Comma separated text or array to a list of trimmed strings.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string[]
	 */
	public static function to_list( $value ) {
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		$items = array_map(
			static function ( $item ) {
				return trim( is_scalar( $item ) ? (string) $item : '' );
			},
			$items
		);

		return array_values( array_filter( $items, 'strlen' ) );
	}

	/**
	 * Whether a callback is true for the value or, for arrays, for any scalar item.
	 *
	 * @param mixed    $actual   Value.
	 * @param callable $callback Test.
	 *
	 * @return bool
	 */
	private static function any_item( $actual, callable $callback ) {
		if ( is_array( $actual ) ) {
			foreach ( $actual as $item ) {
				if ( is_scalar( $item ) && $callback( $item ) ) {
					return true;
				}
			}

			return false;
		}

		if ( null !== $actual && ! is_scalar( $actual ) ) {
			return false;
		}

		return (bool) $callback( null === $actual ? '' : $actual );
	}

	/**
	 * Lower case (multibyte when available).
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private static function lower( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/**
	 * Regex match without warnings: the pattern is used as typed when it has delimiters, else wrapped in `/.../u`.
	 *
	 * @param mixed  $actual  Value.
	 * @param string $pattern Pattern.
	 *
	 * @return array{0:bool,1:string}
	 */
	private static function regex( $actual, $pattern ) {
		if ( '' === $pattern ) {
			return [ false, 'empty pattern' ];
		}

		if ( ! preg_match( '/^([\/#~@%]).*\1[imsxuU]*$/s', $pattern ) ) {
			$pattern = '/' . str_replace( '/', '\/', $pattern ) . '/u';
		}

		$ok = false;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid pattern typed by a user must not print a warning.
		$test = static function ( $item ) use ( $pattern, &$ok ) {
			$result = @preg_match( $pattern, (string) $item );

			if ( false === $result ) {
				return null;
			}

			if ( 1 === $result ) {
				$ok = true;
			}

			return $result;
		};

		foreach ( is_array( $actual ) ? $actual : [ $actual ] as $item ) {
			if ( null !== $item && ! is_scalar( $item ) ) {
				continue;
			}

			if ( null === $test( $item ) ) {
				return [ false, 'invalid pattern' ];
			}
		}

		return [ $ok, '' ];
	}
}
