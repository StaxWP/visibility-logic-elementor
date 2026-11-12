<?php
/**
 * Visibility Logic: block editor (Gutenberg) visibility and loading without Elementor.
 *
 * Run on a test site with this plugin active, with or without Elementor:
 *   wp eval-file wp-content/plugins/visibility-logic-elementor/tests/blocks-test.php
 *   wp eval-file .../tests/blocks-test.php --skip-plugins=elementor,elementor-pro,visibility-logic-elementor-pro
 *
 * Blocks are parsed from block markup (the format saved in post_content) and rendered with render_block(), so the
 * real `render_block` filter runs. One temporary post and two users are created and deleted. Exit code 1 on failure.
 *
 * @package Stax\VisibilityLogic
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput

if ( ! defined( 'STAX_VISIBILITY_VERSION' ) ) {
	WP_CLI::error( 'Visibility Logic must be active.' );
}

global $vlb;
$vlb = [
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
function vlb_is( $label, $expected, $actual ) {
	global $vlb;
	if ( $expected === $actual ) {
		++$vlb['pass'];
		WP_CLI::log( "  ok   {$label}" );
	} else {
		++$vlb['fail'];
		WP_CLI::log( "  FAIL {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

/**
 * Render block markup and return the HTML.
 *
 * @param string $markup Block markup.
 *
 * @return string
 */
function vlb_render( $markup ) {
	$html = '';
	foreach ( parse_blocks( $markup ) as $block ) {
		$html .= render_block( $block );
	}

	return $html;
}

/**
 * Paragraph block markup with visibility values.
 *
 * @param string $text   Text (also the marker).
 * @param array  $values staxVisibility value, empty for none.
 *
 * @return string
 */
function vlb_p( $text, array $values = [] ) {
	$attrs = $values ? ' ' . wp_json_encode( [ 'staxVisibility' => $values ] ) : '';

	return "<!-- wp:paragraph{$attrs} -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
}

// Notices raised by the plugin during the run.
$vlb_notices = [];
set_error_handler( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_error_handler
	function ( $no, $str, $file ) use ( &$vlb_notices ) {
		if ( false !== strpos( $file, 'visibility-logic-elementor' ) ) {
			$vlb_notices[] = $str . ' in ' . basename( $file );
		}

		return false;
	}
);

$suffix = wp_generate_password( 6, false );
$sub    = wp_insert_user(
	[
		'user_login' => 'vlb_sub_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'vlb_sub_' . $suffix . '@example.test',
		'role'       => 'subscriber',
	]
);
update_user_meta( $sub, 'vlb_plan', 'gold' );

$elementor = defined( 'ELEMENTOR_VERSION' );
WP_CLI::log( 'Visibility Logic ' . STAX_VISIBILITY_VERSION . ', WP ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . ', Elementor ' . ( $elementor ? ELEMENTOR_VERSION : 'not active' ) );

WP_CLI::log( 'Loading' );
vlb_is( 'blocks module loaded', true, class_exists( '\Stax\VisibilityLogic\Blocks' ) );
vlb_is( 'free conditions registered for blocks', [ 'user_role', 'user_meta', 'date_time', 'browser_type', 'device_type', 'acf' ], array_values( array_intersect( [ 'user_role', 'user_meta', 'date_time', 'browser_type', 'device_type', 'acf' ], array_keys( \Stax\VisibilityLogic\Conditions::fields( 'blocks' ) ) ) ) );
vlb_is( 'settings panel registered', true, class_exists( '\Stax\VisibilityLogic\Settings' ) );
if ( ! $elementor ) {
	vlb_is( 'no Elementor: Elementor hooks not added', false, (bool) has_filter( 'elementor/frontend/widget/should_render' ) );
	vlb_is( 'no Elementor: no "requires Elementor" notice', false, (bool) has_action( 'admin_notices', [ \Stax\VisibilityLogic\Notices::instance(), 'elementor_notice' ] ) );
}
$paragraph = WP_Block_Type_Registry::get_instance()->get_registered( 'core/paragraph' );
vlb_is( 'attribute declared on core/paragraph', true, $paragraph && isset( $paragraph->attributes['staxVisibility'] ) );

WP_CLI::log( 'Render' );
$guests = [
	'enabled'              => true,
	'show_hide'            => 'yes',
	'user_role_enabled'    => true,
	'user_role_conditions' => [ 'ecl-guest' ],
];
wp_set_current_user( 0 );
vlb_is( 'no rules: rendered', true, false !== strpos( vlb_render( vlb_p( 'PLAIN' ) ), 'PLAIN' ) );
vlb_is( 'guests only: guest sees it', true, false !== strpos( vlb_render( vlb_p( 'GUESTS', $guests ) ), 'GUESTS' ) );
wp_set_current_user( $sub );
vlb_is( 'guests only: subscriber does not', '', trim( vlb_render( vlb_p( 'GUESTS', $guests ) ) ) );
vlb_is( 'rules switched off: rendered', true, false !== strpos( vlb_render( vlb_p( 'OFF', array_merge( $guests, [ 'enabled' => false ] ) ) ), 'OFF' ) );

$kept = vlb_render( vlb_p( 'KEPT', array_merge( $guests, [ 'keep_html' => true ] ) ) );
vlb_is( 'keep HTML: markup kept', true, false !== strpos( $kept, 'KEPT' ) );
vlb_is( 'keep HTML: hidden with CSS', true, false !== strpos( $kept, 'display: none' ) && false !== strpos( $kept, 'stax-visibility-hidden' ) );

$hide_guests = array_merge( $guests, [ 'show_hide' => '' ] );
vlb_is( 'hide for guests: subscriber sees it', true, false !== strpos( vlb_render( vlb_p( 'HIDEG', $hide_guests ) ), 'HIDEG' ) );

$one = array_merge(
	$guests,
	[
		'condition_type'    => 'one',
		'user_meta_enabled' => true,
		'user_meta_options' => 'vlb_plan',
		'user_meta_status'  => 'specific_value',
		'user_meta_value'   => 'gold',
	]
);
vlb_is( 'at least one (role or meta, list as text): subscriber with gold', true, false !== strpos( vlb_render( vlb_p( 'ONE', $one ) ), 'ONE' ) );
vlb_is( 'all (role and meta): subscriber hidden', '', trim( vlb_render( vlb_p( 'ALL', array_merge( $one, [ 'condition_type' => 'all' ] ) ) ) ) );

$past   = gmdate( 'Y-m-d\TH:i', time() - 2 * DAY_IN_SECONDS );
$future = gmdate( 'Y-m-d\TH:i', time() + 2 * DAY_IN_SECONDS );
$date   = [
	'enabled'           => true,
	'show_hide'         => 'yes',
	'date_time_enabled' => true,
	'date_time_type'    => 'date',
	'date_from'         => $past,
	'date_to'           => $future,
];
vlb_is( 'date range around now (HTML datetime-local value): shown', true, false !== strpos( vlb_render( vlb_p( 'DATE', $date ) ), 'DATE' ) );
vlb_is( 'date range in the future: hidden', '', trim( vlb_render( vlb_p( 'DATEF', array_merge( $date, [ 'date_from' => $future ] ) ) ) ) );

$ua                         = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
\Stax\VisibilityLogic\Resources::reset_cache();
$mobile = [
	'enabled'             => true,
	'show_hide'           => 'yes',
	'device_type_enabled' => true,
	'device_types'        => [ 'mobile' ],
];
vlb_is( 'device mobile on a phone: shown', true, false !== strpos( vlb_render( vlb_p( 'MOBILE', $mobile ) ), 'MOBILE' ) );
vlb_is( 'device desktop on a phone: hidden', '', trim( vlb_render( vlb_p( 'DESKTOP', array_merge( $mobile, [ 'device_types' => [ 'desktop' ] ] ) ) ) ) );
if ( null === $ua ) {
	unset( $_SERVER['HTTP_USER_AGENT'] );
} else {
	$_SERVER['HTTP_USER_AGENT'] = $ua;
}
\Stax\VisibilityLogic\Resources::reset_cache();

$group = '<!-- wp:group ' . wp_json_encode( [ 'staxVisibility' => $guests ] ) . " -->\n<div class=\"wp-block-group\">" . vlb_p( 'INSIDE' ) . "</div>\n<!-- /wp:group -->";
vlb_is( 'hidden group: inner blocks not printed', false, strpos( vlb_render( $group ), 'INSIDE' ) );
$group2 = "<!-- wp:group -->\n<div class=\"wp-block-group\">" . vlb_p( 'VISIBLE' ) . vlb_p( 'GONE', $guests ) . "</div>\n<!-- /wp:group -->";
$html   = vlb_render( $group2 );
vlb_is( 'inner block hidden, siblings and group kept', true, false !== strpos( $html, 'VISIBLE' ) && false === strpos( $html, 'GONE' ) && false !== strpos( $html, 'wp-block-group' ) );

$third = '<!-- wp:acme/promo ' . wp_json_encode( [ 'staxVisibility' => $guests ] ) . ' --><div class="acme">THIRD</div><!-- /wp:acme/promo -->';
vlb_is( 'unregistered third-party block: hidden for subscriber', false, strpos( vlb_render( $third ), 'THIRD' ) );
wp_set_current_user( 0 );
vlb_is( 'unregistered third-party block: shown for guest', true, false !== strpos( vlb_render( $third ), 'THIRD' ) );

if ( defined( 'STAX_VISIBILITY_PRO_VERSION' ) && class_exists( '\Stax\VisibilityLogicPro\QueryStringVisibility' ) ) {
	WP_CLI::log( 'Visibility Logic Pro condition on a block (same filter, no fields registered)' );
	$qs = [
		'enabled'                 => true,
		'show_hide'               => 'yes',
		'query_string_enabled'    => true,
		'query_string_conditions' => [
			[
				'_id'             => 'q1',
				'param_name'      => 'vlb_q',
				'param_condition' => 'exists',
			],
		],
	];
	vlb_is( 'pro url parameter missing: hidden', '', trim( vlb_render( vlb_p( 'PROQS', $qs ) ) ) );
	$_REQUEST['vlb_q'] = '1';
	$_GET['vlb_q']     = '1';
	vlb_is( 'pro url parameter present: shown', true, false !== strpos( vlb_render( vlb_p( 'PROQS', $qs ) ), 'PROQS' ) );
	unset( $_REQUEST['vlb_q'], $_GET['vlb_q'] );
}

WP_CLI::log( 'Front-end assets' );
do_action( 'wp_enqueue_scripts' );
vlb_is( 'no block editor script on the front end', false, wp_script_is( 'stax-visibility-blocks', 'enqueued' ) );
vlb_is( 'no block editor style on the front end', false, wp_style_is( 'stax-visibility-blocks', 'enqueued' ) );

WP_CLI::log( 'Rules in use' );
$post_id = wp_insert_post(
	[
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'VLB blocks ' . $suffix,
		'post_content' => vlb_p( 'A' ) . $group . vlb_p( 'B', $date ),
	]
);
$index   = \Stax\VisibilityLogic\Rules::index( true );
$doc     = null;
foreach ( $index['documents'] as $document ) {
	if ( (int) $document['id'] === (int) $post_id ) {
		$doc = $document;
	}
}
vlb_is( 'block post listed', true, null !== $doc );
vlb_is( 'block post: 2 blocks with rules', 2, $doc ? $doc['count'] : 0 );
vlb_is( 'block post: builder', 'blocks', $doc ? $doc['builder'] : '' );
vlb_is( 'block post: conditions', [ 'user_role', 'date_time' ], $doc ? $doc['conditions'] : [] );
wp_delete_post( $post_id, true );
\Stax\VisibilityLogic\Rules::flush();

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $sub );
wp_set_current_user( 0 );
restore_error_handler();

vlb_is( 'no PHP notices from the plugin', [], $vlb_notices );

WP_CLI::log( sprintf( '%d passed, %d failed', $vlb['pass'], $vlb['fail'] ) );
if ( $vlb['fail'] ) {
	exit( 1 );
}
