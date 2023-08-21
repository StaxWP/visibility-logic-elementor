<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin extends Singleton {

	/**
	 * Plugin constructor
	 */
	public function __construct() {
		do_action( 'stax/visibility/pre_init' );

		require_once STAX_VISIBILITY_CORE_HELPERS_PATH . 'Resources.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'BuilderLoader.php';

		// load builders.
		BuilderLoader::instance()->initialize();

		require_once STAX_VISIBILITY_CORE_PATH . 'Upgrades.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Base.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Builders.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/pages/Options.php';
		require_once STAX_VISIBILITY_CORE_PATH . 'admin/Settings.php';

		// load builders' settings.
		BuilderLoader::instance()->load_settings();

		do_action( 'stax/visibility/after/load_settings' );

		do_action( 'stax/visibility/after_init' );
	}
}

Plugin::instance();
