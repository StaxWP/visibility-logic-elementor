<?php
/**
 * Rule tree: the stored form of a rule built in the rule builder.
 *
 *   [
 *     'v'      => 1,
 *     'action' => 'show' | 'hide',   // what happens when the rule matches
 *     'draft'  => false,             // true: never shown to visitors (Hidden from everyone)
 *     'groups' => [                  // OR between groups
 *       [ 'conditions' => [          // AND inside a group
 *           [ 'id' => 'c1', 'type' => 'user_role', 'values' => [ 'user_role_conditions' => [ 'subscriber' ] ] ],
 *       ] ],
 *     ],
 *   ]
 *
 * `values` holds the condition's own settings (the field keys of its Registry entry). Plugins keep extra keys of
 * their own next to the tree, never inside it.
 *
 * @package StaxWP\Conditions
 */

namespace StaxWP\Conditions\V1;

/**
 * Class Tree
 */
class Tree {

	/**
	 * Format version.
	 */
	const VERSION = 1;

	/**
	 * Canonical tree from stored data (array or JSON), or null when the data is not a tree.
	 *
	 * Unknown keys are dropped, empty groups are kept (the builder shows them), conditions without a type are dropped.
	 *
	 * @param mixed $data Stored data.
	 *
	 * @return array|null
	 */
	public static function normalize( $data ) {
		if ( is_string( $data ) && '' !== $data ) {
			$data = json_decode( $data, true );
		}

		if ( ! is_array( $data ) || ! isset( $data['groups'] ) || ! is_array( $data['groups'] ) ) {
			return null;
		}

		$tree = [
			'v'      => self::VERSION,
			'action' => isset( $data['action'] ) && 'hide' === $data['action'] ? 'hide' : 'show',
			'draft'  => ! empty( $data['draft'] ),
			'groups' => [],
		];

		foreach ( array_values( $data['groups'] ) as $g => $group ) {
			$conditions = is_array( $group ) && isset( $group['conditions'] ) && is_array( $group['conditions'] ) ? $group['conditions'] : [];
			$out        = [];

			foreach ( array_values( $conditions ) as $c => $condition ) {
				if ( ! is_array( $condition ) || empty( $condition['type'] ) || ! is_string( $condition['type'] ) ) {
					continue;
				}

				$out[] = [
					'id'     => isset( $condition['id'] ) && is_scalar( $condition['id'] ) && '' !== (string) $condition['id'] ? (string) $condition['id'] : 'c' . $g . '_' . $c,
					'type'   => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $condition['type'] ) ),
					'values' => isset( $condition['values'] ) && is_array( $condition['values'] ) ? $condition['values'] : [],
				];
			}

			$tree['groups'][] = [ 'conditions' => $out ];
		}

		return $tree;
	}

	/**
	 * Groups that have at least one condition.
	 *
	 * @param array $tree Tree.
	 *
	 * @return array[]
	 */
	public static function filled_groups( array $tree ) {
		return array_values(
			array_filter(
				isset( $tree['groups'] ) ? (array) $tree['groups'] : [],
				static function ( $group ) {
					return ! empty( $group['conditions'] );
				}
			)
		);
	}

	/**
	 * Every condition, flat, in order.
	 *
	 * @param array $tree Tree.
	 *
	 * @return array[]
	 */
	public static function conditions( array $tree ) {
		$all = [];

		foreach ( self::filled_groups( $tree ) as $group ) {
			foreach ( $group['conditions'] as $condition ) {
				$all[] = $condition;
			}
		}

		return $all;
	}

	/**
	 * Whether the tree has no condition and is not a draft.
	 *
	 * @param array $tree Tree.
	 *
	 * @return bool
	 */
	public static function is_empty( array $tree ) {
		return empty( $tree['draft'] ) && 0 === count( self::conditions( $tree ) );
	}

	/**
	 * Empty tree.
	 *
	 * @param string $action show|hide.
	 *
	 * @return array
	 */
	public static function make( $action = 'show' ) {
		return [
			'v'      => self::VERSION,
			'action' => 'hide' === $action ? 'hide' : 'show',
			'draft'  => false,
			'groups' => [ [ 'conditions' => [] ] ],
		];
	}
}
