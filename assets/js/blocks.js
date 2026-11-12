/**
 * Visibility Logic: Visibility panel for every block (block editor only, no build step).
 *
 * The panel is generated from the condition registry sent by PHP (window.staxVisibilityBlocks), so free and Pro
 * conditions registered with the `stax/visibility/condition_fields` filter appear here without extra code.
 * Values are stored in the block attribute `staxVisibility` and evaluated on the server in `render_block`.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! config || ! wp.hooks || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var components = wp.components;
	var ATTR = config.attribute;

	// Attribute on every block, also blocks registered only in JavaScript.
	wp.hooks.addFilter( 'blocks.registerBlockType', 'stax-visibility/attribute', function ( settings ) {
		if ( ! settings || ( settings.attributes && settings.attributes[ ATTR ] ) ) {
			return settings;
		}

		settings.attributes = Object.assign( {}, settings.attributes, {} );
		settings.attributes[ ATTR ] = { type: 'object' };

		return settings;
	} );

	function isOn( value ) {
		return value === true || value === 'yes' || value === 1 || value === '1';
	}

	function defaultOf( field ) {
		if ( field.type === 'switch' ) {
			return false;
		}
		if ( field.type === 'multiselect' ) {
			return [];
		}
		return field.default !== undefined ? field.default : '';
	}

	function shown( field, values ) {
		if ( ! field.show_if ) {
			return true;
		}

		return Object.keys( field.show_if ).every( function ( key ) {
			var current = values[ key ];
			if ( current === undefined ) {
				var all = config.general.concat.apply( config.general, config.conditions.map( function ( c ) { return c.fields; } ) );
				var def = all.filter( function ( f ) { return f.key === key; } )[ 0 ];
				current = def ? defaultOf( def ) : '';
			}
			return field.show_if[ key ].indexOf( String( current ) ) !== -1;
		} );
	}

	function renderField( field, values, set ) {
		var value = values[ field.key ] !== undefined ? values[ field.key ] : defaultOf( field );
		var common = { key: field.key, label: field.label, help: field.help || undefined, __nextHasNoMarginBottom: true };

		switch ( field.type ) {
			case 'switch':
				return el( components.ToggleControl, Object.assign( {}, common, {
					checked: isOn( value ),
					onChange: function ( checked ) { set( field.key, !! checked ); },
				} ) );

			case 'select':
				return el( components.SelectControl, Object.assign( {}, common, {
					value: String( value ),
					options: field.options,
					onChange: function ( next ) { set( field.key, next ); },
					__next40pxDefaultSize: true,
				} ) );

			case 'multiselect':
				var list = Array.isArray( value ) ? value.map( String ) : [];
				return el( components.BaseControl, Object.assign( {}, common, { id: 'stax-vl-' + field.key } ),
					el( 'div', { className: 'stax-vl-checkboxes', role: 'group', 'aria-label': field.label },
						field.options.map( function ( option ) {
							return el( components.CheckboxControl, {
								key: option.value,
								label: option.label,
								checked: list.indexOf( option.value ) !== -1,
								__nextHasNoMarginBottom: true,
								onChange: function ( checked ) {
									var next = list.filter( function ( v ) { return v !== option.value; } );
									if ( checked ) {
										next.push( option.value );
									}
									set( field.key, next );
								},
							} );
						} )
					)
				);

			case 'textarea':
				return el( components.TextareaControl, Object.assign( {}, common, {
					value: String( value ),
					onChange: function ( next ) { set( field.key, next ); },
				} ) );

			case 'date':
				return el( components.TextControl, Object.assign( {}, common, {
					type: 'datetime-local',
					value: String( value ).replace( ' ', 'T' ),
					onChange: function ( next ) { set( field.key, String( next ).replace( 'T', ' ' ) ); },
					__next40pxDefaultSize: true,
				} ) );

			case 'time':
				return el( components.TextControl, Object.assign( {}, common, {
					type: 'time',
					value: String( value ),
					placeholder: field.placeholder || '',
					onChange: function ( next ) { set( field.key, next ); },
					__next40pxDefaultSize: true,
				} ) );

			default: // text, list.
				return el( components.TextControl, Object.assign( {}, common, {
					value: Array.isArray( value ) ? value.join( ', ' ) : String( value ),
					placeholder: field.placeholder || '',
					onChange: function ( next ) { set( field.key, next ); },
					__next40pxDefaultSize: true,
				} ) );
		}
	}

	function Panel( props ) {
		var values = props.attributes[ ATTR ] || {};

		function set( key, value ) {
			var next = Object.assign( {}, values );
			next[ key ] = value;
			var update = {};
			update[ ATTR ] = next;
			props.setAttributes( update );
		}

		var enabled = isOn( values.enabled );
		var children = [];

		config.general.forEach( function ( field ) {
			if ( field.key === 'enabled' || enabled ) {
				children.push( renderField( field, values, set ) );
			}
		} );

		if ( enabled ) {
			children.push( el( 'p', { key: 'notice', className: 'stax-vl-note' }, config.labels.notice ) );

			config.conditions.forEach( function ( condition ) {
				var on = isOn( values[ condition.switch ] );
				var inner = [
					renderField( { key: condition.switch, type: 'switch', label: condition.label }, values, set ),
				];

				if ( on ) {
					condition.fields.forEach( function ( field ) {
						if ( shown( field, values ) ) {
							inner.push( renderField( field, values, set ) );
						}
					} );
				}

				children.push( el( 'div', { key: condition.slug, className: 'stax-vl-condition' + ( on ? ' is-on' : '' ) }, inner ) );
			} );

			if ( config.proUrl ) {
				children.push( el( 'p', { key: 'pro', className: 'stax-vl-note' },
					el( components.ExternalLink, { href: config.proUrl }, __( 'More conditions in Visibility Logic Pro', 'visibility-logic-elementor' ) )
				) );
			}
		}

		return el( InspectorControls, null,
			el( components.PanelBody, {
				title: config.labels.panel,
				initialOpen: enabled,
				className: 'stax-vl-panel',
			}, children )
		);
	}

	var withPanel = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( ! props.isSelected ) {
				return el( BlockEdit, props );
			}

			return el( Fragment, null, el( BlockEdit, props ), el( Panel, props ) );
		};
	}, 'withStaxVisibilityPanel' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'stax-visibility/panel', withPanel );

	// Outline blocks that have rules (editor only).
	var withBadge = wp.compose.createHigherOrderComponent( function ( BlockListBlock ) {
		return function ( props ) {
			var values = props.attributes && props.attributes[ ATTR ];

			if ( ! values || ! isOn( values.enabled ) ) {
				return el( BlockListBlock, props );
			}

			return el( BlockListBlock, Object.assign( {}, props, {
				className: ( props.className ? props.className + ' ' : '' ) + 'stax-vl-has-rules',
			} ) );
		};
	}, 'withStaxVisibilityBadge' );

	wp.hooks.addFilter( 'editor.BlockListBlock', 'stax-visibility/badge', withBadge );
}( window.wp, window.staxVisibilityBlocks ) );
