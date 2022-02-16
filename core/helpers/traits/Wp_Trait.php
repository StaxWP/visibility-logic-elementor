<?php

namespace Stax\VisibilityLogic;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

trait Wp_Trait {

	/**
	 * Get user fields
	 *
	 * @param boolean $meta
	 * @param boolean $group
	 * @param boolean $info
	 *
	 * @return array
	 */
	public static function get_user_fields( $meta = false, $group = false, $info = true ) {
		$user_fields_key = array();
		$user_tmp        = wp_get_current_user();
		if ( $user_tmp ) {
			$user_prop = get_object_vars( $user_tmp );
			if ( ! empty( $user_prop['data'] ) ) {
				$user_prop_all = (array) $user_prop['data'];
				$user_prop     = array();
				if ( ! empty( $meta ) && is_string( $meta ) ) {
					foreach ( $user_prop_all as $key => $value ) {
						if ( is_array( $value ) ) {
							continue;
						}
						$pos_key  = stripos( $value, $meta );
						$pos_name = stripos( $key, $meta );
						if ( false === $pos_key && false === $pos_name ) {
							continue;
						}
						$user_prop[ $key ] = $value;
					}
				} else {
					$user_prop = $user_prop_all;
				}
			}

			if ( $meta ) {
				$metas           = self::get_user_metas( $group, ( is_string( $meta ) ) ? $meta : null, $info );
				$user_fields_key = $metas;
			}

			$user_fields = array_keys( $user_prop );
			if ( ! empty( $user_fields ) ) {
				foreach ( $user_fields as $value ) {
					$name = str_replace( 'user_', '', $value );
					$name = str_replace( '_', ' ', $name );
					$name = ucwords( $name ) . ' (' . $value . ')';
					if ( $group ) {
						$user_fields_key['USER'][ $value ] = $name;
					} else {
						$user_fields_key[ $value ] = $name;
					}
				}
			}

			$pos_key = is_string( $meta ) ? stripos( 'avatar', $meta ) : false;
			if ( empty( $meta ) || ! is_string( $meta ) || $pos_key !== false ) {
				if ( $group ) {
					$user_fields_key['USER']['avatar'] = 'Avatar';
				} else {
					$user_fields_key['avatar'] = 'Avatar';
				}
			}

			if ( $group ) {
				$user_fields_key = array_merge( array( 'USER' => $user_fields_key['USER'] ), $user_fields_key );
			}
		}

		return $user_fields_key;
	}

}
