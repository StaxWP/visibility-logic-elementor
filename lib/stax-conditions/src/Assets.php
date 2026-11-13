<?php
/**
 * Script and style of the rule builder, registered once from the copy in use (the highest bundled version).
 *
 * Handles: script `stax-conditions` (window.StaxConditions), style `stax-conditions`.
 *
 * @package StaxWP\Conditions
 */

namespace StaxWP\Conditions\V1;

/**
 * Class Assets
 */
class Assets {

	/**
	 * Script/style handle.
	 */
	const HANDLE = 'stax-conditions';

	/**
	 * Register the handles (safe to call from every plugin; the first call wins, and it always uses the copy in use).
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_script' ) || wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		$copy = stax_conditions_v1();

		if ( ! $copy ) {
			return;
		}

		$base = self::url( $copy['dir'] );

		wp_register_script( self::HANDLE, $base . '/js/dist/stax-conditions.js', [], $copy['version'], true );
		wp_register_style( self::HANDLE, $base . '/css/builder.css', [], $copy['version'] );
	}

	/**
	 * Version in use.
	 *
	 * @return string
	 */
	public static function version() {
		$copy = stax_conditions_v1();

		return $copy ? $copy['version'] : '';
	}

	/**
	 * URL of a copy's folder (plugins and mu-plugins, symlinked plugin folders included).
	 *
	 * @param string $dir Folder.
	 *
	 * @return string
	 */
	public static function url( $dir ) {
		$url = (string) apply_filters( 'stax_conditions_v1_url', '', $dir );

		if ( '' !== $url ) {
			return untrailingslashit( $url );
		}

		return untrailingslashit( plugins_url( '', $dir . '/load.php' ) );
	}
}
