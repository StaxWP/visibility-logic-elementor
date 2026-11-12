<?php
/**
 * Conditions tab.
 *
 * @package Stax\VisibilityLogic
 *
 * @var array $options  All conditions.
 * @var array $included Conditions available on this site (free, and Pro when active).
 * @var array $locked   Pro conditions (Pro not active).
 * @var bool  $has_pro  Pro active.
 * @var array $settings General settings.
 * @var bool  $saved    Just saved.
 */

use Stax\VisibilityLogic\Resources;

defined( 'ABSPATH' ) || exit;
?>
<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible inline"><p><?php esc_html_e( 'Settings saved.', 'visibility-logic-elementor' ); ?></p></div>
<?php endif; ?>

<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
	<input type="hidden" name="action" value="stax_visibility_options_activation">
	<?php wp_nonce_field( 'stax_visibility_options_activation' ); ?>

	<h2><?php esc_html_e( 'Conditions', 'visibility-logic-elementor' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Conditions turned off are removed from the Visibility tab in Elementor and are not checked on your pages, so their saved rules have no effect.', 'visibility-logic-elementor' ); ?>
	</p>

	<fieldset class="stax-vl-conditions">
		<legend class="screen-reader-text"><?php esc_html_e( 'Active conditions', 'visibility-logic-elementor' ); ?></legend>
		<?php foreach ( $included as $stax_vl_slug => $stax_vl_option ) : ?>
			<?php
			$stax_vl_id      = 'stax-vl-condition-' . sanitize_html_class( $stax_vl_slug );
			$stax_vl_require = isset( $stax_vl_option['require']['text']['plugins'] ) ? [ $stax_vl_option['require']['text']['plugins'] ] : [];
			$stax_vl_blocked = ! empty( $stax_vl_option['require'] );
			?>
			<div class="stax-vl-condition<?php echo $stax_vl_blocked ? ' is-blocked' : ''; ?>">
				<?php if ( $stax_vl_blocked ) : ?>
					<span class="stax-vl-condition__name"><?php echo esc_html( wp_strip_all_tags( $stax_vl_option['name'] ) ); ?></span>
				<?php else : ?>
					<input type="checkbox" class="stax-vl-switch" name="<?php echo esc_attr( $stax_vl_slug ); ?>" id="<?php echo esc_attr( $stax_vl_id ); ?>" value="1" <?php checked( ! empty( $stax_vl_option['status'] ) ); ?>>
					<label for="<?php echo esc_attr( $stax_vl_id ); ?>" class="stax-vl-condition__name"><?php echo esc_html( wp_strip_all_tags( $stax_vl_option['name'] ) ); ?></label>
				<?php endif; ?>
				<?php if ( ! Resources::is_free_condition( $stax_vl_slug ) ) : ?>
					<span class="stax-vl-badge stax-vl-badge--pro"><?php esc_html_e( 'Pro', 'visibility-logic-elementor' ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $stax_vl_option['description'] ) ) : ?>
					<p class="stax-vl-condition__desc"><?php echo wp_kses_post( $stax_vl_option['description'] ); ?></p>
				<?php endif; ?>
				<?php foreach ( (array) $stax_vl_require as $stax_vl_text ) : ?>
					<p class="stax-vl-condition__req"><?php echo wp_kses_post( $stax_vl_text ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</fieldset>

	<h2><?php esc_html_e( 'General', 'visibility-logic-elementor' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Date ranges', 'visibility-logic-elementor' ); ?></th>
			<td>
				<label for="stax-vl-site-timezone">
					<input type="checkbox" name="stax_visibility_site_timezone" id="stax-vl-site-timezone" value="1" <?php checked( ! empty( $settings['site_timezone'] ) ); ?>>
					<?php esc_html_e( 'Read Date FROM and Date TO in the site time zone', 'visibility-logic-elementor' ); ?>
				</label>
				<p class="description">
					<?php
					printf(
						/* translators: %s: time zone name. */
						esc_html__( 'Recommended. Site time zone: %s. Versions before 3.0 read these dates as UTC; sites that update keep that until this box is checked, so check your scheduled elements after turning it on.', 'visibility-logic-elementor' ),
						'<code>' . esc_html( wp_timezone_string() ) . '</code>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'visibility-logic-elementor' ) ); ?>
</form>

<?php if ( ! empty( $locked ) ) : ?>
	<h2><?php esc_html_e( 'More conditions in Pro', 'visibility-logic-elementor' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Visibility Logic Pro adds these to the same Visibility tab. Everything above stays free.', 'visibility-logic-elementor' ); ?>
	</p>
	<ul class="stax-vl-conditions stax-vl-conditions--pro">
		<?php foreach ( $locked as $stax_vl_option ) : ?>
			<li class="stax-vl-condition is-pro">
				<span class="stax-vl-condition__name"><?php echo esc_html( wp_strip_all_tags( $stax_vl_option['name'] ) ); ?></span>
				<span class="stax-vl-badge stax-vl-badge--pro"><?php esc_html_e( 'Pro', 'visibility-logic-elementor' ); ?></span>
				<?php if ( ! empty( $stax_vl_option['description'] ) ) : ?>
					<p class="stax-vl-condition__desc"><?php echo esc_html( wp_strip_all_tags( $stax_vl_option['description'] ) ); ?></p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p>
		<a href="<?php echo esc_url( Resources::pro_url( 'conditions' ) ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Compare free and Pro on staxwp.com', 'visibility-logic-elementor' ); ?>
			<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'visibility-logic-elementor' ); ?></span>
		</a>
	</p>
<?php endif; ?>
