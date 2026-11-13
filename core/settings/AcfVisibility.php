<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

/**
 * Class AcfVisibility
 */
class AcfVisibility extends Singleton {

	/**
	 * AcfVisibility constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->register_elementor_settings( 'acf_section' );

		add_filter( 'stax/visibility/apply_conditions', [ $this, 'apply_conditions' ], 10, 3 );
		add_filter( 'stax/visibility/condition_fields', [ $this, 'condition_fields' ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
	}

	/**
	 * Register section
	 *
	 * @param $element
	 *
	 * @return void
	 */
	public function register_section( $element ) {
		$element->start_controls_section(
			self::SECTION_PREFIX . 'acf_section',
			[
				'tab'       => self::VISIBILITY_TAB,
				'label'     => __( 'ACF Field', 'visibility-logic-elementor' ),
				'condition' => [
					self::SECTION_PREFIX . 'enabled' => 'yes',
				],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * @param $element \Elementor\Widget_Base
	 * @param $args
	 */
	public function register_controls( $element, $args ) {
		$element->add_control(
			self::SECTION_PREFIX . 'acf_enabled',
			[
				'label'          => __( 'Enable', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'label_on'       => __( 'Yes', 'visibility-logic-elementor' ),
				'label_off'      => __( 'No', 'visibility-logic-elementor' ),
				'return_value'   => 'yes',
				'prefix_class'   => 'stax-acf_enabled-',
				'style_transfer' => false,
			]
		);

		list( $sources, $locked_sources ) = self::source_options();

		if ( 1 === count( $sources ) ) {
			// Only "Current post" (no Pro sources to offer): 2.x hidden setting.
			$element->add_control(
				self::SECTION_PREFIX . 'acf_source',
				[
					'label'          => __( 'Field Source', 'visibility-logic-elementor' ),
					'type'           => Controls_Manager::HIDDEN,
					'default'        => 'post',
					'style_transfer' => false,
				]
			);

			$element->add_control(
				self::SECTION_PREFIX . 'acf_source_info',
				[
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => __( 'Checks ACF fields on the current post/page.', 'visibility-logic-elementor' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => [
						self::SECTION_PREFIX . 'acf_enabled' => 'yes',
					],
					'style_transfer'  => false,
				]
			);
		} else {
			$element->add_control(
				self::SECTION_PREFIX . 'acf_source',
				[
					'label'          => __( 'Field Source', 'visibility-logic-elementor' ),
					'type'           => 'stax_select',
					'options'        => $sources,
					'locked_options' => $locked_sources,
					'default'        => 'post',
					'condition'      => [
						self::SECTION_PREFIX . 'acf_enabled' => 'yes',
					],
					'style_transfer' => false,
				]
			);
		}

		if ( Pro_Teaser::ON === Pro_Teaser::state( 'acf_sources' ) ) {
			$element->add_control(
				self::SECTION_PREFIX . 'acf_post_id',
				[
					'label'          => __( 'Post ID', 'visibility-logic-elementor' ),
					'type'           => Controls_Manager::NUMBER,
					'condition'      => [
						self::SECTION_PREFIX . 'acf_enabled' => 'yes',
						self::SECTION_PREFIX . 'acf_source'  => 'specific',
					],
					'style_transfer' => false,
				]
			);
		}

		$element->add_control(
			self::SECTION_PREFIX . 'acf_field',
			[
				'label'          => __( 'Field Name', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::TEXT,
				'placeholder'    => __( 'e.g. my_custom_field', 'visibility-logic-elementor' ),
				'description'    => __( 'Enter the ACF field name or key.', 'visibility-logic-elementor' ),
				'label_block'    => true,
				'condition'      => [
					self::SECTION_PREFIX . 'acf_enabled' => 'yes',
				],
				'style_transfer' => false,
			]
		);

		list( $conditions, $locked_conditions ) = self::all_condition_options( true );

		$element->add_control(
			self::SECTION_PREFIX . 'acf_condition',
			[
				'label'          => __( 'Condition', 'visibility-logic-elementor' ),
				'type'           => 'stax_select',
				'options'        => $conditions,
				'locked_options' => $locked_conditions,
				'default'        => 'not_empty',
				'condition'      => [
					self::SECTION_PREFIX . 'acf_enabled' => 'yes',
					self::SECTION_PREFIX . 'acf_field!'  => '',
				],
				'style_transfer' => false,
			]
		);

		$element->add_control(
			self::SECTION_PREFIX . 'acf_value',
			[
				'label'          => __( 'Value', 'visibility-logic-elementor' ),
				'type'           => Controls_Manager::TEXT,
				'label_block'    => true,
				'condition'      => [
					self::SECTION_PREFIX . 'acf_enabled'   => 'yes',
					self::SECTION_PREFIX . 'acf_field!'    => '',
					self::SECTION_PREFIX . 'acf_condition' => self::value_operators(),
				],
				'style_transfer' => false,
			]
		);

		if ( Pro_Teaser::ON === Pro_Teaser::state( 'acf_operators' ) ) {
			$element->add_control(
				self::SECTION_PREFIX . 'acf_value_2',
				[
					'label'          => __( 'Value 2 (upper bound)', 'visibility-logic-elementor' ),
					'type'           => Controls_Manager::TEXT,
					'label_block'    => true,
					'condition'      => [
						self::SECTION_PREFIX . 'acf_enabled'   => 'yes',
						self::SECTION_PREFIX . 'acf_field!'    => '',
						self::SECTION_PREFIX . 'acf_condition' => 'between',
					],
					'style_transfer' => false,
				]
			);
		}

		if ( ! class_exists( 'ACF' ) && ! class_exists( 'acf' ) ) {
			$element->add_control(
				self::SECTION_PREFIX . 'acf_not_installed',
				[
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => __( 'Advanced Custom Fields plugin is not active. This condition will have no effect.', 'visibility-logic-elementor' ),
					'content_classes' => 'stax-generic-notice',
					'condition'       => [
						self::SECTION_PREFIX . 'acf_enabled' => 'yes',
					],
					'style_transfer'  => false,
				]
			);
		}
	}

	/**
	 * ACF condition choices (value => label).
	 *
	 * @return array
	 */
	public static function condition_options() {
		return [
			'empty'      => __( 'Is empty', 'visibility-logic-elementor' ),
			'not_empty'  => __( 'Is not empty', 'visibility-logic-elementor' ),
			'equals'     => __( 'Equals', 'visibility-logic-elementor' ),
			'not_equals' => __( 'Not equals', 'visibility-logic-elementor' ),
			'contains'   => __( 'Contains', 'visibility-logic-elementor' ),
			'is_true'    => __( 'Is true', 'visibility-logic-elementor' ),
			'is_false'   => __( 'Is false', 'visibility-logic-elementor' ),
		] + array_intersect_key( self::new_operators(), array_flip( [ 'equals_ci', 'not_equals_ci', 'starts_with', 'ends_with', 'not_in' ] ) );
	}

	/**
	 * Operators of Visibility Logic Pro (value => label), listed after the free ones (see Pro_Teaser).
	 *
	 * @return array
	 */
	public static function pro_condition_options() {
		return [
			'not_contains'   => __( 'Does not contain', 'visibility-logic-elementor' ),
			'greater_than'   => __( 'Greater than', 'visibility-logic-elementor' ),
			'less_than'      => __( 'Less than', 'visibility-logic-elementor' ),
			'between'        => __( 'Between', 'visibility-logic-elementor' ),
			'date_before'    => __( 'Date is before', 'visibility-logic-elementor' ),
			'date_after'     => __( 'Date is after', 'visibility-logic-elementor' ),
			'has_rows'       => __( 'Has rows (repeater)', 'visibility-logic-elementor' ),
			'no_rows'        => __( 'Has no rows (repeater)', 'visibility-logic-elementor' ),
			'array_contains' => __( 'Array contains', 'visibility-logic-elementor' ),
			'regex'          => __( 'Matches regex', 'visibility-logic-elementor' ),
		] + array_intersect_key( self::new_operators(), array_flip( [ 'lte', 'gte' ] ) );
	}

	/**
	 * Free and Pro operators, with the Pro ones locked when Pro is not active.
	 *
	 * @return array [ options, locked values ]
	 */
	public static function all_condition_options( $legacy = false ) {
		$free = self::condition_options();
		$pro  = self::pro_condition_options();

		if ( $legacy ) {
			$free = array_diff_key( $free, self::new_operators() );
			$pro  = array_diff_key( $pro, self::new_operators() );
		}

		return Pro_Teaser::options( $free, $pro, 'acf_operators' );
	}

	/**
	 * Operators added in 3.0 (shared operator library). Free ones are evaluated here, Pro ones by Pro.
	 *
	 * @return array
	 */
	public static function new_operators() {
		return [
			'equals_ci'     => __( 'Equals (ignore case)', 'visibility-logic-elementor' ),
			'not_equals_ci' => __( 'Not equals (ignore case)', 'visibility-logic-elementor' ),
			'starts_with'   => __( 'Starts with', 'visibility-logic-elementor' ),
			'ends_with'     => __( 'Ends with', 'visibility-logic-elementor' ),
			'not_in'        => __( 'Is not one of', 'visibility-logic-elementor' ),
			'lte'           => __( 'Less than or equal to', 'visibility-logic-elementor' ),
			'gte'           => __( 'Greater than or equal to', 'visibility-logic-elementor' ),
		];
	}

	/**
	 * Field sources: the current post (free), then the Pro ones.
	 *
	 * @return array [ options, locked values ]
	 */
	public static function source_options() {
		return Pro_Teaser::options(
			[ 'post' => __( 'Current Post', 'visibility-logic-elementor' ) ],
			[
				'user'     => __( 'Current User', 'visibility-logic-elementor' ),
				'term'     => __( 'Archive Term', 'visibility-logic-elementor' ),
				'options'  => __( 'Options Page', 'visibility-logic-elementor' ),
				'specific' => __( 'Specific Post ID', 'visibility-logic-elementor' ),
			],
			'acf_sources'
		);
	}

	/**
	 * Operators that compare with a value.
	 *
	 * @return string[]
	 */
	public static function value_operators() {
		return array_merge( [ 'equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'between', 'date_before', 'date_after', 'array_contains', 'regex' ], array_keys( self::new_operators() ) );
	}

	/**
	 * Fields of this condition for the block editor and Elementor 4 atomic elements (see Conditions).
	 *
	 * @param array $conditions Registered conditions.
	 *
	 * @return array
	 */
	public function condition_fields( $conditions ) {
		list( $sources, $locked_sources )       = self::source_options();
		list( $operators, $locked_operators )   = self::all_condition_options();
		$fields                                 = [];

		if ( count( $sources ) > 1 ) {
			$fields[] = [
				'key'            => 'acf_source',
				'type'           => 'select',
				'label'          => __( 'Field Source', 'visibility-logic-elementor' ),
				'options'        => $sources,
				'locked_options' => $locked_sources,
				'default'        => 'post',
			];
		}

		if ( Pro_Teaser::ON === Pro_Teaser::state( 'acf_sources' ) ) {
			$fields[] = [
				'key'     => 'acf_post_id',
				'type'    => 'text',
				'label'   => __( 'Post ID', 'visibility-logic-elementor' ),
				'show_if' => [ 'acf_source' => [ 'specific' ] ],
			];
		}

		$fields[] = [
			'key'         => 'acf_field',
			'required'    => true,
			'type'        => 'text',
			'label'       => __( 'Field Name', 'visibility-logic-elementor' ),
			'placeholder' => 'my_custom_field',
			'help'        => count( $sources ) > 1 ? __( 'ACF field name or key.', 'visibility-logic-elementor' ) : __( 'ACF field of the current post or page.', 'visibility-logic-elementor' ),
		];
		$fields[] = [
			'key'            => 'acf_condition',
			'type'           => 'select',
			'label'          => __( 'Condition', 'visibility-logic-elementor' ),
			'options'        => $operators,
			'locked_options' => $locked_operators,
			'default'        => 'not_empty',
			'options_since'  => array_fill_keys( array_keys( self::new_operators() ), '3.0.0' ),
		];
		$fields[] = [
			'key'     => 'acf_value',
			'type'    => 'text',
			'label'   => __( 'Value', 'visibility-logic-elementor' ),
			'show_if' => [ 'acf_condition' => self::value_operators() ],
		];

		if ( Pro_Teaser::ON === Pro_Teaser::state( 'acf_operators' ) ) {
			$fields[] = [
				'key'     => 'acf_value_2',
				'type'    => 'text',
				'label'   => __( 'Value 2 (upper bound)', 'visibility-logic-elementor' ),
				'show_if' => [ 'acf_condition' => [ 'between' ] ],
			];
		}

		$conditions['acf'] = [
			'label'  => __( 'ACF Field', 'visibility-logic-elementor' ),
			'switch' => 'acf_enabled',
			'fields' => $fields,
		];

		return $conditions;
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

		if ( ! self::flag( $settings, 'acf_enabled' ) ) {
			return $options;
		}

		// ACF must be active
		if ( ! function_exists( 'get_field' ) ) {
			return $options;
		}

		$field_name = self::value( $settings, 'acf_field' );

		if ( empty( $field_name ) ) {
			return $options;
		}

		$condition     = (string) self::value( $settings, 'acf_condition', 'not_empty' );
		$compare_value = (string) self::value( $settings, 'acf_value' );
		$source        = (string) self::value( $settings, 'acf_source', 'post' );

		// Pro sources and operators: evaluated by Visibility Logic Pro (filter below). Without Pro they never run.
		if ( ( '' !== $source && 'post' !== $source ) || array_key_exists( $condition, self::pro_condition_options() ) ) {
			/**
			 * Result of an ACF Field rule that uses a Pro source or operator, null when nothing evaluates it.
			 *
			 * @param bool|null $result Result.
			 * @param array     $rule   acf_source, acf_post_id, acf_field, acf_condition, acf_value, acf_value_2.
			 */
			$result = apply_filters( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
				'stax/visibility/acf/rule',
				null,
				[
					'acf_source'    => $source,
					'acf_post_id'   => self::value( $settings, 'acf_post_id' ),
					'acf_field'     => $field_name,
					'acf_condition' => $condition,
					'acf_value'     => $compare_value,
					'acf_value_2'   => (string) self::value( $settings, 'acf_value_2' ),
				]
			);

			if ( null !== $result ) {
				$options['acf'] = (bool) $result;
			}

			return $options;
		}

		// Get field value from current post/page
		$value = get_field( $field_name );

		// Evaluate condition
		$result = false;

		switch ( $condition ) {
			case 'empty':
				$result = empty( $value );
				break;
			case 'not_empty':
				$result = ! empty( $value );
				break;
			case 'equals':
				if ( is_array( $value ) ) {
					$result = in_array( $compare_value, $value, false ); // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- 2.x loose match (ACF may return ints).
				} else {
					$result = ( null === $value || is_scalar( $value ) ) && (string) $value === $compare_value;
				}
				break;
			case 'not_equals':
				if ( is_array( $value ) ) {
					$result = ! in_array( $compare_value, $value, false ); // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- 2.x loose match (ACF may return ints).
				} else {
					$result = ! ( null === $value || is_scalar( $value ) ) || (string) $value !== $compare_value;
				}
				break;
			case 'contains':
				if ( is_array( $value ) ) {
					$result = in_array( $compare_value, $value, false ); // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- 2.x loose match (ACF may return ints).
				} elseif ( is_string( $value ) ) {
					$result = strpos( $value, $compare_value ) !== false;
				}
				break;
			case 'is_true':
				$result = ! empty( $value ) && '0' !== $value && 'false' !== $value;
				break;
			case 'is_false':
				$result = empty( $value ) || '0' === $value || 'false' === $value;
				break;
			default:
				// 3.0 operators from the shared library.
				if ( array_key_exists( $condition, self::new_operators() ) ) {
					list( $result ) = \StaxWP\Conditions\V1\Operators::compare( $condition, $value, $compare_value );
				}
		}

		$options['acf'] = $result;

		return $options;
	}
}

AcfVisibility::instance();
