<?php

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
				'render_type'    => 'ui',
				'prefix_class'   => 'stax-date_time_enabled-',
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'date_time_type',
			[
				'type'           => Controls_Manager::SELECT,
				'label'          => __( 'Select Type:', 'visibility-logic-elementor' ),
				'options'        => [
					'date'           => __( 'Date FROM/TO', 'visibility-logic-elementor' ),
					'time'           => __( 'Time FROM/TO', 'visibility-logic-elementor' ),
					'week_days'      => __( 'Week Days', 'visibility-logic-elementor' ),
					'week_days_time' => __( 'Week Days + Time FROM/TO', 'visibility-logic-elementor' ),
				],
				'default'        => 'date',
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
				],
				'render_type'    => 'ui',
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
				'render_type'    => 'ui',
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
				'render_type'    => 'ui',
				'style_transfer' => false,
			]
		);

		global $wp_locale;
		$week = [];

		for ( $day_index = 0; $day_index <= 6; $day_index++ ) {
			$week[ esc_attr( $day_index ) ] = $wp_locale->get_weekday( $day_index );
		}

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
				'render_type'    => 'ui',
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
				'render_type'    => 'ui',
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
				'raw'            => __( 'Use timestamps based on server time:', 'visibility-logic-elementor' ) .
									 '<br><strong>' . date( 'Y-m-d H:i', current_time( 'timestamp' ) ) . '</strong>',
				'condition'      => [
					self::SECTION_PREFIX . 'date_time_enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);
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

		if ( ! (bool) $settings[ self::SECTION_PREFIX . 'date_time_enabled' ] ) {
			return $options;
		}

		$options['date_time'] = false;

		switch ( $settings[ self::SECTION_PREFIX . 'date_time_type' ] ) {
			case 'date':
				$date_from    = $settings[ self::SECTION_PREFIX . 'date_from' ];
				$date_to      = $settings[ self::SECTION_PREFIX . 'date_to' ];
				$current_date = current_time( 'timestamp' );

				if ( $date_from && $date_to ) {
					if ( $current_date >= strtotime( $date_from ) && $current_date <= strtotime( $date_to ) ) {
						$options['date_time'] = true;
					}
				} elseif ( $date_from && ! $date_to ) {
					if ( $current_date >= strtotime( $date_from ) ) {
						$options['date_time'] = true;
					}
				} elseif ( ! $date_from && $date_to ) {
					if ( $current_date <= strtotime( $date_to ) ) {
						$options['date_time'] = true;
					}
				}
				break;
			case 'time';
				$time_from = $settings[ self::SECTION_PREFIX . 'time_from' ];
				$time_to   = $settings[ self::SECTION_PREFIX . 'time_to' ];

				$current_time = strtotime( current_time( 'H:i' ) );

				if ( $time_from && $time_to ) {
					if ( $time_from > $time_to ) {
						$time_from = strtotime( $time_from );
						$time_to   = strtotime( $time_to );

						if ( ( $current_time >= $time_from && $current_time <= strtotime( '24:00' ) ) ||
							 ( $current_time >= strtotime( '00:00' ) && $current_time <= $time_to ) ) {
							$options['date_time'] = true;
						}
					} else {
						$time_from = strtotime( $time_from );
						$time_to   = strtotime( $time_to );

						if ( $current_time >= $time_from && $current_time <= $time_to ) {
							$options['date_time'] = true;
						}
					}
				} elseif ( $time_from && ! $time_to ) {
					$time_from = strtotime( $time_from );
					if ( $current_time >= $time_from ) {
						$options['date_time'] = true;
					}
				} elseif ( ! $time_from && $time_to ) {
					$time_to = strtotime( $time_to );
					if ( $current_time <= $time_to ) {
						$options['date_time'] = true;
					}
				}
				break;
			case 'week_days':
				if ( is_array( $settings[ self::SECTION_PREFIX . 'time_week' ] ) && in_array( current_time( 'w' ), $settings[ self::SECTION_PREFIX . 'time_week' ] ) ) {
					$options['date_time'] = true;
				}
				break;
			case 'week_days_time':
				$time_matched = false;
				$day_matched  = false;

				$time_from = $settings[ self::SECTION_PREFIX . 'time_from' ];
				$time_to   = $settings[ self::SECTION_PREFIX . 'time_to' ];

				$current_time = strtotime( current_time( 'H:i' ) );

				if ( $time_from && $time_to ) {
					if ( $time_from > $time_to ) {
						$time_from = strtotime( $time_from );
						$time_to   = strtotime( $time_to );

						if ( ( $current_time >= $time_from && $current_time <= strtotime( '24:00' ) ) ||
							 ( $current_time >= strtotime( '00:00' ) && $current_time <= $time_to ) ) {
							$time_matched = true;
						}
					} else {
						$time_from = strtotime( $time_from );
						$time_to   = strtotime( $time_to );

						if ( $current_time >= $time_from && $current_time <= $time_to ) {
							$time_matched = true;
						}
					}
				} elseif ( $time_from && ! $time_to ) {
					$time_from = strtotime( $time_from );
					if ( $current_time >= $time_from ) {
						$time_matched = true;
					}
				} elseif ( ! $time_from && $time_to ) {
					$time_to = strtotime( $time_to );
					if ( $current_time <= $time_to ) {
						$time_matched = true;
					}
				}

				if ( is_array( $settings[ self::SECTION_PREFIX . 'time_week' ] ) &&
					! empty( $settings[ self::SECTION_PREFIX . 'time_week' ] ) &&
					in_array( current_time( 'w' ), $settings[ self::SECTION_PREFIX . 'time_week' ] ) ) {
					$day_matched = true;
				}

				$options['date_time'] = $time_matched && $day_matched;
				break;
			default:
				$options['date_time'] = false;
		}

		return $options;
	}

}

DateTimeVisibility::instance();
