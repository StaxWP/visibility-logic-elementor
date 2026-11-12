<?php
/**
 * Elementor 4 atomic elements (V4 editor): a Visibility section in the element's settings and the same
 * evaluation as classic elements.
 *
 * Props `stax_visibility_*` are added to every atomic element schema (`elementor/atomic-widgets/props-schema`) and a
 * "Visibility" section of native V4 controls (`elementor/atomic-widgets/controls`) is generated from the condition
 * registry (Conditions::fields( 'atomic' )), so Pro conditions that register fields appear too. At render time the
 * props are unwrapped to the classic setting format and go through Conditions::evaluate() (Plugin::should_render()).
 *
 * Not available for atomic elements: Keep HTML (no wrapper attributes to hide with CSS), Hide when empty, Pro
 * fallback content.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Chips_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Notice_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Textarea_Control;
use Elementor\Modules\AtomicWidgets\PropDependencies\Manager as Dependency_Manager;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Array_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;

/**
 * Class Atomic
 */
class Atomic {

	/**
	 * Render callback (Plugin::item_should_render).
	 *
	 * @var callable|null
	 */
	private static $should_render;

	/**
	 * Whether an Elementor element is an atomic (V4) element.
	 *
	 * @param object $item Element.
	 *
	 * @return bool
	 */
	public static function is_atomic( $item ) {
		return is_object( $item ) && method_exists( $item, 'get_atomic_controls' );
	}

	/**
	 * Whether this Elementor has atomic elements with the extension filters used here.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( Section::class ) && class_exists( Switch_Control::class ) && class_exists( Dependency_Manager::class )
			&& class_exists( String_Array_Prop_Type::class ) && class_exists( Chips_Control::class );
	}

	/**
	 * Hooks.
	 *
	 * @param callable $should_render Plugin::item_should_render.
	 *
	 * @return void
	 */
	public static function init( $should_render ) {
		if ( ! self::available() || ! apply_filters( 'stax/visibility/atomic_enabled', true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
			return;
		}

		self::$should_render = $should_render;

		add_filter( 'elementor/atomic-widgets/props-schema', [ __CLASS__, 'props_schema' ], 20 );
		add_filter( 'elementor/atomic-widgets/controls', [ __CLASS__, 'controls' ], 20, 2 );
		add_action( 'elementor/frontend/before_render', [ __CLASS__, 'hook_element_type' ] );
	}

	/**
	 * Atomic elements that are not widgets (flexbox, div block, grid...) render through their own type's
	 * `should_render` filter; atomic widgets use `elementor/frontend/widget/should_render` (hooked by Plugin).
	 * Hooked the first time an element of the type renders (Elementor registers element types lazily).
	 *
	 * @param object $element Element about to render.
	 *
	 * @return void
	 */
	public static function hook_element_type( $element ) {
		static $hooked = [];

		if ( ! self::is_atomic( $element ) ) {
			return;
		}

		$type = $element->get_type();

		if ( isset( $hooked[ $type ] ) || in_array( $type, [ 'widget', 'section', 'container', 'column' ], true ) ) {
			return;
		}

		$hooked[ $type ] = true;

		add_filter( "elementor/frontend/{$type}/should_render", self::$should_render, 99999, 2 );
	}

	/**
	 * Visibility props on every atomic element.
	 *
	 * @param array $schema Props schema.
	 *
	 * @return array
	 */
	public static function props_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		foreach ( self::field_plan() as $item ) {
			$field = $item['field'];

			switch ( $field['type'] ) {
				case 'switch':
					$prop = Boolean_Prop_Type::make()->default( false );
					break;
				case 'multiselect':
				case 'list':
					$prop = String_Array_Prop_Type::make();
					break;
				default:
					$prop = String_Prop_Type::make();
					if ( isset( $field['default'] ) ) {
						$prop->default( (string) $field['default'] );
					}
			}

			if ( ! empty( $item['depends'] ) ) {
				$prop->set_dependencies( self::dependencies( $item['depends'] ) );
			}

			$schema[ Singleton::SECTION_PREFIX . $field['key'] ] = $prop;
		}

		return $schema;
	}

	/**
	 * Visibility section in the element's settings.
	 *
	 * @param array  $controls Controls.
	 * @param object $element  Element.
	 *
	 * @return array
	 */
	public static function controls( $controls, $element = null ) {
		if ( ! is_array( $controls ) ) {
			return $controls;
		}

		$items = [];

		foreach ( self::field_plan() as $item ) {
			if ( ! empty( $item['locked'] ) ) {
				// Locked condition (add-on without a license): a notice instead of its controls. Its props stay in
				// the schema, so saved values are kept and still apply.
				if ( isset( $item['notice'] ) && class_exists( Notice_Control::class ) ) {
					$items[] = self::lock_control( $item['field'], $item['notice'] );
				}
				continue;
			}

			$items[] = self::control( $item['field'] );
		}

		$controls[] = Section::make()
			->set_id( 'stax-visibility' )
			->set_label( __( 'Visibility', 'visibility-logic-elementor' ) )
			->set_items( $items );

		return $controls;
	}

	/**
	 * Fields in display order with the fields each one depends on.
	 *
	 * @return array[] Items: field, depends ( [ key => true|values ] ).
	 */
	private static function field_plan() {
		$plan = [];

		foreach ( Conditions::for_builder( Conditions::general_fields(), 'atomic' ) as $field ) {
			$plan[] = [
				'field'   => $field,
				'depends' => 'enabled' === $field['key'] ? [] : [ 'enabled' => true ],
			];
		}

		foreach ( Conditions::fields( 'atomic' ) as $condition ) {
			$notice = Conditions::lock_notice( $condition );

			$plan[] = [
				'field'   => [
					'key'   => $condition['switch'],
					'type'  => 'switch',
					'label' => $condition['label'],
				],
				'depends' => [ 'enabled' => true ],
				'locked'  => null !== $notice,
				'notice'  => $notice,
			];

			foreach ( $condition['fields'] as $field ) {
				$depends = [
					'enabled'            => true,
					$condition['switch'] => true,
				];

				if ( ! empty( $field['show_if'] ) ) {
					$depends = array_merge( $depends, (array) $field['show_if'] );
				}

				$plan[] = [
					'field'   => $field,
					'depends' => $depends,
					'locked'  => null !== $notice,
				];
			}
		}

		return $plan;
	}

	/**
	 * Dependencies: hide a control until every condition is met.
	 *
	 * @param array $depends Key => true (switch on) or list of values.
	 *
	 * @return array|null
	 */
	private static function dependencies( array $depends ) {
		$manager = Dependency_Manager::make( Dependency_Manager::RELATION_AND );

		foreach ( $depends as $key => $value ) {
			$manager->where(
				[
					'operator' => true === $value ? 'eq' : 'in',
					'path'     => [ Singleton::SECTION_PREFIX . $key ],
					'value'    => true === $value ? true : array_values( array_map( 'strval', (array) $value ) ),
					'effect'   => 'hide',
				]
			);
		}

		return $manager->get();
	}

	/**
	 * Notice shown instead of a locked condition's controls (bound to its switch prop).
	 *
	 * @param array $field  The condition's switch field.
	 * @param array $notice Conditions::lock_notice().
	 *
	 * @return object
	 */
	private static function lock_control( array $field, array $notice ) {
		$control = Notice_Control::bind_to( Singleton::SECTION_PREFIX . $field['key'] )
			->set_label( wp_strip_all_tags( (string) $field['label'] ) )
			->set_notice_type( 'warning' )
			->set_heading( wp_strip_all_tags( (string) $field['label'] ) . ': ' . $notice['title'] )
			->set_content( $notice['text'] );

		if ( ! empty( $notice['actions'][0] ) ) {
			$control->set_button_text( $notice['actions'][0]['label'] )->set_button_url( $notice['actions'][0]['url'] );
		}

		return $control;
	}

	/**
	 * V4 control of a field.
	 *
	 * @param array $field Field.
	 *
	 * @return object
	 */
	private static function control( array $field ) {
		$bind  = Singleton::SECTION_PREFIX . $field['key'];
		$label = wp_strip_all_tags( (string) $field['label'] );

		switch ( $field['type'] ) {
			case 'switch':
				return Switch_Control::bind_to( $bind )->set_label( $label );

			case 'select':
				return Select_Control::bind_to( $bind )->set_label( $label )->set_options( Conditions::option_list( (array) $field['options'] ) );

			case 'multiselect':
				return Chips_Control::bind_to( $bind )->set_label( $label )->set_options( Conditions::option_list( (array) $field['options'] ) );

			case 'list':
				return Chips_Control::bind_to( $bind )->set_label( $label )->set_free_chips( true );

			case 'textarea':
				return Textarea_Control::bind_to( $bind )->set_label( $label );

			default: // text, date, time.
				$placeholder = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : ( 'date' === $field['type'] ? 'YYYY-MM-DD HH:mm' : '' );

				return Text_Control::bind_to( $bind )->set_label( $label )->set_placeholder( $placeholder );
		}
	}
}
