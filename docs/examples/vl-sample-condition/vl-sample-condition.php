<?php
/**
 * Plugin Name: Visibility Logic sample condition
 * Description: Example of the Visibility Logic developer API: "Visitor has commented" (no settings) and "Weekday number" (with settings).
 * Version: 1.0.0
 * Requires Plugins: visibility-logic-elementor
 * License: GPLv3
 *
 * @package VLSampleCondition
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'stax/visibility/register_conditions',
	static function () {
		if ( ! function_exists( 'stax_visibility_register_condition' ) ) {
			return; // Visibility Logic 2.x or not active.
		}

		$context = '\\Stax\\VisibilityLogic\\Context';

		// No settings: the builder offers "Is true / Is not true".
		stax_visibility_register_condition(
			'vls_has_commented',
			[
				'label'    => 'Visitor has commented',
				'category' => 'who',
				'visitor'  => true,
				'evaluate' => static function () use ( $context ) {
					$user_id = $context::user_id(); // Respects "Preview as".

					if ( ! $user_id ) {
						return false;
					}

					return (bool) get_comments(
						[
							'user_id' => $user_id,
							'count'   => true,
							'number'  => 1,
						]
					);
				},
			]
		);

		// With settings.
		stax_visibility_register_condition(
			'vls_weekday',
			[
				'label'    => 'Weekday number',
				'category' => 'when',
				'visitor'  => true,
				'fields'   => [
					[
						'key'     => 'vls_weekday_days',
						'type'    => 'multiselect',
						'label'   => 'Day is',
						'options' => [
							'1' => 'Monday',
							'2' => 'Tuesday',
							'3' => 'Wednesday',
							'4' => 'Thursday',
							'5' => 'Friday',
							'6' => 'Saturday',
							'7' => 'Sunday',
						],
					],
				],
				'summary'  => 'Day is[ {vls_weekday_days|or}]',
				'short'    => '{vls_weekday_days}',
				'evaluate' => static function ( array $values ) use ( $context ) {
					if ( empty( $values['vls_weekday_days'] ) ) {
						return null; // Nothing picked yet: the condition does not apply.
					}

					return in_array( $context::local( 'N' ), (array) $values['vls_weekday_days'], true );
				},
			]
		);
	}
);
