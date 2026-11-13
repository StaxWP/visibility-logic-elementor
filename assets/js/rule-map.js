/**
 * Visibility Logic: rule tree <-> 2.x settings (JS twin of Rule_Tree::legacy_to_tree(), analyze(), to_storage() and
 * fail_closed(); tests/fixtures/rule-map.json is checked by the PHP and the JS tests).
 *
 * Works on unprefixed values (block attribute keys; the Elementor adapter strips `stax_visibility_`).
 */
( function ( root ) {
	'use strict';

	var KEY = 'rules';
	var SINCE = '3.0.0';

	function typeList( registry ) {
		return ( registry && registry.types ) || [];
	}

	function typeOf( registry, slug ) {
		return typeList( registry ).filter( function ( t ) {
			return t.slug === slug;
		} )[ 0 ] || null;
	}

	function switchOf( type ) {
		return type.switch ? type.switch : type.slug + '_enabled';
	}

	function on( values, key ) {
		var v = values[ key ];
		return v === true || v === 'yes' || v === 1 || v === '1';
	}

	function fieldDefault( field ) {
		if ( Object.prototype.hasOwnProperty.call( field, 'default' ) ) {
			return field.default;
		}
		if ( field.type === 'switch' ) {
			return false;
		}
		return [ 'multiselect', 'list', 'users', 'repeater' ].indexOf( field.type ) !== -1 ? [] : '';
	}

	function isEmptyValue( value ) {
		if ( Array.isArray( value ) ) {
			return value.filter( function ( v ) {
				return v !== null && v !== '' && ! ( Array.isArray( v ) && ! v.length );
			} ).length === 0;
		}
		return value === null || value === undefined || value === '' || value === false || value === 0 || value === '0';
	}

	function versionGte( a, b ) {
		var pa = String( a ).split( '.' ).map( Number );
		var pb = String( b ).split( '.' ).map( Number );
		for ( var i = 0; i < Math.max( pa.length, pb.length ); i++ ) {
			var x = pa[ i ] || 0;
			var y = pb[ i ] || 0;
			if ( x !== y ) {
				return x > y;
			}
		}
		return true;
	}

	function same( a, b ) {
		return JSON.stringify( a ) === JSON.stringify( b );
	}

	function filledGroups( tree ) {
		return ( tree.groups || [] ).filter( function ( g ) {
			return g.conditions && g.conditions.length;
		} );
	}

	function allConditions( tree ) {
		return filledGroups( tree ).reduce( function ( all, g ) {
			return all.concat( g.conditions );
		}, [] );
	}

	var ENABLED_RE = /^([a-z0-9_]+)_enabled$/;

	function legacyToTree( values, registry ) {
		var conditions = [];
		var seen = {};

		typeList( registry ).forEach( function ( type ) {
			var sw = switchOf( type );
			if ( ! on( values, sw ) ) {
				return;
			}
			var v = {};
			( type.fields || [] ).forEach( function ( field ) {
				if ( Object.prototype.hasOwnProperty.call( values, field.key ) ) {
					v[ field.key ] = values[ field.key ];
				}
			} );
			conditions.push( { id: 'l' + conditions.length, type: type.slug, values: v } );
			seen[ sw ] = true;
		} );

		Object.keys( values ).forEach( function ( key ) {
			var m = key.match( ENABLED_RE );
			if ( ! m || key === 'enabled' || seen[ key ] || m[ 1 ] === 'fallback' || ! on( values, key ) ) {
				return;
			}
			conditions.push( { id: 'l' + conditions.length, type: m[ 1 ], values: {} } );
		} );

		var groups;
		if ( values.condition_type === 'one' && conditions.length > 1 ) {
			groups = conditions.map( function ( c ) {
				return { conditions: [ c ] };
			} );
		} else {
			groups = [ { conditions: conditions } ];
		}

		return { v: 1, action: ! conditions.length || on( values, 'show_hide' ) ? 'show' : 'hide', draft: false, groups: groups };
	}

	function usesNewOptions( condition, type, since ) {
		return ( type.fields || [] ).some( function ( field ) {
			if ( ! Object.prototype.hasOwnProperty.call( condition.values || {}, field.key ) ) {
				return false;
			}
			var value = condition.values[ field.key ];
			if ( field.since && versionGte( field.since, since ) && ! same( value, fieldDefault( field ) ) && ! isEmptyValue( value ) ) {
				return true;
			}
			if ( field.options_since && value !== null && typeof value !== 'object' && field.options_since[ String( value ) ] && versionGte( field.options_since[ String( value ) ], since ) ) {
				return true;
			}
			return false;
		} );
	}

	function analyze( tree, registry, since ) {
		since = since || SINCE;
		var groups = filledGroups( tree );
		var reason = '';
		var storable = true;
		var types = {};

		allConditions( tree ).forEach( function ( condition ) {
			var type = typeOf( registry, condition.type );
			if ( ! type || type.readonly || type.tree === false ) {
				storable = false;
			}
			if ( types[ condition.type ] && reason === '' ) {
				reason = 'repeat';
			}
			types[ condition.type ] = true;
			if ( reason === '' && type && usesNewOptions( condition, type, since ) ) {
				reason = 'new';
			}
		} );

		if ( tree.draft ) {
			reason = 'draft';
		} else if ( reason === '' && groups.length > 1 && groups.some( function ( g ) {
			return g.conditions.length > 1;
		} ) ) {
			reason = 'groups';
		}

		return { flat: reason === '', reason: reason, storable: storable };
	}

	function failClosed( values ) {
		var out = {};
		Object.keys( values ).forEach( function ( key ) {
			var m = key.match( ENABLED_RE );
			if ( m && m[ 1 ] !== 'fallback' && on( values, key ) ) {
				out[ key ] = '';
			}
		} );
		out.show_hide = 'yes';
		out.condition_type = 'all';
		out.user_role_enabled = 'yes';
		out.user_role_conditions = [ 'administrator' ];
		return out;
	}

	function toStorage( tree, values, registry, since ) {
		var analysis = analyze( tree, registry, since );
		var changes = {};

		function set( key, value ) {
			var has = Object.prototype.hasOwnProperty.call( values, key ) && values[ key ] !== undefined;
			var defaults = { condition_type: 'all' };
			var current = has ? values[ key ] : ( defaults[ key ] !== undefined ? defaults[ key ] : null );
			if ( ! same( current, value ) && ! ( current === null && ( value === '' || ( Array.isArray( value ) && ! value.length ) ) ) ) {
				changes[ key ] = value;
			}
		}

		if ( ! analysis.flat ) {
			if ( ! analysis.storable ) {
				return null;
			}
			set( KEY, tree );
			var closed = failClosed( values );
			Object.keys( closed ).forEach( function ( key ) {
				set( key, closed[ key ] );
			} );
			return changes;
		}

		var conditions = allConditions( tree );
		var groups = filledGroups( tree );
		var inTree = {};
		conditions.forEach( function ( c ) {
			inTree[ c.type ] = c;
		} );

		if ( conditions.length ) {
			set( 'show_hide', tree.action === 'hide' ? '' : 'yes' );
		}

		if ( conditions.length > 1 ) {
			set( 'condition_type', groups.length > 1 ? 'one' : 'all' );
		}

		var switches = {};
		typeList( registry ).forEach( function ( type ) {
			var sw = switchOf( type );
			switches[ sw ] = true;
			if ( inTree[ type.slug ] ) {
				set( sw, 'yes' );
				Object.keys( inTree[ type.slug ].values || {} ).forEach( function ( key ) {
					set( key, inTree[ type.slug ].values[ key ] );
				} );
			} else if ( on( values, sw ) ) {
				set( sw, '' );
			}
		} );

		Object.keys( values ).forEach( function ( key ) {
			var m = key.match( ENABLED_RE );
			if ( m && on( values, key ) && ! switches[ key ] && m[ 1 ] !== 'fallback' && ! inTree[ m[ 1 ] ] ) {
				set( key, '' );
			}
		} );

		set( KEY, '' );

		return changes;
	}

	var api = { KEY: KEY, SINCE: SINCE, legacyToTree: legacyToTree, analyze: analyze, toStorage: toStorage, failClosed: failClosed };

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = api;
	} else {
		root.StaxVisibilityRuleMap = api;
	}
}( typeof window !== 'undefined' ? window : this ) );
