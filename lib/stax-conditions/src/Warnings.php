<?php
/**
 * Rule warnings shown in the builder and in a rules overview. JS twin: js/src/warnings.js (same codes, same order).
 *
 * Codes: draft, locked, disabled, incomplete, expired, future, never, cache.
 *
 * @package StaxWP\Conditions
 */

namespace StaxWP\Conditions\V1;

/**
 * Class Warnings
 */
class Warnings {

	/**
	 * Warnings of a tree.
	 *
	 * @param array    $tree     Tree.
	 * @param Registry $registry Registry.
	 * @param array    $context  now ('Y-m-d H:i' site time), cache (bool: a page cache was detected).
	 *
	 * @return array[] [ code, id (condition id or ''), type, value (date for expired/future) ].
	 */
	public static function check( array $tree, Registry $registry, array $context = [] ) {
		$out = [];
		$now = isset( $context['now'] ) ? (string) $context['now'] : '';

		if ( ! empty( $tree['draft'] ) ) {
			$out[] = self::item( 'draft' );
		}

		$visitor = false;

		foreach ( Tree::filled_groups( $tree ) as $group ) {
			foreach ( $group['conditions'] as $condition ) {
				$type   = $registry->type( $condition['type'] );
				$values = $condition['values'] + $registry->defaults( $condition['type'] );

				if ( ! $type ) {
					continue;
				}

				if ( ! empty( $type['locked'] ) ) {
					$out[] = self::item( 'locked', $condition );
				} elseif ( ! empty( $type['disabled'] ) ) {
					$out[] = self::item( 'disabled', $condition );
				}

				foreach ( (array) $type['fields'] as $field ) {
					if ( ! empty( $field['required'] ) && Registry::shown( $field, $values, (array) $type['fields'] ) && Operators::is_empty( isset( $values[ $field['key'] ] ) ? $values[ $field['key'] ] : '' ) ) {
						$out[] = self::item( 'incomplete', $condition );
						break;
					}
				}

				if ( '' !== $now ) {
					$end = self::date_of( $type, 'expires', $values );

					if ( '' !== $end && $end < $now ) {
						$out[] = self::item( 'expired', $condition, $end );
					}

					$start = self::date_of( $type, 'starts', $values );

					if ( '' !== $start && $start > $now ) {
						$out[] = self::item( 'future', $condition, $start );
					}
				}

				if ( ! empty( $type['visitor'] ) ) {
					$visitor = true;
				}
			}

			foreach ( self::never( $group, $registry ) as $condition ) {
				$out[] = self::item( 'never', $condition );
			}
		}

		if ( $visitor && ! empty( $context['cache'] ) ) {
			$out[] = self::item( 'cache' );
		}

		return $out;
	}

	/**
	 * Conditions of a group that make it impossible (an exclusive type twice with no common value).
	 *
	 * @param array    $group    Group.
	 * @param Registry $registry Registry.
	 *
	 * @return array[]
	 */
	private static function never( array $group, Registry $registry ) {
		$seen = [];
		$out  = [];

		foreach ( $group['conditions'] as $condition ) {
			$type = $registry->type( $condition['type'] );

			if ( ! $type || empty( $type['exclusive'] ) ) {
				continue;
			}

			$values = $condition['values'] + $registry->defaults( $condition['type'] );
			$field  = $registry->field( $condition['type'], $type['exclusive'] );

			if ( $field && ! Registry::shown( $field, $values, (array) $type['fields'] ) ) {
				continue;
			}

			$set = Operators::to_list( isset( $values[ $type['exclusive'] ] ) ? $values[ $type['exclusive'] ] : [] );

			if ( ! $set ) {
				continue;
			}

			if ( isset( $seen[ $condition['type'] ] ) && ! array_intersect( $seen[ $condition['type'] ], $set ) ) {
				$out[] = $condition;
				continue;
			}

			$seen[ $condition['type'] ] = isset( $seen[ $condition['type'] ] ) ? array_values( array_intersect( $seen[ $condition['type'] ], $set ) ) : $set;
		}

		return $out;
	}

	/**
	 * Date of an expires/starts declaration, normalized to 'Y-m-d H:i', or ''.
	 *
	 * @param array  $type   Type.
	 * @param string $which  expires|starts.
	 * @param array  $values Values.
	 *
	 * @return string
	 */
	private static function date_of( array $type, $which, array $values ) {
		if ( empty( $type[ $which ]['field'] ) ) {
			return '';
		}

		foreach ( isset( $type[ $which ]['when'] ) ? (array) $type[ $which ]['when'] : [] as $key => $allowed ) {
			if ( ! isset( $values[ $key ] ) || ! in_array( (string) $values[ $key ], array_map( 'strval', (array) $allowed ), true ) ) {
				return '';
			}
		}

		$value = isset( $values[ $type[ $which ]['field'] ] ) && is_scalar( $values[ $type[ $which ]['field'] ] ) ? trim( str_replace( 'T', ' ', (string) $values[ $type[ $which ]['field'] ] ) ) : '';

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?/', $value ) ) {
			return '';
		}

		return strlen( $value ) >= 16 ? substr( $value, 0, 16 ) : substr( $value, 0, 10 ) . ' 00:00';
	}

	/**
	 * Warning item.
	 *
	 * @param string     $code      Code.
	 * @param array|null $condition Condition.
	 * @param string     $value     Value.
	 *
	 * @return array
	 */
	private static function item( $code, $condition = null, $value = '' ) {
		return [
			'code'  => $code,
			'id'    => $condition ? $condition['id'] : '',
			'type'  => $condition ? $condition['type'] : '',
			'value' => $value,
		];
	}
}
