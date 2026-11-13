<?php
/**
 * Pro features shown in the free plugin: one helper for every "PRO" mark (Elementor panel, block editor, atomic
 * elements), so the wording, the link and the rules stay in one place.
 *
 * Rules (wordpress.org guidelines): free features are never locked; Pro options are listed after the free ones,
 * disabled, with "(Pro)" in their label, so they cannot be picked or saved and never run; one discreet "More
 * conditions in Pro" line at the end of the Visibility tab carries the only link. In wp-admin, one small line under
 * the Visibility Logic tabs (never on other screens), dismissible for good per user. Nothing is shown when Pro is
 * active.
 *
 * Feature states:
 * - `on`: Visibility Logic Pro is active and provides the feature (`stax/visibility/pro_features` filter).
 * - `teaser`: Pro is not active: options listed disabled with "(Pro)".
 * - `off`: Pro is active but does not provide the feature (an older Pro, or no license): options not listed.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pro_Teaser
 */
class Pro_Teaser {

	const ON     = 'on';
	const TEASER = 'teaser';
	const OFF    = 'off';

	/** User option: the admin "More conditions in Pro" line was dismissed (for good). */
	const DISMISSED = 'stax_vl_pro_line_dismissed';
	/** admin-post action that dismisses the admin line. */
	const DISMISS_ACTION = 'stax_vl_pro_line_dismiss';

	/**
	 * State of a Pro feature (see the class description).
	 *
	 * @param string $feature Feature id: acf_sources, acf_operators, acf_rules, user_meta_rules.
	 *
	 * @return string on|teaser|off
	 */
	public static function state( $feature ) {
		if ( ! self::pro_loaded() ) {
			return self::TEASER;
		}

		$features = (array) apply_filters( 'stax/visibility/pro_features', [] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.

		return in_array( $feature, $features, true ) ? self::ON : self::OFF;
	}

	/**
	 * Whether Pro marks are shown at all (Pro not active).
	 *
	 * @return bool
	 */
	public static function visible() {
		return ! self::pro_loaded();
	}

	/**
	 * Whether Visibility Logic Pro is loaded in this request (its plugin file defines the constant).
	 *
	 * @return bool
	 */
	private static function pro_loaded() {
		return defined( 'STAX_VISIBILITY_PRO_VERSION' );
	}

	/**
	 * Label of a Pro option shown in the free plugin: "Label (Pro)".
	 *
	 * @param string $label Label.
	 *
	 * @return string
	 */
	public static function label( $label ) {
		/* translators: %s: name of an option that needs Visibility Logic Pro. */
		return sprintf( __( '%s (Pro)', 'visibility-logic-elementor' ), $label );
	}

	/**
	 * Options of a select: the free ones, then the Pro ones by feature state.
	 *
	 * @param array  $free    Free options (value => label).
	 * @param array  $pro     Pro options (value => label).
	 * @param string $feature Feature id.
	 *
	 * @return array [ options (value => label), locked values ]
	 */
	public static function options( array $free, array $pro, $feature ) {
		$state = self::state( $feature );

		if ( self::ON === $state ) {
			return [ $free + $pro, [] ];
		}

		if ( self::OFF === $state ) {
			return [ $free, [] ];
		}

		$options = $free;

		foreach ( $pro as $value => $label ) {
			$options[ $value ] = self::label( $label );
		}

		return [ $options, array_map( 'strval', array_keys( $pro ) ) ];
	}

	/**
	 * Visibility Logic Pro page (pricing) with UTM parameters.
	 *
	 * @param string $context Where the link is shown.
	 *
	 * @return string
	 */
	public static function url( $context ) {
		return Resources::pro_url( $context );
	}

	/**
	 * Text of the "More conditions in Pro" line.
	 *
	 * @return string
	 */
	public static function more_text() {
		return __( 'ACF fields of users, terms and options pages, AND/OR rule groups, posts and pages, archives, WooCommerce and EDD customers, location, language, URL parameters and fallback content.', 'visibility-logic-elementor' );
	}

	/**
	 * Title of the "More conditions in Pro" line (the Elementor section label; the start of the block editor line).
	 *
	 * @return string
	 */
	public static function more_title() {
		return __( 'More conditions in Pro', 'visibility-logic-elementor' );
	}

	/**
	 * Label of the link.
	 *
	 * @return string
	 */
	public static function link_label() {
		return __( 'Learn more', 'visibility-logic-elementor' );
	}

	/**
	 * Accessible name of the PRO mark.
	 *
	 * @return string
	 */
	public static function badge_label() {
		return __( 'Pro feature', 'visibility-logic-elementor' );
	}

	/**
	 * The PRO mark (muted pill, text "PRO", accessible name "Pro feature").
	 *
	 * @return string
	 */
	public static function badge_html() {
		return '<span class="stax-vl-pro" role="img" aria-label="' . esc_attr( self::badge_label() ) . '">' . esc_html__( 'PRO', 'visibility-logic-elementor' ) . '</span>';
	}

	/**
	 * The "More conditions in Pro" line: mark, text, one link.
	 *
	 * @param string $context Where the line is shown (UTM medium).
	 *
	 * @return string
	 */
	public static function more_html( $context ) {
		return '<p class="stax-vl-pro-more">' . self::badge_html() . ' ' . esc_html( self::more_text() ) . ' '
			. '<a href="' . esc_url( self::url( $context ) ) . '" target="_blank" rel="noopener">' . esc_html( self::link_label() ) . '</a></p>';
	}

	/**
	 * One small line in wp-admin: PRO mark, text, links separated by middots. Shared by the free plugin's
	 * "More conditions in Pro" line and Visibility Logic Pro's license line (same look on every Visibility Logic tab).
	 *
	 * @param string $text  Text (plain).
	 * @param array  $links Links: [ label, url, external (bool) ].
	 * @param string $id    Optional id attribute (tests and dismiss targets).
	 *
	 * @return string
	 */
	public static function line_html( $text, array $links, $id = '' ) {
		$parts = [];

		foreach ( $links as $link ) {
			if ( ! is_array( $link ) || count( $link ) < 2 ) {
				continue;
			}

			$external = ! empty( $link[2] );
			$parts[]  = '<a href="' . esc_url( $link[1] ) . '"' . ( $external ? ' target="_blank" rel="noopener"' : '' ) . '>'
				. esc_html( $link[0] )
				. ( $external ? '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'visibility-logic-elementor' ) . '</span>' : '' )
				. '</a>';
		}

		return '<p class="stax-vl-pro-line"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>'
			. self::badge_html() . ' ' . esc_html( $text )
			. ( $parts ? ' ' . implode( ' &middot; ', $parts ) : '' )
			. '</p>';
	}

	/**
	 * URL that dismisses a line for the current user (admin-post, nonce).
	 *
	 * @param string $action admin-post action (also the nonce action).
	 *
	 * @return string
	 */
	public static function dismiss_url( $action ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action );
	}

	/**
	 * The admin "More conditions in Pro" line (Visibility Logic screens): empty when Pro is active, for users who
	 * cannot manage options and once dismissed.
	 *
	 * @return string
	 */
	public static function admin_line_html() {
		if ( ! self::visible() || ! current_user_can( 'manage_options' ) || get_user_option( self::DISMISSED ) ) {
			return '';
		}

		return self::line_html(
			__( 'More conditions in Pro: posts and archives, WooCommerce and EDD customers, location, language and URL parameters.', 'visibility-logic-elementor' ),
			[
				[ self::link_label(), self::url( 'panel-line' ), true ],
				[ __( 'Dismiss', 'visibility-logic-elementor' ), self::dismiss_url( self::DISMISS_ACTION ), false ],
			],
			'stax-vl-pro-line'
		);
	}

	/**
	 * Dismisses the admin line for good (admin-post handler).
	 */
	public static function dismiss() {
		check_admin_referer( self::DISMISS_ACTION );

		if ( current_user_can( 'manage_options' ) ) {
			update_user_option( get_current_user_id(), self::DISMISSED, time() );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Data for the block editor script (one component renders the same mark and line).
	 *
	 * @return array|null Null when Pro is active.
	 */
	public static function for_js() {
		if ( ! self::visible() ) {
			return null;
		}

		return [
			'badge' => __( 'PRO', 'visibility-logic-elementor' ),
			'aria'  => self::badge_label(),
			'title' => self::more_title(),
			'text'  => self::more_text(),
			'link'  => self::link_label(),
			'url'   => self::url( 'block-editor' ),
		];
	}
}
