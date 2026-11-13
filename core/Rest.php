<?php
/**
 * REST routes of the editors (namespace `visibility-logic/v1`): user search for the "Specific users" condition.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rest
 */
class Rest {

	/**
	 * Namespace.
	 */
	const NS = 'visibility-logic/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/users',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'users' ],
				// User names and ids are only listed to people who may list users.
				'permission_callback' => static function () {
					return current_user_can( 'list_users' );
				},
				'args'                => [
					'search'  => [ 'type' => 'string', 'default' => '' ],
					'include' => [ 'type' => 'string', 'default' => '' ],
				],
			]
		);
	}

	/**
	 * Users matching a search (name, login, email) or a list of ids, as [ { value, label } ].
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return array
	 */
	public static function users( $request ) {
		$args = [
			'number' => 20,
			'fields' => [ 'ID', 'display_name', 'user_login' ],
		];

		$include = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'include' ) ) ) );

		if ( $include ) {
			$args['include'] = $include;
		} else {
			$search = trim( (string) $request->get_param( 'search' ) );

			if ( strlen( $search ) < 2 ) {
				return [];
			}

			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
		}

		return array_map(
			static function ( $user ) {
				return [
					'value' => (string) $user->ID,
					/* translators: 1: user display name, 2: user id. */
					'label' => sprintf( __( '%1$s (#%2$d)', 'visibility-logic-elementor' ), $user->display_name, $user->ID ),
				];
			},
			get_users( $args )
		);
	}

	/**
	 * Editor data shared by both editors: builder data plus the user search endpoint.
	 *
	 * @return array
	 */
	public static function editor_data() {
		$data = Rule_Tree::for_js();

		$options          = Resources::get_all_widget_options();
		$data['fallback'] = defined( 'STAX_VISIBILITY_PRO_VERSION' ) && ! empty( $options['fallback']['status'] );
		$data['usersUrl']  = current_user_can( 'list_users' ) ? rest_url( self::NS . '/users' ) : '';
		$data['restNonce'] = wp_create_nonce( 'wp_rest' );

		return $data;
	}
}

Rest::init();
