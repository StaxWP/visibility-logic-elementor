<?php
/**
 * Date/Time visibility conditions for Elementor
 *
 * Parts of this code are adapted from:
 * Dynamic Content for Elementor by Dynamic.ooo
 * Source: dynamic-content-for-elementor/includes/extensions/DCE_Extension_Visibility.php
 * License: GPL v2 or later
 *
 * Specifically adapted:
 * - Weekday handling functionality using wp_locale
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Stax\VisibilityLogic\Singleton;

/**
 * Class DateTimeVisibility
 */
class DateTimeVisibility extends Singleton {

	/**
	 * DateTimeVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->register_elementor_settings( 'date_time_section' );

		add_filter( 'stax/visibility/apply_conditions', [ $this, 'apply_conditions' ], 10, 3 );
		add_filter( 'stax/visibility/condition_fields', [ $this, 'condition_fields' ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
	}

	/**
	 * Register section
	 *
	 * @param $element
	 * @return void
	 */
	public function register_section( $element ) {
		$element->start_controls_section(
			self::SECTION_PREFIX . 'date_time_section',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => __( 'Date Time', 'visibility-logic-elementor' ),
				'condition' => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * @param $element \Elementor\Widget_Base
	 * @param $section_id
	 * @param $args
	 */
	public function register_controls( $element, $args ) {
		$element->add_control(
			self::SECTION_PREFIX . 'date_time_enabled',
			[
				'label'          => __( 'Enable', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				// 'render_type'    => 'ui',
				'prefix_class'   => 'stax-date_time_enabled-',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'date_time_type',
			[
				'type'           => Controls_Manager::SELECT,
				'label'          => __( 'Select Type:', 'visibility-logic-elementor' ),
				'options'        => self::type_options( true ),
				'default'        => 'date',
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
				],
				// 'render_type'    => 'ui',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'date_from',
			[
				'label'          => __( 'Date FROM', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::DATE_TIME,
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
					self::SECTION_PREFIX . 'date_time_type' => 'date',
				],
				// 'render_type'    => 'ui',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'date_to',
			[
				'label'          => __( 'Date TO', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::DATE_TIME,
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
					self::SECTION_PREFIX . 'date_time_type' => 'date',
				],
				// 'render_type'    => 'ui',
				'style_transfer' => false,
			]
		);

		$week = self::weekday_options();

		$element->add_control(
			self::SECTION_PREFIX . 'time_week',
			[
				'label'          => __( 'Days of the WEEK', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SELECT2,
				'options'        => $week,
				'description'    => __( 'Select days in the week.', 'visibility-logic-elementor' ),
				'multiple'       => true,
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
					self::SECTION_PREFIX . 'date_time_type' => [ 'week_days', 'week_days_time' ],
				],
				// 'render_type'    => 'ui',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'time_from',
			[
				'label'          => __( 'Time FROM', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::TEXT,
				'placeholder'    => 'HH:mm',
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
					self::SECTION_PREFIX . 'date_time_type' => [ 'time', 'week_days_time' ],
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'time_to',
			[
				'label'          => __( 'Time TO', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::TEXT,
				'placeholder'    => 'HH:mm',
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
					self::SECTION_PREFIX . 'date_time_type' => [ 'time', 'week_days_time' ],
				],
				// 'render_type'    => 'ui',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'date_info_wdt',
			[
				'type'           => Controls_Manager::RAW_HTML,
				'raw'            => __( 'When picking time that extends over midnight, the condition will be applied just for the selected days. Example: Wednesday 23:00 - 01:00 doesn\'t mean that the condition will extend from 23:00 Wednesday till 01:00 Thursday. It will get applied only for Wednesday 00:00 - 01:00 and 23:00 - 24:00.', 'visibility-logic-elementor' ),
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
					self::SECTION_PREFIX . 'date_time_type' => 'week_days_time',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'date_info',
			[
				'type'           => Controls_Manager::RAW_HTML,
				'raw'            => ( Resources::get_setting( 'site_timezone' )
					? esc_html__( 'Dates and times use the site time zone (Settings > General). Now:', 'visibility-logic-elementor' )
					: esc_html__( 'Times use the site time zone, dates FROM/TO use UTC (changed in Settings > Visibility Logic). Site time now:', 'visibility-logic-elementor' ) )
					. '<br><strong>' . esc_html( wp_date( 'Y-m-d H:i' ) ) . '</strong>',
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);
	}

	/**
	 * Date & Time types (value => label).
	 *
	 * @return array
	 */
	public static function type_options( $legacy = false ) {
		$options = [
			'date'           => __( 'Date FROM/TO', 'visibility-logic-elementor' ),
			'time'           => __( 'Time FROM/TO', 'visibility-logic-elementor' ),
			'week_days'      => __( 'Week Days', 'visibility-logic-elementor' ),
			'week_days_time' => __( 'Week Days + Time FROM/TO', 'visibility-logic-elementor' ),
			'yearly'         => __( 'Every year, FROM/TO day', 'visibility-logic-elementor' ),
		];

		// The classic Elementor sections keep the 2.x choices (new ones are made in the rule builder, which stores
		// them so that Visibility Logic 2.x fails closed, see Rule_Tree).
		if ( $legacy ) {
			unset( $options['yearly'] );
		}

		return $options;
	}

	/**
	 * Fields of this condition for the block editor and Elementor 4 atomic elements (see Conditions).
	 *
	 * @param array $conditions Registered conditions.
	 *
	 * @return array
	 */
	public function condition_fields( $conditions ) {
		$conditions['date_time'] = [
			'label'  => __( 'Date & Time', 'visibility-logic-elementor' ),
			'switch' => 'date_time_enabled',
			'fields' => [
				[
					'key'           => 'date_time_type',
					'type'          => 'select',
					'label'         => __( 'Select Type:', 'visibility-logic-elementor' ),
					'options'       => self::type_options(),
					'default'       => 'date',
					'options_since' => [ 'yearly' => '3.0.0' ],
				],
				[
					'key'         => 'date_year_from',
					'type'        => 'text',
					'label'       => __( 'Every year FROM (month-day)', 'visibility-logic-elementor' ),
					'placeholder' => '12-20',
					'show_if'     => [ 'date_time_type' => [ 'yearly' ] ],
					'since'       => '3.0.0',
				],
				[
					'key'         => 'date_year_from_time',
					'type'        => 'time',
					'label'       => __( 'FROM time (optional)', 'visibility-logic-elementor' ),
					'placeholder' => 'HH:mm',
					'show_if'     => [ 'date_time_type' => [ 'yearly' ] ],
					'since'       => '3.0.0',
				],
				[
					'key'         => 'date_year_to',
					'type'        => 'text',
					'label'       => __( 'Every year TO (month-day)', 'visibility-logic-elementor' ),
					'placeholder' => '01-11',
					'help'        => __( 'Ranges over the year end work (20 Dec to 11 Jan). 29 Feb counts as 28 Feb in other years.', 'visibility-logic-elementor' ),
					'show_if'     => [ 'date_time_type' => [ 'yearly' ] ],
					'since'       => '3.0.0',
				],
				[
					'key'         => 'date_year_to_time',
					'type'        => 'time',
					'label'       => __( 'TO time (optional)', 'visibility-logic-elementor' ),
					'placeholder' => 'HH:mm',
					'show_if'     => [ 'date_time_type' => [ 'yearly' ] ],
					'since'       => '3.0.0',
				],
				[
					'key'     => 'date_from',
					'type'    => 'date',
					'label'   => __( 'Date FROM', 'visibility-logic-elementor' ),
					'show_if' => [ 'date_time_type' => [ 'date' ] ],
				],
				[
					'key'     => 'date_to',
					'type'    => 'date',
					'label'   => __( 'Date TO', 'visibility-logic-elementor' ),
					'show_if' => [ 'date_time_type' => [ 'date' ] ],
				],
				[
					'key'     => 'time_week',
					'type'    => 'multiselect',
					'label'   => __( 'Days of the WEEK', 'visibility-logic-elementor' ),
					'options' => self::weekday_options(),
					'show_if' => [ 'date_time_type' => [ 'week_days', 'week_days_time' ] ],
				],
				[
					'key'         => 'time_from',
					'type'        => 'time',
					'label'       => __( 'Time FROM', 'visibility-logic-elementor' ),
					'placeholder' => 'HH:mm',
					'show_if'     => [ 'date_time_type' => [ 'time', 'week_days_time' ] ],
				],
				[
					'key'         => 'time_to',
					'type'        => 'time',
					'label'       => __( 'Time TO', 'visibility-logic-elementor' ),
					'placeholder' => 'HH:mm',
					'show_if'     => [ 'date_time_type' => [ 'time', 'week_days_time' ] ],
				],
			],
		];

		return $conditions;
	}

	/**
	 * Week days (0 = Sunday => localized name).
	 *
	 * @return array
	 */
	public static function weekday_options() {
		global $wp_locale;

		$week = [];

		for ( $day_index = 0; $day_index <= 6; $day_index++ ) {
			$week[ esc_attr( $day_index ) ] = $wp_locale ? $wp_locale->get_weekday( $day_index ) : (string) $day_index;
		}

		return $week;
	}

	/**
	 * Apply conditions
	 *
	 * @param array                   $options
	 * @param array                   $settings
	 * @param \Elementor\Element_Base $item
	 *
	 * @return array
	 */
	public function apply_conditions( $options, $settings, $item ) {
		$settings = $item->get_settings_for_display();

		if ( ! self::flag( $settings, 'date_time_enabled' ) ) {
			return $options;
		}

		$time_week = self::value( $settings, 'time_week', [] );
		$time_week = is_array( $time_week ) ? $time_week : [];
		$is_today  = in_array( Context::local( 'w' ), $time_week ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- 2.x loose match.

		switch ( (string) self::value( $settings, 'date_time_type' ) ) {
			case 'date':
				$options['date_time'] = self::in_date_range( (string) self::value( $settings, 'date_from' ), (string) self::value( $settings, 'date_to' ) );
				break;
			case 'time':
				$options['date_time'] = self::in_time_range( (string) self::value( $settings, 'time_from' ), (string) self::value( $settings, 'time_to' ) );
				break;
			case 'week_days':
				$options['date_time'] = $is_today;
				break;
			case 'week_days_time':
				$options['date_time'] = $is_today
					&& self::in_time_range( (string) self::value( $settings, 'time_from' ), (string) self::value( $settings, 'time_to' ) );
				break;
			case 'yearly':
				$options['date_time'] = self::in_yearly_range(
					(string) self::value( $settings, 'date_year_from' ),
					(string) self::value( $settings, 'date_year_from_time' ),
					(string) self::value( $settings, 'date_year_to' ),
					(string) self::value( $settings, 'date_year_to_time' )
				);
				break;
			default:
				$options['date_time'] = false;
		}

		return $options;
	}

	/**
	 * Whether now is inside a date range (open ended when one side is empty, false when both are).
	 *
	 * Before 2.5.2 the dates were read as UTC. Since 2.5.2 they are read in the site time zone ("Read Date FROM and
	 * Date TO in the site time zone" in Settings > Visibility Logic, on by default; unchecked reads UTC).
	 *
	 * @param string $date_from Date FROM (Y-m-d H:i).
	 * @param string $date_to   Date TO (Y-m-d H:i).
	 *
	 * @return bool
	 */
	public static function in_date_range( $date_from, $date_to ) {
		$current = Context::timestamp();

		// Same tests as 2.x (truthiness, PHP comparison of a failed parse), only the parse can change.
		if ( $date_from && $date_to ) {
			return $current >= self::to_timestamp( $date_from ) && $current <= self::to_timestamp( $date_to );
		}

		if ( $date_from ) {
			return $current >= self::to_timestamp( $date_from );
		}

		if ( $date_to ) {
			return $current <= self::to_timestamp( $date_to );
		}

		return false;
	}

	/**
	 * Timestamp of a saved date.
	 *
	 * @param string $date Date.
	 *
	 * @return int|false
	 */
	private static function to_timestamp( $date ) {
		$timezone = Resources::get_setting( 'site_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );

		/**
		 * Time zone of Date FROM/TO (since 2.5.2). Return `new DateTimeZone( 'UTC' )` to read them as before 2.5.2.
		 *
		 * @param \DateTimeZone $timezone Site time zone, or UTC when "Read Date FROM and Date TO in the site time
		 *                                zone" is unchecked in Settings > Visibility Logic.
		 */
		$filtered = apply_filters( 'stax/visibility/date_timezone', $timezone );

		if ( $filtered instanceof \DateTimeZone ) {
			$timezone = $filtered;
		}

		try {
			return ( new \DateTimeImmutable( (string) $date, $timezone ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Whether the current site time is inside a time window (HH:mm), crossing midnight when FROM > TO.
	 *
	 * @param string $time_from Time FROM.
	 * @param string $time_to   Time TO.
	 *
	 * @return bool
	 */
	public static function in_time_range( $time_from, $time_to ) {
		$current_time = strtotime( Context::local( 'H:i' ) );

		if ( $time_from && $time_to ) {
			$from = strtotime( $time_from );
			$to   = strtotime( $time_to );

			if ( $time_from > $time_to ) {
				return ( $current_time >= $from && $current_time <= strtotime( '24:00' ) )
					|| ( $current_time >= strtotime( '00:00' ) && $current_time <= $to );
			}

			return $current_time >= $from && $current_time <= $to;
		}

		if ( $time_from ) {
			return $current_time >= strtotime( $time_from );
		}

		if ( $time_to ) {
			return $current_time <= strtotime( $time_to );
		}

		return false;
	}

	/**
	 * Every year between two month-days (site time zone, `stax/visibility/date_timezone` filter), optional times.
	 *
	 * Empty FROM = 1 Jan, empty TO = 31 Dec; FROM after TO wraps over the year end; 29 Feb is 28 Feb in other years.
	 *
	 * @param string $from      Month-day (MM-DD or M-D).
	 * @param string $from_time HH:mm, empty for 00:00.
	 * @param string $to        Month-day.
	 * @param string $to_time   HH:mm, empty for 23:59.
	 *
	 * @return bool
	 */
	public static function in_yearly_range( $from, $from_time, $to, $to_time ) {
		$timezone = wp_timezone();
		$filtered = apply_filters( 'stax/visibility/date_timezone', $timezone ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- 2.x hook.

		if ( $filtered instanceof \DateTimeZone ) {
			$timezone = $filtered;
		}

		$now  = ( new \DateTimeImmutable( '@' . Context::timestamp() ) )->setTimezone( $timezone );
		$leap = '1' === $now->format( 'L' );

		$bound = static function ( $month_day, $time, $fallback_day, $fallback_time ) use ( $leap ) {
			$month_day = trim( (string) $month_day );

			if ( ! preg_match( '/^(\d{1,2})[-\/.](\d{1,2})$/', $month_day, $m ) ) {
				$month_day = $fallback_day;
				preg_match( '/^(\d{1,2})-(\d{1,2})$/', $month_day, $m );
			}

			$month = max( 1, min( 12, (int) $m[1] ) );
			$day   = max( 1, min( 31, (int) $m[2] ) );

			if ( 2 === $month && 29 === $day && ! $leap ) {
				$day = 28;
			}

			$time = preg_match( '/^\d{1,2}:\d{2}$/', trim( (string) $time ) ) ? str_pad( trim( (string) $time ), 5, '0', STR_PAD_LEFT ) : $fallback_time;

			return sprintf( '%02d-%02d %s', $month, $day, $time );
		};

		$start   = $bound( $from, $from_time, '01-01', '00:00' );
		$end     = $bound( $to, $to_time, '12-31', '23:59' );
		$current = $now->format( 'm-d H:i' );

		if ( $start <= $end ) {
			return $current >= $start && $current <= $end;
		}

		return $current >= $start || $current <= $end;
	}
}

DateTimeVisibility::instance();
