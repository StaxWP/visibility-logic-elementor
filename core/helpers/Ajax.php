<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Ajax {

	public $query_control;

	/**
	 * Ajax constructor
	 */
	public function __construct() {
		$this->init();
	}

	public function init() {
		include_once STAX_VISIBILITY_CORE_PATH . '/helpers/modules/QueryControl.php';
		$this->query_control = new \Stax\VisibilityLogic\Modules\QueryControl();
	}

}
