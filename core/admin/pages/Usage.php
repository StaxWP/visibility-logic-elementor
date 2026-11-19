<?php
/**
 * Settings > Visibility Logic > Rules (first tab): every element with visibility rules, in plain words, with warnings.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Usage
 */
class Usage extends Base {

	/**
	 * Rows per page of the Rules tab.
	 */
	const PER_PAGE = 50;

	/**
	 * Usage constructor.
	 */
	public function __construct() {
		$this->current_slug = 'usage';

		if ( Resources::is_current_page( $this->current_slug ) ) {
			add_action( STAX_VISIBILITY_HOOK_PREFIX . $this->current_slug . '_page_content', [ $this, 'panel_content' ] );
		}

		add_filter( STAX_VISIBILITY_HOOK_PREFIX . 'admin_menu', [ $this, 'add_menu_item' ] );
		add_action( 'admin_post_stax_visibility_rescan', [ $this, 'rescan' ] );
	}

	/**
	 * Rebuild the index.
	 *
	 * @return void
	 */
	public function rescan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'visibility-logic-elementor' ), 403 );
		}

		check_admin_referer( 'stax_visibility_rescan' );

		Rules::flush();

		wp_safe_redirect( Settings::url( $this->current_slug ) );
		exit;
	}

	/**
	 * Panel content.
	 */
	public function panel_content() {
		// Filters (read only, no nonce needed: they only narrow the list).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filters = [
			'editor'    => isset( $_GET['editor'] ) ? sanitize_key( wp_unslash( $_GET['editor'] ) ) : '',
			'condition' => isset( $_GET['condition'] ) ? sanitize_key( wp_unslash( $_GET['condition'] ) ) : '',
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'q'         => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
		];
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$index = Rules::index();
		$rows  = Rules::overview_rows( $index, $filters );
		$pages = max( 1, (int) ceil( count( $rows ) / self::PER_PAGE ) );
		$page  = min( $page, $pages );

		Resources::load_template(
			'core/admin/pages/templates/usage',
			[
				'index'   => $index,
				'rows'    => array_slice( $rows, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ),
				'matched' => count( $rows ),
				'paging'  => [
					'page'     => $page,
					'pages'    => $pages,
					'per_page' => self::PER_PAGE,
				],
				'filters' => $filters,
				'caches'  => Cache_Check::detect(),
			]
		);
	}

	/**
	 * Add menu item.
	 *
	 * @param array $menu Tabs.
	 *
	 * @return array
	 */
	public function add_menu_item( $menu ) {
		$menu[] = [
			'name'     => __( 'Rules', 'visibility-logic-elementor' ),
			'link'     => Settings::url( $this->current_slug ),
			'query'    => STAX_VISIBILITY_SLUG_PREFIX . $this->current_slug,
			'priority' => 1,
		];

		return $menu;
	}
}

Usage::instance();
