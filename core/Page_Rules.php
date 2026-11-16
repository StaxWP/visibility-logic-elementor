<?php
/**
 * Page-level visibility (backlog B11): one rule for a whole page or post, set in Elementor's page settings or in
 * the block editor's document sidebar.
 *
 * Storage: post meta `_stax_visibility_page` (also mirrored in Elementor's page settings under
 * `stax_visibility_page`, so the Elementor panel shows it):
 *   enabled  bool
 *   rules    rule tree (the rule builder's format; always a tree, Visibility Logic 2.x does not read page rules)
 *   hidden   what visitors get when the rule hides the page: message (free); redirect, login, not_found,
 *            template (Visibility Logic Pro, through the `stax/visibility/page_hidden` action and the
 *            `stax/visibility/page_replacement` filter)
 *   message  HTML shown instead of the content (basic HTML, sanitized)
 *   redirect, template  Pro values (kept as stored)
 *
 * Users who can edit the post always see it, with a notice bar ("Preview as" / "View this page as" applies the rule
 * to them too). The content is replaced in `the_content` and excerpts, so REST responses, feeds and loops get the
 * message too; search engines get `noindex` while the page is hidden for them.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

use StaxWP\Conditions\V1\Tree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Page_Rules
 */
class Page_Rules {

	/**
	 * Post meta key.
	 */
	const META = '_stax_visibility_page';

	/**
	 * Elementor page settings key (and control name).
	 */
	const SETTING = 'stax_visibility_page';

	/**
	 * Answers per post id for this request ( id => bool hidden by the rule ).
	 *
	 * @var array
	 */
	private static $cache = [];

	/**
	 * Re-entry guard while mirroring the meta and Elementor's page settings.
	 *
	 * @var bool
	 */
	private static $syncing = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
		add_action( 'template_redirect', [ __CLASS__, 'template_redirect' ], 5 );
		add_filter( 'the_content', [ __CLASS__, 'filter_content' ], PHP_INT_MAX - 10 );
		add_filter( 'get_the_excerpt', [ __CLASS__, 'filter_excerpt' ], PHP_INT_MAX - 10, 2 );
		add_filter( 'wp_robots', [ __CLASS__, 'robots' ] );
		add_action( 'wp_body_open', [ __CLASS__, 'editor_notice' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'block_editor_assets' ], 20 );
		add_action( 'elementor/documents/register_controls', [ __CLASS__, 'register_elementor_controls' ] );
		add_action( 'elementor/document/after_save', [ __CLASS__, 'elementor_saved' ], 10, 2 );
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'elementor_assets' ], 20 );
		add_action( 'updated_post_meta', [ __CLASS__, 'meta_changed' ], 10, 4 );
		add_action( 'added_post_meta', [ __CLASS__, 'meta_changed' ], 10, 4 );
	}

	/**
	 * Register the meta for the block editor (REST), editable by users who can edit the post.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			'',
			self::META,
			[
				'type'              => 'object',
				'single'            => true,
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', (int) $post_id );
				},
				'show_in_rest'      => [
					'schema' => [
						'type'                 => 'object',
						'additionalProperties' => true,
						'properties'           => [
							'enabled'  => [ 'type' => 'boolean' ],
							'rules'    => [ 'type' => [ 'object', 'null' ], 'additionalProperties' => true ],
							'hidden'   => [ 'type' => 'string' ],
							'message'  => [ 'type' => 'string' ],
							'redirect' => [ 'type' => 'string' ],
							'template' => [ 'type' => 'string' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return [
			'enabled'  => false,
			'rules'    => null,
			'hidden'   => 'message',
			'message'  => '',
			'redirect' => '',
			'template' => '',
		];
	}

	/**
	 * Clean a stored or submitted value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return array
	 */
	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : [];
		$out   = self::defaults();

		Rule_Tree::load_package();

		$out['enabled']  = ! empty( $value['enabled'] ) && 'no' !== $value['enabled'];
		$out['rules']    = ! empty( $value['rules'] ) && is_array( $value['rules'] ) ? Tree::normalize( $value['rules'] ) : null;
		$out['hidden']   = isset( $value['hidden'] ) && array_key_exists( (string) $value['hidden'], self::actions( true ) ) ? (string) $value['hidden'] : 'message';
		$out['message']  = isset( $value['message'] ) ? wp_kses_post( (string) $value['message'] ) : '';
		$out['redirect'] = isset( $value['redirect'] ) ? esc_url_raw( (string) $value['redirect'] ) : '';
		$out['template'] = isset( $value['template'] ) && absint( $value['template'] ) ? (string) absint( $value['template'] ) : '';

		return $out;
	}

	/**
	 * Settings of a post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array
	 */
	public static function get( $post_id ) {
		$value = get_post_meta( (int) $post_id, self::META, true );

		return self::sanitize( is_array( $value ) ? $value : [] );
	}

	/**
	 * Whether a post has an active page rule.
	 *
	 * @param array $settings Settings.
	 *
	 * @return bool
	 */
	public static function active( array $settings ) {
		return ! empty( $settings['enabled'] ) && ! empty( $settings['rules'] ) && ( ! Tree::is_empty( $settings['rules'] ) || ! empty( $settings['rules']['draft'] ) );
	}

	/**
	 * What visitors get when the page is hidden ( value => label ). Visibility Logic Pro adds its options with the
	 * `stax/visibility/page_hidden_actions` filter; without Pro they are listed locked.
	 *
	 * @param bool $all Include the Pro options listed locked.
	 *
	 * @return array
	 */
	public static function actions( $all = false ) {
		$actions = (array) apply_filters( 'stax/visibility/page_hidden_actions', [ 'message' => __( 'Show a message instead of the content', 'visibility-logic-elementor' ) ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.

		if ( $all || Pro_Teaser::visible() ) {
			$actions += self::pro_actions();
		}

		return $actions;
	}

	/**
	 * Pro options, listed locked without Pro.
	 *
	 * @return array
	 */
	public static function pro_actions() {
		return [
			'redirect'  => __( 'Redirect to a page or URL (Pro)', 'visibility-logic-elementor' ),
			'login'     => __( 'Send to the login page, then back (Pro)', 'visibility-logic-elementor' ),
			'not_found' => __( 'Not found (404) (Pro)', 'visibility-logic-elementor' ),
			'template'  => __( 'Show an Elementor template (Pro)', 'visibility-logic-elementor' ),
		];
	}

	/**
	 * Settings fields of the page panel (both editors), filtered by `stax/visibility/page_fields` (Pro adds its own).
	 *
	 * @return array
	 */
	public static function fields() {
		$actions = self::actions();
		$locked  = array_keys( array_diff_key( $actions, (array) apply_filters( 'stax/visibility/page_hidden_actions', [ 'message' => '' ] ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.

		$fields = [
			[
				'key'   => 'active',
				'type'  => 'switch',
				'label' => __( 'Use a rule for this page', 'visibility-logic-elementor' ),
				'help'  => __( 'Applies to the whole page. People who can edit it always see it, with a notice.', 'visibility-logic-elementor' ),
			],
			[
				'key'     => 'hidden',
				'type'    => 'select',
				'label'   => __( 'When the page is hidden', 'visibility-logic-elementor' ),
				'options' => Conditions::option_list( $actions, $locked ),
				'show_if' => [ 'active' => [ 'yes' ] ],
			],
			[
				'key'         => 'message',
				'type'        => 'textarea',
				'label'       => __( 'Message', 'visibility-logic-elementor' ),
				'placeholder' => self::default_message(),
				'help'        => __( 'Basic HTML. The theme header and footer stay.', 'visibility-logic-elementor' ),
				'show_if'     => [
					'active' => [ 'yes' ],
					'hidden' => [ 'message' ],
				],
			],
		];

		return array_values( (array) apply_filters( 'stax/visibility/page_fields', $fields ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
	}

	/**
	 * Message used when none is set.
	 *
	 * @return string
	 */
	public static function default_message() {
		return __( 'This content is not available.', 'visibility-logic-elementor' );
	}

	/**
	 * Whether the rule hides the post for the current visitor (or the "Preview as" visitor), editors included.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return bool
	 */
	public static function rule_hides( $post_id ) {
		$post_id = (int) $post_id;
		$key     = $post_id . ( Context::simulation() ? ':' . md5( (string) wp_json_encode( Context::simulation() ) ) : '' );

		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$settings = self::get( $post_id );
		$hidden   = false;

		if ( self::active( $settings ) ) {
			$result = Rule_Tree::evaluate( $settings['rules'], new Condition_Item( [], 'page-' . $post_id, get_post_type( $post_id ), 'page' ), true, Rule_Tree::recording() );
			$hidden = empty( $result['visible'] );

			if ( Rule_Tree::recording() ) {
				Rule_Tree::add_trace( 'page-' . $post_id, __( 'This page', 'visibility-logic-elementor' ), $settings['rules'], $result );
			}
		}

		self::$cache[ $key ] = $hidden;

		return $hidden;
	}

	/**
	 * Whether the current visitor may see a post the rule hides (people who can edit it, unless they preview as
	 * someone else).
	 *
	 * @param int $post_id Post id.
	 *
	 * @return bool
	 */
	public static function exempt( $post_id ) {
		return ! Context::simulation() && current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Whether the post is hidden for the current visitor (editors exempt).
	 *
	 * @param int $post_id Post id.
	 *
	 * @return bool
	 */
	public static function is_hidden( $post_id ) {
		return $post_id && self::rule_hides( $post_id ) && ! self::exempt( $post_id );
	}

	/**
	 * Forget the answers (tests, after a simulation changes).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$cache = [];
	}

	/**
	 * Single views: let Visibility Logic Pro redirect, send to the login page or answer 404.
	 *
	 * @return void
	 */
	public static function template_redirect() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();

		if ( ! self::is_hidden( $post_id ) ) {
			return;
		}

		/**
		 * The page rule hides this single view for the current visitor. Visibility Logic Pro redirects here.
		 *
		 * @param int   $post_id  Post id.
		 * @param array $settings Page settings.
		 */
		do_action( 'stax/visibility/page_hidden', $post_id, self::get( $post_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
	}

	/**
	 * Condition types used by a page rule.
	 *
	 * @param array $settings Settings.
	 *
	 * @return string[]
	 */
	public static function condition_types( array $settings ) {
		return empty( $settings['rules'] ) ? [] : array_values( array_unique( wp_list_pluck( Tree::conditions( $settings['rules'] ), 'type' ) ) );
	}

	/**
	 * What replaces the content of a hidden post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string HTML.
	 */
	public static function replacement( $post_id ) {
		$settings = self::get( $post_id );
		$message  = '' !== trim( wp_strip_all_tags( $settings['message'], true ) ) || false !== strpos( $settings['message'], '<img' ) ? $settings['message'] : esc_html( self::default_message() );
		$html     = '<div class="stax-visibility-page-message">' . wpautop( wp_kses_post( $message ) ) . '</div>';

		/**
		 * HTML shown instead of the content of a hidden post (Visibility Logic Pro: an Elementor template).
		 *
		 * @param string $html     HTML.
		 * @param int    $post_id  Post id.
		 * @param array  $settings Page settings.
		 */
		return (string) apply_filters( 'stax/visibility/page_replacement', $html, (int) $post_id, $settings ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
	}

	/**
	 * `the_content`: the message instead of the content of a hidden post (single views, loops, REST, feeds).
	 *
	 * @param string $content Content.
	 *
	 * @return string
	 */
	public static function filter_content( $content ) {
		static $depth = 0;

		$post = get_post();

		if ( ! $post || $depth > 0 || ! self::is_hidden( $post->ID ) ) {
			return $content;
		}

		++$depth;
		$html = self::replacement( $post->ID );
		--$depth;

		return $html;
	}

	/**
	 * `get_the_excerpt`: the message as text for hidden posts.
	 *
	 * @param string        $excerpt Excerpt.
	 * @param \WP_Post|null $post    Post.
	 *
	 * @return string
	 */
	public static function filter_excerpt( $excerpt, $post = null ) {
		$post = get_post( $post );

		if ( ! $post || ! self::is_hidden( $post->ID ) ) {
			return $excerpt;
		}

		$settings = self::get( $post->ID );
		$text     = trim( wp_strip_all_tags( $settings['message'], true ) );

		return '' !== $text ? $text : self::default_message();
	}

	/**
	 * `wp_robots`: hidden single views are not indexed.
	 *
	 * @param array $robots Directives.
	 *
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( is_singular() && self::is_hidden( (int) get_queried_object_id() ) ) {
			$robots['noindex'] = true;
		}

		return $robots;
	}

	/**
	 * Notice bar for people who can edit a page with an active rule: the rule in words, and whether it hides the
	 * page for them as a visitor.
	 *
	 * @return void
	 */
	public static function editor_notice() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id  = (int) get_queried_object_id();
		$settings = self::get( $post_id );

		if ( ! self::exempt( $post_id ) || ! self::active( $settings ) ) {
			return;
		}

		$text = self::rule_hides( $post_id )
			/* translators: %s: the rule in words. */
			? __( 'Visibility Logic: this page is hidden for you as a visitor (%s). You see it because you can edit it.', 'visibility-logic-elementor' )
			/* translators: %s: the rule in words. */
			: __( 'Visibility Logic: this page has a rule (%s).', 'visibility-logic-elementor' );

		printf(
			'<div class="stax-visibility-page-notice" role="status" style="margin:0;padding:8px 16px;background:#fcf9e8;border-bottom:1px solid #dba617;color:#1e1e1e;font:14px/1.5 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">%s</div>',
			esc_html( sprintf( $text, Rule_Tree::sentence( $settings['rules'] ) ) )
		);
	}

	/**
	 * Values shown in the panel ( settings fields + tree ) for both editors.
	 *
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	public static function panel_values( array $settings ) {
		return [
			'active'   => ! empty( $settings['enabled'] ),
			'hidden'   => $settings['hidden'],
			'message'  => $settings['message'],
			'redirect' => $settings['redirect'],
			'template' => $settings['template'],
		];
	}

	/**
	 * Editor data of the page panel (both editors).
	 *
	 * @return array
	 */
	public static function editor_data() {
		return [
			'meta'     => self::META,
			'setting'  => self::SETTING,
			'fields'   => self::fields(),
			'defaults' => self::defaults(),
			'labels'   => [
				'panel' => __( 'Page visibility', 'visibility-logic-elementor' ),
				'intro' => __( 'Show or hide this whole page. When hidden, visitors get the option below; people who can edit the page always see it.', 'visibility-logic-elementor' ),
				'popup' => __( 'Show this popup only when the rule allows it. A hidden popup is not added to the page, so its triggers do nothing. Elementor Pro\'s own display conditions and triggers still apply first.', 'visibility-logic-elementor' ),
				'use'   => __( 'Use a rule for this popup', 'visibility-logic-elementor' ),
				'cache' => __( 'Rules that depend on the visitor need the page to be excluded from page caching.', 'visibility-logic-elementor' ),
			],
		];
	}

	/**
	 * Block editor: the document sidebar panel.
	 *
	 * @return void
	 */
	public static function block_editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || empty( $screen->post_type ) || ! is_post_type_viewable( $screen->post_type ) ) {
			return;
		}

		self::enqueue_panel( [ 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-editor', 'wp-api-fetch', 'stax-visibility-blocks' ] );
	}

	/**
	 * Elementor editor: the page settings control.
	 *
	 * @return void
	 */
	public static function elementor_assets() {
		self::enqueue_panel( [ 'jquery', 'stax-visibility-rule-builder' ] );
	}

	/**
	 * Panel script for both editors.
	 *
	 * @param array $deps Dependencies.
	 *
	 * @return void
	 */
	private static function enqueue_panel( array $deps ) {
		wp_enqueue_script( 'stax-visibility-page-rules', STAX_VISIBILITY_ASSETS_URL . 'js/page-rules.js', $deps, STAX_VISIBILITY_VERSION, true );
		wp_localize_script( 'stax-visibility-page-rules', 'staxVisibilityPage', self::editor_data() );
	}

	/**
	 * Elementor documents that get the page panel: pages, posts and custom post types (Visibility Logic Pro adds
	 * popups with the `stax/visibility/page_documents` filter).
	 *
	 * @param object $document Elementor document.
	 *
	 * @return bool
	 */
	public static function supports_document( $document ) {
		$supported = $document instanceof \Elementor\Core\DocumentTypes\PageBase;

		return (bool) apply_filters( 'stax/visibility/page_documents', $supported, $document ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
	}

	/**
	 * Elementor page settings: "Page visibility" section with the rule builder.
	 *
	 * @param object $document Elementor document.
	 *
	 * @return void
	 */
	public static function register_elementor_controls( $document ) {
		if ( ! is_object( $document ) || ! method_exists( $document, 'start_controls_section' ) || ! self::supports_document( $document ) ) {
			return;
		}

		// Popups (Visibility Logic Pro): the rule only; a hidden popup is not printed.
		$kind = method_exists( $document, 'get_name' ) && 'popup' === $document->get_name() ? 'popup' : 'page';

		$document->start_controls_section(
			'stax_visibility_page_section',
			[
				'label' => 'popup' === $kind ? __( 'Popup visibility', 'visibility-logic-elementor' ) : __( 'Page visibility', 'visibility-logic-elementor' ),
				'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
			]
		);

		$document->add_control(
			self::SETTING,
			[
				'type'    => 'stax_page_rules',
				'kind'    => $kind,
				'default' => [],
			]
		);

		$document->end_controls_section();
	}

	/**
	 * Elementor saved a document: copy the page settings value to the meta (the evaluator reads the meta).
	 *
	 * @param object $document Elementor document.
	 * @param array  $data     Saved data.
	 *
	 * @return void
	 */
	public static function elementor_saved( $document, $data = [] ) {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) || ! array_key_exists( self::SETTING, $data['settings'] ) ) {
			return;
		}

		self::$syncing = true;
		update_post_meta( (int) $document->get_main_id(), self::META, self::sanitize( $data['settings'][ self::SETTING ] ) );
		self::$syncing = false;
		self::reset();
	}

	/**
	 * The meta changed outside Elementor (block editor, REST): mirror it in Elementor's page settings when the post
	 * has them, so the Elementor panel shows the same rule.
	 *
	 * @param int    $meta_id   Meta id.
	 * @param int    $post_id   Post id.
	 * @param string $meta_key  Key.
	 * @param mixed  $value     Value.
	 *
	 * @return void
	 */
	public static function meta_changed( $meta_id, $post_id, $meta_key, $value ) {
		if ( self::META !== $meta_key ) {
			return;
		}

		self::reset();
		Rules::flush();

		if ( self::$syncing ) {
			return;
		}

		$page_settings = get_post_meta( (int) $post_id, '_elementor_page_settings', true );

		if ( ! is_array( $page_settings ) || ! array_key_exists( self::SETTING, $page_settings ) ) {
			return;
		}

		self::$syncing                  = true;
		$page_settings[ self::SETTING ] = self::sanitize( $value );
		update_post_meta( (int) $post_id, '_elementor_page_settings', $page_settings );
		self::$syncing = false;
	}

	/**
	 * Posts with a page rule, as documents of the Rules overview.
	 *
	 * @param int $limit Max posts.
	 *
	 * @return array[]
	 */
	public static function overview_documents( $limit ) {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type <> 'revision' AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				ORDER BY pm.post_id DESC LIMIT %d",
				self::META,
				(int) $limit
			)
		);

		$documents = [];

		foreach ( $ids as $id ) {
			$post     = get_post( (int) $id );
			$settings = $post ? self::get( $post->ID ) : null;

			if ( ! $post || ! self::active( $settings ) ) {
				continue;
			}

			$element = [
				'id'         => 'page',
				'type'       => 'page',
				'action'     => isset( $settings['rules']['action'] ) && 'hide' === $settings['rules']['action'] ? 'hide' : 'show',
				'match'      => 'all',
				'conditions' => self::condition_types( $settings ),
				'legacy'     => false,
				'keep_html'  => false,
				'fallback'   => 'message' !== $settings['hidden'] || '' !== $settings['message'],
				'tree'       => $settings['rules'],
				'summary'    => Rule_Tree::sentence( $settings['rules'] ),
				'stored'     => true,
				'draft'      => ! empty( $settings['rules']['draft'] ),
			];

			$documents[] = [
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'post_type'  => $post->post_type,
				'template'   => '',
				'builder'    => 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ? 'elementor' : 'blocks',
				'status'     => $post->post_status,
				'count'      => 1,
				'legacy'     => 0,
				'conditions' => $element['conditions'],
				'elements'   => [ $element ],
			];
		}

		return $documents;
	}
}

Page_Rules::init();
