<?php
/**
 * Visibility Logic: the content of elements and blocks with visibility rules stays out of `post_content` and out of
 * the automatic excerpt (REST `excerpt`, RSS/Atom, `/wp/v2/search?_embed`, `get_the_excerpt()`).
 *
 * Run on a test site with Elementor and this plugin active:
 *   wp eval-file wp-content/plugins/visibility-logic-elementor/tests/excerpt-test.php
 *
 * Patchstack report (2.5.3 and earlier): Elementor saves a plain-HTML copy of every widget in `post_content`, hidden
 * ones included, and WordPress builds the excerpt from it, so guests read "members only" text through every excerpt
 * channel. The posts are saved through Elementor's document save (as the editor does), then every channel is read as a
 * guest. Posts saved before the fix are simulated by writing the old plain text back to `post_content`. The posts and
 * the test user are deleted at the end. Exit code 1 when a check fails.
 *
 * @package Stax\VisibilityLogic
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.DB.DirectDatabaseQuery

if ( ! did_action( 'elementor/loaded' ) || ! defined( 'STAX_VISIBILITY_VERSION' ) ) {
	WP_CLI::error( 'Elementor and Visibility Logic must be active.' );
}

global $wpdb, $vlx;

$vlx = [
	'pass'  => 0,
	'fail'  => 0,
	'posts' => [],
];

/**
 * Assert.
 *
 * @param string $label    Label.
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 */
function vlx_is( $label, $expected, $actual ) {
	global $vlx;
	if ( $expected === $actual ) {
		++$vlx['pass'];
		WP_CLI::log( "  ok   {$label}" );
	} else {
		++$vlx['fail'];
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
function vlx_leaks( $text ) {
	preg_match_all( '/LEAK[A-Z]+/', (string) $text, $m );

	return array_values( array_unique( $m[0] ) );
}

/**
 * Markers of hidden content printed visibly (Keep HTML keeps its element in the page, hidden by CSS, by design).
 *
 * @param string $html HTML.
 *
 * @return string[]
 */
function vlx_shown( $html ) {
	return array_values( array_diff( vlx_leaks( $html ), [ 'LEAKKEEPHTML' ] ) );
}

/**
 * Text editor widget (saved format).
 *
 * @param string $id       Element id.
 * @param string $text     Text.
 * @param array  $settings Extra settings.
 *
 * @return array
 */
function vlx_text( $id, $text, array $settings = [] ) {
	return [
		'id'         => $id,
		'elType'     => 'widget',
		'widgetType' => 'text-editor',
		'settings'   => array_merge( [ 'editor' => '<p>' . $text . '</p>' ], $settings ),
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
function vlx_container( $id, array $children, array $settings = [] ) {
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

/**
 * Create a post and save its Elementor data through the document save, as the editor does.
 *
 * @param string $title    Title.
 * @param array  $elements Elements data.
 *
 * @return int
 */
function vlx_post( $title, array $elements ) {
	global $vlx;
	$id = wp_insert_post(
		[
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_type'   => 'post',
		]
	);
	$vlx['posts'][] = $id;
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	\Elementor\Plugin::$instance->documents->get( $id, false )->save( [ 'elements' => $elements ] );
	clean_post_cache( $id );

	return $id;
}

/**
 * Every excerpt channel of a post, read as the current user.
 *
 * @param int    $id     Post id.
 * @param string $search Search term that finds the post.
 *
 * @return string[] Channel => output.
 */
function vlx_channels( $id, $search ) {
	$out = [];

	clean_post_cache( $id );
	$out['get_the_excerpt'] = get_the_excerpt( $id );

	$response             = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $id ) );
	$data                 = $response->get_data();
	$out['REST excerpt']  = isset( $data['excerpt']['rendered'] ) ? $data['excerpt']['rendered'] : '(no excerpt)';
	$out['REST content']  = isset( $data['content']['rendered'] ) ? $data['content']['rendered'] : '(no content)';

	$request = new WP_REST_Request( 'GET', '/wp/v2/search' );
	$request->set_param( 'search', $search );
	$out['search _embed'] = wp_json_encode( rest_get_server()->response_to_data( rest_do_request( $request ), true ) );

	// RSS <description> and Atom <summary> both print the_excerpt_rss() inside the feed loop.
	$query = new WP_Query(
		[
			'p'         => $id,
			'post_type' => 'any',
		]
	);
	$query->the_post();
	ob_start();
	the_excerpt_rss();
	$out['feed excerpt'] = (string) ob_get_clean();
	wp_reset_postdata();

	return $out;
}

/**
 * Check that no channel leaks and the public text is still there.
 *
 * @param string $label  Label.
 * @param int    $id     Post id.
 * @param string $search Search term.
 * @param string $public Text every visitor may read.
 */
function vlx_check_channels( $label, $id, $search, $public ) {
	foreach ( vlx_channels( $id, $search ) as $channel => $output ) {
		if ( 'REST content' === $channel ) {
			vlx_is( "{$label}: {$channel} without hidden text (as on the page)", [], vlx_shown( $output ) );
			continue;
		}

		vlx_is( "{$label}: {$channel} without hidden text", [], vlx_leaks( $output ) );
		vlx_is( "{$label}: {$channel} keeps the public text", true, false !== strpos( $output, $public ) );
	}
}

/**
 * Elementor's front-end render of a post as the current user.
 *
 * @param int $id Post id.
 *
 * @return string
 */
function vlx_page( $id ) {
	return (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
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
		'user_login' => 'vlx_sub_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'vlx_sub_' . $suffix . '@example.test',
		'role'       => 'subscriber',
	]
);
if ( is_wp_error( $sub_id ) ) {
	WP_CLI::error( 'Could not create the test user.' );
}

$admins   = get_users(
	[
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	]
);
$admin_id = (int) reset( $admins );

WP_CLI::log( 'Visibility Logic ' . STAX_VISIBILITY_VERSION . ', Elementor ' . ELEMENTOR_VERSION . ', PHP ' . PHP_VERSION . ', WP ' . get_bloginfo( 'version' ) );

$elements = [
	vlx_container(
		'c1',
		[
			vlx_text( 'w1', 'Public teaser text.' ),
			vlx_text( 'w2', 'Members only: LEAKWIDGET', $logged_in ),
			vlx_text( 'w3', 'LEAKKEEPHTML', array_merge( $logged_in, [ 'stax_visibility_keep_html' => 'yes' ] ) ),
			vlx_text(
				'w4',
				'LEAKLEGACY',
				[
					'ecl_enabled'      => 'yes',
					'ecl_role_visible' => [ 'ecl-user' ],
				]
			),
		]
	),
	vlx_container( 'c2', [ vlx_container( 'c3', [ vlx_text( 'w5', 'LEAKCONTAINER' ) ] ) ], $logged_in ),
	[
		'id'       => 's1',
		'elType'   => 'section',
		'settings' => $logged_in,
		'elements' => [
			[
				'id'       => 'k1',
				'elType'   => 'column',
				'settings' => [ '_column_size' => 100 ],
				'elements' => [ vlx_text( 'w6', 'LEAKSECTION' ) ],
			],
		],
	],
];

$old_plain_text = "<p>Public teaser text.</p>\n<p>Members only: LEAKWIDGET</p>\n<p>LEAKKEEPHTML</p>\n<p>LEAKLEGACY</p>\n<p>LEAKCONTAINER</p>\n<p>LEAKSECTION</p>";

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'Saved from the Elementor editor' );
wp_set_current_user( $admin_id );
$_REQUEST['action'] = 'elementor'; // Elementor's edit mode, as in the editor's save request.
$post_id            = vlx_post( 'VLX members post ' . $suffix, $elements );
unset( $_REQUEST['action'] );

$content = get_post_field( 'post_content', $post_id );
vlx_is( 'post_content without hidden text', [], vlx_leaks( $content ) );
vlx_is( 'post_content keeps the public text', true, false !== strpos( $content, 'Public teaser text.' ) );

wp_set_current_user( 0 );
vlx_check_channels( 'guest', $post_id, 'VLX members post ' . $suffix, 'Public teaser text.' );
vlx_is( 'guest: core search does not find hidden words', 0, count( get_posts( [ 's' => 'LEAKWIDGET', 'p' => $post_id, 'post_type' => 'any' ] ) ) );

// The page itself: unchanged.
vlx_is( 'guest: page without hidden text', [], vlx_shown( vlx_page( $post_id ) ) );
wp_set_current_user( $sub_id );
vlx_is( 'logged in: page shows the members text', [ 'LEAKWIDGET', 'LEAKLEGACY', 'LEAKCONTAINER', 'LEAKSECTION' ], vlx_shown( vlx_page( $post_id ) ) );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'Saved outside the editor (import, REST, code)' );
wp_set_current_user( $admin_id );
$post_id_2 = vlx_post( 'VLX imported post ' . $suffix, $elements );
vlx_is( 'post_content without hidden text', [], vlx_leaks( get_post_field( 'post_content', $post_id_2 ) ) );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'Saved before the fix (old plain text still in post_content)' );
$wpdb->update( $wpdb->posts, [ 'post_content' => $old_plain_text ], [ 'ID' => $post_id ] );
clean_post_cache( $post_id );
wp_set_current_user( 0 );
vlx_check_channels( 'guest', $post_id, 'VLX members post ' . $suffix, 'Public teaser text.' );
wp_set_current_user( $sub_id );
vlx_is( 'logged in: excerpt without the members text too', [], vlx_leaks( get_the_excerpt( $post_id ) ) );

// Hand-written excerpt: untouched.
wp_update_post(
	[
		'ID'           => $post_id_2,
		'post_excerpt' => 'Hand written excerpt.',
	]
);
wp_set_current_user( 0 );
vlx_is( 'hand-written excerpt kept', 'Hand written excerpt.', get_the_excerpt( $post_id_2 ) );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'Documents without rules' );
wp_set_current_user( $admin_id );
$plain_id = vlx_post( 'VLX plain post ' . $suffix, [ vlx_container( 'c9', [ vlx_text( 'w9', 'Everyone reads PLAINWORD.' ) ] ) ] );
vlx_is( 'post_content as Elementor saves it', \Elementor\Plugin::$instance->db->get_plain_text( $plain_id ), get_post_field( 'post_content', $plain_id ) );
wp_set_current_user( 0 );
vlx_is( 'excerpt as before', true, false !== strpos( get_the_excerpt( $plain_id ), 'PLAINWORD' ) );

// ---------------------------------------------------------------------------------------------------------------
if ( class_exists( '\Stax\VisibilityLogic\Blocks' ) ) {
	WP_CLI::log( 'Block editor' );
	$members = [
		'enabled'              => true,
		'show_hide'            => 'yes',
		'user_role_enabled'    => true,
		'user_role_conditions' => [ 'ecl-user' ],
	];
	$p       = function ( $text, array $values = [] ) {
		$attrs = $values ? ' ' . wp_json_encode( [ 'staxVisibility' => $values ] ) : '';

		return "<!-- wp:paragraph{$attrs} -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
	};
	$blocks  = $p( 'Public block text.' ) . "\n\n"
		. $p( 'LEAKPARAGRAPH', $members ) . "\n\n"
		. '<!-- wp:group ' . wp_json_encode( [ 'staxVisibility' => $members ] ) . " -->\n<div class=\"wp-block-group\">" . $p( 'LEAKGROUP' ) . "</div>\n<!-- /wp:group -->\n\n"
		. "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column " . wp_json_encode( [ 'staxVisibility' => $members ] ) . " -->\n<div class=\"wp-block-column\">" . $p( 'LEAKCOLUMN' ) . "</div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\">" . $p( 'Second column text.' ) . "</div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->";

	wp_set_current_user( $admin_id );
	$block_id       = wp_insert_post(
		[
			'post_title'   => 'VLX block post ' . $suffix,
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => wp_slash( $blocks ),
		]
	);
	$vlx['posts'][] = $block_id;

	wp_set_current_user( 0 );
	vlx_check_channels( 'guest', $block_id, 'VLX block post ' . $suffix, 'Public block text.' );
	vlx_is( 'guest: excerpt keeps the visible column', true, false !== strpos( get_the_excerpt( $block_id ), 'Second column text.' ) );
	wp_set_current_user( $sub_id );
	vlx_is( 'logged in: page shows the members blocks', [ 'LEAKPARAGRAPH', 'LEAKGROUP', 'LEAKCOLUMN' ], vlx_leaks( apply_filters( 'the_content', get_post_field( 'post_content', $block_id ) ) ) );
}

// ---------------------------------------------------------------------------------------------------------------
foreach ( $vlx['posts'] as $id ) {
	wp_delete_post( $id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $sub_id );

WP_CLI::log( sprintf( '%d passed, %d failed', $vlx['pass'], $vlx['fail'] ) );

if ( $vlx['fail'] ) {
	exit( 1 );
}
