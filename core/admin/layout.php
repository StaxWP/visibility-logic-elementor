<?php
/**
 * Panel layout: app header (brand, version, Pro status, quick links), tabs, the current tab and the sidebar.
 *
 * WordPress notices stay outside the app: core moves them after .wp-header-end, above the header.
 *
 * @package Stax\VisibilityLogic
 *
 * @var string $site_url      Pro page URL.
 * @var array  $wrapper_class Wrapper classes.
 * @var array  $menu          Tabs.
 * @var string $current_page  Current page slug.
 * @var array  $pro           Pro status (active, licensed, label, manage_url).
 */

use Stax\VisibilityLogic\Resources;
use Stax\VisibilityLogic\Settings;

defined( 'ABSPATH' ) || exit;

$stax_vl_classes   = array_map( 'sanitize_html_class', array_filter( array_map( 'strval', $wrapper_class ) ) );
$stax_vl_attention = Settings::attention_count();
$stax_vl_new_tab   = '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'visibility-logic-elementor' ) . '</span>';

if ( ! $pro['active'] ) {
	$stax_vl_pill = [ 'free', '', __( 'Free', 'visibility-logic-elementor' ) ];
} elseif ( true === $pro['licensed'] ) {
	$stax_vl_pill = [ 'ok', 'check', __( 'Pro, licensed', 'visibility-logic-elementor' ) ];
} elseif ( false === $pro['licensed'] ) {
	$stax_vl_pill = [ 'warn', 'lock', __( 'Pro, no license', 'visibility-logic-elementor' ) ];
} else {
	$stax_vl_pill = [ 'ok', 'check', __( 'Pro', 'visibility-logic-elementor' ) ];
}
?>
<div class="wrap stax-vl-wrap">
	<h1 class="screen-reader-text"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<?php // Notices from WordPress and other plugins land here, above the app. ?>
	<hr class="wp-header-end">

	<div class="stax-vl stax-main <?php echo esc_attr( implode( ' ', $stax_vl_classes ) ); ?>">
		<header class="stax-vl-header">
			<div class="stax-vl-header__bar">
				<div class="stax-vl-brand">
					<span class="stax-vl-brand__logo"><?php Settings::icon( 'eye' ); ?></span>
					<div>
						<p class="stax-vl-brand__name"><?php esc_html_e( 'Visibility Logic', 'visibility-logic-elementor' ); ?></p>
						<p class="stax-vl-brand__meta">
							<span>
								<?php
								/* translators: %s: version number. */
								printf( esc_html__( 'Version %s', 'visibility-logic-elementor' ), esc_html( STAX_VISIBILITY_VERSION ) );
								if ( defined( 'STAX_VISIBILITY_PRO_VERSION' ) ) {
									echo ' &middot; ';
									/* translators: %s: version number. */
									printf( esc_html__( 'Pro %s', 'visibility-logic-elementor' ), esc_html( STAX_VISIBILITY_PRO_VERSION ) );
								}
								?>
							</span>
							<span class="stax-vl-pill stax-vl-pill--<?php echo esc_attr( $stax_vl_pill[0] ); ?>">
								<?php if ( $stax_vl_pill[1] ) : ?>
									<?php Settings::icon( $stax_vl_pill[1] ); ?>
								<?php endif; ?>
								<?php echo esc_html( $stax_vl_pill[2] ); ?>
							</span>
						</p>
					</div>
				</div>
				<ul class="stax-vl-quick">
					<li>
						<a class="stax-vl-btn stax-vl-btn--ghost stax-vl-btn--sm" href="https://staxwp.com/docs/visibility-logic/" target="_blank" rel="noopener">
							<?php Settings::icon( 'book' ); ?><?php esc_html_e( 'Docs', 'visibility-logic-elementor' ); ?><?php echo $stax_vl_new_tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
						</a>
					</li>
					<li>
						<a class="stax-vl-btn stax-vl-btn--ghost stax-vl-btn--sm" href="https://wordpress.org/support/plugin/visibility-logic-elementor/" target="_blank" rel="noopener">
							<?php Settings::icon( 'chat' ); ?><?php esc_html_e( 'Support', 'visibility-logic-elementor' ); ?><?php echo $stax_vl_new_tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
						</a>
					</li>
					<?php if ( ! $pro['active'] ) : ?>
						<li>
							<a class="stax-vl-btn stax-vl-btn--primary stax-vl-btn--sm" href="<?php echo esc_url( Resources::pro_url( 'header' ) ); ?>" target="_blank" rel="noopener">
								<?php Settings::icon( 'zap' ); ?><?php esc_html_e( 'Get Pro', 'visibility-logic-elementor' ); ?><?php echo $stax_vl_new_tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
							</a>
						</li>
					<?php endif; ?>
				</ul>
			</div>

			<?php if ( ! empty( $menu ) ) : ?>
				<nav class="stax-vl-nav" aria-label="<?php esc_attr_e( 'Visibility Logic sections', 'visibility-logic-elementor' ); ?>">
					<?php foreach ( $menu as $stax_vl_item ) : ?>
						<?php $stax_vl_active = $stax_vl_item['query'] === $current_page; ?>
						<a href="<?php echo esc_url( $stax_vl_item['link'] ); ?>"
							class="<?php echo $stax_vl_active ? 'is-active' : ''; ?>"
							<?php echo $stax_vl_active ? 'aria-current="page"' : ''; ?>>
							<?php Settings::icon( Settings::tab_icon( $stax_vl_item ) ); ?>
							<?php echo esc_html( wp_strip_all_tags( $stax_vl_item['name'] ) ); ?>
							<?php if ( Settings::RULES_PAGE === $stax_vl_item['query'] && $stax_vl_attention ) : ?>
								<span class="stax-vl-nav__count">
									<?php echo esc_html( number_format_i18n( $stax_vl_attention ) ); ?>
									<span class="screen-reader-text"><?php esc_html_e( 'need attention', 'visibility-logic-elementor' ); ?></span>
								</span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</header>

		<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . 'before_panel_action' ); ?>

		<div class="stax-vl-columns">
			<div class="stax-vl-main">
				<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . 'panel_action' ); ?>
			</div>

			<aside class="stax-vl-sidebar" aria-label="<?php esc_attr_e( 'About Visibility Logic', 'visibility-logic-elementor' ); ?>">
				<?php if ( ! $pro['active'] ) : ?>
					<div class="stax-vl-pro-card">
						<h2><?php esc_html_e( 'Visibility Logic Pro', 'visibility-logic-elementor' ); ?></h2>
						<p><?php esc_html_e( 'More conditions in the same Visibility tab.', 'visibility-logic-elementor' ); ?></p>
						<ul>
							<li><?php Settings::icon( 'check' ); ?><?php esc_html_e( 'WooCommerce and EDD customers, shop cart', 'visibility-logic-elementor' ); ?></li>
							<li><?php Settings::icon( 'check' ); ?><?php esc_html_e( 'Visitor country, URL parameters, IP and referrer', 'visibility-logic-elementor' ); ?></li>
							<li><?php Settings::icon( 'check' ); ?><?php esc_html_e( 'Posts, archives, language, dynamic tags', 'visibility-logic-elementor' ); ?></li>
							<li><?php Settings::icon( 'check' ); ?><?php esc_html_e( 'Page redirects, reveal on click, view limits, popups', 'visibility-logic-elementor' ); ?></li>
						</ul>
						<a class="stax-vl-btn" href="<?php echo esc_url( Resources::pro_url( 'sidebar' ) ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'See Pro plans', 'visibility-logic-elementor' ); ?><?php echo $stax_vl_new_tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
						</a>
					</div>
				<?php else : ?>
					<div class="stax-vl-card stax-vl-side-card">
						<h2><?php esc_html_e( 'Visibility Logic Pro', 'visibility-logic-elementor' ); ?></h2>
						<?php if ( '' !== (string) $pro['label'] ) : ?>
							<p><?php echo esc_html( $pro['label'] ); ?></p>
						<?php elseif ( true === $pro['licensed'] ) : ?>
							<p class="stax-vl-status stax-vl-status--ok"><?php esc_html_e( 'Active and licensed. Thank you for supporting the plugin.', 'visibility-logic-elementor' ); ?></p>
						<?php elseif ( false === $pro['licensed'] ) : ?>
							<p class="stax-vl-status stax-vl-status--warn"><?php esc_html_e( 'Active, no license yet. Saved Pro conditions keep working; editing them needs a license.', 'visibility-logic-elementor' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Active.', 'visibility-logic-elementor' ); ?></p>
						<?php endif; ?>
						<ul class="stax-vl-links">
							<?php if ( '' !== (string) $pro['manage_url'] ) : ?>
								<li><a href="<?php echo esc_url( $pro['manage_url'] ); ?>"><?php Settings::icon( 'key' ); ?><?php esc_html_e( 'Manage license', 'visibility-logic-elementor' ); ?></a></li>
							<?php endif; ?>
							<li><a href="https://staxwp.com/account/" target="_blank" rel="noopener"><?php Settings::icon( 'user' ); ?><?php esc_html_e( 'Your staxwp.com account', 'visibility-logic-elementor' ); ?><?php echo $stax_vl_new_tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></a></li>
						</ul>
					</div>
				<?php endif; ?>

				<div class="stax-vl-card stax-vl-side-card">
					<h2><?php esc_html_e( 'Get started', 'visibility-logic-elementor' ); ?></h2>
					<ol class="stax-vl-steps">
						<li><?php esc_html_e( 'Open a widget, section, container or block.', 'visibility-logic-elementor' ); ?></li>
						<li><?php esc_html_e( 'Go to its Visibility tab (Elementor) or Visibility rules panel (blocks).', 'visibility-logic-elementor' ); ?></li>
						<li><?php esc_html_e( 'Add a condition, then check it with "Preview as".', 'visibility-logic-elementor' ); ?></li>
					</ol>
				</div>

				<div class="stax-vl-card stax-vl-side-card">
					<h2><?php esc_html_e( 'Help', 'visibility-logic-elementor' ); ?></h2>
					<ul class="stax-vl-links">
						<li><a href="https://staxwp.com/docs/visibility-logic/" target="_blank" rel="noopener"><?php Settings::icon( 'book' ); ?><?php esc_html_e( 'Documentation', 'visibility-logic-elementor' ); ?><?php echo $stax_vl_new_tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></a></li>
						<li><a href="https://wordpress.org/support/plugin/visibility-logic-elementor/" target="_blank" rel="noopener"><?php Settings::icon( 'chat' ); ?><?php esc_html_e( 'Support forum', 'visibility-logic-elementor' ); ?><?php echo $stax_vl_new_tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></a></li>
						<li><a href="https://wordpress.org/support/plugin/visibility-logic-elementor/reviews/#new-post" target="_blank" rel="noopener"><?php Settings::icon( 'star' ); ?><?php esc_html_e( 'Rate the plugin', 'visibility-logic-elementor' ); ?><?php echo $stax_vl_new_tab; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></a></li>
					</ul>
				</div>
			</aside>
		</div>

		<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . 'after_panel_action' ); ?>
	</div>
</div>
