<?php
/**
 * One Pro notice in the free plugin, after the site's 5th saved visibility rule.
 *
 * - Counts rules when a post is saved (Elementor elements, blocks, the page rule), per post, in one option; no
 *   tracking, nothing leaves the site. Once 5 rules are reached the per-post counts are dropped.
 * - Shown to users who can manage options, on the Dashboard, Plugins and Visibility Logic screens only, until
 *   someone dismisses it: then never again (site option). Never while Visibility Logic Pro is active.
 * - Nothing on fresh installs: a site needs 5 saved rules first.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProNotice
 */
class ProNotice {

	/**
	 * Option: [ 'counts' => [ post id => rules ], 'reached' => timestamp, 'dismissed' => timestamp ].
	 */
	const OPTION = 'stax_visibility_pro_notice';

	/**
	 * Rules before the notice shows.
	 */
	const THRESHOLD = 5;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'save_post', [ __CLASS__, 'saved' ], 20 );
		add_action( 'elementor/document/after_save', [ __CLASS__, 'elementor_saved' ], 20 );
		add_action( 'updated_post_meta', [ __CLASS__, 'meta_saved' ], 20, 3 );
		add_action( 'added_post_meta', [ __CLASS__, 'meta_saved' ], 20, 3 );

		if ( is_admin() ) {
			add_action( 'admin_notices', [ __CLASS__, 'render' ] );
			add_action( 'admin_post_stax_visibility_dismiss_pro_notice', [ __CLASS__, 'dismiss' ] );
		}
	}

	/**
	 * State.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::OPTION, [] );

		return array_merge(
			[
				'counts'    => [],
				'reached'   => 0,
				'dismissed' => 0,
			],
			is_array( $state ) ? $state : []
		);
	}

	/**
	 * Whether counting still matters (Pro not active, threshold not reached).
	 *
	 * @return bool
	 */
	private static function counting() {
		$state = self::state();

		return Pro_Teaser::visible() && ! $state['reached'] && ! $state['dismissed'];
	}

	/**
	 * Rules in one post: Elementor elements, blocks, the page rule.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return int
	 */
	public static function rules_in( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post ) {
			return 0;
		}

		$count = count( Rules::find_in_post( $post->ID ) );

		if ( function_exists( 'parse_blocks' ) && false !== strpos( (string) $post->post_content, '"' . Blocks::ATTRIBUTE . '"' ) ) {
			$count += count( Blocks::find_in_blocks( parse_blocks( $post->post_content ) ) );
		}

		if ( Page_Rules::active( Page_Rules::get( $post->ID ) ) ) {
			++$count;
		}

		return $count;
	}

	/**
	 * Recount one post and store the total.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	public static function recount( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id || ! self::counting() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$state = self::state();
		$count = self::rules_in( $post_id );

		if ( $count ) {
			$state['counts'][ $post_id ] = $count;
		} else {
			unset( $state['counts'][ $post_id ] );
		}

		if ( array_sum( $state['counts'] ) >= self::THRESHOLD ) {
			$state['reached'] = time();
			$state['counts']  = [];
		}

		update_option( self::OPTION, $state, false );
	}

	/**
	 * `save_post`.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	public static function saved( $post_id ) {
		self::recount( $post_id );
	}

	/**
	 * `elementor/document/after_save` (Elementor writes its data after `save_post`).
	 *
	 * @param object $document Document.
	 *
	 * @return void
	 */
	public static function elementor_saved( $document ) {
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			self::recount( $document->get_main_id() );
		}
	}

	/**
	 * Page rule saved from the block editor (REST writes the meta after `save_post`).
	 *
	 * @param int    $meta_id  Meta id.
	 * @param int    $post_id  Post id.
	 * @param string $meta_key Key.
	 *
	 * @return void
	 */
	public static function meta_saved( $meta_id, $post_id, $meta_key ) {
		if ( Page_Rules::META === $meta_key ) {
			self::recount( $post_id );
		}
	}

	/**
	 * Whether the notice shows for the current user (screen not checked).
	 *
	 * @return bool
	 */
	public static function applies() {
		$state = self::state();

		return Pro_Teaser::visible() && $state['reached'] && ! $state['dismissed'] && current_user_can( 'manage_options' );
	}

	/**
	 * Notice.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! UpdateNotice::on_screen() || ! self::applies() ) {
			return;
		}

		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=stax_visibility_dismiss_pro_notice' ), 'stax_visibility_dismiss_pro_notice' );
		?>
		<div class="notice notice-info stax-vl-pro-notice">
			<p>
				<strong><?php esc_html_e( 'Your site has 5 visibility rules.', 'visibility-logic-elementor' ); ?></strong>
				<?php echo esc_html( __( 'Visibility Logic Pro adds:', 'visibility-logic-elementor' ) . ' ' . Pro_Teaser::more_text() ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( Pro_Teaser::url( 'notice', 'five-rules' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See Visibility Logic Pro', 'visibility-logic-elementor' ); ?></a>
				<a class="button" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'visibility-logic-elementor' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss for good (site wide).
	 *
	 * @return void
	 */
	public static function dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'visibility-logic-elementor' ), 403 );
		}

		check_admin_referer( 'stax_visibility_dismiss_pro_notice' );

		$state              = self::state();
		$state['dismissed'] = time();
		$state['counts']    = [];

		update_option( self::OPTION, $state, false );

		$back = wp_get_referer();

		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
}

ProNotice::init();
