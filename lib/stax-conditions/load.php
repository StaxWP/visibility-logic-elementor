<?php
/**
 * StaxWP Conditions: version-safe loader.
 *
 * Every plugin that bundles this package requires this file. Each copy registers its version and folder; the first
 * use of a `StaxWP\Conditions\V1\*` class loads the classes of the HIGHEST registered 1.x copy, so several plugins
 * (Visibility Logic, Visibility Logic Pro, BuddyBuilder Pro, Stax Builder Pro) can bundle different 1.x versions
 * safely. A future 2.x lives in the `V2` namespace and loads next to 1.x.
 *
 * Contract (frozen for 1.x): the function names below and the global `$GLOBALS['stax_conditions_v1']` never change
 * behaviour, because the copy that defines them first may be the oldest one on the site.
 *
 * @package StaxWP\Conditions
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'STAX_CONDITIONS_TESTS' ) ) {
	exit;
}

if ( ! function_exists( 'stax_conditions_v1_register' ) ) {
	/**
	 * Register a bundled copy.
	 *
	 * @param string $version Version of the copy (semver).
	 * @param string $dir     Folder of the copy (the folder of this file).
	 *
	 * @return void
	 */
	function stax_conditions_v1_register( $version, $dir ) {
		if ( ! isset( $GLOBALS['stax_conditions_v1'] ) || ! is_array( $GLOBALS['stax_conditions_v1'] ) ) {
			$GLOBALS['stax_conditions_v1'] = [
				'copies' => [],
				'loaded' => null,
			];

			spl_autoload_register( 'stax_conditions_v1_autoload' );
		}

		$GLOBALS['stax_conditions_v1']['copies'][ (string) $version ] = rtrim( (string) $dir, '/\\' );
	}

	/**
	 * The copy in use: the highest registered version, fixed at the first call.
	 *
	 * @return array{version:string,dir:string}|null
	 */
	function stax_conditions_v1() {
		if ( empty( $GLOBALS['stax_conditions_v1']['copies'] ) ) {
			return null;
		}

		if ( null === $GLOBALS['stax_conditions_v1']['loaded'] ) {
			$versions = array_keys( $GLOBALS['stax_conditions_v1']['copies'] );
			usort( $versions, 'version_compare' );
			$version = (string) end( $versions );

			$GLOBALS['stax_conditions_v1']['loaded'] = [
				'version' => $version,
				'dir'     => $GLOBALS['stax_conditions_v1']['copies'][ $version ],
			];
		}

		return $GLOBALS['stax_conditions_v1']['loaded'];
	}

	/**
	 * Autoloader of `StaxWP\Conditions\V1\*` from the copy in use.
	 *
	 * @param string $class Class name.
	 *
	 * @return void
	 */
	function stax_conditions_v1_autoload( $class ) {
		$prefix = 'StaxWP\\Conditions\\V1\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$copy = stax_conditions_v1();
		$file = $copy ? $copy['dir'] . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php' : '';

		if ( '' !== $file && is_readable( $file ) ) {
			require_once $file;
		}
	}
}

stax_conditions_v1_register( '1.0.0', __DIR__ );
