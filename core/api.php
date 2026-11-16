<?php
/**
 * Public functions of the developer API (stable since 3.0). See docs/developer-conditions.md.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'stax_visibility_register_condition' ) ) {
	/**
	 * Add a condition in code. Same as \Stax\VisibilityLogic\Conditions::register(); call it on the
	 * `stax/visibility/register_conditions` action.
	 *
	 * @param string $slug Slug (lowercase letters, digits, underscores).
	 * @param array  $args label, evaluate (callable), fields, category, keywords, summary, short, visitor, since.
	 *
	 * @return bool
	 */
	function stax_visibility_register_condition( $slug, array $args ) {
		return \Stax\VisibilityLogic\Conditions::register( $slug, $args );
	}
}

if ( ! function_exists( 'stax_visibility_unregister_condition' ) ) {
	/**
	 * Remove a condition added with stax_visibility_register_condition().
	 *
	 * @param string $slug Slug.
	 *
	 * @return void
	 */
	function stax_visibility_unregister_condition( $slug ) {
		\Stax\VisibilityLogic\Conditions::unregister( $slug );
	}
}
