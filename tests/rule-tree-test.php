<?php
/**
 * Visibility Logic 3.0 rule builder: old-vs-new equivalence suite and the 3.0 additions.
 *
 *   wp eval-file wp-content/plugins/visibility-logic-elementor/tests/rule-tree-test.php
 *
 * 1. Equivalence: a generated matrix of 2.x settings (every free condition with several values, 1 to 3 conditions,
 *    Show/Hide, All/At least one, rules off) is evaluated for 4 visitors x 3 devices x 2 moments:
 *    - 2.x evaluator (Conditions::evaluate on the flat keys)
 *    - the same rule as a tree (Rule_Tree::legacy_to_tree + evaluate), with default true and false
 *    - the tree stored in `stax_visibility_rules` with the fail-closed 2.x keys (what the builder writes)
 *    must all give the same answer; the round trip (tree back to settings, nothing edited) writes no key; the
 *    fail-closed keys alone (what 2.x reads after a downgrade) show the element to administrators only.
 * 2. 3.0 additions: B1 yearly period, B2 specific users / capability / all roles, B4 draft, B6 operators, B5
 *    columns, groups, storage decisions, summaries, warnings, block and Elementor rendering of stored trees.
 * 3. Writes tests/fixtures/rule-map.golden.json for the JS twin (node tests/js/rule-map.test.cjs).
 *
 * Creates 3 users and deletes them; general settings restored at the end. Exit code 1 on failure.
 *
 * @package Stax\VisibilityLogic
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions

use Stax\VisibilityLogic\Conditions;
use Stax\VisibilityLogic\Condition_Item;
use Stax\VisibilityLogic\Context;
use Stax\VisibilityLogic\Rule_Tree;
use Stax\VisibilityLogic\Blocks;

if ( ! defined( 'STAX_VISIBILITY_VERSION' ) ) {
	WP_CLI::error( 'Visibility Logic must be active.' );
}

if ( ! function_exists( 'get_field' ) ) {
	/**
	 * ACF stub.
	 *
	 * @param string $name Field.
	 *
	 * @return mixed
	 */
	function get_field( $name ) {
		return isset( $GLOBALS['vlt_acf'][ $name ] ) ? $GLOBALS['vlt_acf'][ $name ] : null;
	}
}
$GLOBALS['vlt_acf'] = [ 'promo' => 'Summer Sale' ];

$vlt_backup = get_option( 'stax_visibility_general', false );
update_option( 'stax_visibility_general', [ 'site_timezone' => true, 'container_widgets' => true, 'element_cache' => false ] );
Rule_Tree::reset();
Conditions::reset();

$vlt = [ 'pass' => 0, 'fail' => 0, 'fails' => [] ];
$ok  = static function ( $cond, $label, $details = null ) use ( &$vlt ) {
	if ( $cond ) {
		++$vlt['pass'];
		return;
	}
	++$vlt['fail'];
	$vlt['fails'][] = $label;
	WP_CLI::log( 'FAIL ' . $label . ( null === $details ? '' : ' ' . wp_json_encode( $details ) ) );
};
$section = static function ( $title ) {
	WP_CLI::log( '== ' . $title );
};
$p = static function ( array $values ) {
	$out = [];
	foreach ( $values as $k => $v ) {
		$out[ 'stax_visibility_' . $k ] = $v;
	}
	return $out;
};
// Like Elementor: control defaults filled in for keys that were never saved.
$eval = static function ( array $settings, $default = true ) {
	$values = [];
	foreach ( $settings as $k => $v ) {
		if ( 0 === strpos( $k, 'stax_visibility_' ) && 'stax_visibility_rules' !== $k ) {
			$values[ substr( $k, 16 ) ] = $v;
		}
	}
	$settings = array_merge( Conditions::to_settings( $values ), $settings );

	return Conditions::evaluate( $settings, new Condition_Item( $settings, 't1', 'heading', 'element' ), $default );
};

// Users.
$users = [ 0 => 'guest' ];
foreach ( [ 'subscriber', 'editor', 'administrator' ] as $role ) {
	$login = 'vlt_' . $role . '_' . wp_generate_password( 6, false );
	$id    = wp_insert_user( [ 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role, 'first_name' => 'subscriber' === $role ? 'Ana' : '' ] );
	if ( ! is_wp_error( $id ) ) {
		$users[ $id ] = $role;
		update_user_meta( $id, 'vlt_level', 'editor' === $role ? '7' : '3' );
	}
}
$cleanup_users = array_filter( array_keys( $users ) );

// ---------------------------------------------------------------------------------------------------------------
$section( '1. Equivalence: 2.x settings vs the same rule as a tree' );

$now        = time();
$today      = (int) wp_date( 'w', $now );
$variants   = [
	'role_guest'    => [ 'user_role_enabled' => 'yes', 'user_role_conditions' => [ 'ecl-guest' ] ],
	'role_user'     => [ 'user_role_enabled' => 'yes', 'user_role_conditions' => [ 'ecl-user' ] ],
	'role_sub'      => [ 'user_role_enabled' => 'yes', 'user_role_conditions' => [ 'subscriber' ] ],
	'role_ed_adm'   => [ 'user_role_enabled' => 'yes', 'user_role_conditions' => [ 'editor', 'administrator' ] ],
	'role_empty'    => [ 'user_role_enabled' => 'yes', 'user_role_conditions' => [] ],
	'dev_mobile'    => [ 'device_type_enabled' => 'yes', 'device_types' => [ 'mobile' ] ],
	'dev_desk_tab'  => [ 'device_type_enabled' => 'yes', 'device_types' => [ 'desktop', 'tablet' ] ],
	'browser'       => [ 'browser_type_enabled' => 'yes', 'browsers' => [ 'is_chrome' ] ],
	'date_now'      => [ 'date_time_enabled' => 'yes', 'date_time_type' => 'date', 'date_from' => wp_date( 'Y-m-d H:i', $now - DAY_IN_SECONDS ), 'date_to' => wp_date( 'Y-m-d H:i', $now + DAY_IN_SECONDS ) ],
	'date_past'     => [ 'date_time_enabled' => 'yes', 'date_time_type' => 'date', 'date_from' => '2020-01-01 00:00', 'date_to' => '2020-02-01 00:00' ],
	'week_today'    => [ 'date_time_enabled' => 'yes', 'date_time_type' => 'week_days', 'time_week' => [ (string) $today ] ],
	'week_other'    => [ 'date_time_enabled' => 'yes', 'date_time_type' => 'week_days', 'time_week' => [ (string) ( ( $today + 3 ) % 7 ) ] ],
	'meta_name'     => [ 'user_meta_enabled' => 'yes', 'user_meta_options' => [ 'first_name' ], 'user_meta_status' => 'not_empty' ],
	'meta_level'    => [ 'user_meta_enabled' => 'yes', 'user_meta_options' => [ 'vlt_level' ], 'user_meta_status' => 'greater_than', 'user_meta_value' => '5' ],
	'acf_promo'     => [ 'acf_enabled' => 'yes', 'acf_field' => 'promo', 'acf_condition' => 'contains', 'acf_value' => 'Sale' ],
	'acf_missing'   => [ 'acf_enabled' => 'yes', 'acf_field' => 'nope', 'acf_condition' => 'not_empty' ],
	'acf_nofield'   => [ 'acf_enabled' => 'yes', 'acf_field' => '' ],
	'switch_off'    => [ 'device_type_enabled' => '', 'device_types' => [ 'mobile' ] ],
];
$names      = array_keys( $variants );
$type_of    = static function ( $name ) use ( $variants ) {
	foreach ( $variants[ $name ] as $k => $v ) {
		if ( '_enabled' === substr( $k, -8 ) ) {
			return $k;
		}
	}
	return $name;
};
$rule_sets  = [];
foreach ( $names as $a ) {
	$rule_sets[] = [ $a ];
}
$count = count( $names );
for ( $i = 0; $i < $count; $i++ ) {
	for ( $j = $i + 1; $j < $count; $j += 2 ) {
		if ( $type_of( $names[ $i ] ) !== $type_of( $names[ $j ] ) ) {
			$rule_sets[] = [ $names[ $i ], $names[ $j ] ];
		}
	}
}
for ( $i = 0; $i + 2 < $count; $i += 2 ) {
	$set = [ $names[ $i ], $names[ ( $i + 5 ) % $count ], $names[ ( $i + 9 ) % $count ] ];
	if ( count( array_unique( array_map( $type_of, $set ) ) ) === 3 ) {
		$rule_sets[] = $set;
	}
}

$matrix = [];
foreach ( $rule_sets as $set ) {
	foreach ( [ 'yes', '' ] as $show ) {
		foreach ( [ 'all', 'one' ] as $type ) {
			$values = [ 'enabled' => 'yes', 'show_hide' => $show, 'condition_type' => $type ];
			foreach ( $set as $name ) {
				$values = array_merge( $values, $variants[ $name ] );
			}
			$matrix[ implode( '+', $set ) . '|' . ( $show ? 'show' : 'hide' ) . '|' . $type ] = $p( $values );
		}
	}
}
// Rules turned off, and a 2.x element whose condition type was never saved (Elementor default).
$matrix['off'] = $p( [ 'enabled' => '', 'user_role_enabled' => 'yes', 'user_role_conditions' => [ 'ecl-guest' ] ] );
$nosave        = $p( [ 'enabled' => 'yes', 'show_hide' => 'yes', 'user_role_enabled' => 'yes', 'user_role_conditions' => [ 'subscriber' ], 'device_type_enabled' => 'yes', 'device_types' => [ 'desktop' ] ] );
$matrix['no condition_type saved'] = $nosave;

$contexts = [];
foreach ( $users as $uid => $role ) {
	foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) {
		foreach ( [ 'now' => $now, 'in 4 days' => $now + 4 * DAY_IN_SECONDS ] as $when => $time ) {
			$contexts[] = [ $uid, $role, $device, $when, $time ];
		}
	}
}

$checks = 0;
$sample = [];
foreach ( $matrix as $label => $settings ) {
	$tree      = Rule_Tree::legacy_to_tree( $settings );
	$roundtrip = Rule_Tree::to_storage( $tree, $settings );
	$ok( [] === $roundtrip, "round trip writes nothing: $label", $roundtrip );

	$stored = array_merge( $settings, Rule_Tree::fail_closed( $settings ), [ 'stax_visibility_rules' => $tree ] );

	foreach ( $contexts as $ctx ) {
		wp_set_current_user( $ctx[0] );
		Context::simulate( [ 'device' => $ctx[2], 'time' => $ctx[4] ] );

		$legacy       = $eval( $settings );
		$legacy_false = $eval( $settings, false );
		$from_tree    = Rule_Tree::evaluate( $tree, null, true )['visible'];
		$from_tree_f  = Rule_Tree::evaluate( $tree, null, false )['visible'];
		$enabled      = ! empty( $settings['stax_visibility_enabled'] );

		if ( $enabled ) {
			$ok( $legacy === $from_tree && $legacy_false === $from_tree_f, "tree = 2.x: $label / {$ctx[1]} {$ctx[2]} {$ctx[3]}", [ $legacy, $from_tree, $legacy_false, $from_tree_f ] );
			if ( ! \StaxWP\Conditions\V1\Tree::is_empty( $tree ) ) {
				$ok( $legacy === $eval( $stored ), "stored tree = 2.x: $label / {$ctx[1]} {$ctx[2]} {$ctx[3]}" );
			}
			$checks += 3;
		}

		if ( 0 === count( $sample ) % 1 && count( $sample ) < 40 && 'now' === $ctx[3] && 'desktop' === $ctx[2] ) {
			$sample[] = $label . ' / ' . $ctx[1] . ' => ' . ( $legacy ? 'shown' : 'hidden' );
		}
	}

	// Downgrade: Visibility Logic 2.x reads only the fail-closed keys.
	if ( ! empty( $settings['stax_visibility_enabled'] ) && ! \StaxWP\Conditions\V1\Tree::is_empty( $tree ) ) {
		$flat_only = array_merge( $settings, Rule_Tree::fail_closed( $settings ) );
		foreach ( $users as $uid => $role ) {
			wp_set_current_user( $uid );
			Context::reset();
			$ok( ( 'administrator' === $role ) === $eval( $flat_only ), "fail closed after downgrade: $label / $role" );
		}
	}
}
Context::reset();
WP_CLI::log( sprintf( '   %d rules x %d visitor contexts, %d equivalence checks', count( $matrix ), count( $contexts ), $checks ) );

// Digest of the 2.x answers (compare with a 2.5.2 run of the same matrix, see docs/rule-builder.md).
$digest = [];
foreach ( $matrix as $label => $settings ) {
	foreach ( $users as $uid => $role ) {
		wp_set_current_user( $uid );
		Context::simulate( [ 'device' => 'desktop', 'time' => $now ] );
		$digest[] = $eval( $settings ) ? '1' : '0';
	}
}
Context::reset();
WP_CLI::log( '   2.x answers digest: ' . md5( implode( '', $digest ) ) . ' (' . count( $digest ) . ' answers)' );

// ---------------------------------------------------------------------------------------------------------------
$section( '2. Storage decisions (2.x format when possible, tree + fail closed otherwise)' );

$cond = static function ( $type, array $values, $id = null ) {
	static $n = 0;
	return [ 'id' => $id ? $id : 'n' . ( ++$n ), 'type' => $type, 'values' => $values ];
};
$tree_of = static function ( $action, array $groups, $draft = false ) {
	return [ 'v' => 1, 'action' => $action, 'draft' => $draft, 'groups' => array_map( static function ( $g ) {
		return [ 'conditions' => $g ];
	}, $groups ) ];
};
$role_sub  = $cond( 'user_role', [ 'user_role_conditions' => [ 'subscriber' ] ] );
$dev_mob   = $cond( 'device_type', [ 'device_types' => [ 'mobile' ] ] );
$role_ed   = $cond( 'user_role', [ 'user_role_conditions' => [ 'editor' ] ] );
$yearly    = $cond( 'date_time', [ 'date_time_type' => 'yearly', 'date_year_from' => '12-20', 'date_year_to' => '01-11' ] );
$by_users  = $cond( 'user_role', [ 'user_role_by' => 'users', 'user_role_users' => [ '1' ] ] );
$meta_ci   = $cond( 'user_meta', [ 'user_meta_options' => [ 'first_name' ], 'user_meta_status' => 'equals_ci', 'user_meta_value' => 'ana' ] );

$cases = [
	'one group, two conditions'          => [ $tree_of( 'show', [ [ $role_sub, $dev_mob ] ] ), true, '' ],
	'one condition per group'            => [ $tree_of( 'show', [ [ $role_sub ], [ $dev_mob ] ] ), true, '' ],
	'(A and B) or C'                     => [ $tree_of( 'show', [ [ $role_sub, $dev_mob ], [ $cond( 'browser_type', [ 'browsers' => [ 'is_chrome' ] ] ) ] ] ), false, 'groups' ],
	'same condition twice'               => [ $tree_of( 'show', [ [ $role_sub ], [ $role_ed ] ] ), false, 'repeat' ],
	'draft'                              => [ $tree_of( 'show', [ [ $role_sub ] ], true ), false, 'draft' ],
	'B1 yearly (3.0 option)'             => [ $tree_of( 'show', [ [ $yearly ] ] ), false, 'new' ],
	'B2 specific users (3.0 option)'     => [ $tree_of( 'show', [ [ $by_users ] ] ), false, 'new' ],
	'B6 ignore case (3.0 operator)'      => [ $tree_of( 'show', [ [ $meta_ci ] ] ), false, 'new' ],
	'empty group ignored'                => [ $tree_of( 'hide', [ [ $role_sub ], [] ] ), true, '' ],
	'roles mode set to default'          => [ $tree_of( 'show', [ [ $cond( 'user_role', [ 'user_role_by' => 'roles', 'user_role_match' => 'any', 'user_role_conditions' => [ 'ecl-guest' ] ] ) ] ] ), true, '' ],
];
foreach ( $cases as $label => $case ) {
	$a = Rule_Tree::analyze( $case[0] );
	$ok( $case[1] === $a['flat'] && $case[2] === $a['reason'], "analyze: $label", $a );
}

$base = $p( [ 'enabled' => 'yes', 'show_hide' => '', 'condition_type' => 'all', 'browser_type_enabled' => 'yes', 'browsers' => [ 'is_safari' ] ] );
$changes = Rule_Tree::to_storage( $cases['one condition per group'][0], $base );
$ok( 'yes' === $changes['stax_visibility_show_hide'] && 'one' === $changes['stax_visibility_condition_type'] && '' === $changes['stax_visibility_browser_type_enabled'] && 'yes' === $changes['stax_visibility_user_role_enabled'] && ! isset( $changes['stax_visibility_rules'] ), '2.x format: show, at least one, removed condition switched off, no tree', $changes );
$changes = Rule_Tree::to_storage( $cases['(A and B) or C'][0], $base );
$ok( isset( $changes['stax_visibility_rules']['groups'] ) && 'yes' === $changes['stax_visibility_show_hide'] && [ 'administrator' ] === $changes['stax_visibility_user_role_conditions'] && '' === $changes['stax_visibility_browser_type_enabled'], 'tree format: tree + fail-closed 2.x keys (administrators only)', $changes );
$with_tree = array_merge( $base, [ 'stax_visibility_rules' => $cases['(A and B) or C'][0] ] );
$changes   = Rule_Tree::to_storage( $cases['one group, two conditions'][0], $with_tree );
$ok( array_key_exists( 'stax_visibility_rules', $changes ) && '' === $changes['stax_visibility_rules'], 'back to 2.x format: the tree key is cleared' );
$readonly = $tree_of( 'show', [ [ $role_sub ], [ $cond( 'some_addon', [] ), $dev_mob ] ] );
$ok( null === Rule_Tree::to_storage( $readonly, $base ), 'a read-only (unknown add-on) condition cannot go into a tree' );
$ok( [] === Rule_Tree::to_storage( Rule_Tree::legacy_to_tree( $p( [ 'enabled' => 'yes', 'some_addon_enabled' => 'yes', 'some_addon_x' => 'a' ] ) ), $p( [ 'enabled' => 'yes', 'some_addon_enabled' => 'yes', 'some_addon_x' => 'a' ] ) ), 'an unknown add-on condition is kept as is in the 2.x format' );

// ---------------------------------------------------------------------------------------------------------------
$section( '3. B1 yearly period, B2 who, B4 draft, B6 operators, groups (stored trees)' );

$stored_eval = static function ( array $tree, $default = true ) use ( $p, $eval ) {
	$settings = array_merge( $p( [ 'enabled' => 'yes' ] ), Rule_Tree::fail_closed( [] ), [ 'stax_visibility_rules' => $tree ] );
	return $eval( $settings, $default );
};
$at = static function ( $date ) {
	return ( new DateTimeImmutable( $date, wp_timezone() ) )->getTimestamp();
};
$y = static function ( $from, $to, $from_time = '', $to_time = '' ) use ( $tree_of, $cond ) {
	return $tree_of( 'show', [ [ $cond( 'date_time', [ 'date_time_type' => 'yearly', 'date_year_from' => $from, 'date_year_to' => $to, 'date_year_from_time' => $from_time, 'date_year_to_time' => $to_time ] ) ] ] );
};
wp_set_current_user( 0 );
foreach (
	[
		[ '2026-12-25 10:00', '12-20', '01-11', '', '', true, 'year-end wrap, December' ],
		[ '2027-01-05 10:00', '12-20', '01-11', '', '', true, 'year-end wrap, January' ],
		[ '2026-06-01 10:00', '12-20', '01-11', '', '', false, 'year-end wrap, June' ],
		[ '2026-07-15 10:00', '07-01', '07-31', '', '', true, 'in range' ],
		[ '2026-08-01 00:00', '07-01', '07-31', '', '', false, 'day after the range' ],
		[ '2026-07-31 23:59', '07-01', '07-31', '', '', true, 'last minute of the range' ],
		[ '2027-02-28 12:00', '02-29', '03-01', '', '', true, '29 Feb bound = 28 Feb in a non-leap year' ],
		[ '2028-02-28 12:00', '02-29', '03-01', '', '', false, '29 Feb bound in a leap year' ],
		[ '2026-07-01 08:00', '07-01', '07-02', '09:00', '', false, 'start time not reached' ],
		[ '2026-07-01 09:30', '07-01', '07-02', '09:00', '', true, 'after the start time' ],
		[ '2026-07-02 18:00', '07-01', '07-02', '', '17:00', false, 'after the end time' ],
		[ '2026-03-03 12:00', '', '', '', '', true, 'empty bounds = whole year' ],
	] as $row
) {
	Context::simulate( [ 'time' => $at( $row[0] ) ] );
	$ok( $row[5] === $stored_eval( $y( $row[1], $row[2], $row[3], $row[4] ) ), 'B1 yearly: ' . $row[6] );
}
// Time zones: the same instant is inside the range in one zone and outside in another.
$utc_noon = ( new DateTimeImmutable( '2026-07-31 23:30', new DateTimeZone( 'UTC' ) ) )->getTimestamp();
foreach ( [ 'UTC' => true, 'Europe/Bucharest' => false, 'America/New_York' => true, 'Asia/Tokyo' => false ] as $zone => $expected ) {
	$filter = static function () use ( $zone ) {
		return new DateTimeZone( $zone );
	};
	add_filter( 'stax/visibility/date_timezone', $filter );
	Context::simulate( [ 'time' => $utc_noon ] );
	$ok( $expected === $stored_eval( $y( '07-01', '07-31' ) ), "B1 yearly: time zone $zone" );
	remove_filter( 'stax/visibility/date_timezone', $filter );
}
Context::reset();

$sub_id = array_search( 'subscriber', $users, true );
$ed_id  = array_search( 'editor', $users, true );
$adm_id = array_search( 'administrator', $users, true );
$who    = static function ( array $values ) use ( $tree_of, $cond ) {
	return $tree_of( 'show', [ [ $cond( 'user_role', $values ) ] ] );
};
$expect = [
	[ $who( [ 'user_role_by' => 'users', 'user_role_users' => [ (string) $sub_id ] ] ), [ 0 => false, $sub_id => true, $ed_id => false ], 'B2 specific users by id' ],
	[ $who( [ 'user_role_by' => 'users', 'user_role_users' => [ '999999' ] ] ), [ 0 => false, $sub_id => false ], 'B2 deleted user id never matches' ],
	[ $who( [ 'user_role_by' => 'capability', 'user_role_caps' => [ 'edit_others_posts' ] ] ), [ 0 => false, $sub_id => false, $ed_id => true, $adm_id => true ], 'B2 capability from the role' ],
	[ $who( [ 'user_role_by' => 'roles', 'user_role_match' => 'all', 'user_role_conditions' => [ 'ecl-user', 'editor' ] ] ), [ 0 => false, $sub_id => false, $ed_id => true ], 'B2 all selected roles' ],
	[ $who( [ 'user_role_by' => 'roles', 'user_role_match' => 'all', 'user_role_conditions' => [ 'subscriber', 'editor' ] ] ), [ $sub_id => false, $ed_id => false ], 'B2 all roles with one missing' ],
];
foreach ( $expect as $row ) {
	foreach ( $row[1] as $uid => $visible ) {
		wp_set_current_user( $uid );
		$ok( $visible === $stored_eval( $row[0] ), $row[2] . ' / ' . ( $users[ $uid ] ?? $uid ) );
	}
}
if ( $sub_id ) {
	$u = get_userdata( $sub_id );
	$u->add_cap( 'vlt_special' );
	wp_set_current_user( $sub_id );
	$ok( true === $stored_eval( $who( [ 'user_role_by' => 'capability', 'user_role_caps' => [ 'vlt_special' ] ] ) ), 'B2 per-user capability' );
}

// B4 draft.
wp_set_current_user( $adm_id );
$ok( false === $stored_eval( $tree_of( 'show', [ [] ], true ) ), 'B4 draft without conditions: hidden, even for administrators' );
$ok( false === $stored_eval( $tree_of( 'show', [ [ $cond( 'user_role', [ 'user_role_conditions' => [ 'administrator' ] ] ) ] ], true ) ), 'B4 draft overrides matching conditions' );
$ok( false === $stored_eval( $tree_of( 'hide', [ [ $role_sub ] ], true ) ), 'B4 draft with hide action: hidden' );

// B6 operators in User Meta (3.0 operators through the shared library).
wp_set_current_user( $sub_id );
foreach (
	[
		[ 'equals_ci', 'ANA', '', true ], [ 'not_equals_ci', 'ana', '', false ], [ 'starts_with', 'An', '', true ], [ 'ends_with', 'na', '', true ],
		[ 'not_in', 'Bob, Ana', '', false ], [ 'not_in', 'Bob', '', true ],
	] as $row
) {
	$t = $tree_of( 'show', [ [ $cond( 'user_meta', [ 'user_meta_options' => [ 'first_name' ], 'user_meta_status' => $row[0], 'user_meta_value' => $row[1] ] ) ] ] );
	$ok( $row[3] === $stored_eval( $t ), "B6 user meta {$row[0]} {$row[1]}" );
}
foreach ( [ [ 'lte', '3', true ], [ 'lte', '2', false ], [ 'gte', '3', true ], [ 'gte', 'x', false ] ] as $row ) {
	$t = $tree_of( 'show', [ [ $cond( 'user_meta', [ 'user_meta_options' => [ 'vlt_level' ], 'user_meta_status' => $row[0], 'user_meta_value' => $row[1] ] ) ] ] );
	$ok( $row[2] === $stored_eval( $t ), "B6 user meta {$row[0]} {$row[1]} (value 3)" );
}
foreach ( [ [ 'equals_ci', 'summer sale', true ], [ 'starts_with', 'Summer', true ], [ 'ends_with', 'Sale', true ], [ 'not_in', 'A,Summer Sale', false ] ] as $row ) {
	$t = $tree_of( 'show', [ [ $cond( 'acf', [ 'acf_field' => 'promo', 'acf_condition' => $row[0], 'acf_value' => $row[1] ] ) ] ] );
	$ok( $row[2] === $stored_eval( $t ), "B6 ACF {$row[0]} {$row[1]}" );
}
// 2.x operators keep their 2.x answers (they do not go through the library).
$t = $tree_of( 'show', [ [ $cond( 'user_meta', [ 'user_meta_options' => [ 'vlt_level' ], 'user_meta_status' => 'less_than', 'user_meta_value' => '3' ] ) ] ] );
$ok( true === $stored_eval( $t ), '2.x less_than stays inclusive (2.x answer)' );

// Groups: (subscriber and mobile) or editor.
$t = $tree_of( 'show', [ [ $role_sub, $dev_mob ], [ $role_ed ] ] );
foreach ( [ [ $sub_id, 'mobile', true ], [ $sub_id, 'desktop', false ], [ $ed_id, 'desktop', true ], [ 0, 'mobile', false ] ] as $row ) {
	wp_set_current_user( $row[0] );
	Context::simulate( [ 'device' => $row[1] ] );
	$ok( $row[2] === $stored_eval( $t ), '(subscriber and mobile) or editor: ' . ( $users[ $row[0] ] ?? '' ) . ' ' . $row[1] );
}
Context::reset();

// Role preview (Context): a role without an account.
Context::simulate( [ 'roles' => [ 'editor' ] ] );
$ok( true === $stored_eval( $who( [ 'user_role_conditions' => [ 'editor' ] ] ) ) && true === $stored_eval( $who( [ 'user_role_by' => 'capability', 'user_role_caps' => [ 'edit_others_posts' ] ] ) ), 'preview as role editor: role and capability' );
Context::simulate( [ 'roles' => [] ] );
$ok( true === $stored_eval( $who( [ 'user_role_conditions' => [ 'ecl-guest' ] ] ) ), 'preview as guest' );
Context::reset();
$ok( get_current_user_id() === $row[0], 'preview never changes the current user' );

// Preview as / why hidden (REST explain).
wp_set_current_user( $adm_id );
\Stax\VisibilityLogic\Preview::simulate( [ 'as' => 'guest', 'device' => 'mobile' ] );
$explained = \Stax\VisibilityLogic\Preview::explain( [ 'enabled' => true, 'rules' => $t ] + Rule_Tree::fail_closed( [], '' ), 'x1', 'heading' );
Context::reset();
$ok( false === $explained['visible'] && 'not_matched' === $explained['reason'] && 2 === count( $explained['groups'] ) && 'User role is Subscriber' === $explained['groups'][0]['conditions'][0]['text'] && true === $explained['groups'][0]['conditions'][1]['result'], 'explain: guest on mobile, trace in words', $explained );
\Stax\VisibilityLogic\Preview::simulate( [ 'as' => 'role:editor' ] );
$explained = \Stax\VisibilityLogic\Preview::explain( [ 'enabled' => true, 'rules' => $t ], 'x1', 'heading' );
Context::reset();
$ok( true === $explained['visible'] && 'matched' === $explained['reason'], 'explain: role editor matches the second group' );
$ok( true === \Stax\VisibilityLogic\Preview::explain( [ 'enabled' => false ] )['visible'], 'explain: rules off' );
$ok( $adm_id === get_current_user_id(), 'explain never changes the current user' );
$req = new WP_REST_Request( 'POST', '/visibility-logic/v1/explain' );
$req->set_param( 'context', [ 'as' => 'user', 'user' => $sub_id ] );
wp_set_current_user( $ed_id );
$ok( false === \Stax\VisibilityLogic\Preview::can_explain( $req ), 'explain: simulating a specific user needs list_users' );
$req->set_param( 'context', [ 'as' => 'guest' ] );
$ok( true === \Stax\VisibilityLogic\Preview::can_explain( $req ), 'explain: editors may preview as guest' );
wp_set_current_user( $sub_id );
$ok( false === \Stax\VisibilityLogic\Preview::can_explain( $req ), 'explain: subscribers may not' );

// Rules overview rows.
$rows = \Stax\VisibilityLogic\Rules::overview_rows(
	[ 'documents' => [ [ 'id' => 1, 'title' => 'P', 'post_type' => 'page', 'builder' => 'elementor', 'status' => 'publish', 'elements' => [
		[ 'id' => 'a', 'type' => 'heading', 'conditions' => [ 'date_time' ], 'tree' => $tree_of( 'show', [ [ $cond( 'date_time', [ 'date_time_type' => 'date', 'date_to' => '2020-01-01 00:00' ] ) ] ] ), 'summary' => 's1', 'stored' => false ],
		[ 'id' => 'b', 'type' => 'heading', 'conditions' => [ 'user_role' ], 'tree' => $tree_of( 'show', [ [ $role_sub ] ], true ), 'summary' => 's2', 'stored' => true ],
	] ] ] ],
	[ 'status' => 'problems' ]
);
$ok( 1 === count( $rows ) && 'a' === $rows[0]['element']['id'] && in_array( 'expired', $rows[0]['codes'], true ), 'overview: "Needs attention" filter keeps the expired rule, not the draft', $rows );

// ---------------------------------------------------------------------------------------------------------------
$section( '4. Summaries, warnings, registry' );

$ok( 'Shown only when (User role is Subscriber and Device is Mobile) or User role is Editor' === Rule_Tree::sentence( $t ), 'sentence with groups', Rule_Tree::sentence( $t ) );
$ok( 'Hidden on the live site (draft)' === Rule_Tree::sentence( $tree_of( 'show', [ [] ], true ) ), 'sentence of a draft' );
$ok( 'Shown only when Every year from 12-20 to 01-11' === Rule_Tree::sentence( $tree_of( 'show', [ [ $yearly ] ] ) ), 'B1 summary', Rule_Tree::sentence( $tree_of( 'show', [ [ $yearly ] ] ) ) );
$users_summary = Rule_Tree::sentence( $who( [ 'user_role_by' => 'users', 'user_role_users' => [ (string) $sub_id ] ] ) );
$ok( false !== strpos( $users_summary, get_userdata( $sub_id )->display_name ), 'B2 summary shows user names', $users_summary );
$ok( 'Subscriber +1' === Rule_Tree::short( $tree_of( 'show', [ [ $role_sub, $dev_mob ] ] ) ), 'short label', Rule_Tree::short( $tree_of( 'show', [ [ $role_sub, $dev_mob ] ] ) ) );
$codes = wp_list_pluck( Rule_Tree::warnings( $tree_of( 'show', [ [ $cond( 'date_time', [ 'date_time_type' => 'date', 'date_to' => '2020-01-01 00:00' ] ) ] ] ) ), 'code' );
$ok( in_array( 'expired', $codes, true ), 'warning: date range ended', $codes );
$codes = wp_list_pluck( Rule_Tree::warnings( $tree_of( 'show', [ [ $cond( 'device_type', [ 'device_types' => [ 'mobile' ] ] ), $cond( 'device_type', [ 'device_types' => [ 'desktop' ] ] ) ] ] ) ), 'code' );
$ok( in_array( 'never', $codes, true ), 'warning: can never be true', $codes );
$codes = wp_list_pluck( Rule_Tree::warnings( $tree_of( 'show', [ [ $cond( 'user_role', [] ) ] ] ) ), 'code' );
$ok( in_array( 'incomplete', $codes, true ), 'warning: incomplete', $codes );

$registry = Rule_Tree::registry( true );
$ok( 'who' === $registry->type( 'user_role' )['category'] && 'when' === $registry->type( 'date_time' )['category'] && 'device' === $registry->type( 'device_type' )['category'], 'registry: task categories' );
$ok( 6 <= count( $registry->presets() ), 'registry: built-in presets' );
$pro_types = array_filter( $registry->types(), static function ( $t ) {
	return ! empty( $t['pro'] );
} );
$ok( count( $pro_types ) >= 5, 'registry: Pro conditions listed (locked without Pro, editable with Pro)', count( $pro_types ) );
if ( ! defined( 'STAX_VISIBILITY_PRO_VERSION' ) ) {
	$locked = array_filter( $pro_types, static function ( $t ) {
		return ! empty( $t['locked'] );
	} );
	$ok( count( $locked ) === count( $pro_types ), 'without Pro: every Pro condition is locked' );
}

// ---------------------------------------------------------------------------------------------------------------
$section( '5. Rendering stored trees (blocks, Elementor, columns)' );

wp_set_current_user( 0 );
$block_tree = $tree_of( 'show', [ [ $cond( 'user_role', [ 'user_role_conditions' => [ 'ecl-guest' ] ] ) ], [ $cond( 'user_role', [ 'user_role_conditions' => [ 'administrator' ] ] ) ] ] );
$block      = [ 'blockName' => 'core/paragraph', 'attrs' => [ Blocks::ATTRIBUTE => array_merge( [ 'enabled' => true, 'rules' => $block_tree ], array_diff_key( Rule_Tree::fail_closed( [], '' ), [] ) ) ], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => [ '<p>x</p>' ] ];
$ok( '<p>x</p>' === Blocks::render_block( '<p>x</p>', $block ), 'block with a stored tree: guest sees it' );
wp_set_current_user( $sub_id );
$ok( '' === Blocks::render_block( '<p>x</p>', $block ), 'block with a stored tree: subscriber does not' );
wp_set_current_user( $adm_id );
$ok( '<p>x</p>' === Blocks::render_block( '<p>x</p>', $block ), 'block with a stored tree: administrator (second group)' );

if ( did_action( 'elementor/loaded' ) ) {
	$render = static function ( array $data ) {
		\Stax\VisibilityLogic\Plugin::instance()->reset_cache();
		$element = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $data );
		ob_start();
		$element->print_element();
		return (string) ob_get_clean();
	};
	$widget = static function ( $id, array $settings ) {
		return [ 'id' => $id, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array_merge( [ 'title' => 'T ' . $id ], $settings ), 'elements' => [] ];
	};
	$guest_or_admin = array_merge( $p( [ 'enabled' => 'yes' ] ), Rule_Tree::fail_closed( [] ), [ 'stax_visibility_rules' => $block_tree ] );
	wp_set_current_user( 0 );
	$ok( false !== strpos( $render( $widget( 'rt1', $guest_or_admin ) ), 'data-id="rt1"' ), 'Elementor widget with a stored tree: guest sees it' );
	wp_set_current_user( $sub_id );
	$ok( false === strpos( $render( $widget( 'rt1', $guest_or_admin ) ), 'data-id="rt1"' ), 'Elementor widget with a stored tree: subscriber does not' );

	// B5 columns.
	$guest_only = $p( [ 'enabled' => 'yes', 'show_hide' => 'yes', 'user_role_enabled' => 'yes', 'user_role_conditions' => [ 'ecl-guest' ] ] );
	$column     = static function ( $id, array $settings, array $children ) {
		return [ 'id' => $id, 'elType' => 'column', 'settings' => array_merge( [ '_column_size' => 50 ], $settings ), 'elements' => $children ];
	};
	$sec = static function ( $id, array $settings, array $columns ) {
		return [ 'id' => $id, 'elType' => 'section', 'settings' => $settings, 'elements' => $columns ];
	};
	wp_set_current_user( $sub_id );
	$html = $render( $sec( 's1', [], [ $column( 'c1', $guest_only, [ $widget( 'w1', [] ) ] ), $column( 'c2', [], [ $widget( 'w2', [] ) ] ) ] ) );
	$ok( false === strpos( $html, 'data-id="c1"' ) && false !== strpos( $html, 'data-id="c2"' ), 'B5 column hidden by its rule, the other column stays' );
	$html = $render( $sec( 's2', $p( [ 'hide_when_empty' => 'yes' ] ), [ $column( 'c3', $guest_only, [ $widget( 'w3', [] ) ] ), $column( 'c4', $guest_only, [ $widget( 'w4', [] ) ] ) ] ) );
	$ok( false === strpos( $html, 'data-id="s2"' ), 'B5 hide when empty: section whose columns are all hidden', strlen( $html ) );
	wp_set_current_user( 0 );
	$html = $render( $sec( 's3', $p( [ 'hide_when_empty' => 'yes' ] ), [ $column( 'c5', $guest_only, [ $widget( 'w5', [] ) ] ) ] ) );
	$ok( false !== strpos( $html, 'data-id="w5"' ), 'B5 column rule passes for guests' );
	$plain = $render( $sec( 's4', [], [ $column( 'c6', [], [ $widget( 'w6', [] ) ] ) ] ) );
	$ok( false === strpos( $plain, 'stax-' ), 'B5 a column without rules gets no extra classes' );
}

// ---------------------------------------------------------------------------------------------------------------
$section( '6. Golden file for the JS twin (assets/js/rule-map.js)' );

$golden = [ 'registry' => Rule_Tree::registry()->to_array(), 'since' => Rule_Tree::SINCE, 'cases' => [] ];
$unprefix = static function ( array $settings ) {
	$out = [];
	foreach ( $settings as $k => $v ) {
		$out[ substr( $k, strlen( 'stax_visibility_' ) ) ] = $v;
	}
	return $out;
};
foreach ( array_slice( $matrix, 0, 60, true ) as $label => $settings ) {
	$values = $unprefix( $settings );
	$tree   = Rule_Tree::legacy_to_tree( $values, '' );
	$golden['cases'][] = [ 'label' => $label, 'values' => $values, 'tree' => $tree, 'analyze' => Rule_Tree::analyze( $tree ), 'storage' => Rule_Tree::to_storage( $tree, $values, '' ) ];
}
foreach ( $cases as $label => $case ) {
	$values = $unprefix( $base );
	$golden['cases'][] = [ 'label' => 'edit: ' . $label, 'values' => $values, 'edited' => $case[0], 'analyze' => Rule_Tree::analyze( $case[0] ), 'storage' => Rule_Tree::to_storage( $case[0], $values, '' ) ];
}
$golden_file = dirname( __FILE__ ) . '/fixtures/rule-map.golden.json';
wp_mkdir_p( dirname( $golden_file ) );
file_put_contents( $golden_file, wp_json_encode( $golden, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
$ok( file_exists( $golden_file ), 'golden file written' );

// Cleanup.
Context::reset();
if ( false === $vlt_backup ) {
	delete_option( 'stax_visibility_general' );
} else {
	update_option( 'stax_visibility_general', $vlt_backup );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $cleanup_users as $uid ) {
	wp_delete_user( $uid );
}
wp_set_current_user( 0 );

WP_CLI::log( sprintf( '%d passed, %d failed', $vlt['pass'], $vlt['fail'] ) );
if ( $vlt['fail'] ) {
	exit( 1 );
}
