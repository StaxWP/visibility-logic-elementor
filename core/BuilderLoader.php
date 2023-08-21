<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BuilderLoader extends Singleton {

	/**
	 * Initialize enabled builders
	 *
	 * @return void
	 */
	public function initialize() {
		foreach ( Resources::instance()->get_all_builders() as $builder => $data ) {
			if ( ! $data['status'] ) {
				continue;
			}

			$builder_class = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $builder ) ) ) . 'Builder';

			if ( ! file_exists( STAX_VISIBILITY_CORE_PATH . "builders/{$builder_class}.php" ) ) {
				return;
			}

			require_once STAX_VISIBILITY_CORE_PATH . "builders/{$builder_class}.php";

			$builder_class = "Stax\VisibilityLogic\\$builder_class";
			$builder_class::instance();
		}
	}

	/**
	 * Load builders' settings
	 *
	 * @return void
	 */
	public function load_settings() {
		foreach ( Resources::instance()->get_all_builders() as $builder => $data ) {
			if ( ! $data['status'] ) {
				continue;
			}

			$builder_class = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $builder ) ) ) . 'Builder';

			if ( ! file_exists( STAX_VISIBILITY_CORE_PATH . "builders/{$builder_class}.php" ) ) {
				return;
			}

			$builder_class = "Stax\VisibilityLogic\\$builder_class";
			$builder_class::instance()->load_settings();
		}
	}
}
