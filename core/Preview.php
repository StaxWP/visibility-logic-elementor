<?php
/**
 * "Preview as" and "Why is this hidden?".
 *
 * - Editors: REST `visibility-logic/v1/explain` evaluates the (unsaved) rules of the elements of a document for a
 *   simulated visitor (guest, role, specific user, device, date and time) and returns the trace in words.
 * - Front end, for users who can edit posts: an admin bar menu "Visibility: N hidden here" with the reason of each
 *   hidden element of this request, and "View this page as" (guest or a role), a nonce-signed query argument that
 *   only changes the Visibility Logic evaluation.
 *
 * Safety (plan R6): the simulation lives in Context and is read only by the condition modules; the current user,
 * capabilities and content are never changed. Simulating a specific user needs `list_users`.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Preview
 */
class Preview {

	/**
	 * Query argument of the front-end preview.
	 */
	const ARG = 'stax_vl_as';

	/**
	 * Nonce action of the front-end preview.
	 */
	const NONCE = 'stax_vl_preview';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'wp', [ __CLASS__, 'front_end' ] );
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar' ], 999 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'admin_bar_style' ] );
	}

	/**
	 * REST route.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			Rest::NS,
			'/explain',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'explain_request' ],
				'permission_callback' => [ __CLASS__, 'can_explain' ],
				'args'                => [
					'post_id'  => [ 'type' => 'integer', 'default' => 0 ],
					'context'  => [ 'type' => 'object', 'default' => [] ],
					'elements' => [ 'type' => 'array', 'default' => [] ],
				],
			]
		);
	}

	/**
	 * Permission: edit the previewed document (or posts in general); list users to simulate one.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return bool
	 */
	public static function can_explain( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! ( $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' ) ) ) {
			return false;
		}

		$context = (array) $request->get_param( 'context' );

		return ! ( isset( $context['as'] ) && 'user' === $context['as'] && ! current_user_can( 'list_users' ) );
	}

	/**
	 * Explain a list of elements.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return array
	 */
	public static function explain_request( $request ) {
		$out      = [];
		$elements = array_slice( (array) $request->get_param( 'elements' ), 0, 300 );

		self::simulate( (array) $request->get_param( 'context' ) );

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) || ! isset( $element['values'] ) || ! is_array( $element['values'] ) ) {
				continue;
			}

			$out[] = array_merge(
				[ 'id' => isset( $element['id'] ) ? (string) $element['id'] : '' ],
				self::explain( $element['values'], isset( $element['id'] ) ? (string) $element['id'] : '', isset( $element['name'] ) ? (string) $element['name'] : '' )
			);
		}

		Context::reset();

		return $out;
	}

	/**
	 * Simulation from an editor context ( as: me|guest|role:<slug>|user, user, device, time 'Y-m-d\TH:i' ).
	 *
	 * @param array $context Context.
	 *
	 * @return void
	 */
	public static function simulate( array $context ) {
		$simulation = [];
		$as         = isset( $context['as'] ) ? (string) $context['as'] : 'me';

		if ( 'guest' === $as ) {
			$simulation['roles'] = [];
		} elseif ( 0 === strpos( $as, 'role:' ) ) {
			$role = sanitize_key( substr( $as, 5 ) );

			if ( get_role( $role ) ) {
				$simulation['roles'] = [ $role ];
			}
		} elseif ( 'user' === $as && ! empty( $context['user'] ) && current_user_can( 'list_users' ) ) {
			$simulation['user'] = absint( $context['user'] );
		}

		if ( ! empty( $context['device'] ) && in_array( $context['device'], [ 'desktop', 'tablet', 'mobile' ], true ) ) {
			$simulation['device'] = $context['device'];
		}

		if ( ! empty( $context['time'] ) ) {
			try {
				$simulation['time'] = ( new \DateTimeImmutable( str_replace( 'T', ' ', (string) $context['time'] ), wp_timezone() ) )->getTimestamp();
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- invalid date: keep now.
			}
		}

		// Country (Visibility Logic Pro's geolocation reads it; a typed code, never a lookup).
		if ( ! empty( $context['country'] ) && self::country_enabled() && preg_match( '/^[A-Za-z]{2}$/', (string) $context['country'] ) ) {
			$simulation['country'] = strtoupper( (string) $context['country'] );
		}

		if ( $simulation ) {
			Context::simulate( $simulation );
		} else {
			Context::reset();
		}
	}

	/**
	 * Whether "Preview as" offers a country (an add-on with location conditions turns it on).
	 *
	 * @return bool
	 */
	public static function country_enabled() {
		return (bool) apply_filters( 'stax/visibility/preview_country', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
	}

	/**
	 * Explain one element's rule (unprefixed values: block attribute keys, or Elementor keys without the prefix).
	 *
	 * @param array  $values Values.
	 * @param string $id     Element id.
	 * @param string $name   Element name.
	 *
	 * @return array visible, reason (off|no_conditions|matched|not_matched|draft), action, sentence, groups.
	 */
	public static function explain( array $values, $id = '', $name = '' ) {
		$settings = Conditions::to_settings( $values );

		if ( ! Singleton::flag( $settings, 'enabled' ) ) {
			return [
				'visible'  => true,
				'reason'   => 'off',
				'action'   => 'show',
				'sentence' => '',
				'groups'   => [],
			];
		}

		$tree   = Rule_Tree::from_settings( $settings );
		$result = Rule_Tree::evaluate( $tree, new Condition_Item( $settings, $id, $name, 'element' ), true, true );

		return self::describe( $tree, $result );
	}

	/**
	 * Trace in words.
	 *
	 * @param array $tree   Tree.
	 * @param array $result Evaluator result with trace.
	 *
	 * @return array
	 */
	public static function describe( array $tree, array $result ) {
		$by_id = [];

		foreach ( \StaxWP\Conditions\V1\Tree::conditions( $tree ) as $condition ) {
			$by_id[ $condition['id'] ] = $condition;
		}

		$groups = [];

		foreach ( $result['groups'] as $group ) {
			$conditions = [];

			foreach ( $group['conditions'] as $c ) {
				$conditions[] = [
					'text'   => isset( $by_id[ $c['id'] ] ) ? Rule_Tree::condition_sentence( $by_id[ $c['id'] ] ) : $c['type'],
					'result' => $c['result'],
				];
			}

			$groups[] = [
				'result'     => $group['result'],
				'conditions' => $conditions,
			];
		}

		return [
			'visible'  => (bool) $result['visible'],
			'reason'   => $result['reason'],
			'action'   => isset( $tree['action'] ) ? $tree['action'] : 'show',
			'sentence' => Rule_Tree::sentence( $tree ),
			'groups'   => $groups,
		];
	}

	/**
	 * Front end: record traces for the admin bar, and apply a signed "View this page as".
	 *
	 * @return void
	 */
	public static function front_end() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
		if ( isset( $_GET[ self::ARG ], $_GET['_stax_vl'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_stax_vl'] ) ), self::NONCE ) ) {
			self::simulate( [ 'as' => sanitize_text_field( wp_unslash( $_GET[ self::ARG ] ) ) ] );

			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- cache plugins' constant.
			}

			nocache_headers();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( is_admin_bar_showing() ) {
			Rule_Tree::record_traces();

			// WordPress prints the admin bar at wp_body_open, before the content: print it in the footer instead
			// (it is fixed on screen either way), so it can list what the page hid.
			remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );
		}
	}

	/**
	 * Admin bar: hidden elements of this request with the reason, and "View this page as".
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 *
	 * @return void
	 */
	public static function admin_bar( $bar ) {
		if ( is_admin() || ! Rule_Tree::recording() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$traces = Rule_Tree::traces();
		$hidden = array_filter(
			$traces,
			static function ( $t ) {
				return empty( $t['trace']['visible'] );
			}
		);
		$sim    = Context::simulation();
		$who    = self::who( $sim );

		$bar->add_node(
			[
				'id'    => 'stax-vl',
				/* translators: %d: number of elements hidden on this page. */
				'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html( sprintf( _n( 'Visibility: %d hidden here', 'Visibility: %d hidden here', count( $hidden ), 'visibility-logic-elementor' ), count( $hidden ) ) ) . ( $sim ? ' · ' . esc_html( $who ) : '' ) . '</span>',
				'href'  => Settings::url( 'usage' ),
				'meta'  => [ 'class' => 'stax-vl-ab' . ( $sim ? ' is-preview' : '' ) ],
			]
		);

		$n = 0;

		foreach ( $traces as $trace ) {
			++$n;
			$described = self::describe( $trace['tree'], $trace['trace'] );
			$node      = 'stax-vl-e' . $n;
			$label     = ( $described['visible'] ? __( 'Shown', 'visibility-logic-elementor' ) : __( 'Hidden', 'visibility-logic-elementor' ) ) . ': ' . ucfirst( str_replace( [ '-', '_' ], ' ', $trace['name'] ) ) . ( '' !== $trace['id'] ? ' #' . $trace['id'] : '' );

			$bar->add_node(
				[
					'parent' => 'stax-vl',
					'id'     => $node,
					'title'  => esc_html( $label ),
					'meta'   => [ 'title' => $described['sentence'] ],
				]
			);

			$bar->add_node(
				[
					'parent' => $node,
					'id'     => $node . '-s',
					'title'  => esc_html( $described['sentence'] ),
				]
			);

			$c = 0;

			foreach ( $described['groups'] as $group ) {
				foreach ( $group['conditions'] as $condition ) {
					++$c;
					$state = true === $condition['result'] ? __( 'yes', 'visibility-logic-elementor' ) : ( false === $condition['result'] ? __( 'no', 'visibility-logic-elementor' ) : __( 'does not apply here', 'visibility-logic-elementor' ) );

					$bar->add_node(
						[
							'parent' => $node,
							'id'     => $node . '-c' . $c,
							'title'  => esc_html( ( true === $condition['result'] ? '✓ ' : ( false === $condition['result'] ? '✗ ' : '– ' ) ) . $condition['text'] . ': ' . $state ),
						]
					);
				}
			}

			if ( 'draft' === $described['reason'] ) {
				$bar->add_node(
					[
						'parent' => $node,
						'id'     => $node . '-d',
						'title'  => esc_html__( 'Hidden on the live site (draft).', 'visibility-logic-elementor' ),
					]
				);
			}

			if ( $n >= 50 ) {
				break;
			}
		}

		// View this page as.
		$bar->add_node(
			[
				'parent' => 'stax-vl',
				'id'     => 'stax-vl-as',
				'title'  => esc_html__( 'View this page as', 'visibility-logic-elementor' ),
			]
		);

		$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$current = remove_query_arg( [ self::ARG, '_stax_vl' ], set_url_scheme( '//' . wp_parse_url( home_url(), PHP_URL_HOST ) . ( wp_parse_url( home_url(), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url(), PHP_URL_PORT ) : '' ) . $request ) );
		$options = [ 'guest' => __( 'Guest', 'visibility-logic-elementor' ) ];

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			/* translators: %s: role name. */
			$options[ 'role:' . $slug ] = sprintf( __( 'Role: %s', 'visibility-logic-elementor' ), translate_user_role( $name ) );
		}

		foreach ( $options as $as => $name ) {
			$bar->add_node(
				[
					'parent' => 'stax-vl-as',
					'id'     => 'stax-vl-as-' . sanitize_key( str_replace( ':', '-', $as ) ),
					'title'  => esc_html( $name ),
					'href'   => add_query_arg(
						[
							self::ARG  => rawurlencode( $as ),
							'_stax_vl' => wp_create_nonce( self::NONCE ),
						],
						$current
					),
				]
			);
		}

		if ( $sim ) {
			$bar->add_node(
				[
					'parent' => 'stax-vl-as',
					'id'     => 'stax-vl-as-me',
					'title'  => esc_html__( 'Stop: view as me', 'visibility-logic-elementor' ),
					'href'   => $current,
				]
			);
		}
	}

	/**
	 * Label of the simulated visitor.
	 *
	 * @param array|null $sim Simulation.
	 *
	 * @return string
	 */
	private static function who( $sim ) {
		if ( ! $sim ) {
			return '';
		}

		if ( isset( $sim['roles'] ) ) {
			if ( empty( $sim['roles'] ) ) {
				return __( 'viewing as Guest', 'visibility-logic-elementor' );
			}

			$names = wp_roles()->get_names();
			$role  = $sim['roles'][0];

			/* translators: %s: role name. */
			return sprintf( __( 'viewing as %s', 'visibility-logic-elementor' ), isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role );
		}

		return __( 'preview', 'visibility-logic-elementor' );
	}

	/**
	 * Admin bar icon (front end, editors only).
	 *
	 * @return void
	 */
	public static function admin_bar_style() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_register_style( 'stax-visibility-admin-bar', false, [], STAX_VISIBILITY_VERSION );
		wp_enqueue_style( 'stax-visibility-admin-bar' );
		wp_add_inline_style( 'stax-visibility-admin-bar', '#wpadminbar .stax-vl-ab .ab-icon:before{content:"\f177";top:2px;}#wpadminbar .stax-vl-ab.is-preview > .ab-item{background:#6e49cb;color:#fff;}' );
	}
}

Preview::init();
