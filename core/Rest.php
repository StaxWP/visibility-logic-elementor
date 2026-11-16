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
		$data['explainUrl'] = rest_url( self::NS . '/explain' );
		$data['previewCountry'] = Preview::country_enabled();
		$data['postsUrl']       = rest_url( 'wp/v2/search' );
		$data['proColumnsLocked'] = defined( 'STAX_VISIBILITY_PRO_VERSION' ) && ! Singleton::pro_supports_columns();
		$data['templates']      = [];

		/**
		 * Extra settings of an element in the block editor panel (same field format as the condition registry; the
		 * value is stored under the field key in the block attribute). Visibility Logic Pro: Reveal on click.
		 *
		 * @param array $fields Fields.
		 */
		$data['elementSettings'] = array_values( (array) apply_filters( 'stax/visibility/element_settings', [] ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.

		if ( $data['fallback'] ) {
			foreach ( get_posts( [ 'post_type' => 'elementor_library', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ] ) as $template ) {
				$data['templates'][] = [
					'value' => (string) $template->ID,
					'label' => get_the_title( $template ) . ' (#' . $template->ID . ')',
				];
			}
		}
		$data['roles']      = [];

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$data['roles'][] = [
				'value' => (string) $slug,
				'label' => translate_user_role( $name ),
			];
		}

		$data['preview'] = [
			'previewAs'  => __( 'Preview as', 'visibility-logic-elementor' ),
			'me'         => __( 'Me (no preview)', 'visibility-logic-elementor' ),
			'guest'      => __( 'Guest', 'visibility-logic-elementor' ),
			/* translators: %s: role name. */
			'role'       => __( 'Role: %s', 'visibility-logic-elementor' ),
			'user'       => __( 'A specific user', 'visibility-logic-elementor' ),
			'userId'     => __( 'User', 'visibility-logic-elementor' ),
			'device'     => __( 'Device', 'visibility-logic-elementor' ),
			'anyDevice'  => __( 'This device', 'visibility-logic-elementor' ),
			'desktop'    => __( 'Desktop', 'visibility-logic-elementor' ),
			'tablet'     => __( 'Tablet', 'visibility-logic-elementor' ),
			'mobile'     => __( 'Mobile', 'visibility-logic-elementor' ),
			'time'       => __( 'Date and time', 'visibility-logic-elementor' ),
			'timeHelp'   => __( 'Empty: now (site time zone).', 'visibility-logic-elementor' ),
			'country'    => __( 'Country', 'visibility-logic-elementor' ),
			'countryHelp' => __( 'Two-letter code, for example DE.', 'visibility-logic-elementor' ),
			'why'        => __( 'Preview as and why', 'visibility-logic-elementor' ),
			/* translators: %s: who the page is previewed for. */
			'visible'    => __( 'Shown for %s', 'visibility-logic-elementor' ),
			/* translators: %s: who the page is previewed for. */
			'hidden'     => __( 'Hidden for %s', 'visibility-logic-elementor' ),
			'noRule'     => __( 'No rule applies: shown.', 'visibility-logic-elementor' ),
			'draft'      => __( 'Hidden on the live site (draft).', 'visibility-logic-elementor' ),
			'met'        => __( 'yes', 'visibility-logic-elementor' ),
			'notMet'     => __( 'no', 'visibility-logic-elementor' ),
			'skipped'    => __( 'does not apply here', 'visibility-logic-elementor' ),
			/* translators: %d: group number. */
			'group'      => __( 'Group %d', 'visibility-logic-elementor' ),
			'matched'    => __( 'The rule matches.', 'visibility-logic-elementor' ),
			'notMatched' => __( 'The rule does not match.', 'visibility-logic-elementor' ),
			'actionShow' => __( 'Action: show when it matches.', 'visibility-logic-elementor' ),
			'actionHide' => __( 'Action: hide when it matches.', 'visibility-logic-elementor' ),
			'off'        => __( 'Rules are off for this element.', 'visibility-logic-elementor' ),
		];

		return $data;
	}
}

Rest::init();
