<?php
/**
 * Read-only index of the visibility rules saved in Elementor documents.
 *
 * Used by Settings > Visibility Logic > Rules in use, the `wp visibility-logic` command and the WordPress Abilities.
 * Nothing here runs on front-end requests.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rules
 */
class Rules {

	/**
	 * Transient holding the last scan.
	 */
	const CACHE_KEY = 'stax_visibility_rules_index';

	/**
	 * Most documents returned by one scan.
	 */
	const MAX_DOCUMENTS = 500;

	/**
	 * Hooks that keep the cached index fresh.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'updated_post_meta', [ __CLASS__, 'maybe_flush' ], 10, 3 );
		add_action( 'added_post_meta', [ __CLASS__, 'maybe_flush' ], 10, 3 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'maybe_flush' ], 10, 3 );
		add_action( 'trashed_post', [ __CLASS__, 'flush' ] );
		add_action( 'untrashed_post', [ __CLASS__, 'flush' ] );
		add_action( 'deleted_post', [ __CLASS__, 'flush' ] );
	}

	/**
	 * Flush when Elementor data changes.
	 *
	 * @param int    $meta_id   Meta id.
	 * @param int    $object_id Post id.
	 * @param string $meta_key  Meta key.
	 *
	 * @return void
	 */
	public static function maybe_flush( $meta_id, $object_id, $meta_key ) {
		if ( '_elementor_data' === $meta_key ) {
			self::flush();
		}
	}

	/**
	 * Forget the cached index.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Whether element settings have visibility rules turned on (current or 1.x format).
	 *
	 * @param array $settings Element settings (saved format).
	 *
	 * @return bool
	 */
	public static function has_rules( $settings ) {
		if ( ! is_array( $settings ) ) {
			return false;
		}

		return ! empty( $settings[ Singleton::SECTION_PREFIX . 'enabled' ] )
			|| ( isset( $settings['ecl_enabled'] ) && 'yes' === $settings['ecl_enabled'] );
	}

	/**
	 * Conditions switched on in element settings (`stax_visibility_<condition>_enabled`), Pro ones included.
	 *
	 * @param array $settings Element settings.
	 *
	 * @return string[]
	 */
	public static function active_conditions( $settings ) {
		$conditions = [];
		$prefix     = Singleton::SECTION_PREFIX;

		foreach ( (array) $settings as $key => $value ) {
			if ( empty( $value ) || ! is_string( $key ) || 0 !== strpos( $key, $prefix ) ) {
				continue;
			}

			if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '([a-z0-9_]+)_enabled$/', $key, $m ) && 'fallback' !== $m[1] ) {
				$conditions[] = $m[1];
			}
		}

		return $conditions;
	}

	/**
	 * Elements with rules in an Elementor element tree (saved format).
	 *
	 * @param array $elements Elements.
	 *
	 * @return array[] List of: id, type, action (show|hide), match (all|one), conditions, legacy, keep_html, fallback.
	 */
	public static function find_in_elements( $elements ) {
		$found = [];

		foreach ( (array) $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : [];

			if ( self::has_rules( $settings ) ) {
				$current = ! empty( $settings[ Singleton::SECTION_PREFIX . 'enabled' ] );

				$found[] = [
					'id'         => isset( $element['id'] ) ? (string) $element['id'] : '',
					'type'       => ! empty( $element['widgetType'] ) ? (string) $element['widgetType'] : ( isset( $element['elType'] ) ? (string) $element['elType'] : '' ),
					'action'     => $current ? ( Singleton::flag( $settings, 'show_hide' ) ? 'show' : 'hide' ) : ( ! empty( $settings['ecl_role_visible'] ) ? 'show' : 'hide' ),
					'match'      => $current ? ( 'one' === Singleton::value( $settings, 'condition_type', 'all' ) ? 'one' : 'all' ) : 'all',
					'conditions' => $current ? self::active_conditions( $settings ) : [ 'user_role' ],
					'legacy'     => ! $current,
					'keep_html'  => $current && Singleton::flag( $settings, 'keep_html' ),
					'fallback'   => $current && Singleton::flag( $settings, 'fallback_enabled' ),
				];
			}

			if ( ! empty( $element['elements'] ) ) {
				$found = array_merge( $found, self::find_in_elements( $element['elements'] ) );
			}
		}

		return $found;
	}

	/**
	 * Elements with rules in one post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array[]
	 */
	public static function find_in_post( $post_id ) {
		$data = get_post_meta( (int) $post_id, '_elementor_data', true );

		if ( is_string( $data ) && '' !== $data ) {
			$data = json_decode( $data, true );
		}

		return is_array( $data ) ? self::find_in_elements( $data ) : [];
	}

	/**
	 * Documents that contain elements with rules (cached for 12 hours, flushed when Elementor data changes).
	 *
	 * @param bool $fresh Ignore the cache.
	 *
	 * @return array{generated:int,truncated:bool,documents:array[]}
	 */
	public static function index( $fresh = false ) {
		$cached = $fresh ? false : get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) && isset( $cached['documents'] ) ) {
			return $cached;
		}

		global $wpdb;

		$like_new = '%' . $wpdb->esc_like( '"' . Singleton::SECTION_PREFIX . 'enabled":"yes"' ) . '%';
		$like_old = '%' . $wpdb->esc_like( '"ecl_enabled":"yes"' ) . '%';

		// One indexed scan of the Elementor data meta, only on request (admin screen, CLI, abilities).
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_elementor_data' AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s )
				AND p.post_type <> 'revision' AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				ORDER BY pm.post_id DESC LIMIT %d",
				$like_new,
				$like_old,
				self::MAX_DOCUMENTS + 1
			)
		);

		$truncated = count( $ids ) > self::MAX_DOCUMENTS;
		$documents = [];

		foreach ( array_slice( $ids, 0, self::MAX_DOCUMENTS ) as $id ) {
			$post = get_post( (int) $id );

			if ( ! $post ) {
				continue;
			}

			$elements = self::find_in_post( $post->ID );

			if ( empty( $elements ) ) {
				continue;
			}

			$conditions = [];
			foreach ( $elements as $element ) {
				$conditions = array_merge( $conditions, $element['conditions'] );
			}

			$documents[] = [
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'post_type'  => $post->post_type,
				'template'   => (string) get_post_meta( $post->ID, '_elementor_template_type', true ),
				'status'     => $post->post_status,
				'count'      => count( $elements ),
				'legacy'     => count( array_filter( wp_list_pluck( $elements, 'legacy' ) ) ),
				'conditions' => array_values( array_unique( $conditions ) ),
				'elements'   => $elements,
			];
		}

		$index = [
			'generated' => time(),
			'truncated' => $truncated,
			'documents' => $documents,
		];

		set_transient( self::CACHE_KEY, $index, 12 * HOUR_IN_SECONDS );

		return $index;
	}

	/**
	 * Elementor editor URL of a post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string
	 */
	public static function edit_url( $post_id ) {
		return add_query_arg(
			[
				'post'   => (int) $post_id,
				'action' => 'elementor',
			],
			admin_url( 'post.php' )
		);
	}

	/**
	 * Human label of a condition slug (`user_role` => "User Role").
	 *
	 * @param string $slug Condition slug.
	 *
	 * @return string
	 */
	public static function condition_label( $slug ) {
		static $labels = null;

		if ( null === $labels ) {
			$labels = [];
			foreach ( Resources::get_all_widget_options() as $key => $option ) {
				$labels[ str_replace( '-', '_', $key ) ] = $option['name'];
			}
			$labels['post_page'] = isset( $labels['post_page'] ) ? $labels['post_page'] : __( 'Post & Page', 'visibility-logic-elementor' );
			$labels['post']      = $labels['post_page'];
		}

		return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucwords( str_replace( '_', ' ', $slug ) );
	}
}

Rules::init();
