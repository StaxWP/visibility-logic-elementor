<?php

namespace Stax\VisibilityLogic\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Stax\VisibilityLogic\Plugin;
use Elementor\Core\Base\Module;

class QueryControl extends Module {

	/**
	 * Module constructor.
	 *
	 * @param array $args
	 */
	public function __construct() {
		$this->add_actions();
	}

	/**
	 * Get the name of the module
	 *
	 * @return string
	 */
	public function get_name() {
		return 'stax-query-control';
	}

	/**
	 * Registeres actions to Elementor hooks
	 *
	 * @return void
	 */
	protected function add_actions() {
		add_action( 'elementor/ajax/register_actions', [ $this, 'register_ajax_actions' ] );
	}

	/**
	 * Register ajax actions
	 *
	 * @param [type] $ajax_manager
	 * @return void
	 */
	public function register_ajax_actions( $ajax_manager ) {
		$ajax_manager->register_ajax_action( 'stax_query_control_value_titles', [ $this, 'ajax_call_control_value_titles' ] );
		$ajax_manager->register_ajax_action( 'stax_query_control_filter_autocomplete', [ $this, 'ajax_call_filter_autocomplete' ] );
	}

	/**
	 * Call filter autocomplete
	 *
	 * @param array $data
	 * @return array
	 */
	public function ajax_call_filter_autocomplete( array $data ) {
		if ( empty( $data['query_type'] ) || empty( $data['q'] ) ) {
			throw new \Exception( 'Bad Request' );
		}

		$results = call_user_func( [ $this, 'get_' . $data['query_type'] ], $data );

		return [
			'results' => $results,
		];
	}

	/**
	 * Calls function to get value titles depending on ajax query type
	 *
	 * @return array
	 */
	public function ajax_call_control_value_titles( $request ) {
		$results = call_user_func( [ $this, 'get_value_titles_for_' . $request['query_type'] ], $request );

		return $results;
	}

	/**
	 * Get fields (post/user/term)
	 *
	 * @param array $data
	 * @return array
	 */
	protected function get_fields( $data ) {
		$results = [];
		if ( 'any' === $data['object_type'] ) {
			$object_types = [ 'post', 'user', 'term' ];
		} else {
			$object_types = [ $data['object_type'] ];
		}

		foreach ( $object_types as $object_type ) {
			$function = 'get_' . $object_type . '_fields';

			if ( 'post' === $object_type && Plugin::instance()->has_pro() ) {
				$fields = \Stax\VisibilityLogicPro\FunctionCaller::{$function}( $data['q'] );
			} else {
				$fields = \Stax\VisibilityLogic\FunctionCaller::{$function}( $data['q'] );
			}

			if ( ! empty( $fields ) ) {
				foreach ( $fields as $field_key => $field_name ) {
					$results[] = [
						'id'   => $field_key,
						'text' => ( 'any' === $data['object_type'] ? '[' . $object_type . '] ' : '' ) . $field_name,
					];
				}
			}
		}

		return $results;
	}

	/**
	 * Get value for metas
	 *
	 * @param [type] $request
	 * @return array
	 */
	protected function get_value_titles_for_metas( $request ) {
		$ids      = (array) $request['id'];
		$results  = [];
		$function = 'get_' . $request['object_type'] . '_metas';

		foreach ( $ids as $aid ) {
			$fields = \Stax\VisibilityLogic\FunctionCaller::{$function}( false, $aid );
			foreach ( $fields as $field_key => $field_name ) {
				if ( in_array( $field_key, $ids ) ) {
					$results[ $field_key ] = $field_name;
				}
			}
		}

		return $results;
	}

	/**
	 * Get value for fields
	 *
	 * @param [type] $request
	 * @return array
	 */
	protected function get_value_titles_for_fields( $request ) {
		$ids     = (array) $request['id'];
		$results = [];

		if ( 'any' === $request['object_type'] ) {
			$object_types = [ 'post', 'user', 'term' ];
		} else {
			$object_types = [ $request['object_type'] ];
		}

		foreach ( $object_types as $object_type ) {
			$function = 'get_' . $object_type . '_fields';
			foreach ( $ids as $id ) {
				if ( 'post' === $object_type && Plugin::instance()->has_pro() ) {
					$fields = \Stax\VisibilityLogicPro\FunctionCaller::{$function}( $id );
				} else {
					$fields = \Stax\VisibilityLogic\FunctionCaller::{$function}( $id );
				}

				if ( ! empty( $fields ) ) {
					foreach ( $fields as $field_key => $field_name ) {
						if ( in_array( $field_key, $ids ) ) {
							$results[ $field_key ] = $field_name;
						}
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Get values for posts
	 *
	 * @param [type] $request
	 * @return array
	 */
	protected function get_value_titles_for_posts( $request ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_value_titles_for_posts( $request );
		}

		return [];
	}

	/**
	 * Get values for terms
	 *
	 * @param [type] $request
	 * @return array
	 */
	protected function get_value_titles_for_terms( $request ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_value_titles_for_terms( $request );
		}

		return [];
	}

	/**
	 * Get posts
	 *
	 * @param array $data
	 * @return array
	 */
	protected function get_posts( $data ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_posts( $data );
		}

		return [];
	}

	/**
	 * Get post terms
	 *
	 * @param array $data
	 * @return array
	 */
	protected function get_terms( $data ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_terms( $data );
		}

		return [];
	}

	/**
	 * Get values for products variations
	 *
	 * @param [type] $request
	 * @return array
	 */
	protected function get_value_titles_for_products_variations( $request ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_value_titles_for_products_variations( $request );
		}

		return [];
	}

	/**
	 * Get products variations
	 *
	 * @param array $data
	 * @return array
	 */
	protected function get_products_variations( $data ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_products_variations( $data );
		}

		return [];
	}

	/**
	 * Get values for products
	 *
	 * @param [type] $request
	 * @return array
	 */
	protected function get_value_titles_for_products( $request ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_value_titles_for_products( $request );
		}

		return [];
	}

	/**
	 * Get products
	 *
	 * @param array $data
	 * @return array
	 */
	protected function get_products( $data ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_products( $data );
		}

		return [];
	}

	/**
	 * Get values for subscriptions
	 *
	 * @param [type] $request
	 * @return array
	 */
	protected function get_value_titles_for_subscriptions( $request ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_value_titles_for_subscriptions( $request );
		}

		return [];
	}

	/**
	 * Get subscriptions
	 *
	 * @param array $data
	 * @return array
	 */
	protected function get_subscriptions( $data ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_subscriptions( $data );
		}

		return [];
	}

	/**
	 * Get values for edd products
	 *
	 * @param [type] $request
	 * @return array
	 */
	protected function get_value_titles_for_edd_products( $request ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_value_titles_for_edd_products( $request );
		}

		return [];
	}

	/**
	 * Get edd products
	 *
	 * @param array $data
	 * @return array
	 */
	protected function get_edd_products( $data ) {
		if ( Plugin::instance()->has_pro() ) {
			return \Stax\VisibilityLogicPro\FunctionCaller::get_edd_products( $data );
		}

		return [];
	}

}
