<?php
/**
 * One-time notice for sites updated from 2.x: the opt-in behaviours of 3.0 (element cache per element, Hide when
 * empty fix). Shown on the Dashboard, Plugins and Visibility Logic screens to users who can manage options, until
 * the user dismisses it or both options are on. Never on the front end.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UpdateNotice
 */
class UpdateNotice {

	/**
	 * User meta set when the notice is dismissed.
	 */
	const USER_META = 'stax_visibility_dismissed_3_0_notice';

	/**
	 * Site option set when 3.0 stored the 2.x settings on update.
	 */
	const SITE_FLAG = 'stax_visibility_updated_from_2x';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_notices', [ __CLASS__, 'render' ] );
		add_action( 'admin_notices', [ __CLASS__, 'render_pro_outdated' ] );
		add_action( 'admin_post_stax_visibility_dismiss_notice', [ __CLASS__, 'dismiss' ] );
	}

	/**
	 * Whether the notice applies to the current user (screen not checked).
	 *
	 * @return bool
	 */
	public static function applies() {
		if ( ! current_user_can( 'manage_options' ) || '1' !== (string) get_option( self::SITE_FLAG, '' ) ) {
			return false;
		}

		if ( get_user_meta( get_current_user_id(), self::USER_META, true ) ) {
			return false;
		}

		return ! Resources::get_setting( 'element_cache' );
	}

	/**
	 * Whether the current admin screen may show the plugin's notices: Dashboard, Plugins, Visibility Logic screens.
	 *
	 * @return bool
	 */
	public static function on_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && ( in_array( $screen->id, [ 'dashboard', 'plugins' ], true ) || false !== strpos( (string) $screen->id, 'stax-visibility' ) );
	}

	/**
	 * Notice.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! self::on_screen() || ! self::applies() ) {
			return;
		}

		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=stax_visibility_dismiss_notice' ), 'stax_visibility_dismiss_notice' );
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Visibility Logic 3.0: your site works exactly as before.', 'visibility-logic-elementor' ); ?></strong></p>
			<p><?php esc_html_e( 'New: a rule builder that reads your rules as sentences ("Show this element when ..."), Preview as a guest, a role or another date inside the editor, and a Rules overview with warnings. Your saved rules are not changed; they open in the builder as they are.', 'visibility-logic-elementor' ); ?></p>
			<p><?php esc_html_e( 'Faster pages are available as an option, off on updated sites so nothing changes without you:', 'visibility-logic-elementor' ); ?></p>
			<ul class="ul-disc">
				<?php if ( ! Resources::get_setting( 'element_cache' ) ) : ?>
					<li><?php esc_html_e( 'Faster pages: keep Elementor\'s element cache on for pages with visibility rules.', 'visibility-logic-elementor' ); ?></li>
				<?php endif; ?>
			</ul>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( Settings::url( 'usage' ) ); ?>"><?php esc_html_e( 'See your rules', 'visibility-logic-elementor' ); ?></a>
				<a class="button" href="<?php echo esc_url( Settings::url( 'options' ) . '#stax-vl-general' ); ?>"><?php esc_html_e( 'Review the options', 'visibility-logic-elementor' ); ?></a>
				<a class="button" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'visibility-logic-elementor' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Visibility Logic Pro older than 3.0: ask to update it. Nothing is broken meanwhile (saved Pro rules keep
	 * working), so the notice only explains and links to the update; it goes away once Pro is updated.
	 *
	 * @return void
	 */
	public static function render_pro_outdated() {
		if ( ! Singleton::pro_outdated() || ! current_user_can( 'update_plugins' ) || ! self::on_screen() ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'Update Visibility Logic Pro to 3.0.', 'visibility-logic-elementor' ); ?></strong>
			<?php
			/* translators: %s: installed Visibility Logic Pro version. */
			echo esc_html( sprintf( __( 'You have Pro %s. Your rules keep working as before; with Pro 3.0 you can edit Pro conditions in the new rule builder.', 'visibility-logic-elementor' ), STAX_VISIBILITY_PRO_VERSION ) );
			?>
			<a href="<?php echo esc_url( admin_url( 'plugins.php?plugin_status=upgrade' ) ); ?>"><?php esc_html_e( 'Go to updates', 'visibility-logic-elementor' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Dismiss for the current user.
	 *
	 * @return void
	 */
	public static function dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'visibility-logic-elementor' ), 403 );
		}

		check_admin_referer( 'stax_visibility_dismiss_notice' );

		update_user_meta( get_current_user_id(), self::USER_META, time() );

		$back = wp_get_referer();

		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
}

UpdateNotice::init();
