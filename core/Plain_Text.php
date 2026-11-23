<?php
/**
 * Keeps the content of elements and blocks with visibility rules out of `post_content` and out of the automatic excerpt.
 *
 * Elementor saves a plain-HTML copy of every widget in `post_content` and WordPress builds the excerpt from it (REST
 * `excerpt`, RSS `<description>`, Atom `<summary>`, `/wp/v2/search?_embed`, themes calling `get_the_excerpt()`). The
 * rules only ran when Elementor rendered the page, so the text of a hidden widget reached every visitor through those
 * channels. Block markup is kept in `post_content` as written, and WordPress builds the excerpt from the inner blocks of
 * groups and columns without rendering the wrapper, so a hidden group leaked the same way.
 *
 * An element or a block with "Enable Visibility Logic" on is now left out of both, for every visitor:
 * - Elementor's plain-text copy is rebuilt without it when the document is saved (also keeps the hidden words out of the
 *   core search);
 * - while WordPress builds an excerpt, the content it reads is rebuilt the same way, so posts saved before this fix
 *   (their `post_content` still holds the hidden text) and block posts are covered without saving them again.
 *
 * Hand-written excerpts are not changed. The page itself renders as before.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plain_Text
 */
class Plain_Text {

	/**
	 * `get_the_excerpt` calls in progress (WordPress builds the automatic excerpt at priority 10).
	 *
	 * @var int
	 */
	private static $excerpt_depth = 0;

	/**
	 * Posts whose plain text is being built (a widget printing the excerpt of its own post).
	 *
	 * @var array
	 */
	private static $building = [];

	/**
	 * Built plain text per post and Elementor data, for the request.
	 *
	 * @var array
	 */
	private static $cache = [];

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'get_the_excerpt', [ __CLASS__, 'excerpt_start' ], 9 );
		add_filter( 'get_the_excerpt', [ __CLASS__, 'excerpt_end' ], 11 );
		add_filter( 'content_pagination', [ __CLASS__, 'excerpt_source' ], 10, 2 );
		add_filter( 'wp_insert_post_data', [ __CLASS__, 'saved_plain_text' ], 10, 2 );
	}

	/**
	 * `get_the_excerpt`, before WordPress builds the excerpt.
	 *
	 * @param string $excerpt Excerpt.
	 *
	 * @return string
	 */
	public static function excerpt_start( $excerpt ) {
		++self::$excerpt_depth;

		return $excerpt;
	}

	/**
	 * `get_the_excerpt`, after WordPress built the excerpt.
	 *
	 * @param string $excerpt Excerpt.
	 *
	 * @return string
	 */
	public static function excerpt_end( $excerpt ) {
		self::$excerpt_depth = max( 0, self::$excerpt_depth - 1 );

		return $excerpt;
	}

	/**
	 * `content_pagination`: the content WordPress reads to build an excerpt (`wp_trim_excerpt()` reads it through
	 * `get_the_content()`), without the elements and blocks that have rules.
	 *
	 * @param string[] $pages Content split by `<!--nextpage-->`.
	 * @param \WP_Post $post  Post.
	 *
	 * @return string[]
	 */
	public static function excerpt_source( $pages, $post ) {
		if ( self::$excerpt_depth < 1 || ! $post instanceof \WP_Post || ! is_array( $pages ) ) {
			return $pages;
		}

		$text = self::elementor_text( $post->ID );

		if ( null !== $text ) {
			return [ $text ];
		}

		if ( false === strpos( (string) $post->post_content, '"' . Blocks::ATTRIBUTE . '"' ) ) {
			return $pages;
		}

		return array_map( [ __CLASS__, 'without_blocks_with_rules' ], $pages );
	}

	/**
	 * `wp_insert_post_data`: when Elementor writes its plain-text copy of a document, write it without the elements
	 * that have rules.
	 *
	 * @param array $data    Post data (slashed, sanitized).
	 * @param array $postarr Post array.
	 *
	 * @return array
	 */
	public static function saved_plain_text( $data, $postarr ) {
		if ( empty( $postarr['ID'] ) || ! self::is_building() ) {
			return $data;
		}

		$document = \Elementor\Plugin::$instance->documents->get( (int) $postarr['ID'] );
		$text     = $document ? self::build( $document ) : null;

		if ( null !== $text ) {
			// The same sanitizing WordPress gave Elementor's copy (KSES for users without `unfiltered_html`).
			$data['post_content'] = sanitize_post_field( 'post_content', wp_slash( $text ), (int) $postarr['ID'], 'db' );
		}

		return $data;
	}

	/**
	 * Whether Elementor is printing a plain-text copy of a document.
	 *
	 * Only `DB::save_plain_text()` (and build() below) switch the dynamic tags to the "remove" mode: the copy holds no
	 * dynamic values, and no element with rules either (Plugin::item_should_render(), Plugin::content_change()).
	 *
	 * @return bool
	 */
	public static function is_building() {
		$tags = self::dynamic_tags();

		return $tags && 'remove' === $tags->get_parsing_mode();
	}

	/**
	 * Plain text of an Elementor post without the elements that have rules, or null when it has none (or is not built
	 * with Elementor).
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string|null
	 */
	public static function elementor_text( $post_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return null;
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );

		// Fast path: no visibility setting saved in the document.
		if ( empty( $raw ) || ( is_string( $raw ) && false === strpos( $raw, '"' . Singleton::SECTION_PREFIX . 'enabled"' ) && false === strpos( $raw, '"ecl_enabled"' ) ) ) {
			return null;
		}

		// A widget of the post printing the excerpt of the same post: nothing.
		if ( isset( self::$building[ $post_id ] ) ) {
			return '';
		}

		$key = $post_id . ':' . md5( is_string( $raw ) ? $raw : wp_json_encode( $raw ) );

		if ( array_key_exists( $key, self::$cache ) ) {
			return self::$cache[ $key ];
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		if ( ! $document || ! $document->is_built_with_elementor() ) {
			return null;
		}

		self::$building[ $post_id ] = true;
		$text                       = self::build( $document );
		unset( self::$building[ $post_id ] );

		self::$cache[ $key ] = $text;

		return $text;
	}

	/**
	 * Elementor's plain text of a document, printed without the elements that have rules (and everything in them), or
	 * null when no element has rules.
	 *
	 * @param \Elementor\Core\Base\Document $document Document.
	 *
	 * @return string|null
	 */
	private static function build( $document ) {
		$tags = self::dynamic_tags();
		$db   = isset( \Elementor\Plugin::$instance->db ) ? \Elementor\Plugin::$instance->db : null;

		if ( ! $tags || ! $db || ! method_exists( $db, 'get_plain_text_from_data' ) ) {
			return null;
		}

		$dropped  = false;
		$elements = self::without_elements_with_rules( (array) $document->get_elements_data(), $dropped );

		if ( ! $dropped ) {
			return null;
		}

		// As DB::save_plain_text(): dynamic tags removed.
		$mode = $tags->get_parsing_mode();
		$tags->set_parsing_mode( 'remove' );
		$text = (string) $db->get_plain_text_from_data( $elements );
		$tags->set_parsing_mode( $mode );

		return $text;
	}

	/**
	 * Elements data without the elements that have rules (current, Elementor 4 atomic or 1.x settings).
	 *
	 * @param array $elements Elements data.
	 * @param bool  $dropped  Set to true when an element was left out.
	 *
	 * @return array
	 */
	private static function without_elements_with_rules( array $elements, &$dropped ) {
		$kept = [];

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['settings'] ) && Rules::has_rules( $element['settings'] ) ) {
				$dropped = true;
				continue;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::without_elements_with_rules( $element['elements'], $dropped );
			}

			$kept[] = $element;
		}

		return $kept;
	}

	/**
	 * Block markup without the blocks that have rules (and the blocks in them).
	 *
	 * @param string $content Block markup.
	 *
	 * @return string
	 */
	public static function without_blocks_with_rules( $content ) {
		if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) {
			return $content;
		}

		$dropped = false;
		$blocks  = self::drop_blocks( parse_blocks( (string) $content ), $dropped );

		return $dropped ? serialize_blocks( $blocks ) : $content;
	}

	/**
	 * Parsed blocks without the blocks that have rules (`innerContent` keeps one placeholder per inner block).
	 *
	 * @param array $blocks  Parsed blocks.
	 * @param bool  $dropped Set to true when a block was left out.
	 *
	 * @return array
	 */
	private static function drop_blocks( array $blocks, &$dropped ) {
		$kept = [];

		foreach ( $blocks as $block ) {
			if ( ! empty( $block['attrs'][ Blocks::ATTRIBUTE ]['enabled'] ) ) {
				$dropped = true;
				continue;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner   = [];
				$content = [];
				$index   = 0;

				foreach ( (array) $block['innerContent'] as $chunk ) {
					if ( is_string( $chunk ) ) {
						$content[] = $chunk;
						continue;
					}

					$child = isset( $block['innerBlocks'][ $index ] ) ? self::drop_blocks( [ $block['innerBlocks'][ $index ] ], $dropped ) : [];
					++$index;

					if ( $child ) {
						$inner[]   = $child[0];
						$content[] = null;
					}
				}

				$block['innerBlocks']  = $inner;
				$block['innerContent'] = $content;
			}

			$kept[] = $block;
		}

		return $kept;
	}

	/**
	 * Elementor's dynamic tags manager, when it can switch the parsing mode.
	 *
	 * @return object|null
	 */
	private static function dynamic_tags() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->dynamic_tags ) ) {
			return null;
		}

		$tags = \Elementor\Plugin::$instance->dynamic_tags;

		return method_exists( $tags, 'get_parsing_mode' ) && method_exists( $tags, 'set_parsing_mode' ) ? $tags : null;
	}
}

Plain_Text::init();
