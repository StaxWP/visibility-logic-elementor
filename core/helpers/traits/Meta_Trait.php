<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

trait Meta_Trait {

	/**
	 * Get user metas
	 *
	 * @param boolean $grouped
	 * @return array
	 */
	public static function get_user_metas( $grouped = false, $like = '', $info = true ) {
		$user_metas         = [];
		$user_metas_grouped = [];

		$acf_groups = get_posts(
			[
				'post_type'        => 'acf-field-group',
				'numberposts'      => -1,
				'post_status'      => 'publish',
				'suppress_filters' => false,
			]
		);
		if ( ! empty( $acf_groups ) ) {
			foreach ( $acf_groups as $aacf_group ) {
				$is_user_group = in_array( 'user', self::get_acf_group_locations( $aacf_group ) );
				$aacf_meta     = maybe_unserialize( $aacf_group->post_content );

				if ( $is_user_group ) {
					$acf = get_posts(
						[
							'post_type'        => 'acf-field',
							'numberposts'      => -1,
							'post_status'      => 'publish',
							'post_parent'      => $aacf_group->ID,
							'suppress_filters' => false,
						]
					);

					if ( ! empty( $acf ) ) {
						foreach ( $acf as $aacf ) {
							$aacf_meta = maybe_unserialize( $aacf->post_content );

							if ( $like ) {
								$pos_key  = stripos( $aacf->post_excerpt, $like );
								$pos_name = stripos( $aacf->post_title, $like );
								if ( false === $pos_key && false === $pos_name ) {
									continue;
								}
							}

							$field_name = $aacf->post_title;

							if ( $info ) {
								$field_name .= ' [' . $aacf_meta['type'] . ']';
							}

							$user_metas[ $aacf->post_excerpt ]                = $field_name;
							$user_metas_grouped['ACF'][ $aacf->post_excerpt ] = $user_metas[ $aacf->post_excerpt ];
						}
					}
				}
			}
		}

		global $wpdb;

		if ( ! is_multisite() ) {
			$table = $wpdb->prefix . 'usermeta';
		} else {
			$table = $wpdb->get_blog_prefix( get_main_site_id() ) . 'usermeta';
		}

		if ( defined( 'CUSTOM_USER_META_TABLE' ) ) {
			$table = CUSTOM_USER_META_TABLE;
		}

		$query = 'SELECT DISTINCT meta_key FROM ' . esc_sql( $table );

		if ( $like ) {
			$query .= " WHERE meta_key LIKE '%" . esc_sql( $like ) . "%'";
		}

		$results = $wpdb->get_results( $query );

		if ( ! empty( $results ) ) {
			$metas = [];
			foreach ( $results as $key => $auser ) {
				$metas[ $auser->meta_key ] = $auser->meta_key;
			}
			ksort( $metas );
			$manual_metas = $metas;
			foreach ( $manual_metas as $ameta ) {
				if ( substr( $ameta, 0, 1 ) == '_' ) {
					$ameta = $tmp = substr( $ameta, 1 );
					if ( in_array( $tmp, $manual_metas ) ) {
						continue;
					}
				}
				if ( ! isset( $postMetas[ $ameta ] ) ) {
					$user_metas[ $ameta ]                 = $ameta;
					$user_metas_grouped['META'][ $ameta ] = $ameta;
				}
			}
		}

		if ( $grouped ) {
			return $user_metas_grouped;
		}

		return $user_metas;
	}

	/**
	 * Get ACF group locations
	 *
	 * @param [type] $aacf_group
	 * @return array
	 */
	public static function get_acf_group_locations( $aacf_group ) {
		$locations = [];

		if ( is_string( $aacf_group ) ) {
			$acf_groups = get_posts(
				[
					'post_type'        => 'acf-field-group',
					'post_excerpt'     => $aacf_group,
					'numberposts'      => -1,
					'post_status'      => 'publish',
					'suppress_filters' => false,
				]
			);
			if ( ! empty( $acf_groups ) ) {
				$aacf_group = reset( $acf_groups );
			} else {
				return false;
			}
		}

		$aacf_meta = maybe_unserialize( $aacf_group->post_content );

		if ( ! empty( $aacf_meta['location'] ) ) {
			foreach ( $aacf_meta['location'] as $gkey => $gvalue ) {
				foreach ( $gvalue as $rkey => $rvalue ) {
					$pieces                 = explode( '_', $rvalue['param'] );
					$location               = reset( $pieces );
					$locations[ $location ] = $location;

					if ( 'page' === $location ) {
						$locations['post'] = 'post';
					}
					if ( 'current' === $location ) {
						$locations['user'] = 'user';
					}
				}
			}
		}

		return $locations;
	}

}
