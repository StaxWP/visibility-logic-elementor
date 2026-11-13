<?php
/**
 * Visibility Logic for Elementor: condition evaluation and backward compatibility tests.
 *
 * Run on a test site with Elementor and this plugin active:
 *   wp eval-file wp-content/plugins/visibility-logic-elementor/tests/visibility-test.php
 *
 * Every case builds real Elementor elements from saved-data arrays (the format stored in `_elementor_data`), prints
 * them through Elementor's own render pipeline and checks which elements reach the HTML. Nothing is written to posts; the
 * general settings run in the 2.x (update) mode, the default after an update, and are restored at the end;
 * two temporary users are created and deleted at the end. Exit code 1 when a check fails.
 *
 * Covered: General (show/hide, all/one, keep HTML, hide when empty for sections and containers), User Role (guests,
 * logged in, roles), legacy 1.x settings (ecl_*), User Meta operators and protected (`_key`) meta keys, Date & Time
 * (date range, time range, over midnight, week days, week days + time), Device Type, Browser Type, ACF Field (with a stub
 * when ACF is not active).
 *
 * @package Stax\VisibilityLogic
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput

if ( ! did_action( 'elementor/loaded' ) || ! defined( 'STAX_VISIBILITY_VERSION' ) ) {
	WP_CLI::error( 'Elementor and Visibility Logic must be active.' );
}

// ACF stub, used only when ACF is not installed (values come from $GLOBALS['vle_test_acf']).
if ( ! function_exists( 'get_field' ) ) {
	/**
	 * Test stub of ACF's get_field().
	 *
	 * @param string $name Field name.
	 *
	 * @return mixed
	 */
	function get_field( $name ) {
		return isset( $GLOBALS['vle_test_acf'][ $name ] ) ? $GLOBALS['vle_test_acf'][ $name ] : null;
	}
}

$GLOBALS['vle_test_acf'] = [];

// Settings of the run: the defaults of a site updating from 2.x (2.5.2 fixes on, element cache off), restored at the end.
$GLOBALS['vle_settings_backup'] = get_option( 'stax_visibility_general', false );
update_option(
	'stax_visibility_general',
	[
		'site_timezone'     => true,
		'container_widgets' => true,
		'element_cache'     => false,
	]
);

/**
 * Change one general setting for the next checks.
 *
 * @param string $key   Setting.
 * @param bool   $value Value.
 */
function vle_setting( $key, $value ) {
	$settings         = (array) get_option( 'stax_visibility_general', [] );
	$settings[ $key ] = $value;
	update_option( 'stax_visibility_general', $settings );
}

global $vle;
$vle = [
	'pass'  => 0,
	'fail'  => 0,
	'users' => [],
];

/**
 * Widget data (saved format).
 *
 * @param string $id       Element id.
 * @param array  $settings Settings.
 *
 * @return array
 */
function vle_w( $id, array $settings = [] ) {
	return [
		'id'         => $id,
		'elType'     => 'widget',
		'widgetType' => 'heading',
		'settings'   => array_merge( [ 'title' => 'Title ' . $id ], $settings ),
		'elements'   => [],
	];
}

/**
 * Visibility settings with the stax_visibility_ prefix.
 *
 * @param array $settings Unprefixed settings.
 *
 * @return array
 */
function vle_s( array $settings ) {
	$out = [];
	foreach ( $settings as $k => $v ) {
		$out[ 'stax_visibility_' . $k ] = $v;
	}

	return $out;
}

/**
 * Render saved element data through Elementor and return the HTML.
 *
 * @param array $data Element data.
 *
 * @return string
 */
function vle_render( array $data ) {
	if ( method_exists( \Stax\VisibilityLogic\Plugin::instance(), 'reset_cache' ) ) {
		\Stax\VisibilityLogic\Plugin::instance()->reset_cache();
	}
	$element = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $data );
	if ( ! $element ) {
		return '';
	}
	ob_start();
	$element->print_element();

	return (string) ob_get_clean();
}

/**
 * Whether an element id is in the HTML and not hidden by CSS.
 *
 * @param string $html HTML.
 * @param string $id   Element id.
 *
 * @return string visible|css|absent
 */
function vle_state( $html, $id ) {
	if ( ! preg_match( '/<[^>]*data-(?:id|interaction-id)="' . preg_quote( $id, '/' ) . '"[^>]*>/', $html, $m ) ) {
		return 'absent';
	}

	return ( false !== strpos( $m[0], 'stax-visibility-hidden' ) || false !== strpos( $m[0], 'display: none' ) ) ? 'css' : 'visible';
}

/**
 * Assert.
 *
 * @param string $label    Label.
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 */
function vle_is( $label, $expected, $actual ) {
	global $vle;
	if ( $expected === $actual ) {
		++$vle['pass'];
		WP_CLI::log( "  ok   {$label}" );
	} else {
		++$vle['fail'];
		WP_CLI::log( "  FAIL {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

/**
 * Render one widget as a user and check its state.
 *
 * @param string $label    Label.
 * @param array  $settings Widget settings (saved format).
 * @param int    $user     User id (0 = guest).
 * @param string $expected visible|css|absent.
 */
function vle_case( $label, array $settings, $user, $expected ) {
	wp_set_current_user( $user );
	$id = 'w' . substr( md5( $label ), 0, 6 );
	vle_is( $label, $expected, vle_state( vle_render( vle_w( $id, $settings ) ), $id ) );
}

// Users.
$suffix  = wp_generate_password( 6, false );
$sub_id  = wp_insert_user(
	[
		'user_login' => 'vle_sub_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'vle_sub_' . $suffix . '@example.test',
		'role'       => 'subscriber',
		'first_name' => 'Ana',
	]
);
$edit_id = wp_insert_user(
	[
		'user_login' => 'vle_ed_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'user_email' => 'vle_ed_' . $suffix . '@example.test',
		'role'       => 'editor',
	]
);
if ( is_wp_error( $sub_id ) || is_wp_error( $edit_id ) ) {
	WP_CLI::error( 'Could not create test users.' );
}
$vle['users'] = [ $sub_id, $edit_id ];
update_user_meta( $sub_id, 'vle_points', '42' );
update_user_meta( $sub_id, 'vle_plan', 'gold' );
update_user_meta( $sub_id, 'vle_tags', [ 'a', 'b', 'c' ] );
update_user_meta( $sub_id, 'vle_bio', 'I like Elementor' );

WP_CLI::log( 'Visibility Logic ' . STAX_VISIBILITY_VERSION . ', Elementor ' . ELEMENTOR_VERSION . ', PHP ' . PHP_VERSION . ', WP ' . get_bloginfo( 'version' ) );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'General' );
vle_case( 'no settings: visible', [], 0, 'visible' );
vle_case( 'enabled, no condition enabled: visible', vle_s( [ 'enabled' => 'yes' ] ), 0, 'visible' );
vle_case(
	'enabled off but role set: visible',
	vle_s(
		[
			'enabled'              => '',
			'user_role_enabled'    => 'yes',
			'user_role_conditions' => [ 'ecl-guest' ],
		]
	),
	0,
	'visible'
);

$guest_show = vle_s(
	[
		'enabled'              => 'yes',
		'show_hide'            => 'yes',
		'user_role_enabled'    => 'yes',
		'user_role_conditions' => [ 'ecl-guest' ],
	]
);
$guest_hide = vle_s(
	[
		'enabled'              => 'yes',
		'show_hide'            => '',
		'user_role_enabled'    => 'yes',
		'user_role_conditions' => [ 'ecl-guest' ],
	]
);
vle_case( 'show for guests: guest', $guest_show, 0, 'visible' );
vle_case( 'show for guests: subscriber', $guest_show, $sub_id, 'absent' );
vle_case( 'hide for guests: guest', $guest_hide, 0, 'absent' );
vle_case( 'hide for guests: subscriber', $guest_hide, $sub_id, 'visible' );
vle_case(
	'show_hide missing (hide default): guest',
	vle_s(
		[
			'enabled'              => 'yes',
			'user_role_enabled'    => 'yes',
			'user_role_conditions' => [ 'ecl-guest' ],
		]
	),
	0,
	'absent'
);
vle_case( 'keep HTML: hidden by CSS', array_merge( $guest_hide, vle_s( [ 'keep_html' => 'yes' ] ) ), 0, 'css' );
vle_case( 'keep HTML: shown when conditions allow', array_merge( $guest_hide, vle_s( [ 'keep_html' => 'yes' ] ) ), $sub_id, 'visible' );

$all = vle_s(
	[
		'enabled'              => 'yes',
		'show_hide'            => 'yes',
		'condition_type'       => 'all',
		'user_role_enabled'    => 'yes',
		'user_role_conditions' => [ 'subscriber' ],
		'user_meta_enabled'    => 'yes',
		'user_meta_options'    => [ 'vle_plan' ],
		'user_meta_status'     => 'specific_value',
		'user_meta_value'      => 'silver',
	]
);
vle_case( 'all: one condition false: hidden', $all, $sub_id, 'absent' );
vle_case( 'one: one condition true: visible', array_merge( $all, vle_s( [ 'condition_type' => 'one' ] ) ), $sub_id, 'visible' );
vle_case( 'one: none true: hidden', array_merge( $all, vle_s( [ 'condition_type' => 'one' ] ) ), $edit_id, 'absent' );
vle_case( 'condition_type missing defaults to all', array_diff_key( $all, [ 'stax_visibility_condition_type' => 1 ] ), $sub_id, 'absent' );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'User Role' );
$role = function ( array $roles, $show = 'yes' ) {
	return vle_s(
		[
			'enabled'              => 'yes',
			'show_hide'            => $show,
			'user_role_enabled'    => 'yes',
			'user_role_conditions' => $roles,
		]
	);
};
vle_case( 'logged in users: guest', $role( [ 'ecl-user' ] ), 0, 'absent' );
vle_case( 'logged in users: subscriber', $role( [ 'ecl-user' ] ), $sub_id, 'visible' );
vle_case( 'editor role: editor', $role( [ 'editor' ] ), $edit_id, 'visible' );
vle_case( 'editor role: subscriber', $role( [ 'editor' ] ), $sub_id, 'absent' );
vle_case( 'guest or editor: guest', $role( [ 'ecl-guest', 'editor' ] ), 0, 'visible' );
vle_case( 'role enabled with empty list: no effect', $role( [] ), 0, 'visible' );
vle_case(
	'role switch off: no effect',
	vle_s(
		[
			'enabled'              => 'yes',
			'show_hide'            => 'yes',
			'user_role_enabled'    => '',
			'user_role_conditions' => [ 'editor' ],
		]
	),
	0,
	'visible'
);

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'Legacy 1.x settings (ecl_*)' );
vle_case(
	'ecl visible for guests: guest',
	[
		'ecl_enabled'      => 'yes',
		'ecl_role_visible' => [ 'ecl-guest' ],
	],
	0,
	'visible'
);
vle_case(
	'ecl visible for guests: subscriber',
	[
		'ecl_enabled'      => 'yes',
		'ecl_role_visible' => [ 'ecl-guest' ],
	],
	$sub_id,
	'absent'
);
vle_case(
	'ecl visible for users: guest',
	[
		'ecl_enabled'      => 'yes',
		'ecl_role_visible' => [ 'ecl-user' ],
	],
	0,
	'absent'
);
vle_case(
	'ecl visible for editor: subscriber',
	[
		'ecl_enabled'      => 'yes',
		'ecl_role_visible' => [ 'editor' ],
	],
	$sub_id,
	'absent'
);
vle_case(
	'ecl visible for editor: editor',
	[
		'ecl_enabled'      => 'yes',
		'ecl_role_visible' => [ 'editor' ],
	],
	$edit_id,
	'visible'
);
vle_case(
	'ecl hidden for guests: guest',
	[
		'ecl_enabled'     => 'yes',
		'ecl_role_hidden' => [ 'ecl-guest' ],
	],
	0,
	'absent'
);
vle_case(
	'ecl hidden for subscriber: subscriber',
	[
		'ecl_enabled'     => 'yes',
		'ecl_role_hidden' => [ 'subscriber' ],
	],
	$sub_id,
	'absent'
);
vle_case(
	'ecl hidden for subscriber: guest',
	[
		'ecl_enabled'     => 'yes',
		'ecl_role_hidden' => [ 'subscriber' ],
	],
	0,
	'visible'
);
vle_case(
	'ecl disabled: ignored',
	[
		'ecl_enabled'      => '',
		'ecl_role_visible' => [ 'ecl-user' ],
	],
	0,
	'visible'
);
vle_case(
	'new settings win over ecl when enabled',
	array_merge(
		[
			'ecl_enabled'      => 'yes',
			'ecl_role_visible' => [ 'ecl-user' ],
		],
		$role( [ 'ecl-guest' ] )
	),
	0,
	'visible'
);

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'User Meta' );
$meta       = function ( $key, $status, $value = '', $value2 = '' ) {
	return vle_s(
		[
			'enabled'           => 'yes',
			'show_hide'         => 'yes',
			'user_meta_enabled' => 'yes',
			'user_meta_options' => [ $key ],
			'user_meta_status'  => $status,
			'user_meta_value'   => $value,
			'user_meta_value_2' => $value2,
		]
	);
};
$meta_cases = [
	[ 'first_name', 'not_empty', '', '', true ],
	[ 'vle_missing', 'empty', '', '', true ],
	[ 'vle_plan', 'specific_value', 'gold', '', true ],
	[ 'vle_plan', 'specific_value', 'Gold', '', false ],
	[ 'vle_plan', 'specific_value_multiple', 'silver,gold', '', true ],
	[ 'vle_plan', 'not_specific_value', 'gold', '', false ],
	[ 'vle_bio', 'contain', 'Elementor', '', true ],
	[ 'vle_bio', 'not_contain', 'Gutenberg', '', true ],
	[ 'vle_points', 'is_between', '10', '50', true ],
	[ 'vle_points', 'is_between', '43', '50', false ],
	[ 'vle_points', 'less_than', '50', '', true ],
	[ 'vle_points', 'greater_than', '50', '', false ],
	[ 'vle_tags', 'is_array', '', '', true ],
	[ 'vle_tags', 'is_array_and_contains', 'x,b', '', true ],
	[ 'vle_tags', 'is_array_and_contains', 'x,y', '', false ],
	[ 'vle_plan', 'none', '', '', true ],
	[ 'user_email', 'contain', '@example.test', '', true ],
];
foreach ( $meta_cases as $c ) {
	vle_case( "meta {$c[0]} {$c[1]} {$c[2]}", $meta( $c[0], $c[1], $c[2], $c[3] ), $sub_id, $c[4] ? 'visible' : 'absent' );
}
vle_case( 'meta for guest: first_name not_empty', $meta( 'first_name', 'not_empty' ), 0, 'absent' );

// Protected keys (leading underscore, #12221): the picker offers the stored key, the rule reads it.
update_user_meta( $sub_id, '_vle_secret', 'yes' );
update_user_meta( $sub_id, 'vle_twin', 'plain' );
update_user_meta( $sub_id, '_vle_twin', 'field_123' );
if ( ! class_exists( '\\Stax\\VisibilityLogic\\Modules\\QueryControl' ) ) {
	require_once STAX_VISIBILITY_CORE_PATH . 'helpers/modules/QueryControl.php';
}
$query_control = new \Stax\VisibilityLogic\Modules\QueryControl();
$picker        = function ( $q ) use ( $query_control ) {
	$found = $query_control->ajax_call_filter_autocomplete(
		[
			'query_type'  => 'fields',
			'object_type' => 'user',
			'q'           => $q,
		]
	);

	return wp_list_pluck( $found['results'], 'id' );
};
wp_set_current_user( $edit_id );
vle_is( 'picker: _vle_secret offered with its underscore', true, in_array( '_vle_secret', $picker( 'vle_secret' ), true ) );
vle_is( 'picker: no stripped vle_secret', false, in_array( 'vle_secret', $picker( 'vle_secret' ), true ) );
vle_is( 'picker: twin vle_twin offered', true, in_array( 'vle_twin', $picker( 'vle_twin' ), true ) );
vle_is( 'picker: _vle_twin hidden next to its twin', false, in_array( '_vle_twin', $picker( 'vle_twin' ), true ) );
vle_is( 'picker: typing _vle_twin offers it', true, in_array( '_vle_twin', $picker( '_vle_twin' ), true ) );
$titles = $query_control->ajax_call_control_value_titles(
	[
		'query_type'  => 'fields',
		'object_type' => 'user',
		'id'          => [ '_vle_secret', 'vle_secret' ],
	]
);
vle_is( 'titles: _vle_secret resolved', '_vle_secret', isset( $titles['_vle_secret'] ) ? $titles['_vle_secret'] : null );
vle_is( 'titles: key saved before 3.0 (vle_secret) kept selected', 'vle_secret', isset( $titles['vle_secret'] ) ? $titles['vle_secret'] : null );
vle_case( 'meta _vle_secret specific_value yes', $meta( '_vle_secret', 'specific_value', 'yes' ), $sub_id, 'visible' );
vle_case( 'meta _vle_secret not_empty', $meta( '_vle_secret', 'not_empty' ), $sub_id, 'visible' );
vle_case( 'meta vle_secret (saved without underscore) not_empty: unchanged, absent', $meta( 'vle_secret', 'not_empty' ), $sub_id, 'absent' );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'Date & Time' );
$fmt   = 'Y-m-d H:i';
$now   = time();
$dt    = function ( array $s ) {
	return vle_s(
		array_merge(
			[
				'enabled'           => 'yes',
				'show_hide'         => 'yes',
				'date_time_enabled' => 'yes',
			],
			$s
		)
	);
};
$past  = gmdate( $fmt, $now - 2 * DAY_IN_SECONDS );
$past2 = gmdate( $fmt, $now - DAY_IN_SECONDS );
$fut   = gmdate( $fmt, $now + 2 * DAY_IN_SECONDS );
vle_case(
	'date range around now',
	$dt(
		[
			'date_time_type' => 'date',
			'date_from'      => $past,
			'date_to'        => $fut,
		]
	),
	0,
	'visible'
);
vle_case(
	'date range in the past',
	$dt(
		[
			'date_time_type' => 'date',
			'date_from'      => $past,
			'date_to'        => $past2,
		]
	),
	0,
	'absent'
);
vle_case(
	'date from only (past)',
	$dt(
		[
			'date_time_type' => 'date',
			'date_from'      => $past,
		]
	),
	0,
	'visible'
);
vle_case(
	'date from only (future)',
	$dt(
		[
			'date_time_type' => 'date',
			'date_from'      => $fut,
		]
	),
	0,
	'absent'
);
vle_case(
	'date to only (future)',
	$dt(
		[
			'date_time_type' => 'date',
			'date_to'        => $fut,
		]
	),
	0,
	'visible'
);
vle_case( 'date with no dates', $dt( [ 'date_time_type' => 'date' ] ), 0, 'absent' );
vle_case(
	'date type missing defaults to date',
	$dt(
		[
			'date_from' => $past,
			'date_to'   => $fut,
		]
	),
	0,
	'visible'
);

// Date FROM/TO time zone: site time zone by default since 2.5.2; the setting unchecked reads them as UTC (2.5.1 and earlier).
if ( class_exists( '\\Stax\\VisibilityLogic\\DateTimeVisibility' ) && method_exists( '\\Stax\\VisibilityLogic\\Resources', 'get_setting' ) ) {
	$tz_backup  = [ get_option( 'timezone_string' ), get_option( 'gmt_offset' ) ];
	update_option( 'timezone_string', 'Pacific/Kiritimati' ); // UTC+14.
	$local_now  = new DateTimeImmutable( 'now', wp_timezone() );
	$from_local = $local_now->modify( '-1 hour' )->format( $fmt );
	$to_local   = $local_now->modify( '+1 hour' )->format( $fmt );
	vle_setting( 'site_timezone', false );
	vle_case(
		'date range in site time, setting unchecked (UTC, before 2.5.2): hidden',
		$dt(
			[
				'date_time_type' => 'date',
				'date_from'      => $from_local,
				'date_to'        => $to_local,
			]
		),
		0,
		'absent'
	);
	vle_setting( 'site_timezone', true );
	vle_case(
		'date range in site time, site time zone (default): visible',
		$dt(
			[
				'date_time_type' => 'date',
				'date_from'      => $from_local,
				'date_to'        => $to_local,
			]
		),
		0,
		'visible'
	);
	update_option( 'timezone_string', $tz_backup[0] );
	update_option( 'gmt_offset', $tz_backup[1] );
	vle_setting( 'site_timezone', true );
}

$local   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
$hm      = function ( $offset ) use ( $local ) {
	return gmdate( 'H:i', $local + $offset );
};
$crosses = (int) gmdate( 'G', $local ) < 2 || (int) gmdate( 'G', $local ) > 21;
if ( ! $crosses ) {
	vle_case(
		'time window around now',
		$dt(
			[
				'date_time_type' => 'time',
				'time_from'      => $hm( -3600 ),
				'time_to'        => $hm( 3600 ),
			]
		),
		0,
		'visible'
	);
	vle_case(
		'time window later today',
		$dt(
			[
				'date_time_type' => 'time',
				'time_from'      => $hm( 3600 ),
				'time_to'        => $hm( 7200 ),
			]
		),
		0,
		'absent'
	);
	vle_case(
		'time over midnight, now outside',
		$dt(
			[
				'date_time_type' => 'time',
				'time_from'      => $hm( 3600 ),
				'time_to'        => $hm( -3600 ),
			]
		),
		0,
		'absent'
	);
	vle_case(
		'time over midnight, now inside',
		$dt(
			[
				'date_time_type' => 'time',
				'time_from'      => $hm( -3600 ),
				'time_to'        => $hm( -7200 ),
			]
		),
		0,
		'visible'
	);
	vle_case(
		'time from only (earlier)',
		$dt(
			[
				'date_time_type' => 'time',
				'time_from'      => $hm( -3600 ),
			]
		),
		0,
		'visible'
	);
	vle_case(
		'time to only (earlier)',
		$dt(
			[
				'date_time_type' => 'time',
				'time_to'        => $hm( -3600 ),
			]
		),
		0,
		'absent'
	);
} else {
	WP_CLI::log( '  skip time windows (too close to midnight)' );
}
$today    = (string) current_time( 'w' );
$tomorrow = (string) ( ( (int) $today + 1 ) % 7 );
vle_case(
	'week day today',
	$dt(
		[
			'date_time_type' => 'week_days',
			'time_week'      => [ $today ],
		]
	),
	0,
	'visible'
);
vle_case(
	'week day tomorrow',
	$dt(
		[
			'date_time_type' => 'week_days',
			'time_week'      => [ $tomorrow ],
		]
	),
	0,
	'absent'
);
if ( ! $crosses ) {
	vle_case(
		'week day + time, today now',
		$dt(
			[
				'date_time_type' => 'week_days_time',
				'time_week'      => [ $today ],
				'time_from'      => $hm( -3600 ),
				'time_to'        => $hm( 3600 ),
			]
		),
		0,
		'visible'
	);
	vle_case(
		'week day + time, tomorrow',
		$dt(
			[
				'date_time_type' => 'week_days_time',
				'time_week'      => [ $tomorrow ],
				'time_from'      => $hm( -3600 ),
				'time_to'        => $hm( 3600 ),
			]
		),
		0,
		'absent'
	);
}

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'Device and browser' );
$ua_backup = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
$dev       = function ( array $devices ) {
	return vle_s(
		[
			'enabled'             => 'yes',
			'show_hide'           => 'yes',
			'device_type_enabled' => 'yes',
			'device_types'        => $devices,
		]
	);
};
$uas       = [
	'desktop' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
	'mobile'  => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
	'tablet'  => 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
];
foreach ( $uas as $device => $ua ) {
	$_SERVER['HTTP_USER_AGENT'] = $ua;
	if ( method_exists( '\Stax\VisibilityLogic\Resources', 'reset_cache' ) ) {
		\Stax\VisibilityLogic\Resources::reset_cache();
	}
	vle_case( "device {$device}: matches", $dev( [ $device ] ), 0, 'visible' );
	vle_case( "device {$device}: other device", $dev( array_values( array_diff( array_keys( $uas ), [ $device ] ) ) ), 0, 'absent' );
}
unset( $_SERVER['HTTP_USER_AGENT'] );
if ( method_exists( '\Stax\VisibilityLogic\Resources', 'reset_cache' ) ) {
	\Stax\VisibilityLogic\Resources::reset_cache();
}
vle_case( 'device without user agent: desktop', $dev( [ 'desktop' ] ), 0, 'visible' );

$browser = function ( array $b ) {
	return vle_s(
		[
			'enabled'              => 'yes',
			'show_hide'            => 'yes',
			'browser_type_enabled' => 'yes',
			'browsers'             => $b,
		]
	);
};
global $is_chrome, $is_safari, $is_gecko;
$saved_globals              = [ $is_chrome, $is_safari, $is_gecko ];
$_SERVER['HTTP_USER_AGENT'] = $uas['desktop'];
$is_chrome                  = true;
$is_safari                  = false;
$is_gecko                   = false;
if ( method_exists( '\Stax\VisibilityLogic\Resources', 'reset_cache' ) ) {
	\Stax\VisibilityLogic\Resources::reset_cache();
}
vle_case( 'browser chrome: matches', $browser( [ 'is_chrome' ] ), 0, 'visible' );
vle_case( 'browser safari: no match', $browser( [ 'is_safari', 'is_gecko' ] ), 0, 'absent' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36 Edg/140.0';
if ( method_exists( '\Stax\VisibilityLogic\Resources', 'reset_cache' ) ) {
	\Stax\VisibilityLogic\Resources::reset_cache();
}
vle_case( 'browser edge detected before chrome', $browser( [ 'is_edge' ] ), 0, 'visible' );
list( $is_chrome, $is_safari, $is_gecko ) = $saved_globals;
if ( null === $ua_backup ) {
	unset( $_SERVER['HTTP_USER_AGENT'] );
} else {
	$_SERVER['HTTP_USER_AGENT'] = $ua_backup;
}
if ( method_exists( '\Stax\VisibilityLogic\Resources', 'reset_cache' ) ) {
	\Stax\VisibilityLogic\Resources::reset_cache();
}

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'ACF Field' );
$acf                     = function ( $field, $cond, $value = '' ) {
	return vle_s(
		[
			'enabled'       => 'yes',
			'show_hide'     => 'yes',
			'acf_enabled'   => 'yes',
			'acf_field'     => $field,
			'acf_condition' => $cond,
			'acf_value'     => $value,
		]
	);
};
$GLOBALS['vle_test_acf'] = [
	'vle_color' => 'red',
	'vle_flag'  => '1',
	'vle_list'  => [ 'one', 'two' ],
	'vle_empty' => '',
];
$acf_real                = class_exists( 'ACF' );
if ( $acf_real ) {
	WP_CLI::log( '  ACF is active: field checks use the real get_field() on no post, expecting empty values' );
	vle_case( 'acf not_empty on missing field', $acf( 'vle_nope', 'not_empty' ), 0, 'absent' );
	vle_case( 'acf empty on missing field', $acf( 'vle_nope', 'empty' ), 0, 'visible' );
} else {
	vle_case( 'acf equals', $acf( 'vle_color', 'equals', 'red' ), 0, 'visible' );
	vle_case( 'acf not_equals', $acf( 'vle_color', 'not_equals', 'red' ), 0, 'absent' );
	vle_case( 'acf contains (string)', $acf( 'vle_color', 'contains', 'e' ), 0, 'visible' );
	vle_case( 'acf contains (array)', $acf( 'vle_list', 'contains', 'two' ), 0, 'visible' );
	vle_case( 'acf equals (array)', $acf( 'vle_list', 'equals', 'three' ), 0, 'absent' );
	vle_case( 'acf is_true', $acf( 'vle_flag', 'is_true' ), 0, 'visible' );
	vle_case( 'acf is_false', $acf( 'vle_flag', 'is_false' ), 0, 'absent' );
	vle_case( 'acf empty', $acf( 'vle_empty', 'empty' ), 0, 'visible' );
	vle_case( 'acf not_empty', $acf( 'vle_empty', 'not_empty' ), 0, 'absent' );
}
vle_case( 'acf with no field name: no effect', $acf( '', 'not_empty' ), 0, 'visible' );

// ---------------------------------------------------------------------------------------------------------------
WP_CLI::log( 'Sections and containers' );
$hidden_w  = vle_w( 'hw1', $guest_show );
$hidden_w2 = vle_w( 'hw2', $guest_show );
$shown_w   = vle_w( 'sw1' );
wp_set_current_user( $sub_id );

$section   = function ( $id, array $widgets, array $settings = [] ) {
	return [
		'id'       => $id,
		'elType'   => 'section',
		'settings' => $settings,
		'elements' => [
			[
				'id'       => $id . 'c',
				'elType'   => 'column',
				'settings' => [ '_column_size' => 100 ],
				'elements' => $widgets,
			],
		],
	];
};
$container = function ( $id, array $children, array $settings = [] ) {
	return [
		'id'       => $id,
		'elType'   => 'container',
		'settings' => $settings,
		'elements' => $children,
	];
};
$hwe       = vle_s( [ 'hide_when_empty' => 'yes' ] );

$html = vle_render( $section( 'sec1', [ $hidden_w, $hidden_w2 ], $hwe ) );
vle_is( 'section, all widgets hidden, hide when empty: section hidden', 'absent', vle_state( $html, 'sec1' ) );
$html = vle_render( $section( 'sec2', [ $hidden_w, $shown_w ], $hwe ) );
vle_is( 'section, one widget visible, hide when empty: section shown', 'visible', vle_state( $html, 'sec2' ) );
vle_is( 'section, hidden widget is absent', 'absent', vle_state( $html, 'hw1' ) );
vle_is( 'section, visible widget is present', 'visible', vle_state( $html, 'sw1' ) );
$html = vle_render( $section( 'sec3', [ $hidden_w ] ) );
vle_is( 'section without hide when empty stays', 'visible', vle_state( $html, 'sec3' ) );
$html = vle_render( $section( 'sec4', [ $shown_w ], $guest_show ) );
vle_is( 'section with its own condition: hidden', 'absent', vle_state( $html, 'sec4' ) );
vle_is( 'section with its own condition: children not printed', 'absent', vle_state( $html, 'sw1' ) );

$html = vle_render( $container( 'con1', [ $container( 'con1a', [ $hidden_w ] ), $container( 'con1b', [ $hidden_w2 ] ) ], $hwe ) );
vle_is( 'nested containers, all hidden, hide when empty: hidden', 'absent', vle_state( $html, 'con1' ) );
$html = vle_render( $container( 'con2', [ $container( 'con2a', [ $hidden_w ] ), $container( 'con2b', [ $shown_w ] ) ], $hwe ) );
vle_is( 'nested containers, one visible, hide when empty: shown', 'visible', vle_state( $html, 'con2' ) );
$html = vle_render( $container( 'con3', [ $hidden_w, $hidden_w2 ], $hwe ) );
vle_is( 'flat container, all hidden, hide when empty: hidden', 'absent', vle_state( $html, 'con3' ) );
$html = vle_render( $container( 'con4', [ $hidden_w, $shown_w ], $hwe ) );
vle_is( 'flat container, one visible, hide when empty: shown (2.5.2 fix, default)', 'visible', vle_state( $html, 'con4' ) );
$html = vle_render( $container( 'con7', [ $container( 'con7a', [ $hidden_w, $shown_w ] ) ], $hwe ) );
vle_is( 'nested container, inner flat with one visible: shown', 'visible', vle_state( $html, 'con7' ) );
$html = vle_render( $container( 'con8', [ $container( 'con8a', [ $container( 'con8b', [ $hidden_w ] ) ] ) ], $hwe ) );
vle_is( 'three levels, deepest container counts as content (2.x)', 'visible', vle_state( $html, 'con8' ) );
$html = vle_render( $container( 'con9', [ vle_w( 'kh1', array_merge( $guest_show, vle_s( [ 'keep_html' => 'yes' ] ) ) ) ], $hwe ) );
vle_is( 'flat container, only a CSS-hidden widget: kept (2.5.2 fix, default)', 'visible', vle_state( $html, 'con9' ) );

// container_widgets unchecked: the walk of 2.5.1 and earlier.
if ( method_exists( '\Stax\VisibilityLogic\Resources', 'get_setting' ) ) {
	vle_setting( 'container_widgets', false );
	$html = vle_render( $container( 'con4', [ $hidden_w, $shown_w ], $hwe ) );
	vle_is( 'container_widgets off: flat container, one visible: hidden as before 2.5.2', 'absent', vle_state( $html, 'con4' ) );
	$html = vle_render( $container( 'con9', [ vle_w( 'kh1', array_merge( $guest_show, vle_s( [ 'keep_html' => 'yes' ] ) ) ) ], $hwe ) );
	vle_is( 'container_widgets off: only a CSS-hidden widget: hidden as before 2.5.2', 'absent', vle_state( $html, 'con9' ) );
	$html = vle_render( $container( 'con2', [ $container( 'con2a', [ $hidden_w ] ), $container( 'con2b', [ $shown_w ] ) ], $hwe ) );
	vle_is( 'container_widgets off: nested containers, one visible: shown', 'visible', vle_state( $html, 'con2' ) );
	$html = vle_render( $section( 'sec2', [ $hidden_w, $shown_w ], $hwe ) );
	vle_is( 'container_widgets off: sections unchanged', 'visible', vle_state( $html, 'sec2' ) );
	vle_setting( 'container_widgets', true );
}

// Element cache flag: 2.x mode marks nothing dynamic; the 3.0 mode marks elements with rules.
if ( method_exists( \Stax\VisibilityLogic\Plugin::instance(), 'is_dynamic_content' ) ) {
	$plugin = \Stax\VisibilityLogic\Plugin::instance();
	vle_is( 'element cache 2.x mode: rules not marked dynamic', false, $plugin->is_dynamic_content( false, [ 'settings' => $guest_show ] ) );
	vle_setting( 'element_cache', true );
	vle_is( 'element cache 3.0 mode: rules marked dynamic', true, $plugin->is_dynamic_content( false, [ 'settings' => $guest_show ] ) );
	vle_is( 'element cache 3.0 mode: no rules, not dynamic', false, $plugin->is_dynamic_content( false, [ 'settings' => [ 'title' => 'x' ] ] ) );
	vle_setting( 'element_cache', false );
}
$html = vle_render( $container( 'con5', [ $shown_w ], $guest_show ) );
vle_is( 'container with its own condition: hidden', 'absent', vle_state( $html, 'con5' ) );
$html = vle_render( $container( 'con6', [ $shown_w ], array_merge( $guest_show, vle_s( [ 'keep_html' => 'yes' ] ) ) ) );
vle_is( 'container keep HTML: present', true, 'absent' !== vle_state( $html, 'con6' ) );

// ---------------------------------------------------------------------------------------------------------------
// Elementor 4 atomic elements: same conditions through the Visibility section props.
if ( class_exists( '\\Stax\\VisibilityLogic\\Atomic' ) && \Stax\VisibilityLogic\Atomic::available() && \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'e-heading' ) ) {
	WP_CLI::log( 'Elementor 4 atomic elements' );

	$atomic_props = function ( array $values ) {
		$props = [];
		foreach ( $values as $key => $value ) {
			if ( is_bool( $value ) ) {
				$props[ 'stax_visibility_' . $key ] = [ '$$type' => 'boolean', 'value' => $value ];
			} elseif ( is_array( $value ) ) {
				$props[ 'stax_visibility_' . $key ] = [
					'$$type' => 'string-array',
					'value'  => array_map(
						function ( $item ) {
							return [ '$$type' => 'string', 'value' => $item ];
						},
						$value
					),
				];
			} else {
				$props[ 'stax_visibility_' . $key ] = [ '$$type' => 'string', 'value' => $value ];
			}
		}
		return $props;
	};
	$atomic_heading = function ( $id, array $values ) use ( $atomic_props ) {
		return [
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => 'e-heading',
			'settings'   => $atomic_props( $values ),
			'elements'   => [],
		];
	};
	$guest_only = [
		'enabled'              => true,
		'show_hide'            => 'yes',
		'user_role_enabled'    => true,
		'user_role_conditions' => [ 'ecl-guest' ],
	];

	$schema = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'e-heading' )::get_props_schema();
	vle_is( 'atomic schema has the visibility props', true, isset( $schema['stax_visibility_enabled'], $schema['stax_visibility_user_role_conditions'], $schema['stax_visibility_date_from'] ) );
	vle_is( 'atomic schema has no Keep HTML prop', false, isset( $schema['stax_visibility_keep_html'] ) );
	$controls      = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $atomic_heading( 'at0', [] ) )->get_atomic_controls();
	$section_ids   = array_map(
		function ( $c ) {
			return method_exists( $c, 'get_id' ) ? $c->get_id() : '';
		},
		$controls
	);
	vle_is( 'atomic controls have the Visibility section', true, in_array( 'stax-visibility', $section_ids, true ) );

	wp_set_current_user( 0 );
	vle_is( 'atomic heading, guests only: guest sees it', 'visible', vle_state( vle_render( $atomic_heading( 'at1', $guest_only ) ), 'at1' ) );
	wp_set_current_user( $sub_id );
	vle_is( 'atomic heading, guests only: subscriber does not', 'absent', vle_state( vle_render( $atomic_heading( 'at1', $guest_only ) ), 'at1' ) );
	vle_is( 'atomic heading, rules off: visible', 'visible', vle_state( vle_render( $atomic_heading( 'at2', array_merge( $guest_only, [ 'enabled' => false ] ) ) ), 'at2' ) );
	vle_is( 'atomic heading, no props: visible', 'visible', vle_state( vle_render( $atomic_heading( 'at3', [] ) ), 'at3' ) );
	vle_is(
		'atomic heading, one of role/meta: subscriber with plan gold',
		'visible',
		vle_state(
			vle_render(
				$atomic_heading(
					'at4',
					array_merge(
						$guest_only,
						[
							'condition_type'    => 'one',
							'user_meta_enabled' => true,
							'user_meta_options' => [ 'vle_plan' ],
							'user_meta_status'  => 'specific_value',
							'user_meta_value'   => 'gold',
						]
					)
				)
			),
			'at4'
		)
	);
	$atomic_box = [
		'id'       => 'afx1',
		'elType'   => 'e-flexbox',
		'settings' => $atomic_props( $guest_only ),
		'elements' => [ $atomic_heading( 'at5', [] ) ],
	];
	$html       = vle_render( $atomic_box );
	vle_is( 'atomic flexbox, guests only: hidden for subscriber', 'absent', vle_state( $html, 'afx1' ) );
	vle_is( 'atomic flexbox hidden: child not printed', 'absent', vle_state( $html, 'at5' ) );
	wp_set_current_user( 0 );
	vle_is( 'atomic flexbox, guests only: guest sees it', 'visible', vle_state( vle_render( $atomic_box ), 'afx1' ) );
	// Locked add-on condition: notice instead of controls, props kept, saved values still apply.
	$locked_fields = function ( $conditions ) {
		$conditions['vle_locked'] = [
			'label'         => 'VLE Locked',
			'switch'        => 'vle_locked_enabled',
			'locked'        => true,
			'locked_notice' => [ 'title' => 'Needs a license' ],
			'fields'        => [
				[
					'key'   => 'vle_locked_value',
					'type'  => 'text',
					'label' => 'Value',
				],
			],
		];
		return $conditions;
	};
	$locked_eval   = function ( $options, $settings, $item ) {
		$settings = $item->get_settings_for_display();
		if ( ! empty( $settings['stax_visibility_vle_locked_enabled'] ) ) {
			$options['vle_locked'] = 'open' === $settings['stax_visibility_vle_locked_value'];
		}
		return $options;
	};
	add_filter( 'stax/visibility/condition_fields', $locked_fields );
	add_filter( 'stax/visibility/apply_conditions', $locked_eval, 10, 3 );
	\Stax\VisibilityLogic\Conditions::reset();
	$locked_schema = \Stax\VisibilityLogic\Atomic::props_schema( [] );
	vle_is( 'locked condition: props stay in the atomic schema', true, isset( $locked_schema['stax_visibility_vle_locked_enabled'], $locked_schema['stax_visibility_vle_locked_value'] ) );
	$locked_controls = \Stax\VisibilityLogic\Atomic::controls( [] );
	$locked_types    = array_map(
		function ( $c ) {
			$j = json_decode( wp_json_encode( $c ), true );
			return $j['value']['type'] . ':' . $j['value']['bind'];
		},
		end( $locked_controls )->get_items()
	);
	vle_is( 'locked condition: a notice instead of its switch', true, in_array( 'notice:stax_visibility_vle_locked_enabled', $locked_types, true ) && ! in_array( 'switch:stax_visibility_vle_locked_enabled', $locked_types, true ) );
	vle_is( 'locked condition: its fields have no control', false, in_array( 'text:stax_visibility_vle_locked_value', $locked_types, true ) );
	wp_set_current_user( 0 );
	$locked_values = [
		'enabled'            => true,
		'show_hide'          => 'yes',
		'vle_locked_enabled' => true,
		'vle_locked_value'   => 'closed',
	];
	vle_is( 'locked condition: saved atomic values still apply (closed)', 'absent', vle_state( vle_render( $atomic_heading( 'atl1', $locked_values ) ), 'atl1' ) );
	vle_is( 'locked condition: saved atomic values still apply (open)', 'visible', vle_state( vle_render( $atomic_heading( 'atl2', array_merge( $locked_values, [ 'vle_locked_value' => 'open' ] ) ) ), 'atl2' ) );
	remove_filter( 'stax/visibility/condition_fields', $locked_fields );
	remove_filter( 'stax/visibility/apply_conditions', $locked_eval, 10 );
	\Stax\VisibilityLogic\Conditions::reset();

	$found = \Stax\VisibilityLogic\Rules::find_in_elements( [ $atomic_box, $atomic_heading( 'at6', array_merge( $guest_only, [ 'enabled' => false ] ) ) ] );
	vle_is( 'scanner reads atomic props (enabled only)', [ 'afx1' ], wp_list_pluck( $found, 'id' ) );
}

// Visibility Logic Pro (when active): its conditions go through the same filter and are never locked on the front end.
if ( defined( 'STAX_VISIBILITY_PRO_VERSION' ) && class_exists( '\Stax\VisibilityLogicPro\QueryStringVisibility' ) ) {
	WP_CLI::log( 'Visibility Logic Pro ' . STAX_VISIBILITY_PRO_VERSION );
	$qs = vle_s(
		[
			'enabled'                 => 'yes',
			'show_hide'               => 'yes',
			'query_string_enabled'    => 'yes',
			'query_string_conditions' => [
				[
					'_id'             => 'q1',
					'param_name'      => 'vle_q',
					'param_condition' => 'exists',
				],
			],
		]
	);
	vle_case( 'pro url parameter missing: hidden', $qs, 0, 'absent' );
	$_REQUEST['vle_q'] = '1';
	$_GET['vle_q']     = '1';
	vle_case( 'pro url parameter present: visible', $qs, 0, 'visible' );
	unset( $_REQUEST['vle_q'], $_GET['vle_q'] );
}

if ( class_exists( '\Stax\VisibilityLogic\Pro_Teaser' ) ) {
	$has_pro = defined( 'STAX_VISIBILITY_PRO_VERSION' );
	WP_CLI::log( 'Merged ACF Field / User Meta sections and PRO marks (' . ( $has_pro ? 'Pro active' : 'free only' ) . ')' );

	$GLOBALS['vle_test_acf'] = [
		'vle_color' => 'red',
		'vle_count' => '7',
	];
	$acf_rule = function ( array $rule ) {
		return vle_s(
			array_merge(
				[
					'enabled'     => 'yes',
					'show_hide'   => 'yes',
					'acf_enabled' => 'yes',
				],
				$rule
			)
		);
	};

	// A 2.x free rule (no acf_source saved) and one saved with acf_source = post: same as before.
	if ( ! class_exists( 'ACF' ) ) {
		vle_case( 'acf 2.x rule without acf_source', $acf_rule( [ 'acf_field' => 'vle_color', 'acf_condition' => 'equals', 'acf_value' => 'red' ] ), 0, 'visible' );
		vle_case( 'acf rule with acf_source post', $acf_rule( [ 'acf_source' => 'post', 'acf_field' => 'vle_color', 'acf_condition' => 'equals', 'acf_value' => 'blue' ] ), 0, 'absent' );

		// Saved with a Pro operator (Pro active when it was saved): evaluated by Pro, never without it.
		$pro_op = $acf_rule( [ 'acf_field' => 'vle_count', 'acf_condition' => 'greater_than', 'acf_value' => '9' ] );
		vle_case( 'acf rule with a Pro operator: ' . ( $has_pro ? 'evaluated by Pro' : 'never runs without Pro' ), $pro_op, 0, $has_pro ? 'absent' : 'visible' );
		$pro_src = $acf_rule( [ 'acf_source' => 'options', 'acf_field' => 'vle_none', 'acf_condition' => 'not_empty' ] );
		vle_case( 'acf rule with a Pro source: ' . ( $has_pro ? 'evaluated by Pro' : 'never runs without Pro' ), $pro_src, 0, $has_pro ? 'absent' : 'visible' );
	}

	// Elementor controls of the merged section.
	$widget   = \Elementor\Plugin::$instance->elements_manager->create_element_instance( vle_w( 'ctl' ) );
	$controls = $widget->get_controls();
	$cond     = isset( $controls['stax_visibility_acf_condition'] ) ? $controls['stax_visibility_acf_condition'] : [];
	// Elementor leaves labels and options out of the front-end control stack: they are read from the module.
	list( $ops, $ops_locked ) = \Stax\VisibilityLogic\AcfVisibility::all_condition_options();
	vle_is( 'acf condition control lists the free operators unlocked', true, isset( $ops['equals'] ) && ! in_array( 'equals', $ops_locked, true ) && $ops_locked === array_values( (array) $cond['locked_options'] ) );
	vle_is( 'acf condition control is a stax_select', 'stax_select', isset( $cond['type'] ) ? $cond['type'] : '' );

	if ( ! $has_pro ) {
		vle_is( 'free only: Pro operators listed, disabled, labelled (Pro)', [ true, 'Matches regex (Pro)' ], [ in_array( 'regex', (array) $cond['locked_options'], true ), $ops['regex'] ] );
		vle_is( 'free only: Pro sources listed, disabled', [ 'user', 'term', 'options', 'specific' ], array_values( (array) $controls['stax_visibility_acf_source']['locked_options'] ) );
		vle_is( 'free only: one "More conditions in Pro" section', true, isset( $controls['stax_visibility_more_pro'] ) );
		vle_is(
			'free only: no empty section per Pro condition (2.x)',
			[],
			array_values(
				array_filter(
					array_keys( $controls ),
					function ( $k ) {
						return 0 === strpos( $k, 'stax_visibility_pro_' );
					}
				)
			)
		);
		vle_is( 'free only: no Pro controls registered (nothing to save)', [ false, false, false ], [ isset( $controls['stax_visibility_acf_post_id'] ), isset( $controls['stax_visibility_acf_rules'] ), isset( $controls['stax_visibility_user_meta_rules'] ) ] );

		$html = \Stax\VisibilityLogic\Pro_Teaser::more_html( 'editor' );
		vle_is( 'PRO mark: text PRO, aria-label "Pro feature"', 1, preg_match( '#<span class="stax-vl-pro" role="img" aria-label="Pro feature">PRO</span>#', $html ) );
		vle_is( 'PRO line: exactly one link, product page with UTM', [ 1, 1 ], [ substr_count( $html, '<a ' ), preg_match( '#https://staxwp\.com/visibility-logic-for-elementor/\?utm_source=visibility-logic-free&(amp;|\#038;)?utm_medium=editor&(amp;|\#038;)?utm_campaign=upgrade\#pricing#', $html ) ] );
		vle_is( 'block editor gets the PRO line data', true, is_array( \Stax\VisibilityLogic\Pro_Teaser::for_js() ) );

		$fields = \Stax\VisibilityLogic\Conditions::fields( 'blocks' );
		$list   = [];
		foreach ( $fields['acf']['fields'] as $f ) {
			if ( 'acf_condition' === $f['key'] ) {
				$list = \Stax\VisibilityLogic\Conditions::option_list( $f['options'], $f['locked_options'] );
			}
		}
		$regex = array_values( wp_list_filter( $list, [ 'value' => 'regex' ] ) );
		$equal = array_values( wp_list_filter( $list, [ 'value' => 'equals' ] ) );
		vle_is( 'block editor: Pro operator disabled, free one enabled', [ true, false ], [ ! empty( $regex[0]['disabled'] ), ! empty( $equal[0]['disabled'] ) ] );
	} else {
		vle_is( 'Pro active: no PRO marks', [ false, false, null ], [ isset( $controls['stax_visibility_more_pro'] ), false !== strpos( implode( ' ', $ops ), '(Pro)' ), \Stax\VisibilityLogic\Pro_Teaser::for_js() ] );
	}

	vle_is( 'scanner labels the rule groups', [ 'ACF Field (rule group)', 'User Meta (rule group)' ], [ \Stax\VisibilityLogic\Rules::condition_label( 'acf_rules' ), \Stax\VisibilityLogic\Rules::condition_label( 'user_meta_rules' ) ] );
	$GLOBALS['vle_test_acf'] = [];
}

if ( class_exists( '\Stax\VisibilityLogic\Rules' ) ) {
	WP_CLI::log( 'Rules scanner' );
	$tree  = [
		$section( 's1', [ vle_w( 'a1', $guest_show ), vle_w( 'a2' ) ] ),
		$container(
			'c1',
			[
				vle_w(
					'a3',
					[
						'ecl_enabled'      => 'yes',
						'ecl_role_visible' => [ 'ecl-user' ],
					]
				),
			],
			$role( [ 'editor' ] )
		),
	];
	$found = \Stax\VisibilityLogic\Rules::find_in_elements( $tree );
	vle_is( 'scanner finds 3 elements', 3, count( $found ) );
	vle_is( 'scanner reports conditions', [ 'user_role' ], $found[0]['conditions'] );
	vle_is( 'scanner flags legacy settings', true, $found[2]['legacy'] );
	vle_is( 'scanner reports action', 'show', $found[0]['action'] );
	vle_is(
		'active conditions include Pro keys',
		[ 'post', 'query_string' ],
		\Stax\VisibilityLogic\Rules::active_conditions(
			vle_s(
				[
					'enabled'              => 'yes',
					'post_enabled'         => 'yes',
					'query_string_enabled' => 'yes',
					'fallback_enabled'     => 'yes',
					'acf_enabled'          => '',
				]
			)
		)
	);
	vle_is( 'has_rules ignores disabled', false, \Stax\VisibilityLogic\Rules::has_rules( vle_s( [ 'enabled' => '' ] ) ) );
}

if ( class_exists( '\\Stax\\VisibilityLogic\\Abilities' ) ) {
	WP_CLI::log( 'Abilities' );
	$conditions = \Stax\VisibilityLogic\Abilities::list_conditions();
	vle_is(
		'list-conditions has the 6 free conditions',
		6,
		count(
			array_filter(
				$conditions['conditions'],
				function ( $c ) {
					return ! $c['pro'];
				}
			)
		)
	);
	if ( function_exists( 'wp_get_ability' ) ) {
		vle_is( 'list-rules ability registered', true, null !== wp_get_ability( 'visibility-logic/list-rules' ) );
		vle_is( 'list-conditions ability registered', true, null !== wp_get_ability( 'visibility-logic/list-conditions' ) );
		wp_set_current_user( 0 );
		$ability = wp_get_ability( 'visibility-logic/list-rules' );
		vle_is( 'list-rules refused for guests', true, is_wp_error( $ability->execute( [] ) ) );
	}
}

// Cleanup.
if ( false === $GLOBALS['vle_settings_backup'] ) {
	delete_option( 'stax_visibility_general' );
} else {
	update_option( 'stax_visibility_general', $GLOBALS['vle_settings_backup'] );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $vle['users'] as $uid ) {
	wp_delete_user( $uid );
}
wp_set_current_user( 0 );

WP_CLI::log( sprintf( '%d passed, %d failed', $vle['pass'], $vle['fail'] ) );
if ( $vle['fail'] ) {
	exit( 1 );
}
