<?php
/**
 * Rule builder data: the condition registry for the builder, the stored rule tree, the mapping between the tree and
 * the 2.x settings (both ways), evaluation, summaries and warnings. The engine is the shared StaxWP Conditions
 * package (lib/stax-conditions); this class is the Visibility Logic adapter.
 *
 * Storage (backward compatibility, see docs/progress.md):
 * - Old rules keep working as they are: no bulk migration. The builder shows the 2.x settings as a tree
 *   ("All" = one group, "At least one" = one condition per group) and, when the edited rule can still be written
 *   in the 2.x format, writes only the 2.x keys (nothing changes when nothing was edited).
 * - A rule that 2.x cannot express (groups, the same condition twice, draft, options added in 3.0) is stored as a
 *   tree in `stax_visibility_rules`, and the 2.x keys are set to a fail-closed rule: shown to administrators only.
 *   So a downgrade to 2.x never shows members-only content to everyone.
 * - The JS twin (assets/js/rule-map.js) does the same in the editors; tests/fixtures/rule-map.json is checked by
 *   both.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

use StaxWP\Conditions\V1\Evaluator;
use StaxWP\Conditions\V1\Registry;
use StaxWP\Conditions\V1\Summary;
use StaxWP\Conditions\V1\Tree;
use StaxWP\Conditions\V1\Warnings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rule_Tree
 */
class Rule_Tree {

	/**
	 * Setting name of the stored tree (without the prefix).
	 */
	const KEY = 'rules';

	/**
	 * First version whose options force tree storage.
	 */
	const SINCE = '3.0.0';

	/**
	 * Element-level settings that never belong to a condition.
	 */
	const ELEMENT_KEYS = [ 'enabled', 'show_hide', 'condition_type', 'keep_html', 'hide_when_empty', 'rules', 'fallback_enabled', 'fallback_type', 'fallback_text', 'fallback_template', 'fallback_wrapper' ];

	/**
	 * Registry cache.
	 *
	 * @var Registry|null
	 */
	private static $registry = null;

	/**
	 * Traces recorded in this request (admin bar "why hidden"), when recording is on.
	 *
	 * @var array|null
	 */
	private static $traces = null;

	/**
	 * Categories (task based).
	 *
	 * @return array
	 */
	public static function categories() {
		return [
			'who'    => __( 'Who', 'visibility-logic-elementor' ),
			'when'   => __( 'When', 'visibility-logic-elementor' ),
			'device' => __( 'Device and browser', 'visibility-logic-elementor' ),
			'page'   => __( 'Page and content', 'visibility-logic-elementor' ),
			'shop'   => __( 'Shop', 'visibility-logic-elementor' ),
			'where'  => __( 'Where from', 'visibility-logic-elementor' ),
			'other'  => __( 'Other', 'visibility-logic-elementor' ),
		];
	}

	/**
	 * Builder data of the free conditions (category, summary templates, keywords, warning hints). Add-ons put the
	 * same keys in their `stax/visibility/condition_fields` entries.
	 *
	 * @return array
	 */
	public static function meta() {
		return [
			'user_role'    => [
				'category'  => 'who',
				'keywords'  => [ __( 'member', 'visibility-logic-elementor' ), __( 'logged in', 'visibility-logic-elementor' ), __( 'guest', 'visibility-logic-elementor' ), __( 'user', 'visibility-logic-elementor' ), __( 'capability', 'visibility-logic-elementor' ) ],
				'visitor'   => true,
				'exclusive' => 'user_role_conditions',
				/* translators: summary templates: keep the {placeholders} and [optional parts] as they are. */
				'summary'   => [
					'field' => 'user_role_by',
					'cases' => [
						'users'      => __( 'Visitor is one of[ {user_role_users}]', 'visibility-logic-elementor' ),
						'capability' => __( 'Visitor has the capability[ {user_role_caps|or}]', 'visibility-logic-elementor' ),
						'*'          => __( 'User role[ is {user_role_conditions|or}]', 'visibility-logic-elementor' ),
					],
				],
				'short'     => [
					'field' => 'user_role_by',
					'cases' => [
						'users'      => __( 'Specific users', 'visibility-logic-elementor' ),
						'capability' => __( '[{user_role_caps}]', 'visibility-logic-elementor' ),
						'*'          => '{user_role_conditions}',
					],
				],
			],
			'user_meta'    => [
				'category' => 'who',
				'keywords' => [ __( 'profile', 'visibility-logic-elementor' ), __( 'field', 'visibility-logic-elementor' ), __( 'custom field', 'visibility-logic-elementor' ) ],
				'visitor'  => true,
				'summary'  => __( 'User meta[ {user_meta_options}] {user_meta_status|lower}[ {user_meta_value}][ and {user_meta_value_2}]', 'visibility-logic-elementor' ),
				'short'    => __( 'User meta[ {user_meta_options}]', 'visibility-logic-elementor' ),
			],
			'date_time'    => [
				'category' => 'when',
				'keywords' => [ __( 'schedule', 'visibility-logic-elementor' ), __( 'time', 'visibility-logic-elementor' ), __( 'day', 'visibility-logic-elementor' ), __( 'week', 'visibility-logic-elementor' ), __( 'season', 'visibility-logic-elementor' ), __( 'yearly', 'visibility-logic-elementor' ) ],
				'visitor'  => true,
				'expires'  => [ 'field' => 'date_to', 'when' => [ 'date_time_type' => [ 'date' ] ] ],
				'starts'   => [ 'field' => 'date_from', 'when' => [ 'date_time_type' => [ 'date' ] ] ],
				'summary'  => [
					'field' => 'date_time_type',
					'cases' => [
						'date'           => __( 'Date is[ from {date_from}][ until {date_to}]', 'visibility-logic-elementor' ),
						'time'           => __( 'Time of day is[ from {time_from}][ to {time_to|after:time_from}]', 'visibility-logic-elementor' ),
						'week_days'      => __( 'Day of the week is[ {time_week|or}]', 'visibility-logic-elementor' ),
						// 2.x semantics: a range over midnight applies within the selected day only, so no "next day".
						'week_days_time' => __( 'Day is[ {time_week|or}][ from {time_from}][ to {time_to}]', 'visibility-logic-elementor' ),
						'yearly'         => __( 'Every year[ from {date_year_from}][ {date_year_from_time}][ to {date_year_to}][ {date_year_to_time}]', 'visibility-logic-elementor' ),
						'*'              => __( 'Date and time', 'visibility-logic-elementor' ),
					],
				],
				'short'    => [
					'field' => 'date_time_type',
					'cases' => [
						'date'      => __( '[until {date_to}]', 'visibility-logic-elementor' ),
						'time'      => __( '[{time_from}-{time_to}]', 'visibility-logic-elementor' ),
						'week_days' => '[{time_week}]',
						'yearly'    => __( 'Every year[ {date_year_from}-{date_year_to}]', 'visibility-logic-elementor' ),
						'*'         => __( 'Schedule', 'visibility-logic-elementor' ),
					],
				],
			],
			'browser_type' => [
				'category'  => 'device',
				'keywords'  => [ 'chrome', 'safari', 'firefox', 'edge' ],
				'visitor'   => true,
				'exclusive' => 'browsers',
				'summary'   => __( 'Browser[ is {browsers|or}]', 'visibility-logic-elementor' ),
				'short'     => '{browsers}',
			],
			'device_type'  => [
				'category'  => 'device',
				'keywords'  => [ __( 'mobile', 'visibility-logic-elementor' ), __( 'tablet', 'visibility-logic-elementor' ), __( 'desktop', 'visibility-logic-elementor' ), __( 'phone', 'visibility-logic-elementor' ) ],
				'visitor'   => true,
				'exclusive' => 'device_types',
				'summary'   => __( 'Device[ is {device_types|or}]', 'visibility-logic-elementor' ),
				'short'     => '{device_types}',
			],
			'page'         => [
				'category' => 'page',
				'keywords' => [ __( 'front page', 'visibility-logic-elementor' ), __( 'post type', 'visibility-logic-elementor' ), __( 'search', 'visibility-logic-elementor' ), '404', __( 'blog', 'visibility-logic-elementor' ) ],
				'summary'  => __( 'Page[ type is {page_types|or}][ post type is {page_post_types|or}][ is one of {page_ids}]', 'visibility-logic-elementor' ),
				'short'    => __( 'Page[: {page_types}][ {page_ids}]', 'visibility-logic-elementor' ),
			],
			'acf'          => [
				'category' => 'page',
				'keywords' => [ 'advanced custom fields', __( 'custom field', 'visibility-logic-elementor' ), __( 'meta', 'visibility-logic-elementor' ) ],
				'summary'  => __( 'ACF field[ {acf_field}] {acf_condition|lower}[ {acf_value}][ and {acf_value_2}]', 'visibility-logic-elementor' ),
				'short'    => __( 'ACF[ {acf_field}]', 'visibility-logic-elementor' ),
			],
		];
	}

	/**
	 * Built-in presets (they only fill normal conditions).
	 *
	 * @return array
	 */
	public static function presets() {
		$condition = static function ( $type, array $values ) {
			return [
				'type'   => $type,
				'values' => $values,
			];
		};

		return [
			'members'   => [
				'label'       => __( 'Logged-in members only', 'visibility-logic-elementor' ),
				'description' => __( 'Hidden from guests', 'visibility-logic-elementor' ),
				'action'      => 'show',
				'groups'      => [ [ 'conditions' => [ $condition( 'user_role', [ 'user_role_conditions' => [ 'ecl-user' ] ] ) ] ] ],
			],
			'guests'    => [
				'label'       => __( 'Guests only', 'visibility-logic-elementor' ),
				'description' => __( 'Hidden from logged-in users', 'visibility-logic-elementor' ),
				'action'      => 'show',
				'groups'      => [ [ 'conditions' => [ $condition( 'user_role', [ 'user_role_conditions' => [ 'ecl-guest' ] ] ) ] ] ],
			],
			'no_mobile' => [
				'label'       => __( 'Hide on mobile', 'visibility-logic-elementor' ),
				'description' => __( 'Phones, detected on the server', 'visibility-logic-elementor' ),
				'action'      => 'hide',
				'groups'      => [ [ 'conditions' => [ $condition( 'device_type', [ 'device_types' => [ 'mobile' ] ] ) ] ] ],
			],
			'dates'     => [
				'label'       => __( 'Only during a date range', 'visibility-logic-elementor' ),
				'description' => __( 'Pick the start and end', 'visibility-logic-elementor' ),
				'action'      => 'show',
				'groups'      => [ [ 'conditions' => [ $condition( 'date_time', [ 'date_time_type' => 'date' ] ) ] ] ],
			],
			'hours'     => [
				'label'       => __( 'Business hours', 'visibility-logic-elementor' ),
				'description' => __( 'Monday to Friday, 9:00 to 17:00', 'visibility-logic-elementor' ),
				'action'      => 'show',
				'groups'      => [ [ 'conditions' => [ $condition( 'date_time', [ 'date_time_type' => 'week_days_time', 'time_week' => [ '1', '2', '3', '4', '5' ], 'time_from' => '09:00', 'time_to' => '17:00' ] ) ] ] ],
			],
			'admins'    => [
				'label'       => __( 'Administrators only', 'visibility-logic-elementor' ),
				'description' => __( 'Work in progress content', 'visibility-logic-elementor' ),
				'action'      => 'show',
				'groups'      => [ [ 'conditions' => [ $condition( 'user_role', [ 'user_role_conditions' => [ 'administrator' ] ] ) ] ] ],
			],
		];
	}

	/**
	 * Translated summary wording.
	 *
	 * @return array
	 */
	public static function labels() {
		return [
			/* translators: %s: the conditions of the rule. */
			'show'       => __( 'Shown only when %s', 'visibility-logic-elementor' ),
			/* translators: %s: the conditions of the rule. */
			'hide'       => __( 'Hidden when %s', 'visibility-logic-elementor' ),
			'and'        => __( ' and ', 'visibility-logic-elementor' ),
			'or'         => __( ' or ', 'visibility-logic-elementor' ),
			'draft'      => __( 'Hidden on the live site (draft)', 'visibility-logic-elementor' ),
			'none'       => __( 'No conditions yet', 'visibility-logic-elementor' ),
			/* translators: 1: first items, 2: number of other items. */
			'more'       => __( '%1$s and %2$d more', 'visibility-logic-elementor' ),
			'yes'        => __( 'yes', 'visibility-logic-elementor' ),
			'no'         => __( 'no', 'visibility-logic-elementor' ),
			'short_show' => '%s',
			/* translators: %s: short rule label. */
			'short_hide' => __( 'Hidden: %s', 'visibility-logic-elementor' ),
			'short_more' => '%1$s +%2$d',
			/* translators: %d: number of users. */
			'users'      => __( '%d users', 'visibility-logic-elementor' ),
			/* translators: %d: number of rules. */
			'rows'       => __( '%d rules', 'visibility-logic-elementor' ),
			/* translators: %d: number of pages or posts. */
			'posts'      => __( '%d items', 'visibility-logic-elementor' ),
			'next_day'   => __( ' (next day)', 'visibility-logic-elementor' ),
		];
	}

	/**
	 * Load the shared package (the highest bundled copy is used, see lib/stax-conditions/load.php).
	 *
	 * @return void
	 */
	public static function load_package() {
		require_once STAX_VISIBILITY_PATH . 'lib/stax-conditions/load.php';
	}

	/**
	 * The registry of the builder: every registered condition (free, Pro, add-ons), Pro conditions listed locked
	 * when Pro is missing, conditions without builder fields (older add-ons) as read-only types.
	 *
	 * @param bool $fresh Rebuild.
	 *
	 * @return Registry
	 */
	public static function registry( $fresh = false ) {
		if ( null !== self::$registry && ! $fresh ) {
			return self::$registry;
		}

		$meta     = self::meta();
		$options  = Resources::get_all_widget_options();
		$by_slug  = [];
		$types    = [];
		$switches = [];

		foreach ( $options as $slug => $option ) {
			$by_slug[ 'post-page' === $slug ? 'post' : str_replace( '-', '_', $slug ) ] = $option;
		}

		foreach ( Conditions::fields( 'rules' ) as $slug => $condition ) {
			$type = array_merge( isset( $meta[ $slug ] ) ? $meta[ $slug ] : [], $condition );
			$base = substr( $condition['switch'], 0, -8 ); // Without "_enabled".

			$type['label']    = wp_strip_all_tags( (string) $condition['label'] );
			$type['fields']   = array_values( $condition['fields'] );
			$type['lock']     = Conditions::lock_notice( $condition );
			$type['locked']   = null !== $type['lock'];
			$type['disabled'] = isset( $by_slug[ $base ] ) && empty( $by_slug[ $base ]['status'] );
			$type['pro']      = ! empty( $condition['pro'] ) || ( isset( $by_slug[ $base ] ) && ! empty( $by_slug[ $base ]['pro'] ) );

			unset( $type['locked_notice'] );

			$types[ $slug ]                  = $type;
			$switches[ $condition['switch'] ] = true;
		}

		// Conditions of the 2.x list without builder fields: Pro conditions without Pro (listed, locked) and older
		// add-on conditions (read-only: edited in the classic sections).
		foreach ( $by_slug as $base => $option ) {
			// Fallback is element output ("When hidden"), not a condition.
			if ( 'fallback' === $base || isset( $switches[ $base . '_enabled' ] ) || isset( $types[ $base ] ) ) {
				continue;
			}

			$pro_missing = ! empty( $option['pro'] ) && Pro_Teaser::visible();

			$types[ $base ] = [
				'label'    => wp_strip_all_tags( (string) $option['name'] ),
				'category' => self::guess_category( $base ),
				'fields'   => [],
				'switch'   => $base . '_enabled',
				'pro'      => ! empty( $option['pro'] ),
				'locked'   => $pro_missing,
				// Without Pro: what it does and one link (utm_content = condition id) when it is clicked.
				'lock'     => $pro_missing ? Pro_Teaser::condition_note( $base, $option['name'], isset( $option['description'] ) ? $option['description'] : '' ) : null,
				'readonly' => ! $pro_missing,
				'hidden'   => ! $pro_missing, // Not offered in the picker; shown when an element already uses it.
				'tree'     => false,
			];
		}

		// Time zone next to every date and time input (V-M9).
		$zone = sprintf(
			/* translators: 1: time zone name, 2: current site time. */
			__( 'Site time zone: %1$s (now %2$s).', 'visibility-logic-elementor' ),
			wp_timezone_string(),
			Context::local( 'Y-m-d H:i' )
		);

		foreach ( $types as $slug => $type ) {
			$types[ $slug ]['fields'] = self::with_zone_help( (array) $type['fields'], $zone );
		}

		$registry = new Registry( $types, self::categories(), self::presets() );

		if ( did_action( 'wp_loaded' ) ) {
			self::$registry = $registry;
		}

		return $registry;
	}

	/**
	 * Add the time zone note to date and time fields (repeater rows included).
	 *
	 * @param array  $fields Fields.
	 * @param string $zone   Note.
	 *
	 * @return array
	 */
	private static function with_zone_help( array $fields, $zone ) {
		foreach ( $fields as $i => $field ) {
			$type = isset( $field['type'] ) ? $field['type'] : '';

			if ( in_array( $type, [ 'date', 'time' ], true ) ) {
				$fields[ $i ]['help'] = trim( ( isset( $field['help'] ) ? $field['help'] . ' ' : '' ) . $zone );
			} elseif ( 'repeater' === $type && ! empty( $field['fields'] ) ) {
				$fields[ $i ]['fields'] = self::with_zone_help( (array) $field['fields'], $zone );
			}
		}

		return $fields;
	}

	/**
	 * Forget the cached registry (tests, settings changes).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$registry = null;
	}

	/**
	 * Category of a condition that did not declare one.
	 *
	 * @param string $slug Slug.
	 *
	 * @return string
	 */
	private static function guess_category( $slug ) {
		$map = [
			'post'                 => 'page',
			'archive'              => 'page',
			'conditional_tags'     => 'page',
			'dynamic_conditions'   => 'page',
			'advanced_acf'         => 'page',
			'woocommerce_users'    => 'shop',
			'edd_users'            => 'shop',
			'geo_location'         => 'where',
			'ip_referrer'          => 'where',
			'query_string'         => 'where',
			'language'             => 'where',
			'advanced_user_meta'   => 'who',
			'shop'                 => 'shop',
			'split'                => 'other',
			'view_cap'             => 'who',
			'content_details'      => 'page',
		];

		return isset( $map[ $slug ] ) ? $map[ $slug ] : 'other';
	}

	/**
	 * Switch setting of a type ( `<slug>_enabled` when not declared).
	 *
	 * @param string $type Type slug.
	 *
	 * @return string
	 */
	public static function switch_of( $type ) {
		$entry = self::registry()->type( $type );

		return $entry && ! empty( $entry['switch'] ) ? (string) $entry['switch'] : $type . '_enabled';
	}

	/**
	 * Stored tree of element settings (`<prefix>rules`), or null when the element uses the 2.x format.
	 *
	 * @param array  $settings Settings.
	 * @param string $prefix   Key prefix (Singleton::SECTION_PREFIX for Elementor-style settings, '' for block values).
	 *
	 * @return array|null
	 */
	public static function stored( array $settings, $prefix = Singleton::SECTION_PREFIX ) {
		if ( empty( $settings[ $prefix . self::KEY ] ) ) {
			return null;
		}

		self::load_package();

		$tree = Tree::normalize( $settings[ $prefix . self::KEY ] );

		return $tree && ! Tree::is_empty( $tree ) ? $tree : null;
	}

	/**
	 * The rule of element settings as a tree: the stored tree, or the 2.x settings mapped ("All" = one group,
	 * "At least one" = one condition per group).
	 *
	 * @param array  $settings Settings.
	 * @param string $prefix   Prefix.
	 *
	 * @return array
	 */
	public static function from_settings( array $settings, $prefix = Singleton::SECTION_PREFIX ) {
		$stored = self::stored( $settings, $prefix );

		return $stored ? $stored : self::legacy_to_tree( $settings, $prefix );
	}

	/**
	 * 2.x settings to a tree.
	 *
	 * @param array  $settings Settings.
	 * @param string $prefix   Prefix.
	 *
	 * @return array
	 */
	public static function legacy_to_tree( array $settings, $prefix = Singleton::SECTION_PREFIX ) {
		self::load_package();

		$registry   = self::registry();
		$conditions = [];
		$seen       = [];

		foreach ( $registry->types() as $slug => $type ) {
			$switch = ! empty( $type['switch'] ) ? $type['switch'] : $slug . '_enabled';

			if ( ! self::on( $settings, $prefix . $switch ) ) {
				continue;
			}

			$values = [];

			foreach ( (array) $type['fields'] as $field ) {
				if ( isset( $field['key'] ) && array_key_exists( $prefix . $field['key'], $settings ) ) {
					$values[ $field['key'] ] = $settings[ $prefix . $field['key'] ];
				}
			}

			$conditions[]     = [
				'id'     => 'l' . count( $conditions ),
				'type'   => $slug,
				'values' => $values,
			];
			$seen[ $switch ] = true;
		}

		// Switches that no registered condition owns (an add-on that is not active): kept as read-only conditions.
		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || ( '' !== $prefix && 0 !== strpos( $key, $prefix ) ) || ! self::on( $settings, $key ) ) {
				continue;
			}

			$name = substr( $key, strlen( $prefix ) );

			if ( ! preg_match( '/^([a-z0-9_]+)_enabled$/', $name, $m ) || isset( $seen[ $name ] ) || in_array( $m[1], [ 'fallback' ], true ) || '' === $m[1] || 'enabled' === $name ) {
				continue;
			}

			$conditions[] = [
				'id'     => 'l' . count( $conditions ),
				'type'   => $m[1],
				'values' => [],
			];
		}

		$one    = 'one' === ( isset( $settings[ $prefix . 'condition_type' ] ) ? $settings[ $prefix . 'condition_type' ] : 'all' );
		$groups = [];

		if ( $one && count( $conditions ) > 1 ) {
			foreach ( $conditions as $condition ) {
				$groups[] = [ 'conditions' => [ $condition ] ];
			}
		} else {
			$groups[] = [ 'conditions' => $conditions ];
		}

		return [
			'v'      => Tree::VERSION,
			// A rule without conditions starts as "Show" (the 2.x default "Hide" only matters once a condition exists).
			'action' => ( ! $conditions || self::on( $settings, $prefix . 'show_hide' ) ) ? 'show' : 'hide',
			'draft'  => false,
			'groups' => $groups,
		];
	}

	/**
	 * Whether a 2.x setting is on ('yes', true, 1).
	 *
	 * @param array  $settings Settings.
	 * @param string $key      Key.
	 *
	 * @return bool
	 */
	private static function on( array $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return false;
		}

		$value = $settings[ $key ];

		return true === $value || 'yes' === $value || 1 === $value || '1' === $value;
	}

	/**
	 * Whether the tree can be written in the 2.x format, and why not.
	 *
	 * @param array $tree Tree.
	 *
	 * @return array{flat:bool,reason:string,storable:bool} storable: the tree itself can be stored (no read-only
	 *                                                       condition in it).
	 */
	public static function analyze( array $tree ) {
		self::load_package();

		$registry = self::registry();
		$groups   = Tree::filled_groups( $tree );
		$reason   = '';
		$storable = true;
		$types    = [];

		foreach ( Tree::conditions( $tree ) as $condition ) {
			$type = $registry->type( $condition['type'] );

			if ( ! $type || ! empty( $type['readonly'] ) || false === $type['tree'] ) {
				$storable = false;
			}

			if ( isset( $types[ $condition['type'] ] ) && '' === $reason ) {
				$reason = 'repeat';
			}

			$types[ $condition['type'] ] = true;

			if ( '' === $reason && $type && ( ( ! empty( $type['since'] ) && version_compare( $type['since'], self::SINCE, '>=' ) ) || self::uses_new_options( $condition, $type ) ) ) {
				$reason = 'new';
			}
		}

		if ( ! empty( $tree['draft'] ) ) {
			$reason = 'draft';
		} elseif ( '' === $reason && count( $groups ) > 1 ) {
			foreach ( $groups as $group ) {
				if ( count( $group['conditions'] ) > 1 ) {
					$reason = 'groups';
					break;
				}
			}
		}

		return [
			'flat'     => '' === $reason,
			'reason'   => $reason,
			'storable' => $storable,
		];
	}

	/**
	 * Whether a condition uses a field or an option added in 3.0 (2.x would not understand it).
	 *
	 * @param array $condition Condition.
	 * @param array $type      Type.
	 *
	 * @return bool
	 */
	private static function uses_new_options( array $condition, array $type ) {
		foreach ( (array) $type['fields'] as $field ) {
			if ( ! isset( $field['key'] ) || ! array_key_exists( $field['key'], $condition['values'] ) ) {
				continue;
			}

			$value = $condition['values'][ $field['key'] ];

			// Rows of a repeater: the same check per row.
			if ( 'repeater' === ( isset( $field['type'] ) ? $field['type'] : '' ) && is_array( $value ) && ! empty( $field['fields'] ) ) {
				foreach ( $value as $row ) {
					if ( is_array( $row ) && self::uses_new_options( [ 'values' => $row ], [ 'fields' => $field['fields'] ] ) ) {
						return true;
					}
				}

				continue;
			}

			if ( ! empty( $field['since'] ) && version_compare( $field['since'], self::SINCE, '>=' ) && $value !== Registry::field_default( $field ) && ! \StaxWP\Conditions\V1\Operators::is_empty( $value ) ) {
				return true;
			}

			if ( ! empty( $field['options_since'] ) && is_scalar( $value ) && isset( $field['options_since'][ (string) $value ] ) && version_compare( $field['options_since'][ (string) $value ], self::SINCE, '>=' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Settings to write for an edited tree: only the keys whose value changes.
	 *
	 * @param array  $tree     Edited tree.
	 * @param array  $settings Current settings.
	 * @param string $prefix   Prefix.
	 *
	 * @return array|null Changes ( key => value ), null when the tree cannot be stored (read-only condition in a
	 *                    rule that 2.x cannot express).
	 */
	public static function to_storage( array $tree, array $settings, $prefix = Singleton::SECTION_PREFIX ) {
		$analysis = self::analyze( $tree );
		$changes  = [];
		$set      = static function ( $key, $value ) use ( &$changes, $settings, $prefix ) {
			// Never-saved keys read as their control default (Elementor).
			$defaults = [ 'condition_type' => 'all' ];
			$current  = array_key_exists( $prefix . $key, $settings ) ? $settings[ $prefix . $key ] : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : null );

			if ( $current !== $value && ! ( null === $current && ( '' === $value || [] === $value ) ) ) {
				$changes[ $prefix . $key ] = $value;
			}
		};

		if ( ! $analysis['flat'] ) {
			if ( ! $analysis['storable'] ) {
				return null;
			}

			$set( self::KEY, $tree );

			foreach ( self::fail_closed( $settings, $prefix ) as $key => $value ) {
				$set( substr( $key, strlen( $prefix ) ), $value );
			}

			return $changes;
		}

		$registry   = self::registry();
		$conditions = Tree::conditions( $tree );
		$groups     = Tree::filled_groups( $tree );
		$in_tree    = [];

		foreach ( $conditions as $condition ) {
			$in_tree[ $condition['type'] ] = $condition;
		}

		if ( $conditions ) {
			$set( 'show_hide', 'hide' === $tree['action'] ? '' : 'yes' );
		}

		if ( count( $conditions ) > 1 ) {
			$set( 'condition_type', count( $groups ) > 1 ? 'one' : 'all' );
		}

		$switches = [];

		foreach ( $registry->types() as $slug => $type ) {
			$switch              = ! empty( $type['switch'] ) ? $type['switch'] : $slug . '_enabled';
			$switches[ $switch ] = true;

			if ( isset( $in_tree[ $slug ] ) ) {
				$set( $switch, 'yes' );

				foreach ( $in_tree[ $slug ]['values'] as $key => $value ) {
					$set( $key, $value );
				}
			} elseif ( self::on( $settings, $prefix . $switch ) ) {
				$set( $switch, '' );
			}
		}

		// Switches of conditions no registered type owns: off unless the tree still has them.
		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || ( '' !== $prefix && 0 !== strpos( $key, $prefix ) ) || ! self::on( $settings, $key ) ) {
				continue;
			}

			$name = substr( $key, strlen( $prefix ) );

			if ( preg_match( '/^([a-z0-9_]+)_enabled$/', $name, $m ) && ! isset( $switches[ $name ] ) && 'fallback' !== $m[1] && ! isset( $in_tree[ $m[1] ] ) ) {
				$set( $name, '' );
			}
		}

		$set( self::KEY, '' );

		return $changes;
	}

	/**
	 * The 2.x keys of a tree-stored rule: Visibility Logic 2.x (which ignores the tree) shows the element to
	 * administrators only, never to everyone.
	 *
	 * @param array  $settings Current settings.
	 * @param string $prefix   Prefix.
	 *
	 * @return array Full keys => values.
	 */
	public static function fail_closed( array $settings, $prefix = Singleton::SECTION_PREFIX ) {
		$out = [];

		foreach ( $settings as $key => $value ) {
			if ( is_string( $key ) && ( '' === $prefix || 0 === strpos( $key, $prefix ) ) && preg_match( '/^([a-z0-9_]+)_enabled$/', substr( $key, strlen( $prefix ) ), $m ) && 'fallback' !== $m[1] && self::on( $settings, $key ) ) {
				$out[ $key ] = '';
			}
		}

		$out[ $prefix . 'show_hide' ]            = 'yes';
		$out[ $prefix . 'condition_type' ]       = 'all';
		$out[ $prefix . 'user_role_enabled' ]    = 'yes';
		$out[ $prefix . 'user_role_conditions' ] = [ 'administrator' ];

		return $out;
	}

	/**
	 * Evaluate a tree for the current visitor (or the simulated one, see Context).
	 *
	 * @param array  $tree    Tree.
	 * @param object $item    Element (id and name are passed to the condition modules).
	 * @param bool   $default Answer when the rule does not apply.
	 * @param bool   $trace   Full trace.
	 *
	 * @return array Evaluator result.
	 */
	public static function evaluate( array $tree, $item, $default = true, $trace = false ) {
		self::load_package();

		$id   = is_object( $item ) && method_exists( $item, 'get_id' ) ? (string) $item->get_id() : '';
		$name = is_object( $item ) && method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '';
		$kind = $item instanceof Condition_Item ? $item->get_type() : 'element';

		return Evaluator::evaluate(
			$tree,
			static function ( $condition ) use ( $id, $name, $kind ) {
				return self::check( $condition, $id, $name, $kind );
			},
			$default,
			$trace
		);
	}

	/**
	 * Evaluate one condition through the condition modules (`stax/visibility/apply_conditions`), with only that
	 * condition switched on.
	 *
	 * @param array  $condition Condition.
	 * @param string $id        Element id.
	 * @param string $name      Element name.
	 * @param string $kind      block|atomic|element.
	 *
	 * @return bool|null Null when no module answered (inactive add-on, condition turned off in Settings).
	 */
	public static function check( array $condition, $id = '', $name = '', $kind = 'element' ) {
		$switch = self::switch_of( $condition['type'] );
		$values = array_merge(
			(array) $condition['values'],
			[
				'enabled'        => true,
				'show_hide'      => 'yes',
				'condition_type' => 'all',
				$switch          => true,
			]
		);

		$settings = Conditions::to_settings( $values );
		$item     = new Condition_Item( $settings, $id, $name, $kind );
		$options  = apply_filters( 'stax/visibility/apply_conditions', [], $settings, $item ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.

		if ( empty( $options ) || ! is_array( $options ) ) {
			return null;
		}

		return ! in_array( false, array_map( 'boolval', $options ), true );
	}

	/**
	 * Display names of user ids (summaries).
	 *
	 * @return array type => formatter.
	 */
	public static function formatters() {
		return [
			'users' => static function ( $value ) {
				$names = [];

				foreach ( array_slice( (array) $value, 0, 3 ) as $id ) {
					$user    = get_userdata( (int) $id );
					$names[] = $user ? $user->display_name : '#' . (int) $id;
				}

				$more = count( (array) $value ) - count( $names );

				/* translators: 1: user names, 2: number of other users. */
				return $more > 0 ? sprintf( __( '%1$s and %2$d more', 'visibility-logic-elementor' ), implode( ', ', $names ), $more ) : implode( ', ', $names );
			},
			'posts' => static function ( $value ) {
				$names = [];

				foreach ( array_slice( (array) $value, 0, 3 ) as $id ) {
					$title   = get_the_title( (int) $id );
					$names[] = '' !== $title ? $title : '#' . (int) $id;
				}

				$more = count( (array) $value ) - count( $names );

				/* translators: 1: titles, 2: number of other items. */
				return $more > 0 ? sprintf( __( '%1$s and %2$d more', 'visibility-logic-elementor' ), implode( ', ', $names ), $more ) : implode( ', ', $names );
			},
		];
	}

	/**
	 * Full sentence of a tree.
	 *
	 * @param array $tree Tree.
	 *
	 * @return string
	 */
	public static function sentence( array $tree ) {
		self::load_package();

		return Summary::sentence( $tree, self::registry(), self::labels(), self::formatters() );
	}

	/**
	 * Short label of a tree.
	 *
	 * @param array $tree Tree.
	 *
	 * @return string
	 */
	public static function short( array $tree ) {
		self::load_package();

		return Summary::short( $tree, self::registry(), self::labels(), self::formatters() );
	}

	/**
	 * Sentence of one condition.
	 *
	 * @param array $condition Condition.
	 *
	 * @return string
	 */
	public static function condition_sentence( array $condition ) {
		self::load_package();

		return Summary::condition( $condition, self::registry(), self::labels(), self::formatters() );
	}

	/**
	 * Warnings of a tree (codes, see StaxWP\Conditions\V1\Warnings).
	 *
	 * @param array $tree Tree.
	 *
	 * @return array[]
	 */
	public static function warnings( array $tree ) {
		self::load_package();

		return Warnings::check( $tree, self::registry(), self::warning_context() );
	}

	/**
	 * Context of the warnings: site time now and whether a page cache was detected.
	 *
	 * @return array
	 */
	public static function warning_context() {
		return [
			'now'   => Context::local( 'Y-m-d H:i' ),
			'cache' => Cache_Check::has_page_cache(),
		];
	}

	/**
	 * Warning text.
	 *
	 * @param array $warning Warning.
	 *
	 * @return string
	 */
	public static function warning_text( array $warning ) {
		$type  = $warning['type'] ? self::registry()->type( $warning['type'] ) : null;
		$label = $type ? $type['label'] : $warning['type'];

		switch ( $warning['code'] ) {
			case 'draft':
				return __( 'Hidden on the live site: visitors never see this element.', 'visibility-logic-elementor' );
			case 'locked':
				/* translators: %s: condition name. */
				return sprintf( __( '%s needs a Pro license; it is kept and still applies.', 'visibility-logic-elementor' ), $label );
			case 'disabled':
				/* translators: %s: condition name. */
				return sprintf( __( '%s is turned off in the settings; it does not apply.', 'visibility-logic-elementor' ), $label );
			case 'incomplete':
				/* translators: %s: condition name. */
				return sprintf( __( '%s is not complete yet.', 'visibility-logic-elementor' ), $label );
			case 'expired':
				/* translators: 1: condition name, 2: date. */
				return sprintf( __( 'The date range of %1$s ended on %2$s.', 'visibility-logic-elementor' ), $label, $warning['value'] );
			case 'future':
				/* translators: 1: condition name, 2: date. */
				return sprintf( __( '%1$s starts on %2$s.', 'visibility-logic-elementor' ), $label, $warning['value'] );
			case 'never':
				/* translators: %s: condition name. */
				return sprintf( __( 'This group can never be true: %s conflicts with another condition.', 'visibility-logic-elementor' ), $label );
			case 'cache':
				return __( 'This rule depends on the visitor. If this page is cached for everyone, visitors may see the wrong version.', 'visibility-logic-elementor' );
		}

		return (string) $warning['code'];
	}

	/**
	 * Builder data for the editors (registry, labels, context).
	 *
	 * @return array
	 */
	public static function for_js() {
		self::load_package();

		$registry = self::registry()->to_array();

		return [
			'registry' => $registry,
			'labels'   => self::builder_labels(),
			'context'  => self::warning_context(),
			'timezone' => wp_timezone_string(),
			'proUrl'   => Pro_Teaser::visible() ? Pro_Teaser::url( 'panel' ) : '',
			'key'      => self::KEY,
			'since'    => self::SINCE,
		];
	}

	/**
	 * Translated builder labels (StaxConditions.builderLabels keys).
	 *
	 * @return array
	 */
	public static function builder_labels() {
		return self::labels() + [
			'builder'         => __( 'Visibility rules', 'visibility-logic-elementor' ),
			'actionLabel'     => __( 'Action', 'visibility-logic-elementor' ),
			'actionShow'      => __( 'Show', 'visibility-logic-elementor' ),
			'actionHide'      => __( 'Hide', 'visibility-logic-elementor' ),
			'when'            => __( 'this element when', 'visibility-logic-elementor' ),
			'allOf'           => __( 'All of these', 'visibility-logic-elementor' ),
			/* translators: %d: group number. */
			'groupN'          => __( 'Group %d: all of these', 'visibility-logic-elementor' ),
			'orLabel'         => __( 'or', 'visibility-logic-elementor' ),
			'addCondition'    => __( 'Add condition', 'visibility-logic-elementor' ),
			'addGroup'        => __( 'Add "or" group', 'visibility-logic-elementor' ),
			/* translators: %d: group number. */
			'removeGroup'     => __( 'Remove group %d', 'visibility-logic-elementor' ),
			'edit'            => __( 'Edit', 'visibility-logic-elementor' ),
			'remove'          => __( 'Remove', 'visibility-logic-elementor' ),
			/* translators: %s: condition summary. */
			'removeCondition' => __( 'Remove condition: %s', 'visibility-logic-elementor' ),
			'duplicate'       => __( 'Duplicate', 'visibility-logic-elementor' ),
			'moveTo'          => __( 'Move to', 'visibility-logic-elementor' ),
			/* translators: %d: group number. */
			'moveGroup'       => __( 'Group %d', 'visibility-logic-elementor' ),
			'moveNew'         => __( 'New "or" group', 'visibility-logic-elementor' ),
			'draftHelp'       => __( 'Visitors never see this element; you still see it in the editor.', 'visibility-logic-elementor' ),
			'search'          => __( 'Search conditions', 'visibility-logic-elementor' ),
			'all'             => __( 'All', 'visibility-logic-elementor' ),
			'presets'         => __( 'Start from a preset', 'visibility-logic-elementor' ),
			'close'           => __( 'Close', 'visibility-logic-elementor' ),
			'pro'             => __( 'Pro', 'visibility-logic-elementor' ),
			'proFeature'      => __( 'Pro feature', 'visibility-logic-elementor' ),
			/* translators: %s: condition name. */
			'lockedText'      => __( '%s needs Visibility Logic Pro.', 'visibility-logic-elementor' ),
			'learnMore'       => __( 'Learn more', 'visibility-logic-elementor' ),
			'noResults'       => __( 'No condition matches your search.', 'visibility-logic-elementor' ),
			'readOnly'        => __( 'This condition is edited in the classic settings.', 'visibility-logic-elementor' ),
			'addValue'        => __( 'Add', 'visibility-logic-elementor' ),
			/* translators: %s: value. */
			'removeValue'     => __( 'Remove %s', 'visibility-logic-elementor' ),
			'addRow'          => __( 'Add rule', 'visibility-logic-elementor' ),
			/* translators: %d: rule number. */
			'removeRow'       => __( 'Remove rule %d', 'visibility-logic-elementor' ),
			/* translators: %d: rule number. */
			'rowN'            => __( 'Rule %d', 'visibility-logic-elementor' ),
			'searchUsers'     => __( 'Search users', 'visibility-logic-elementor' ),
			'noUsers'         => __( 'No users found.', 'visibility-logic-elementor' ),
			'filterOptions'   => __( 'Filter options', 'visibility-logic-elementor' ),
			'warnings'        => [
				'draft'      => self::warning_text( [ 'code' => 'draft', 'type' => '', 'value' => '' ] ),
				/* translators: %s: condition name. */
				'locked'     => __( '%s needs a Pro license; it is kept and still applies.', 'visibility-logic-elementor' ),
				/* translators: %s: condition name. */
				'disabled'   => __( '%s is turned off in the settings; it does not apply.', 'visibility-logic-elementor' ),
				/* translators: %s: condition name. */
				'incomplete' => __( '%s is not complete yet.', 'visibility-logic-elementor' ),
				/* translators: 1: condition name, 2: date. */
				'expired'    => __( 'The date range of %1$s ended on %2$s.', 'visibility-logic-elementor' ),
				/* translators: 1: condition name, 2: date. */
				'future'     => __( '%1$s starts on %2$s.', 'visibility-logic-elementor' ),
				/* translators: %s: condition name. */
				'never'      => __( 'This group can never be true: %s conflicts with another condition.', 'visibility-logic-elementor' ),
				'cache'      => self::warning_text( [ 'code' => 'cache', 'type' => '', 'value' => '' ] ),
			],
			// Visibility Logic additions (element options, editor messages).
			'active'          => __( 'Rules on', 'visibility-logic-elementor' ),
			'activeHelp'      => __( 'Turn off to keep the rule without applying it.', 'visibility-logic-elementor' ),
			'whenHidden'      => __( 'When hidden', 'visibility-logic-elementor' ),
			'remove_page'     => __( 'Remove it from the page', 'visibility-logic-elementor' ),
			'keepHtml'        => __( 'Keep the HTML, hide with CSS', 'visibility-logic-elementor' ),
			'fallback'        => __( 'Show other content (Pro)', 'visibility-logic-elementor' ),
			'fallbackText'    => __( 'Show a message instead', 'visibility-logic-elementor' ),
			'fallbackTpl'     => __( 'Show an Elementor template instead', 'visibility-logic-elementor' ),
			'fallbackTplPro'  => __( 'Show an Elementor template instead (Pro)', 'visibility-logic-elementor' ),
			'fallbackMessage' => __( 'Message', 'visibility-logic-elementor' ),
			'fallbackHelp'    => __( 'Shown to visitors who do not see the element. Basic HTML is allowed.', 'visibility-logic-elementor' ),
			'fallbackChoose'  => __( 'Template', 'visibility-logic-elementor' ),
			'proColumns'      => __( 'Pro conditions on columns need Visibility Logic Pro 3.0 or newer.', 'visibility-logic-elementor' ),
			'searchPosts'     => __( 'Search pages and posts', 'visibility-logic-elementor' ),
			'hideEmpty'       => __( 'Hide this container when everything inside is hidden', 'visibility-logic-elementor' ),
			'classic'         => __( 'Classic settings', 'visibility-logic-elementor' ),
			'classicShow'     => __( 'Show the classic settings', 'visibility-logic-elementor' ),
			'classicHide'     => __( 'Hide the classic settings', 'visibility-logic-elementor' ),
			'readonlyBlock'   => __( 'This rule uses a condition the builder cannot store in groups. Keep one group, or edit it in the classic settings.', 'visibility-logic-elementor' ),
			'treeNote'        => __( 'Saved in the 3.0 format. Visibility Logic 2.x would show this element to administrators only.', 'visibility-logic-elementor' ),
		];
	}

	/**
	 * Start recording traces of this request (front-end "why hidden" list for editors).
	 *
	 * @return void
	 */
	public static function record_traces() {
		if ( null === self::$traces ) {
			self::$traces = [];
		}
	}

	/**
	 * Whether traces are recorded.
	 *
	 * @return bool
	 */
	public static function recording() {
		return null !== self::$traces;
	}

	/**
	 * Record a trace.
	 *
	 * @param string $id    Element id.
	 * @param string $name  Element name.
	 * @param array  $tree  Tree.
	 * @param array  $trace Evaluator result with trace.
	 *
	 * @return void
	 */
	public static function add_trace( $id, $name, array $tree, array $trace ) {
		if ( null === self::$traces || count( self::$traces ) >= 200 ) {
			return;
		}

		self::$traces[ $id . '|' . count( self::$traces ) ] = [
			'id'    => (string) $id,
			'name'  => (string) $name,
			'tree'  => $tree,
			'trace' => $trace,
		];
	}

	/**
	 * Recorded traces.
	 *
	 * @return array[]
	 */
	public static function traces() {
		return null === self::$traces ? [] : array_values( self::$traces );
	}
}
