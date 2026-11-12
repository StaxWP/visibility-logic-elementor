<?php
/**
 * Block editor (Gutenberg): a Visibility panel on every block and server-side evaluation in `render_block`.
 *
 * The rules are stored in the block attribute `staxVisibility` (setting names without the `stax_visibility_`
 * prefix, the same values as the Elementor controls) and evaluated by Conditions::evaluate(), the same evaluator
 * as Elementor. No script or style is added to the front end. Blocks without the attribute are untouched.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blocks
 */
class Blocks {

	/**
	 * Block attribute.
	 */
	const ATTRIBUTE = 'staxVisibility';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'register_block_type' ) || ! apply_filters( 'stax/visibility/blocks_enabled', true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
			return;
		}

		add_filter( 'register_block_type_args', [ __CLASS__, 'add_attribute' ], 10, 2 );
		add_filter( 'render_block', [ __CLASS__, 'render_block' ], 10, 2 );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'editor_assets' ] );
	}

	/**
	 * Declare the attribute on every server-registered block (REST validation, server-side rendered blocks).
	 *
	 * @param array  $args Block type arguments.
	 * @param string $name Block name.
	 *
	 * @return array
	 */
	public static function add_attribute( $args, $name ) {
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = [];
		}

		if ( ! isset( $args['attributes'][ self::ATTRIBUTE ] ) ) {
			$args['attributes'][ self::ATTRIBUTE ] = [ 'type' => 'object' ];
		}

		return $args;
	}

	/**
	 * Hide a block whose rules do not pass (or keep it in the HTML with display: none).
	 *
	 * @param string $content Rendered block.
	 * @param array  $block   Parsed block.
	 *
	 * @return string
	 */
	public static function render_block( $content, $block ) {
		if ( empty( $block['attrs'][ self::ATTRIBUTE ] ) || ! is_array( $block['attrs'][ self::ATTRIBUTE ] ) ) {
			return $content;
		}

		$values = $block['attrs'][ self::ATTRIBUTE ];

		if ( empty( $values['enabled'] ) || self::is_editor_preview() ) {
			return $content;
		}

		if ( self::is_visible( $values, isset( $block['blockName'] ) ? (string) $block['blockName'] : '' ) ) {
			return $content;
		}

		if ( ! empty( $values['keep_html'] ) ) {
			return self::hide_with_css( $content );
		}

		return '';
	}

	/**
	 * Whether a block with these saved values is visible for the current visitor.
	 *
	 * @param array  $values Saved attribute value.
	 * @param string $name   Block name.
	 *
	 * @return bool
	 */
	public static function is_visible( array $values, $name = '' ) {
		$settings = Conditions::to_settings( $values, 'blocks' );
		$item     = new Condition_Item( $settings, isset( $values['id'] ) ? (string) $values['id'] : '', $name, 'block' );

		return Conditions::evaluate( $settings, $item, true );
	}

	/**
	 * Add `stax-visibility-hidden` and `display: none` to the first tag of the block.
	 *
	 * @param string $content Block HTML.
	 *
	 * @return string
	 */
	private static function hide_with_css( $content ) {
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new \WP_HTML_Tag_Processor( $content );

			if ( $processor->next_tag() ) {
				$processor->add_class( 'stax-visibility-hidden' );
				$style = (string) $processor->get_attribute( 'style' );
				$processor->set_attribute( 'style', trim( 'display: none;' . ( '' !== $style ? ' ' . $style : '' ) ) );

				return $processor->get_updated_html();
			}
		}

		return '<div class="stax-visibility-hidden" style="display: none;">' . $content . '</div>';
	}

	/**
	 * Server-side render requests of the block editor (the editor always shows every block).
	 *
	 * @return bool
	 */
	private static function is_editor_preview() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';

		return false !== strpos( $route, '/block-renderer/' ) && current_user_can( 'edit_posts' );
	}

	/**
	 * Block editor script and styles (editor only).
	 *
	 * @return void
	 */
	public static function editor_assets() {
		wp_enqueue_script(
			'stax-visibility-blocks',
			STAX_VISIBILITY_ASSETS_URL . 'js/blocks.js',
			[ 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-blocks' ],
			STAX_VISIBILITY_VERSION,
			true
		);

		wp_set_script_translations( 'stax-visibility-blocks', 'visibility-logic-elementor' );

		wp_localize_script(
			'stax-visibility-blocks',
			'staxVisibilityBlocks',
			[
				'attribute'  => self::ATTRIBUTE,
				'general'    => self::fields_for_js( Conditions::for_builder( Conditions::general_fields(), 'blocks' ) ),
				'conditions' => array_values(
					array_map(
						static function ( $slug, $condition ) {
							return [
								'slug'   => $slug,
								'label'  => wp_strip_all_tags( (string) $condition['label'] ),
								'switch' => $condition['switch'],
								'pro'    => ! empty( $condition['pro'] ),
								'locked' => Conditions::lock_notice( $condition ),
								'fields' => self::fields_for_js( $condition['fields'] ),
							];
						},
						array_keys( Conditions::fields( 'blocks' ) ),
						Conditions::fields( 'blocks' )
					)
				),
				'proUrl'     => Plugin::instance()->has_pro() ? '' : Resources::pro_url( 'block-editor' ),
				'labels'     => [
					'panel'  => __( 'Visibility', 'visibility-logic-elementor' ),
					'badge'  => __( 'Visibility rules', 'visibility-logic-elementor' ),
					'notice' => __( 'Rules apply on the live site; the editor always shows the block.', 'visibility-logic-elementor' ),
				],
			]
		);

		wp_enqueue_style(
			'stax-visibility-blocks',
			STAX_VISIBILITY_ASSETS_URL . 'css/blocks-editor.css',
			[],
			STAX_VISIBILITY_VERSION
		);
	}

	/**
	 * Fields with options as lists (JS keeps order).
	 *
	 * @param array $fields Fields.
	 *
	 * @return array
	 */
	private static function fields_for_js( array $fields ) {
		$out = [];

		foreach ( $fields as $field ) {
			$field['label'] = wp_strip_all_tags( (string) $field['label'] );

			if ( isset( $field['options'] ) ) {
				$field['options'] = Conditions::option_list( (array) $field['options'] );
			}

			$out[] = $field;
		}

		return $out;
	}

	/**
	 * Blocks with rules in a block tree (parse_blocks() output), for Rules in use.
	 *
	 * @param array  $blocks Blocks.
	 * @param string $path   Position path.
	 *
	 * @return array[]
	 */
	public static function find_in_blocks( array $blocks, $path = '' ) {
		$found = [];

		foreach ( $blocks as $index => $block ) {
			$position = '' === $path ? (string) ( $index + 1 ) : $path . '.' . ( $index + 1 );
			$values   = isset( $block['attrs'][ self::ATTRIBUTE ] ) && is_array( $block['attrs'][ self::ATTRIBUTE ] ) ? $block['attrs'][ self::ATTRIBUTE ] : [];

			if ( ! empty( $values['enabled'] ) ) {
				$settings = Conditions::to_settings( $values, 'blocks' );

				$found[] = [
					'id'         => $position,
					'type'       => isset( $block['blockName'] ) ? (string) $block['blockName'] : '',
					'action'     => Singleton::flag( $settings, 'show_hide' ) ? 'show' : 'hide',
					'match'      => 'one' === Singleton::value( $settings, 'condition_type', 'all' ) ? 'one' : 'all',
					'conditions' => Rules::active_conditions( $settings ),
					'legacy'     => false,
					'keep_html'  => Singleton::flag( $settings, 'keep_html' ),
					'fallback'   => false,
				];
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, self::find_in_blocks( $block['innerBlocks'], $position ) );
			}
		}

		return $found;
	}
}

Blocks::init();
