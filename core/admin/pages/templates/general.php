<?php
/**
 * General tab: how rules are read and rendered on this site.
 *
 * @package Stax\VisibilityLogic
 *
 * @var array $settings General settings.
 * @var bool  $saved    Just saved.
 */

use Stax\VisibilityLogic\Resources;

defined( 'ABSPATH' ) || exit;

$stax_vl_recommended = __( 'Recommended', 'visibility-logic-elementor' );

$stax_vl_rows = [
	'site_timezone'     => [
		'name'    => __( 'Use the site time zone for date ranges', 'visibility-logic-elementor' ),
		'tag'     => $stax_vl_recommended,
		/* translators: %s: time zone name. */
		'desc'    => sprintf( esc_html__( 'Date FROM and Date TO mean the time in %s, like the times of day.', 'visibility-logic-elementor' ), '<code>' . esc_html( wp_timezone_string() ) . '</code>' ),
		'history' => __( 'Before 2.5.2 these dates were read as UTC. Switching this off reads them as UTC again; changing it moves existing schedules by the difference between UTC and the site time zone, so check scheduled elements after changing it.', 'visibility-logic-elementor' ),
	],
	'container_widgets' => [
		'name'    => __( '"Hide when empty" counts widgets placed directly in a container', 'visibility-logic-elementor' ),
		'tag'     => $stax_vl_recommended,
		'desc'    => esc_html__( 'Switched off, a container that holds its widgets directly is always treated as empty and hidden.', 'visibility-logic-elementor' ),
		'history' => __( 'Before 2.5.2, "Hide when empty" only looked at widgets inside inner containers or columns.', 'visibility-logic-elementor' ),
	],
	'element_cache'     => [
		'name'    => __( 'Keep Elementor\'s element cache on for pages with rules', 'visibility-logic-elementor' ),
		'tag'     => __( 'Faster', 'visibility-logic-elementor' ),
		'desc'    => esc_html__( 'Only the elements with visibility rules are rendered on every visit; the rest of the page comes from Elementor\'s cache. Changing it clears Elementor\'s element cache.', 'visibility-logic-elementor' ),
		'history' => __( 'Before 3.0 the whole page skipped the cache, so other dynamic widgets on those pages were also rendered on every visit; with this on, they follow their own Elementor cache settings.', 'visibility-logic-elementor' ),
	],
];
?>
<div class="stax-vl-title">
	<div>
		<h2><?php esc_html_e( 'General', 'visibility-logic-elementor' ); ?></h2>
		<p><?php esc_html_e( 'How rules are read and rendered on this site.', 'visibility-logic-elementor' ); ?></p>
	</div>
</div>

<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible inline"><p><?php esc_html_e( 'Settings saved.', 'visibility-logic-elementor' ); ?></p></div>
<?php endif; ?>

<form class="stax-vl-card" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-stax-vl-dirty>
	<input type="hidden" name="action" value="stax_visibility_options_activation">
	<input type="hidden" name="section" value="general">
	<?php wp_nonce_field( 'stax_visibility_options_activation' ); ?>

	<?php foreach ( $stax_vl_rows as $stax_vl_key => $stax_vl_row ) : ?>
		<?php $stax_vl_id = 'stax-vl-' . str_replace( '_', '-', $stax_vl_key ); ?>
		<div class="stax-vl-setting">
			<div>
				<label class="stax-vl-setting__name" for="<?php echo esc_attr( $stax_vl_id ); ?>">
					<?php echo esc_html( $stax_vl_row['name'] ); ?>
					<span class="stax-vl-tag stax-vl-tag--ok"><?php echo esc_html( $stax_vl_row['tag'] ); ?></span>
				</label>
				<p class="stax-vl-setting__desc" id="<?php echo esc_attr( $stax_vl_id ); ?>-desc"><?php echo wp_kses( $stax_vl_row['desc'], [ 'code' => [] ] ); ?></p>
				<details class="stax-vl-details">
					<summary><?php esc_html_e( 'Earlier versions', 'visibility-logic-elementor' ); ?></summary>
					<p class="stax-vl-setting__desc"><?php echo esc_html( $stax_vl_row['history'] ); ?></p>
				</details>
			</div>
			<input type="checkbox" class="stax-vl-switch" name="stax_visibility_<?php echo esc_attr( $stax_vl_key ); ?>" id="<?php echo esc_attr( $stax_vl_id ); ?>" value="1" aria-describedby="<?php echo esc_attr( $stax_vl_id ); ?>-desc" <?php checked( ! empty( $settings[ $stax_vl_key ] ) ); ?>>
		</div>
	<?php endforeach; ?>

	<?php Resources::load_template( 'core/admin/pages/templates/savebar' ); ?>
</form>
