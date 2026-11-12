<?php
/**
 * `wp visibility-logic`: read-only commands.
 *
 * @package Stax\VisibilityLogic
 * @since 3.0.0
 */

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inspect Visibility Logic rules.
 */
class Cli {

	/**
	 * Lists documents (or the elements of one document) with visibility rules.
	 *
	 * ## OPTIONS
	 *
	 * [--post=<id>]
	 * : Show the elements of one post, page or template.
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp visibility-logic rules
	 *     wp visibility-logic rules --post=42 --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 *
	 * @return void
	 */
	public function rules( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( ! empty( $assoc_args['post'] ) ) {
			$rows = [];
			foreach ( Rules::find_in_post( (int) $assoc_args['post'] ) as $element ) {
				$element['conditions'] = implode( ',', $element['conditions'] );
				$rows[]                = $element;
			}
			\WP_CLI\Utils\format_items( $format, $rows, [ 'id', 'type', 'action', 'match', 'conditions', 'legacy', 'keep_html', 'fallback' ] );

			return;
		}

		$rows = [];
		foreach ( Rules::index( true )['documents'] as $document ) {
			$rows[] = [
				'id'         => $document['id'],
				'title'      => $document['title'],
				'post_type'  => $document['post_type'],
				'status'     => $document['status'],
				'elements'   => $document['count'],
				'legacy'     => $document['legacy'],
				'conditions' => implode( ',', $document['conditions'] ),
			];
		}

		\WP_CLI\Utils\format_items( $format, $rows, [ 'id', 'title', 'post_type', 'status', 'elements', 'legacy', 'conditions' ] );
	}

	/**
	 * Lists the conditions and whether they are turned on.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml. Default: table.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 *
	 * @return void
	 */
	public function conditions( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		\WP_CLI\Utils\format_items( $format, Abilities::list_conditions()['conditions'], [ 'slug', 'name', 'enabled', 'pro' ] );
	}
}

\WP_CLI::add_command( 'visibility-logic', Cli::class );
