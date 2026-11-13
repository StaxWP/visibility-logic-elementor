<?php
/**
 * E2E fixtures: one Elementor page and one block editor page with known elements (no customer data).
 *
 *   wp eval-file tests/e2e/setup.php            prints: elementor=<id> blocks=<id>
 *   wp eval-file tests/e2e/setup.php cleanup    deletes the pages
 *
 * @package Stax\VisibilityLogic
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput

$vle2e_ids = (array) get_option( 'stax_vl_e2e_pages', [] );

if ( isset( $args[0] ) && 'cleanup' === $args[0] ) {
	foreach ( $vle2e_ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	delete_option( 'stax_vl_e2e_pages' );
	echo "cleaned\n";
	return;
}

foreach ( $vle2e_ids as $id ) {
	wp_delete_post( (int) $id, true );
}

$heading = static function ( $id, $title, array $visibility = [] ) {
	$settings = [ 'title' => $title, 'header_size' => 'h2' ];
	foreach ( $visibility as $key => $value ) {
		$settings[ 'stax_visibility_' . $key ] = $value;
	}
	return [ 'id' => $id, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => $settings, 'elements' => [] ];
};

$data = [
	[
		'id'       => 'e2ec1',
		'elType'   => 'container',
		'settings' => [],
		'elements' => [
			$heading( 'e2ew1', 'Plain heading' ),
			$heading( 'e2ew2', 'Members area', [ 'enabled' => 'yes', 'show_hide' => 'yes', 'user_role_enabled' => 'yes', 'user_role_conditions' => [ 'ecl-user' ] ] ),
			$heading( 'e2ew3', 'Summer offer' ),
		],
	],
];

$elementor = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'VL e2e Elementor' ] );
update_post_meta( $elementor, '_elementor_edit_mode', 'builder' );
update_post_meta( $elementor, '_elementor_template_type', 'wp-page' );
update_post_meta( $elementor, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
update_post_meta( $elementor, '_wp_page_template', 'elementor_canvas' );
update_post_meta( $elementor, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

$blocks = wp_insert_post(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'VL e2e blocks',
		'post_content' => "<!-- wp:paragraph -->\n<p>Plain paragraph</p>\n<!-- /wp:paragraph -->\n\n"
			. '<!-- wp:paragraph {"staxVisibility":{"enabled":true,"show_hide":"yes","user_role_enabled":true,"user_role_conditions":["ecl-user"]}} -->' . "\n<p>Members paragraph</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:paragraph -->\n<p>Offer paragraph</p>\n<!-- /wp:paragraph -->",
	]
);

update_option( 'stax_vl_e2e_pages', [ $elementor, $blocks ], false );

if ( class_exists( '\\Stax\\VisibilityLogic\\Rules' ) ) {
	\Stax\VisibilityLogic\Rules::flush();
}

echo "elementor={$elementor} blocks={$blocks}\n";
