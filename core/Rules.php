<?php
/**
 * Read-only index of the visibility rules saved in Elementor documents.
 *
 * Used by Settings > Visibility Logic > Rules, the `wp visibility-logic` command and the WordPress Abilities.
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
	const CACHE_KEY = 'stax_visibility_rules_index_v3';

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
		add_action( 'save_post', [ __CLASS__, 'maybe_flush_post' ], 10, 2 );
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
	 * Flush when a post with block rules is saved.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 *
	 * @return void
	 */
	public static function maybe_flush_post( $post_id, $post ) {
		if ( $post instanceof \WP_Post && false !== strpos( (string) $post->post_content, '"' . Blocks::ATTRIBUTE . '"' ) ) {
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

		return self::is_enabled( $settings ) || ( isset( $settings['ecl_enabled'] ) && 'yes' === $settings['ecl_enabled'] );
	}

	/**
	 * Whether "Enable Visibility Logic" is on (classic value, or an Elementor 4 atomic prop).
	 *
	 * @param array $settings Element settings (saved format).
	 *
	 * @return bool
	 */
	public static function is_enabled( $settings ) {
		$key = Singleton::SECTION_PREFIX . 'enabled';

		if ( ! is_array( $settings ) || empty( $settings[ $key ] ) ) {
			return false;
		}

		if ( is_array( $settings[ $key ] ) ) {
			$value = Conditions::unwrap( $settings[ $key ] );

			return true === $value || 'yes' === $value;
		}

		return true;
	}

	/**
	 * Saved settings in the classic format (atomic props unwrapped and converted).
	 *
	 * @param array $settings Element settings (saved format).
	 *
	 * @return array
	 */
	public static function normalize( $settings ) {
		$settings = (array) $settings;
		$key      = Singleton::SECTION_PREFIX . 'enabled';

		if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
			return $settings;
		}

		$values = [];

		foreach ( $settings as $name => $value ) {
			if ( is_string( $name ) && 0 === strpos( $name, Singleton::SECTION_PREFIX ) ) {
				$values[ substr( $name, strlen( Singleton::SECTION_PREFIX ) ) ] = Conditions::unwrap( $value );
			}
		}

		return Conditions::to_settings( $values, 'atomic' );
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

			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? self::normalize( $element['settings'] ) : [];

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
				] + self::rule_details( $current ? $settings : null );
			}

			if ( ! empty( $element['elements'] ) ) {
				$found = array_merge( $found, self::find_in_elements( $element['elements'] ) );
			}
		}

		return $found;
	}

	/**
	 * Rule details of an element for the overview, the abilities and WP-CLI: the rule as a tree, its sentence,
	 * whether it is stored as a tree (3.0 format) and whether it is a draft.
	 *
	 * @param array|null $settings Classic (prefixed) settings, null for 1.x settings.
	 *
	 * @return array{tree:array|null,summary:string,stored:bool,draft:bool}
	 */
	public static function rule_details( $settings ) {
		if ( null === $settings ) {
			return [
				'tree'    => null,
				'summary' => __( 'Visibility Logic 1.x settings (user roles)', 'visibility-logic-elementor' ),
				'stored'  => false,
				'draft'   => false,
			];
		}

		$stored = Rule_Tree::stored( $settings );
		$tree   = $stored ? $stored : Rule_Tree::legacy_to_tree( $settings );

		return [
			'tree'    => $tree,
			'summary' => Rule_Tree::sentence( $tree ),
			'stored'  => null !== $stored,
			'draft'   => ! empty( $tree['draft'] ),
		];
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
		$like_v4  = '%' . $wpdb->esc_like( '"' . Singleton::SECTION_PREFIX . 'enabled":{"$$type":"boolean","value":true' ) . '%';

		// One indexed scan of the Elementor data meta, only on request (admin screen, CLI, abilities).
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_elementor_data' AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s )
				AND p.post_type <> 'revision' AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				ORDER BY pm.post_id DESC LIMIT %d",
				$like_new,
				$like_old,
				$like_v4,
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
				'builder'    => 'elementor',
				'status'     => $post->post_status,
				'count'      => count( $elements ),
				'legacy'     => count( array_filter( wp_list_pluck( $elements, 'legacy' ) ) ),
				'conditions' => array_values( array_unique( $conditions ) ),
				'elements'   => $elements,
			];
		}

		// Block editor content (`staxVisibility` block attribute).
		$block_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_content LIKE %s AND post_type <> 'revision' AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				ORDER BY ID DESC LIMIT %d",
				'%' . $wpdb->esc_like( '"' . Blocks::ATTRIBUTE . '":{' ) . '%',
				self::MAX_DOCUMENTS + 1
			)
		);

		$truncated = $truncated || count( $block_ids ) > self::MAX_DOCUMENTS;

		foreach ( array_slice( $block_ids, 0, self::MAX_DOCUMENTS ) as $id ) {
			$post   = get_post( (int) $id );
			$blocks = $post && function_exists( 'parse_blocks' ) ? Blocks::find_in_blocks( parse_blocks( $post->post_content ) ) : [];

			if ( empty( $blocks ) ) {
				continue;
			}

			$conditions = [];
			foreach ( $blocks as $block ) {
				$conditions = array_merge( $conditions, $block['conditions'] );
			}

			$documents[] = [
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'post_type'  => $post->post_type,
				'template'   => '',
				'builder'    => 'blocks',
				'status'     => $post->post_status,
				'count'      => count( $blocks ),
				'legacy'     => 0,
				'conditions' => array_values( array_unique( $conditions ) ),
				'elements'   => $blocks,
			];
		}

		// Page-level rules (Page_Rules: Elementor page settings and the block editor document panel).
		$documents = array_merge( $documents, Page_Rules::overview_documents( self::MAX_DOCUMENTS ) );

		$index = [
			'generated' => time(),
			'truncated' => $truncated,
			'documents' => $documents,
		];

		set_transient( self::CACHE_KEY, $index, 12 * HOUR_IN_SECONDS );

		return $index;
	}

	/**
	 * Whether a row contains the search text (page title, element name or id, rule in words).
	 *
	 * @param string $query   Search text.
	 * @param array  $doc     Document of the index.
	 * @param array  $element Element of the index.
	 *
	 * @return bool
	 */
	private static function row_matches( $query, array $doc, array $element ) {
		$parts = [];
		foreach ( [ [ $doc, 'title' ], [ $element, 'label' ], [ $element, 'name' ], [ $element, 'id' ], [ $element, 'summary' ] ] as $pair ) {
			if ( isset( $pair[0][ $pair[1] ] ) && is_scalar( $pair[0][ $pair[1] ] ) ) {
				$parts[] = (string) $pair[0][ $pair[1] ];
			}
		}

		return false !== stripos( implode( ' ', $parts ), (string) $query );
	}

	/**
	 * One row per element for the Rules overview, with its sentence and warnings, filtered.
	 *
	 * @param array $index   Rules::index() result.
	 * @param array $filters editor (elementor|blocks), condition (slug), status (problems|draft|tree|<warning code>).
	 *
	 * @return array[]
	 */
	public static function overview_rows( array $index, array $filters = [] ) {
		$rows = [];

		foreach ( $index['documents'] as $doc ) {
			$builder = isset( $doc['builder'] ) ? $doc['builder'] : 'elementor';

			$pages = ! empty( $filters['editor'] ) && 'page' === $filters['editor'];

			if ( ! empty( $filters['editor'] ) && ! $pages && $filters['editor'] !== $builder ) {
				continue;
			}

			foreach ( $doc['elements'] as $element ) {
				if ( $pages !== ( isset( $element['type'] ) && 'page' === $element['type'] ) && ! empty( $filters['editor'] ) ) {
					continue;
				}

				if ( ! empty( $filters['condition'] ) && ! in_array( $filters['condition'], (array) $element['conditions'], true ) ) {
					continue;
				}

				if ( ! empty( $filters['q'] ) && ! self::row_matches( $filters['q'], $doc, $element ) ) {
					continue;
				}

				$tree     = isset( $element['tree'] ) && is_array( $element['tree'] ) ? $element['tree'] : null;
				$warnings = $tree ? Rule_Tree::warnings( $tree ) : [];
				$codes    = wp_list_pluck( $warnings, 'code' );
				$stored   = ! empty( $element['stored'] );
				$status   = isset( $filters['status'] ) ? (string) $filters['status'] : '';

				if ( '' !== $status ) {
					$problems = array_diff( $codes, [ 'cache', 'draft' ] );

					if ( ( 'problems' === $status && ! $problems ) || ( 'tree' === $status && ! $stored ) || ( ! in_array( $status, [ 'problems', 'tree' ], true ) && ! in_array( $status, $codes, true ) ) ) {
						continue;
					}
				}

				$rows[] = [
					'doc'      => $doc,
					'builder'  => $builder,
					'element'  => $element,
					'summary'  => isset( $element['summary'] ) ? $element['summary'] : '',
					'warnings' => array_map( [ Rule_Tree::class, 'warning_text' ], $warnings ),
					'codes'    => $codes,
					'stored'   => $stored,
				];
			}
		}

		return $rows;
	}

	/**
	 * Elementor editor URL of a post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string
	 */
	public static function edit_url( $post_id, $builder = 'elementor' ) {
		if ( 'blocks' === $builder ) {
			return (string) get_edit_post_link( (int) $post_id, 'raw' );
		}

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
			$labels['post_page'] = isset( $labels['post_page'] ) ? $labels['post_page'] : __( 'Posts & Pages', 'visibility-logic-elementor' );
			$labels['post']      = $labels['post_page'];
			// Rule groups that Visibility Logic Pro adds to the free ACF Field and User Meta sections.
			$labels['acf_rules']       = __( 'ACF Field (rule group)', 'visibility-logic-elementor' );
			$labels['user_meta_rules'] = __( 'User Meta (rule group)', 'visibility-logic-elementor' );
		}

		return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucwords( str_replace( '_', ' ', $slug ) );
	}
}

Rules::init();
