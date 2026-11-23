<?php
/**
 * Visibility Logic 3.0: rules on menu items, classic widgets, and pages hidden from lists.
 *
 * Run on a test site with this plugin active:
 *   wp eval-file wp-content/plugins/visibility-logic-elementor/tests/area-rules-test.php
 *
 * Creates a menu, posts and two users, and deletes them at the end. Exit code 1 on failure.
 *
 * @package Stax\VisibilityLogic
 */

use Stax\VisibilityLogic\Area_Rules;
use Stax\VisibilityLogic\Context;
use Stax\VisibilityLogic\Page_Rules;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput

if ( ! class_exists( Area_Rules::class ) ) {
	WP_CLI::error( 'Visibility Logic 3.0 must be active.' );
}

global $vla;
$vla = [
	'pass' => 0,
	'fail' => 0,
];

/**
 * Assert.
 *
 * @param bool   $condition Condition.
 * @param string $label     Label.
 * @param mixed  $debug     Shown on failure.
 */
function vla_ok( $condition, $label, $debug = null ) {
	global $vla;
	if ( $condition ) {
		++$vla['pass'];
		WP_CLI::log( "  ok   {$label}" );
	} else {
		++$vla['fail'];
		WP_CLI::log( "  FAIL {$label}" . ( null !== $debug ? ': ' . wp_json_encode( $debug ) : '' ) );
	}
}

$members = static function ( $action = 'show', $draft = false ) {
	return [
		'v'      => 1,
		'action' => $action,
		'draft'  => $draft,
		'groups' => [ [ 'conditions' => [ [ 'id' => 'm1', 'type' => 'user_role', 'values' => [ 'user_role_conditions' => [ 'ecl-user' ] ] ] ] ] ],
	];
};

$sub_id = wp_insert_user( [ 'user_login' => 'vla_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ] );
$adm_id = wp_insert_user( [ 'user_login' => 'vla_adm_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ] );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( '== Sanitize' );

vla_ok( null === Area_Rules::sanitize( '' ) && null === Area_Rules::sanitize( '{"enabled":true}' ), 'no rule: nothing stored' );
vla_ok( null === Area_Rules::sanitize( [ 'enabled' => true, 'rules' => [ 'v' => 1, 'action' => 'show', 'groups' => [] ] ] ), 'empty tree: nothing stored' );
$clean = Area_Rules::sanitize( wp_json_encode( [ 'enabled' => 'no', 'rules' => $members() ] ) );
vla_ok( is_array( $clean ) && false === $clean['enabled'] && ! empty( $clean['rules']['groups'] ), 'JSON from the form is decoded, "no" means off', $clean );
vla_ok( is_array( Area_Rules::sanitize( [ 'enabled' => true, 'rules' => [ 'v' => 1, 'action' => 'show', 'draft' => true, 'groups' => [] ] ] ) ), 'hide from everyone without conditions is kept' );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( '== Menus' );

$menu_id = wp_create_nav_menu( 'VLA menu ' . wp_rand() );
$add     = static function ( $title, $parent = 0 ) use ( $menu_id ) {
	return wp_update_nav_menu_item(
		$menu_id,
		0,
		[
			'menu-item-title'     => $title,
			'menu-item-url'       => 'https://example.com/' . sanitize_title( $title ),
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent,
		]
	);
};
$top    = $add( 'VLA-MEMBERS' );
$child  = $add( 'VLA-CHILD', $top );
$grand  = $add( 'VLA-GRANDCHILD', $child );
$public = $add( 'VLA-PUBLIC' );

$menu_as = static function ( $user ) use ( $menu_id ) {
	wp_set_current_user( $user );
	return wp_nav_menu(
		[
			'menu'        => $menu_id,
			'echo'        => false,
			'fallback_cb' => '__return_empty_string',
		]
	);
};

update_post_meta( $top, Area_Rules::META, [ 'enabled' => true, 'rules' => $members() ] );
$guest = $menu_as( 0 );
vla_ok( false === strpos( $guest, 'VLA-MEMBERS' ) && false === strpos( $guest, 'VLA-CHILD' ) && false === strpos( $guest, 'VLA-GRANDCHILD' ), 'guest: hidden item and its submenu items (two levels) are gone', $guest );
vla_ok( false !== strpos( $guest, 'VLA-PUBLIC' ), 'guest: other items stay' );
$member = $menu_as( $sub_id );
vla_ok( false !== strpos( $member, 'VLA-MEMBERS' ) && false !== strpos( $member, 'VLA-GRANDCHILD' ), 'member sees the item and its submenu' );
$admin = $menu_as( $adm_id );
vla_ok( false !== strpos( $admin, 'VLA-MEMBERS' ), 'administrator (logged in) sees it: rules apply to everyone' );

update_post_meta( $top, Area_Rules::META, [ 'enabled' => false, 'rules' => $members() ] );
vla_ok( false !== strpos( $menu_as( 0 ), 'VLA-MEMBERS' ), 'Off (keep the rule): guest sees it' );

update_post_meta( $top, Area_Rules::META, [ 'enabled' => true, 'rules' => $members( 'show', true ) ] );
vla_ok( false === strpos( $menu_as( $adm_id ), 'VLA-MEMBERS' ), 'hide from everyone: hidden for administrators too' );

update_post_meta( $child, Area_Rules::META, [ 'enabled' => true, 'rules' => $members( 'hide' ) ] );
delete_post_meta( $top, Area_Rules::META );
$member = $menu_as( $sub_id );
vla_ok( false !== strpos( $member, 'VLA-MEMBERS' ) && false === strpos( $member, 'VLA-CHILD' ) && false === strpos( $member, 'VLA-GRANDCHILD' ), 'rule on a submenu item hides only that branch', $member );
delete_post_meta( $child, Area_Rules::META );

// Save from Appearance > Menus.
wp_set_current_user( $adm_id );
$_POST[ Area_Rules::MENU_FIELD ] = [ $public => wp_slash( wp_json_encode( [ 'enabled' => true, 'rules' => $members() ] ) ) ];
Area_Rules::save_menu_item( $menu_id, $public );
vla_ok( is_array( get_post_meta( $public, Area_Rules::META, true ) ), 'menu screen save stores the rule' );
$_POST[ Area_Rules::MENU_FIELD ] = [ $public => '' ];
Area_Rules::save_menu_item( $menu_id, $public );
vla_ok( '' === get_post_meta( $public, Area_Rules::META, true ), 'empty field deletes the rule' );
unset( $_POST[ Area_Rules::MENU_FIELD ] );
update_post_meta( $public, Area_Rules::META, [ 'enabled' => true, 'rules' => $members() ] );
Area_Rules::save_menu_item( $menu_id, $public );
vla_ok( is_array( get_post_meta( $public, Area_Rules::META, true ) ), 'saving without the field (other code) keeps the rule' );
wp_set_current_user( $sub_id );
$_POST[ Area_Rules::MENU_FIELD ] = [ $public => '' ];
Area_Rules::save_menu_item( $menu_id, $public );
vla_ok( is_array( get_post_meta( $public, Area_Rules::META, true ) ), 'users without edit_theme_options cannot change it' );
unset( $_POST[ Area_Rules::MENU_FIELD ] );

// Rules overview: one row per menu item with a rule.
$index = \Stax\VisibilityLogic\Rules::index( true );
$rows  = array_values( array_filter( \Stax\VisibilityLogic\Rules::overview_rows( $index, [ 'editor' => 'menus' ] ), static function ( $row ) use ( $public ) {
	return (string) $public === $row['element']['id'];
} ) );
vla_ok( 1 === count( $rows ) && 'VLA-PUBLIC' === $rows[0]['element']['name'] && false !== strpos( $rows[0]['summary'], 'Logged in' ), 'Rules overview lists the menu item with its rule in words', $rows ? $rows[0]['summary'] : null );
vla_ok( false !== strpos( \Stax\VisibilityLogic\Rules::edit_url( $menu_id, 'menus' ), 'nav-menus.php?action=edit&menu=' . $menu_id ), 'Edit link opens the menu' );

// Preview as guest.
wp_set_current_user( $adm_id );
Context::simulate( [ 'user' => 0 ] );
vla_ok( false === strpos( wp_nav_menu( [ 'menu' => $menu_id, 'echo' => false ] ), 'VLA-PUBLIC' ), 'Preview as guest applies menu rules' );
Context::reset();

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( '== Classic widgets' );

$widget = new WP_Widget_Text();
$widget->_set( 7 );
$rule = [ 'enabled' => true, 'rules' => $members() ];

wp_set_current_user( 0 );
vla_ok( false === Area_Rules::filter_widget( [ 'text' => 'x', Area_Rules::KEY => $rule ], $widget, [] ), 'guest: widget hidden' );
wp_set_current_user( $sub_id );
vla_ok( is_array( Area_Rules::filter_widget( [ 'text' => 'x', Area_Rules::KEY => $rule ], $widget, [] ) ), 'member: widget shown' );
vla_ok( is_array( Area_Rules::filter_widget( [ 'text' => 'x' ], $widget, [] ) ), 'widget without a rule: untouched' );

$saved = Area_Rules::save_widget( [ 'text' => 'x' ], [ 'text' => 'x', Area_Rules::KEY => wp_json_encode( $rule ) ], [], $widget );
vla_ok( isset( $saved[ Area_Rules::KEY ]['rules'] ), 'widget form save stores the rule' );
$saved = Area_Rules::save_widget( [ 'text' => 'y' ], [ 'text' => 'y' ], $saved, $widget );
vla_ok( isset( $saved[ Area_Rules::KEY ]['rules'] ), 'save without the field keeps the old rule' );
$saved = Area_Rules::save_widget( [ 'text' => 'y' ], [ 'text' => 'y', Area_Rules::KEY => '' ], $saved, $widget );
vla_ok( ! isset( $saved[ Area_Rules::KEY ] ), 'empty field removes the rule' );

ob_start();
Area_Rules::widget_field( $widget, null, [ Area_Rules::KEY => $rule ] );
$form = ob_get_clean();
vla_ok( false !== strpos( $form, 'stax-vl-area__value' ) && false !== strpos( $form, 'widget-text[7][' . Area_Rules::KEY . ']' ), 'widget form has the field with the widget field name', $form );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( '== Pages hidden from lists' );

$post = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'VLA members article' ] );
$open = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'VLA open article' ] );
update_post_meta( $post, Page_Rules::META, [ 'enabled' => true, 'hidden' => 'message', 'rules' => $members() ] );

$titles_as = static function ( $user ) use ( $post, $open ) {
	wp_set_current_user( $user );
	Page_Rules::reset();
	$query = new WP_Query( [ 'post__in' => [ $post, $open ], 'post_type' => 'post', 'orderby' => 'ID' ] );
	return wp_list_pluck( $query->posts, 'ID' );
};
vla_ok( true === Page_Rules::get( $post )['lists'], '"Also hide it from lists" is on by default' );
vla_ok( [ $open ] === array_values( $titles_as( 0 ) ), 'guest: hidden post left the list', $titles_as( 0 ) );
vla_ok( 2 === count( $titles_as( $sub_id ) ), 'member: both posts listed' );
vla_ok( 2 === count( $titles_as( $adm_id ) ), 'editor: both posts listed (exempt, as on the page)' );

update_post_meta( $post, Page_Rules::META, [ 'enabled' => true, 'hidden' => 'message', 'lists' => false, 'rules' => $members() ] );
vla_ok( 2 === count( $titles_as( 0 ) ), 'switched off: the post stays in lists for guests (teaser)' );

$page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'VLA members page' ] );
update_post_meta( $page, Page_Rules::META, [ 'enabled' => true, 'hidden' => 'message', 'rules' => $members() ] );
wp_set_current_user( 0 );
Page_Rules::reset();
vla_ok( ! in_array( $page, wp_list_pluck( get_pages( [ 'include' => [ $page ] ] ), 'ID' ), true ), 'get_pages (page lists, page menus): hidden page left' );

// ---------------------------------------------------------------------------------------------------------------
wp_set_current_user( 0 );
wp_delete_nav_menu( $menu_id );
foreach ( [ $top, $child, $grand, $public, $post, $open, $page ] as $id ) {
	wp_delete_post( $id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $sub_id );
wp_delete_user( $adm_id );

WP_CLI::log( "\n{$vla['pass']} passed, {$vla['fail']} failed" );
if ( $vla['fail'] ) {
	exit( 1 );
}
