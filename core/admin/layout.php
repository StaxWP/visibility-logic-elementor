<?php
/**
 * Panel layout: header, tabs, the current tab and the sidebar.
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

defined( 'ABSPATH' ) || exit;

$stax_vl_classes = array_map( 'sanitize_html_class', array_filter( array_map( 'strval', $wrapper_class ) ) );
?>
<div class="wrap stax-vl stax-main <?php echo esc_attr( implode( ' ', $stax_vl_classes ) ); ?>">
	<div class="stax-vl-header">
		<h1 class="stax-vl-title">
			<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
			<?php esc_html_e( 'Visibility Logic', 'visibility-logic-elementor' ); ?>
			<?php if ( $pro['active'] ) : ?>
				<span class="stax-vl-badge stax-vl-badge--pro"><?php esc_html_e( 'Pro', 'visibility-logic-elementor' ); ?></span>
			<?php endif; ?>
		</h1>
		<p class="stax-vl-version">
			<?php esc_html_e( 'for Elementor and the Block Editor', 'visibility-logic-elementor' ); ?>
			&middot;
			<?php
			/* translators: %s: version number. */
			printf( esc_html__( 'Version %s', 'visibility-logic-elementor' ), esc_html( STAX_VISIBILITY_VERSION ) );
			if ( defined( 'STAX_VISIBILITY_PRO_VERSION' ) ) {
				echo ' &middot; ';
				/* translators: %s: version number. */
				printf( esc_html__( 'Pro %s', 'visibility-logic-elementor' ), esc_html( STAX_VISIBILITY_PRO_VERSION ) );
			}
			?>
		</p>
	</div>

	<?php // Notices from other plugins land here, above the tabs. ?>
	<hr class="wp-header-end">

	<?php if ( ! empty( $menu ) ) : ?>
		<nav class="nav-tab-wrapper stax-vl-tabs" aria-label="<?php esc_attr_e( 'Visibility Logic sections', 'visibility-logic-elementor' ); ?>">
			<?php foreach ( $menu as $stax_vl_item ) : ?>
				<?php $stax_vl_active = $stax_vl_item['query'] === $current_page; ?>
				<a href="<?php echo esc_url( $stax_vl_item['link'] ); ?>"
					class="nav-tab<?php echo $stax_vl_active ? ' nav-tab-active' : ''; ?>"
					<?php echo $stax_vl_active ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( wp_strip_all_tags( $stax_vl_item['name'] ) ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . 'before_panel_action' ); ?>

	<div class="stax-vl-columns">
		<div class="stax-vl-main">
			<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . 'panel_action' ); ?>
		</div>

		<aside class="stax-vl-sidebar" aria-label="<?php esc_attr_e( 'About Visibility Logic', 'visibility-logic-elementor' ); ?>">
			<?php if ( ! $pro['active'] ) : ?>
				<div class="stax-vl-card">
					<h2><?php esc_html_e( 'Visibility Logic Pro', 'visibility-logic-elementor' ); ?></h2>
					<p><?php esc_html_e( 'More conditions for the same Visibility tab:', 'visibility-logic-elementor' ); ?></p>
					<ul class="stax-vl-list">
						<li><?php esc_html_e( 'Posts, pages, archives and conditional tags', 'visibility-logic-elementor' ); ?></li>
						<li><?php esc_html_e( 'URL parameters, IP, referrer and visitor country', 'visibility-logic-elementor' ); ?></li>
						<li><?php esc_html_e( 'WooCommerce and EDD customers, language', 'visibility-logic-elementor' ); ?></li>
						<li><?php esc_html_e( 'Advanced ACF, dynamic tags, fallback content', 'visibility-logic-elementor' ); ?></li>
					</ul>
					<p>
						<a class="button" href="<?php echo esc_url( Resources::pro_url( 'sidebar' ) ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Learn about Pro', 'visibility-logic-elementor' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'visibility-logic-elementor' ); ?></span>
						</a>
					</p>
				</div>
			<?php else : ?>
				<div class="stax-vl-card">
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
							<li><a href="<?php echo esc_url( $pro['manage_url'] ); ?>"><?php esc_html_e( 'Manage license', 'visibility-logic-elementor' ); ?></a></li>
						<?php endif; ?>
						<li><a href="https://staxwp.com/account/" target="_blank" rel="noopener"><?php esc_html_e( 'Your staxwp.com account', 'visibility-logic-elementor' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'visibility-logic-elementor' ); ?></span></a></li>
					</ul>
				</div>
			<?php endif; ?>

			<div class="stax-vl-card">
				<h2><?php esc_html_e( 'Help', 'visibility-logic-elementor' ); ?></h2>
				<p><?php esc_html_e( 'Open any widget, section or container in Elementor, then the Visibility tab.', 'visibility-logic-elementor' ); ?></p>
				<ul class="stax-vl-links">
					<li><a href="https://wordpress.org/support/plugin/visibility-logic-elementor/" target="_blank" rel="noopener"><?php esc_html_e( 'Support forum', 'visibility-logic-elementor' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'visibility-logic-elementor' ); ?></span></a></li>
					<li><a href="https://wordpress.org/support/plugin/visibility-logic-elementor/reviews/#new-post" target="_blank" rel="noopener"><?php esc_html_e( 'Rate the plugin', 'visibility-logic-elementor' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'visibility-logic-elementor' ); ?></span></a></li>
				</ul>
			</div>
		</aside>
	</div>

	<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . 'after_panel_action' ); ?>
</div>
