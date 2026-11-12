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
				'options'        => self::type_options(),
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
					: esc_html__( 'Times use the site time zone, dates FROM/TO use UTC (Settings > Visibility Logic can switch them to the site time zone). Site time now:', 'visibility-logic-elementor' ) )
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
	public static function type_options() {
		return [
			'date'           => __( 'Date FROM/TO', 'visibility-logic-elementor' ),
			'time'           => __( 'Time FROM/TO', 'visibility-logic-elementor' ),
			'week_days'      => __( 'Week Days', 'visibility-logic-elementor' ),
			'week_days_time' => __( 'Week Days + Time FROM/TO', 'visibility-logic-elementor' ),
		];
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
					'key'     => 'date_time_type',
					'type'    => 'select',
					'label'   => __( 'Select Type:', 'visibility-logic-elementor' ),
					'options' => self::type_options(),
					'default' => 'date',
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
		$is_today  = in_array( current_time( 'w' ), $time_week ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- 2.x loose match.

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
			default:
				$options['date_time'] = false;
		}

		return $options;
	}

	/**
	 * Whether now is inside a date range (open ended when one side is empty, false when both are).
	 *
	 * Until 3.0 the dates were read as UTC. Sites that turn on "Site time zone for date ranges" in
	 * Settings > Visibility Logic (on by default for new installs) read them in the site time zone.
	 *
	 * @param string $date_from Date FROM (Y-m-d H:i).
	 * @param string $date_to   Date TO (Y-m-d H:i).
	 *
	 * @return bool
	 */
	public static function in_date_range( $date_from, $date_to ) {
		$current = current_datetime()->getTimestamp();

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
		if ( Resources::get_setting( 'site_timezone' ) ) {
			try {
				return ( new \DateTimeImmutable( $date, wp_timezone() ) )->getTimestamp();
			} catch ( \Exception $e ) {
				return false;
			}
		}

		return strtotime( $date );
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
		$current_time = strtotime( current_time( 'H:i' ) );

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
}

DateTimeVisibility::instance();
