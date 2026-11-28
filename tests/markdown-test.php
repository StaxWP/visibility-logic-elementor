<?php
/**
 * Visibility Logic: the content of elements with visibility rules stays out of Elementor's Markdown output
 * (`/<slug>.md`, `/llms-full.txt`, the WebMCP `read_page_content` tool, Elementor Pro's Template widget).
 *
 * Run on a test site with Elementor 4.4+ (Markdown renderer) and this plugin active:
 *   wp eval-file wp-content/plugins/visibility-logic-elementor/tests/markdown-test.php
 *
 * Patchstack report (2.5.4 and earlier): Elementor builds that Markdown with each element's `render_markdown()`, which
 * skips the `should_render` and `render_content` filters the rules run on, so guests read "members only" text. Elementor
 * filters only the top-level elements (`elementor/markdown/element_output`); hidden children must go too. Elements with
 * rules are left out for every visitor, as in the plain text (the outputs are cached for everyone). Elementor's cached
 * copies made before the fix are cleared once. The post and the test user are deleted at the end. Exit code 1 when a
 * check fails.
 *
 * @package Stax\VisibilityLogic
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput

if ( ! did_action( 'elementor/loaded' ) || ! defined( 'STAX_VISIBILITY_VERSION' ) ) {
	WP_CLI::error( 'Elementor and Visibility Logic must be active.' );
}

if ( ! class_exists( '\Elementor\Modules\MarkdownRender\Markdown_Renderer' ) ) {
	WP_CLI::log( 'Elementor ' . ELEMENTOR_VERSION . ' has no Markdown renderer: nothing to check.' );
	return;
}

global $vlm;

$vlm = [
	'pass' => 0,
	'fail' => 0,
];

/**
 * Assert.
 *
 * @param string $label    Label.
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 */
function vlm_is( $label, $expected, $actual ) {
	global $vlm;
	if ( $expected === $actual ) {
		++$vlm['pass'];
		WP_CLI::log( "  ok   {$label}" );
	} else {
		++$vlm['fail'];
		WP_CLI::log( "  FAIL {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

/**
 * Markers of hidden content found in a text.
 *
 * @param string $text Text.
 *
 * @return string[]
 */
function vlm_leaks( $text ) {
	preg_match_all( '/LEAK[A-Z]+/', (string) $text, $m );

	return array_values( array_unique( $m[0] ) );
}

/**
 * Widget (saved format).
 *
 * @param string $id       Element id.
 * @param string $type     Widget type (text-editor, heading).
 * @param string $text     Text.
 * @param array  $settings Extra settings.
 *
 * @return array
 */
function vlm_widget( $id, $type, $text, array $settings = [] ) {
	$content = 'heading' === $type ? [ 'title' => $text ] : [ 'editor' => '<p>' . $text . '</p>' ];

	return [
		'id'         => $id,
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => array_merge( $content, $settings ),
		'elements'   => [],
	];
}

/**
 * Container (saved format), or a section with one column when Elementor's containers are off.
 *
 * @param string $id       Element id.
 * @param array  $children Children.
 * @param array  $settings Settings.
 *
 * @return array
 */
function vlm_container( $id, array $children, array $settings = [] ) {
	$experiments = \Elementor\Plugin::$instance->experiments;

	if ( ! $experiments || ! $experiments->is_feature_active( 'container' ) ) {
		return [
			'id'       => $id,
			'elType'   => 'section',
			'isInner'  => false,
			'settings' => $settings,
			'elements' => [
				[
					'id'       => $id . 'k',
					'elType'   => 'column',
					'settings' => [ '_column_size' => 100 ],
					'elements' => $children,
				],
			],
		];
	}

	return [
		'id'       => $id,
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => $settings,
		'elements' => $children,
	];
}

$logged_in = [
	'stax_visibility_enabled'              => 'yes',
	'stax_visibility_show_hide'            => 'yes',
	'stax_visibility_user_role_enabled'    => 'yes',
	'stax_visibility_user_role_conditions' => [ 'ecl-user' ],
];

$suffix = wp_generate_password( 6, false );
$sub_id = wp_insert_user(
	[
		'user_login' => 'vlm_sub_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'vlm_sub_' . $suffix . '@example.test',
		'role'       => 'subscriber',
	]
);
if ( is_wp_error( $sub_id ) ) {
	WP_CLI::error( 'Could not create the test user.' );
}

WP_CLI::log( 'Visibility Logic ' . STAX_VISIBILITY_VERSION . ', Elementor ' . ELEMENTOR_VERSION . ', PHP ' . PHP_VERSION . ', WP ' . get_bloginfo( 'version' ) );

// The report's page: a public heading, a members-only heading and text, a members-only container whose child has no
// rule; plus a members-only widget two levels deep in a public container and a 1.x (`ecl_enabled`) rule.
$elements = [
	vlm_container(
		'vlm1',
		[
			vlm_widget( 'vlm1a', 'heading', 'Public heading' ),
			vlm_widget( 'vlm1b', 'heading', 'LEAKHEADING members only', $logged_in ),
			vlm_widget( 'vlm1c', 'text-editor', 'LEAKTEXT members only body', $logged_in ),
		]
	),
	vlm_container( 'vlm2', [ vlm_widget( 'vlm2a', 'text-editor', 'LEAKCHILD in a hidden container' ) ], $logged_in ),
	vlm_container(
		'vlm3',
		[
			vlm_widget( 'vlm3a', 'text-editor', 'Public text' ),
			vlm_container( 'vlm3b', [ vlm_widget( 'vlm3c', 'text-editor', 'LEAKDEEP nested members only', $logged_in ) ] ),
			vlm_widget( 'vlm3d', 'text-editor', 'LEAKOLD 1.x rule', [ 'ecl_enabled' => 'yes', 'ecl_role' => [ 'administrator' ] ] ),
		]
	),
];

// Saved as an administrator, as the editor does.
$admins = get_users(
	[
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	]
);
wp_set_current_user( (int) reset( $admins ) );

$post_id = wp_insert_post(
	[
		'post_title'  => 'VLM members area ' . $suffix,
		'post_status' => 'publish',
		'post_type'   => 'page',
	]
);
update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
\Elementor\Plugin::$instance->documents->get( $post_id, false )->save( [ 'elements' => $elements ] );
clean_post_cache( $post_id );

$render = static function () use ( $post_id ) {
	$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
	$renderer = new \Elementor\Modules\MarkdownRender\Markdown_Renderer();

	return [
		'document' => $renderer->render( $document ),
		'elements' => $renderer->render_elements_data( $document->get_elements_data() ),
	];
};

foreach ( [ 'guest' => 0, 'logged in' => $sub_id ] as $who => $user ) {
	wp_set_current_user( $user );
	foreach ( $render() as $path => $markdown ) {
		vlm_is( "{$who}: Markdown ({$path}) has no hidden text", [], vlm_leaks( $markdown ) );
		vlm_is( "{$who}: Markdown ({$path}) keeps the public heading and text", true, false !== strpos( $markdown, 'Public heading' ) && false !== strpos( $markdown, 'Public text' ) );
	}
}

// The page itself is unchanged: a logged-in visitor still sees the members-only elements.
wp_set_current_user( $sub_id );
$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $post_id, false );
// (LEAKOLD: a 1.x-format setting the page does not apply; Markdown leaves any element with it out.)
vlm_is( 'logged in: page shows the members-only elements', [ 'LEAKHEADING', 'LEAKTEXT', 'LEAKCHILD', 'LEAKDEEP' ], array_values( array_diff( vlm_leaks( $html ), [ 'LEAKOLD' ] ) ) );
wp_set_current_user( 0 );
$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $post_id, false );
vlm_is( 'guest: page hides them', [], array_values( array_diff( vlm_leaks( $html ), [ 'LEAKOLD' ] ) ) );

// 3.0: a page with a page rule gives no content in Markdown, for every visitor.
$page_rule_id = 0;
if ( class_exists( '\Stax\VisibilityLogic\Page_Rules' ) ) {
	wp_set_current_user( (int) reset( $admins ) );
	$page_rule_id = wp_insert_post(
		[
			'post_title'   => 'VLM page rule ' . $suffix,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_excerpt' => 'LEAKPAGEEXCERPT',
		]
	);
	update_post_meta( $page_rule_id, '_elementor_edit_mode', 'builder' );
	\Elementor\Plugin::$instance->documents->get( $page_rule_id, false )->save( [ 'elements' => [ vlm_container( 'vlm4', [ vlm_widget( 'vlm4a', 'text-editor', 'LEAKPAGE members page body' ) ] ) ] ] );
	update_post_meta(
		$page_rule_id,
		\Stax\VisibilityLogic\Page_Rules::META,
		[
			'enabled' => true,
			'rules'   => [
				'action' => 'show',
				'groups' => [ [ 'conditions' => [ [ 'id' => 'c1', 'type' => 'user_role', 'values' => [ 'user_role_conditions' => [ 'ecl-user' ] ] ] ] ] ],
			],
		]
	);
	clean_post_cache( $page_rule_id );
	vlm_is( 'page rule saved', true, \Stax\VisibilityLogic\Page_Rules::active( \Stax\VisibilityLogic\Page_Rules::get( $page_rule_id ) ) );

	foreach ( [ 'guest' => 0, 'logged in' => $sub_id ] as $who => $user ) {
		wp_set_current_user( $user );
		$document = \Elementor\Plugin::$instance->documents->get( $page_rule_id, false );
		$renderer = new \Elementor\Modules\MarkdownRender\Markdown_Renderer();
		$extract  = \Elementor\Modules\MarkdownRender\Module::execute_while_rendering_markdown(
			static function () use ( $renderer, $document ) {
				return $renderer->render_elements_data( $document->get_elements_data() );
			}
		);
		vlm_is( "{$who}: page with a page rule: no content in Markdown", [], vlm_leaks( $renderer->render( $document ) . $extract ) );
	}

	wp_set_current_user( $sub_id );
	\Stax\VisibilityLogic\Page_Rules::reset();
	$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $page_rule_id, false );
	vlm_is( 'logged in: page with a page rule still renders', [ 'LEAKPAGE' ], vlm_leaks( $html ) );
	vlm_is( 'excerpt outside Markdown unchanged', 'LEAKPAGEEXCERPT', get_the_excerpt( $page_rule_id ) );
}

// Copies cached before the fix (per post Markdown, llms.txt) are cleared once after the update.
update_post_meta(
	$post_id,
	'_elementor_markdown_cache',
	[
		'timeout' => time() + HOUR_IN_SECONDS,
		'content' => 'LEAKCACHED',
	]
);
set_transient( 'elementor_agents_llms_full_txt', 'LEAKCACHED', HOUR_IN_SECONDS );
delete_option( \Stax\VisibilityLogic\Plain_Text::MARKDOWN_CACHE_OPTION );
\Stax\VisibilityLogic\Plain_Text::clear_markdown_cache();
vlm_is( 'cached Markdown of the post cleared', '', get_post_meta( $post_id, '_elementor_markdown_cache', true ) );
vlm_is( 'cached llms-full.txt cleared', false, get_transient( 'elementor_agents_llms_full_txt' ) );
vlm_is( 'cleared once per version', STAX_VISIBILITY_VERSION, get_option( \Stax\VisibilityLogic\Plain_Text::MARKDOWN_CACHE_OPTION ) );
set_transient( 'elementor_agents_llms_full_txt', 'kept', HOUR_IN_SECONDS );
\Stax\VisibilityLogic\Plain_Text::clear_markdown_cache();
vlm_is( 'not cleared again in the same version', 'kept', get_transient( 'elementor_agents_llms_full_txt' ) );
delete_transient( 'elementor_agents_llms_full_txt' );

// ---------------------------------------------------------------------------------------------------------------
wp_delete_post( $post_id, true );
if ( $page_rule_id ) {
	wp_delete_post( $page_rule_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $sub_id );

WP_CLI::log( sprintf( '%d passed, %d failed', $vlm['pass'], $vlm['fail'] ) );

if ( $vlm['fail'] ) {
	exit( 1 );
}
