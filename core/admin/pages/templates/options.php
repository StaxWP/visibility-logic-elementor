<?php
/**
 * Conditions tab: turn conditions on or off (grouped cards), Pro conditions locked when Pro is missing.
 *
 * @package Stax\VisibilityLogic
 *
 * @var array $options  All conditions.
 * @var array $included Conditions available on this site (free, and Pro when active).
 * @var array $locked   Pro conditions (Pro not active).
 * @var bool  $has_pro  Pro active.
 * @var bool  $saved    Just saved.
 */

use Stax\VisibilityLogic\Resources;
use Stax\VisibilityLogic\Settings;

defined( 'ABSPATH' ) || exit;

// Group and icon of each condition; conditions from add-ons go to "More".
$stax_vl_groups = [
	'visitor' => [
		'label' => __( 'Visitor', 'visibility-logic-elementor' ),
		'items' => [
			'user-role'          => 'user',
			'user-meta'          => 'db',
			'advanced-user-meta' => 'db',
			'device-type'        => 'phone',
			'browser-type'       => 'globe',
			'geo-location'       => 'globe',
			'ip-referrer'        => 'link',
			'language'           => 'globe',
			'woocommerce-users'  => 'cart',
			'edd-users'          => 'cart',
		],
	],
	'content' => [
		'label' => __( 'Time and content', 'visibility-logic-elementor' ),
		'items' => [
			'date-time'          => 'clock',
			'page'               => 'file',
			'post-page'          => 'file',
			'archive'            => 'file',
			'content-details'    => 'file',
			'acf'                => 'stack',
			'advanced-acf'       => 'stack',
			'conditional-tags'   => 'code',
			'dynamic-conditions' => 'code',
			'query-string'       => 'link',
			'shop'               => 'cart',
		],
	],
	'display' => [
		'label' => __( 'Display', 'visibility-logic-elementor' ),
		'items' => [
			'fallback' => 'eye',
			'split'    => 'shuffle',
			'view-cap' => 'eye',
		],
	],
	'more'    => [
		'label' => __( 'More', 'visibility-logic-elementor' ),
		'items' => [],
	],
];

$stax_vl_icons = [];
foreach ( $stax_vl_groups as $stax_vl_key => $stax_vl_group ) {
	foreach ( $stax_vl_group['items'] as $stax_vl_slug => $stax_vl_icon ) {
		$stax_vl_icons[ $stax_vl_slug ] = [ $stax_vl_key, $stax_vl_icon ];
	}
}

$stax_vl_sorted = [];
foreach ( $included as $stax_vl_slug => $stax_vl_option ) {
	$stax_vl_group                                 = isset( $stax_vl_icons[ $stax_vl_slug ] ) ? $stax_vl_icons[ $stax_vl_slug ][0] : 'more';
	$stax_vl_sorted[ $stax_vl_group ][ $stax_vl_slug ] = $stax_vl_option;
}
?>
<div class="stax-vl-title">
	<div>
		<h2><?php esc_html_e( 'Conditions', 'visibility-logic-elementor' ); ?></h2>
		<p><?php esc_html_e( 'Turn off the ones you don\'t use: they leave the editors and are not checked on your pages, so their saved rules have no effect.', 'visibility-logic-elementor' ); ?></p>
	</div>
	<label class="stax-vl-search">
		<?php Settings::icon( 'search' ); ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Find a condition', 'visibility-logic-elementor' ); ?></span>
		<input type="search" data-stax-vl-search="#stax-vl-conditions-form" placeholder="<?php esc_attr_e( 'Find a condition', 'visibility-logic-elementor' ); ?>">
	</label>
</div>

<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible inline"><p><?php esc_html_e( 'Settings saved.', 'visibility-logic-elementor' ); ?></p></div>
<?php endif; ?>

<form class="stax-vl-card" id="stax-vl-conditions-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-stax-vl-dirty>
	<input type="hidden" name="action" value="stax_visibility_options_activation">
	<input type="hidden" name="section" value="conditions">
	<?php wp_nonce_field( 'stax_visibility_options_activation' ); ?>

	<div class="stax-vl-card__body">
		<?php foreach ( $stax_vl_groups as $stax_vl_key => $stax_vl_group ) : ?>
			<?php
			$stax_vl_items  = isset( $stax_vl_sorted[ $stax_vl_key ] ) ? $stax_vl_sorted[ $stax_vl_key ] : [];
			$stax_vl_legend = 'stax-vl-group-' . $stax_vl_key;
			if ( ! $stax_vl_items ) {
				continue;
			}
			?>
			<fieldset class="stax-vl-group" aria-labelledby="<?php echo esc_attr( $stax_vl_legend ); ?>">
				<h3 id="<?php echo esc_attr( $stax_vl_legend ); ?>"><?php echo esc_html( $stax_vl_group['label'] ); ?></h3>
				<div class="stax-vl-conditions">
					<?php foreach ( $stax_vl_items as $stax_vl_slug => $stax_vl_option ) : ?>
						<?php
						$stax_vl_id      = 'stax-vl-condition-' . sanitize_html_class( $stax_vl_slug );
						$stax_vl_require = isset( $stax_vl_option['require']['text']['plugins'] ) ? [ $stax_vl_option['require']['text']['plugins'] ] : [];
						$stax_vl_blocked = ! empty( $stax_vl_option['require'] );
						$stax_vl_on      = ! empty( $stax_vl_option['status'] );
						$stax_vl_icon    = isset( $stax_vl_icons[ $stax_vl_slug ] ) ? $stax_vl_icons[ $stax_vl_slug ][1] : 'box';
						$stax_vl_classes = 'stax-vl-condition' . ( $stax_vl_blocked ? ' is-blocked' : ( $stax_vl_on ? '' : ' is-off' ) );
						?>
						<label class="<?php echo esc_attr( $stax_vl_classes ); ?>" for="<?php echo esc_attr( $stax_vl_id ); ?>" data-stax-vl-item>
							<span class="stax-vl-condition__icon"><?php Settings::icon( $stax_vl_icon ); ?></span>
							<span class="stax-vl-condition__text">
								<span class="stax-vl-condition__name">
									<?php echo esc_html( wp_strip_all_tags( $stax_vl_option['name'] ) ); ?>
									<?php if ( ! Resources::is_free_condition( $stax_vl_slug ) ) : ?>
										<span class="stax-vl-badge stax-vl-badge--pro"><?php esc_html_e( 'Pro', 'visibility-logic-elementor' ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( ! empty( $stax_vl_option['description'] ) ) : ?>
									<span class="stax-vl-condition__desc"><?php echo wp_kses_post( $stax_vl_option['description'] ); ?></span>
								<?php endif; ?>
								<?php foreach ( (array) $stax_vl_require as $stax_vl_text ) : ?>
									<span class="stax-vl-condition__req"><?php echo wp_kses_post( $stax_vl_text ); ?></span>
								<?php endforeach; ?>
							</span>
							<?php if ( $stax_vl_blocked ) : ?>
								<input type="checkbox" class="stax-vl-switch" id="<?php echo esc_attr( $stax_vl_id ); ?>" disabled>
							<?php else : ?>
								<input type="checkbox" class="stax-vl-switch" name="<?php echo esc_attr( $stax_vl_slug ); ?>" id="<?php echo esc_attr( $stax_vl_id ); ?>" value="1" <?php checked( $stax_vl_on ); ?>>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>

		<?php if ( ! empty( $locked ) ) : ?>
			<div class="stax-vl-group">
				<h3>
					<?php esc_html_e( 'More in Pro', 'visibility-logic-elementor' ); ?>
					<a href="<?php echo esc_url( Resources::pro_url( 'conditions' ) ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Compare free and Pro', 'visibility-logic-elementor' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'visibility-logic-elementor' ); ?></span>
					</a>
				</h3>
				<ul class="stax-vl-conditions">
					<?php foreach ( $locked as $stax_vl_slug => $stax_vl_option ) : ?>
						<li class="stax-vl-condition is-locked" data-stax-vl-item>
							<span class="stax-vl-condition__icon"><?php Settings::icon( isset( $stax_vl_icons[ $stax_vl_slug ] ) ? $stax_vl_icons[ $stax_vl_slug ][1] : 'box' ); ?></span>
							<span class="stax-vl-condition__text">
								<span class="stax-vl-condition__name">
									<?php echo esc_html( wp_strip_all_tags( $stax_vl_option['name'] ) ); ?>
									<span class="stax-vl-badge stax-vl-badge--pro"><?php esc_html_e( 'Pro', 'visibility-logic-elementor' ); ?></span>
								</span>
								<?php if ( ! empty( $stax_vl_option['description'] ) ) : ?>
									<span class="stax-vl-condition__desc"><?php echo esc_html( wp_strip_all_tags( $stax_vl_option['description'] ) ); ?></span>
								<?php endif; ?>
							</span>
							<?php Settings::icon( 'lock' ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>

	<?php Resources::load_template( 'core/admin/pages/templates/savebar' ); ?>
</form>
