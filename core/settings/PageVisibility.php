<?php
/**
 * Page condition (free since 3.0, backlog B7): page type, post type, specific pages and posts.
 *
 * Builder-only condition (no classic Elementor section): rules that use it are stored as rule trees, so
 * Visibility Logic 2.x fails closed. Inside a loop item (Loop Grid, Query block) the current post of the loop is
 * checked; in a single template, the post being shown. Taxonomies, meta, archives details stay in Pro.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PageVisibility
 */
class PageVisibility extends Singleton {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'stax/visibility/apply_conditions', [ $this, 'apply_conditions' ], 10, 3 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- public 2.x hook name.
		add_filter( 'stax/visibility/condition_fields', [ $this, 'condition_fields' ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
	}

	/**
	 * Page types.
	 *
	 * @return array
	 */
	public static function page_types() {
		return [
			'front'   => __( 'Front page', 'visibility-logic-elementor' ),
			'blog'    => __( 'Blog (posts page)', 'visibility-logic-elementor' ),
			'single'  => __( 'Single post', 'visibility-logic-elementor' ),
			'page'    => __( 'Page', 'visibility-logic-elementor' ),
			'archive' => __( 'Archive', 'visibility-logic-elementor' ),
			'search'  => __( 'Search results', 'visibility-logic-elementor' ),
			'404'     => __( 'Not found (404)', 'visibility-logic-elementor' ),
		];
	}

	/**
	 * Public post types.
	 *
	 * @return array
	 */
	public static function post_types() {
		$out = [];

		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $slug => $type ) {
			if ( 'attachment' !== $slug && 'elementor_library' !== $slug ) {
				$out[ $slug ] = $type->labels->singular_name;
			}
		}

		return $out;
	}

	/**
	 * Registry entry.
	 *
	 * @param array $conditions Conditions.
	 *
	 * @return array
	 */
	public function condition_fields( $conditions ) {
		$conditions['page'] = [
			'label'  => __( 'Page', 'visibility-logic-elementor' ),
			'switch' => 'page_enabled',
			'since'  => '3.0.0',
			'fields' => [
				[
					'key'     => 'page_types',
					'type'    => 'multiselect',
					'label'   => __( 'Page type is', 'visibility-logic-elementor' ),
					'options' => self::page_types(),
				],
				[
					'key'     => 'page_post_types',
					'type'    => 'multiselect',
					'label'   => __( 'Post type is', 'visibility-logic-elementor' ),
					'options' => self::post_types(),
				],
				[
					'key'   => 'page_ids',
					'type'  => 'posts',
					'label' => __( 'Specific pages or posts', 'visibility-logic-elementor' ),
					'help'  => __( 'Inside a loop, the loop item is checked.', 'visibility-logic-elementor' ),
				],
			],
		];

		return $conditions;
	}

	/**
	 * Evaluate: every part that is set must match.
	 *
	 * @param array  $options  Results.
	 * @param array  $settings Settings.
	 * @param object $item     Element.
	 *
	 * @return array
	 */
	public function apply_conditions( $options, $settings, $item ) {
		$settings = $item->get_settings_for_display();

		if ( ! self::flag( $settings, 'page_enabled' ) ) {
			return $options;
		}

		$types      = array_filter( array_map( 'strval', (array) self::value( $settings, 'page_types', [] ) ) );
		$post_types = array_filter( array_map( 'strval', (array) self::value( $settings, 'page_post_types', [] ) ) );
		$ids        = array_filter( array_map( 'intval', (array) self::value( $settings, 'page_ids', [] ) ) );

		if ( ! $types && ! $post_types && ! $ids ) {
			return $options;
		}

		$result = true;

		if ( $types ) {
			$result = (bool) array_filter( $types, [ __CLASS__, 'is_page_type' ] );
		}

		if ( $result && $post_types ) {
			$current = in_the_loop() || is_singular() ? get_post_type() : '';
			$result  = '' !== (string) $current ? in_array( $current, $post_types, true ) : is_post_type_archive( $post_types );
		}

		if ( $result && $ids ) {
			$result = in_array( (int) get_the_ID(), $ids, true );
		}

		$options['page'] = $result;

		return $options;
	}

	/**
	 * Whether the request is a page type.
	 *
	 * @param string $type Type.
	 *
	 * @return bool
	 */
	public static function is_page_type( $type ) {
		switch ( $type ) {
			case 'front':
				return is_front_page();
			case 'blog':
				return is_home();
			case 'single':
				return is_singular() && ! is_page();
			case 'page':
				return is_page();
			case 'archive':
				return is_archive();
			case 'search':
				return is_search();
			case '404':
				return is_404();
		}

		return false;
	}
}

PageVisibility::instance();
