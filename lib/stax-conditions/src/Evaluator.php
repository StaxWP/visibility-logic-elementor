<?php
/**
 * Rule evaluation with an optional trace ("why is this hidden?").
 *
 * Semantics:
 * - a condition answers true, false or null (null = cannot apply here, e.g. its plugin is not active: skipped);
 * - a group passes when every condition that answered is true and at least one answered; a group where nothing
 *   answered is skipped;
 * - the rule matches when one group that was not skipped passes; when every group is skipped (or there is no
 *   condition) the rule does not apply and the default answer is used;
 * - action show: visible when it matches; action hide: hidden when it matches;
 * - draft: never visible (the plugin decides who may still see it, e.g. "Preview as me").
 *
 * These are the 2.x semantics of "All" (one group) and "At least one" (one condition per group), so a 2.x rule
 * mapped to a tree gives the same answer.
 *
 * @package StaxWP\Conditions
 */

namespace StaxWP\Conditions\V1;

/**
 * Class Evaluator
 */
class Evaluator {

	/**
	 * Evaluate a tree.
	 *
	 * @param array    $tree    Normalized tree (Tree::normalize()).
	 * @param callable $check   function( array $condition ): bool|null|array{result:bool|null,reason?:string}.
	 * @param bool     $default Answer when the rule does not apply.
	 * @param bool     $trace   Evaluate every condition and return the details (slower; for previews and debugging).
	 *
	 * @return array{visible:bool,matched:bool|null,reason:string,groups:array}
	 */
	public static function evaluate( array $tree, callable $check, $default = true, $trace = false ) {
		$result = [
			'visible' => (bool) $default,
			'matched' => null,
			'reason'  => 'no_conditions',
			'groups'  => [],
		];

		if ( ! empty( $tree['draft'] ) ) {
			$result['visible'] = false;
			$result['reason']  = 'draft';

			if ( ! $trace ) {
				return $result;
			}
		}

		$any_applied = false;
		$matched     = false;

		foreach ( Tree::filled_groups( $tree ) as $group ) {
			$group_trace = [
				'result'     => null,
				'conditions' => [],
			];
			$answered    = 0;
			$passes      = true;

			foreach ( $group['conditions'] as $condition ) {
				$answer = self::answer( $check( $condition ) );

				$group_trace['conditions'][] = [
					'id'     => $condition['id'],
					'type'   => $condition['type'],
					'result' => $answer[0],
					'reason' => $answer[1],
				];

				if ( null === $answer[0] ) {
					continue;
				}

				++$answered;

				if ( false === $answer[0] ) {
					$passes = false;

					if ( ! $trace ) {
						break;
					}
				}
			}

			if ( $answered > 0 ) {
				$any_applied           = true;
				$group_trace['result'] = $passes;

				if ( $passes ) {
					$matched = true;
				}
			}

			$result['groups'][] = $group_trace;

			if ( $matched && ! $trace ) {
				break;
			}
		}

		if ( 'draft' === $result['reason'] ) {
			$result['matched'] = $any_applied ? $matched : null;

			return $result;
		}

		if ( ! $any_applied ) {
			return $result;
		}

		$show              = 'hide' !== ( isset( $tree['action'] ) ? $tree['action'] : 'show' );
		$result['matched'] = $matched;
		$result['reason']  = $matched ? 'matched' : 'not_matched';
		$result['visible'] = $show ? $matched : ! $matched;

		return $result;
	}

	/**
	 * Answer of a check as [ bool|null, reason ].
	 *
	 * @param mixed $answer Check output.
	 *
	 * @return array{0:bool|null,1:string}
	 */
	private static function answer( $answer ) {
		if ( is_array( $answer ) ) {
			$value  = array_key_exists( 'result', $answer ) ? $answer['result'] : null;
			$reason = isset( $answer['reason'] ) ? (string) $answer['reason'] : '';

			return [ null === $value ? null : (bool) $value, $reason ];
		}

		return [ null === $answer ? null : (bool) $answer, '' ];
	}
}
