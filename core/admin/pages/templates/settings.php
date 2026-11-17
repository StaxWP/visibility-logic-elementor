<?php
/**
 * Settings tab: Elementor cache, and a one-time switch for sites that still use the behavior from before 2.5.2.
 *
 * @package Stax\VisibilityLogic
 *
 * @var array    $settings General settings.
 * @var string[] $old      Settings still on the old behavior (site_timezone, container_widgets).
 * @var bool     $saved    Just saved.
 */

use Stax\VisibilityLogic\Resources;
use Stax\VisibilityLogic\Settings;

defined( 'ABSPATH' ) || exit;

$stax_vl_old_text = [
	/* translators: %s: time zone name. */
	'site_timezone'     => sprintf( __( 'Date ranges are read in UTC, not in the site time zone (%s).', 'visibility-logic-elementor' ), wp_timezone_string() ),
	'container_widgets' => __( '"Hide when empty" hides containers that hold their widgets directly.', 'visibility-logic-elementor' ),
];
?>
<div class="stax-vl-title">
	<div>
		<h2><?php esc_html_e( 'Settings', 'visibility-logic-elementor' ); ?></h2>
		<p><?php esc_html_e( 'How visibility rules run on this site.', 'visibility-logic-elementor' ); ?></p>
	</div>
</div>

<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible inline"><p><?php esc_html_e( 'Settings saved.', 'visibility-logic-elementor' ); ?></p></div>
<?php endif; ?>

<?php if ( $old ) : ?>
	<form class="stax-vl-alert stax-vl-alert--warn" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<?php Settings::icon( 'alert' ); ?>
		<div>
			<h3><?php esc_html_e( 'This site still uses older behavior', 'visibility-logic-elementor' ); ?></h3>
			<p><?php esc_html_e( 'Kept from a version before 2.5.2, so nothing changed on its own when you updated:', 'visibility-logic-elementor' ); ?></p>
			<ul>
				<?php foreach ( $old as $stax_vl_key ) : ?>
					<li><?php echo esc_html( $stax_vl_old_text[ $stax_vl_key ] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( in_array( 'site_timezone', $old, true ) ) : ?>
				<p><?php esc_html_e( 'After switching, check your scheduled elements: their times move by the difference between UTC and the site time zone.', 'visibility-logic-elementor' ); ?></p>
			<?php endif; ?>
			<input type="hidden" name="action" value="stax_visibility_options_activation">
			<input type="hidden" name="section" value="current_behavior">
			<?php wp_nonce_field( 'stax_visibility_options_activation' ); ?>
			<div class="stax-vl-alert__actions">
				<button type="submit" class="stax-vl-btn stax-vl-btn--sm"><?php esc_html_e( 'Switch to the current behavior', 'visibility-logic-elementor' ); ?></button>
			</div>
		</div>
	</form>
<?php endif; ?>

<form class="stax-vl-card" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-stax-vl-dirty>
	<input type="hidden" name="action" value="stax_visibility_options_activation">
	<input type="hidden" name="section" value="settings">
	<?php wp_nonce_field( 'stax_visibility_options_activation' ); ?>

	<div class="stax-vl-setting">
		<div>
			<label class="stax-vl-setting__name" for="stax-vl-element-cache">
				<?php esc_html_e( 'Faster pages with visibility rules', 'visibility-logic-elementor' ); ?>
				<span class="stax-vl-tag stax-vl-tag--ok"><?php esc_html_e( 'Recommended', 'visibility-logic-elementor' ); ?></span>
			</label>
			<p class="stax-vl-setting__desc" id="stax-vl-element-cache-desc">
				<?php esc_html_e( 'Elementor\'s cache stays on; only the elements with rules are checked on each visit. Turn it off only if another dynamic widget on those pages stops updating. Changing it clears Elementor\'s cache.', 'visibility-logic-elementor' ); ?>
			</p>
		</div>
		<input type="checkbox" class="stax-vl-switch" name="stax_visibility_element_cache" id="stax-vl-element-cache" value="1" aria-describedby="stax-vl-element-cache-desc" <?php checked( ! empty( $settings['element_cache'] ) ); ?>>
	</div>

	<?php Resources::load_template( 'core/admin/pages/templates/savebar' ); ?>
</form>
