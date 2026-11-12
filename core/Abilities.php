<?php
/**
 * WordPress Abilities API (WordPress 6.9+): read-only abilities for AI agents and MCP clients.
 *
 * - `visibility-logic/list-conditions`: the conditions available on the site and whether they are turned on.
 * - `visibility-logic/list-rules`: the Elementor documents and elements that have visibility rules.
 *
 * Nothing is registered when the Abilities API is not available.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Abilities
 */
class Abilities {

	/**
	 * Category slug.
	 */
	const CATEGORY = 'visibility-logic';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register' ] );
	}

	/**
	 * Category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Visibility Logic', 'visibility-logic-elementor' ),
				'description' => __( 'Visibility rules of Elementor widgets, sections and containers.', 'visibility-logic-elementor' ),
			]
		);
	}

	/**
	 * Abilities.
	 *
	 * @return void
	 */
	public static function register() {
		$meta = [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
			'public'       => true,
		];

		wp_register_ability(
			'visibility-logic/list-conditions',
			[
				'label'               => __( 'List visibility conditions', 'visibility-logic-elementor' ),
				'description'         => __( 'Lists the Visibility Logic conditions (user role, date and time, device and more), whether each one is turned on, and which need Visibility Logic Pro.', 'visibility-logic-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'pro_active' => [ 'type' => 'boolean' ],
						'conditions' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'slug'        => [ 'type' => 'string' ],
									'name'        => [ 'type' => 'string' ],
									'description' => [ 'type' => 'string' ],
									'enabled'     => [ 'type' => 'boolean' ],
									'pro'         => [ 'type' => 'boolean' ],
								],
							],
						],
					],
				],
				'execute_callback'    => [ __CLASS__, 'list_conditions' ],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => $meta,
			]
		);

		wp_register_ability(
			'visibility-logic/list-rules',
			[
				'label'               => __( 'List elements with visibility rules', 'visibility-logic-elementor' ),
				'description'         => __( 'Lists the Elementor pages, posts and templates that contain elements with Visibility Logic rules, with each element id, widget type, action (show or hide), match (all or one) and the conditions it uses. Read only.', 'visibility-logic-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'post_id'   => [
							'type'        => 'integer',
							'description' => __( 'Only this post, page or template.', 'visibility-logic-elementor' ),
							'minimum'     => 1,
						],
						'post_type' => [
							'type'        => 'string',
							'description' => __( 'Only this post type, for example page or elementor_library.', 'visibility-logic-elementor' ),
						],
						'condition' => [
							'type'        => 'string',
							'description' => __( 'Only elements using this condition, for example user_role or date_time.', 'visibility-logic-elementor' ),
						],
						'limit'     => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 200,
							'default' => 50,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'total'     => [ 'type' => 'integer' ],
						'truncated' => [ 'type' => 'boolean' ],
						'documents' => [
							'type'  => 'array',
							'items' => [ 'type' => 'object' ],
						],
					],
				],
				'execute_callback'    => [ __CLASS__, 'list_rules' ],
				'permission_callback' => static function ( $input = [] ) {
					if ( is_array( $input ) && ! empty( $input['post_id'] ) ) {
						return current_user_can( 'edit_post', (int) $input['post_id'] );
					}

					return current_user_can( 'edit_posts' );
				},
				'meta'                => $meta,
			]
		);
	}

	/**
	 * Execute `visibility-logic/list-conditions`.
	 *
	 * @return array
	 */
	public static function list_conditions() {
		$conditions = [];

		foreach ( Resources::get_all_widget_options() as $slug => $option ) {
			$pro          = ! empty( $option['pro'] );
			$conditions[] = [
				'slug'        => str_replace( '-', '_', $slug ),
				'name'        => wp_strip_all_tags( (string) $option['name'] ),
				'description' => isset( $option['description'] ) ? wp_strip_all_tags( (string) $option['description'] ) : '',
				'enabled'     => ! $pro && ! empty( $option['status'] ),
				'pro'         => ! Resources::is_free_condition( $slug ),
			];
		}

		return [
			'pro_active' => Plugin::instance()->has_pro(),
			'conditions' => $conditions,
		];
	}

	/**
	 * Execute `visibility-logic/list-rules`.
	 *
	 * @param array $input Input.
	 *
	 * @return array
	 */
	public static function list_rules( $input = [] ) {
		$input     = is_array( $input ) ? $input : [];
		$limit     = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 50;
		$condition = isset( $input['condition'] ) ? sanitize_key( $input['condition'] ) : '';

		if ( ! empty( $input['post_id'] ) ) {
			$post      = get_post( (int) $input['post_id'] );
			$elements  = $post ? Rules::find_in_post( $post->ID ) : [];
			$documents = $post && $elements ? [
				[
					'id'        => (int) $post->ID,
					'title'     => get_the_title( $post ),
					'post_type' => $post->post_type,
					'status'    => $post->post_status,
					'count'     => count( $elements ),
					'elements'  => $elements,
				],
			] : [];
			$truncated = false;
		} else {
			$index     = Rules::index();
			$documents = $index['documents'];
			$truncated = $index['truncated'];
		}

		$out = [];

		foreach ( $documents as $document ) {
			if ( ! empty( $input['post_type'] ) && $document['post_type'] !== $input['post_type'] ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $document['id'] ) ) {
				continue;
			}

			if ( '' !== $condition ) {
				$document['elements'] = array_values(
					array_filter(
						$document['elements'],
						static function ( $element ) use ( $condition ) {
							return in_array( $condition, $element['conditions'], true );
						}
					)
				);

				if ( empty( $document['elements'] ) ) {
					continue;
				}

				$document['count'] = count( $document['elements'] );
			}

			$out[] = [
				'id'        => $document['id'],
				'title'     => $document['title'],
				'post_type' => $document['post_type'],
				'status'    => $document['status'],
				'edit_url'  => Rules::edit_url( $document['id'] ),
				'count'     => $document['count'],
				'elements'  => $document['elements'],
			];
		}

		return [
			'total'     => count( $out ),
			'truncated' => $truncated || count( $out ) > $limit,
			'documents' => array_slice( $out, 0, $limit ),
		];
	}
}

Abilities::init();
