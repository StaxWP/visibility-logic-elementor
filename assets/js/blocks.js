/**
 * Visibility Logic: "Visibility rules" panel for every block (block editor only, no build step).
 *
 * The panel hosts the shared rule builder (window.StaxConditions). Values are stored in the block attribute
 * `staxVisibility` (2.x setting names without the `stax_visibility_` prefix) and evaluated on the server in
 * `render_block`. Rules that the 2.x format can express are written as 2.x keys; others as a tree in `rules`
 * (window.StaxVisibilityRuleMap, same as Elementor). The summary also reads WordPress's own block visibility
 * (`metadata.blockVisibility`). List View rows get a badge with the short summary.
 */
( function ( wp, config, SC, RuleMap ) {
	'use strict';

	if ( ! wp || ! config || ! wp.hooks || ! wp.blockEditor || ! SC || ! RuleMap ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useRef = wp.element.useRef;
	var useEffect = wp.element.useEffect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var components = wp.components;
	var ATTR = config.attribute;
	var data = config.rules;
	var L = data.labels;

	wp.hooks.addFilter( 'blocks.registerBlockType', 'stax-visibility/attribute', function ( settings ) {
		if ( ! settings || ( settings.attributes && settings.attributes[ ATTR ] ) ) {
			return settings;
		}
		settings.attributes = Object.assign( {}, settings.attributes );
		settings.attributes[ ATTR ] = { type: 'object' };
		return settings;
	} );

	function on( value ) {
		return value === true || value === 'yes' || value === 1 || value === '1';
	}

	function treeOf( values ) {
		var stored = values.rules ? SC.tree.normalize( values.rules ) : null;
		if ( stored && ! SC.tree.isEmpty( stored ) ) {
			return stored;
		}
		return RuleMap.legacyToTree( values, data.registry );
	}

	// Block attribute conventions: switches as booleans.
	function toBlock( changes ) {
		var out = {};
		Object.keys( changes ).forEach( function ( key ) {
			var value = changes[ key ];
			out[ key ] = /_enabled$/.test( key ) && ( value === 'yes' || value === '' ) ? value === 'yes' : value;
		} );
		return out;
	}

	// WordPress 6.9+ block visibility (metadata.blockVisibility): false = hidden everywhere, or per viewport.
	function coreVisibility( attributes ) {
		var meta = attributes && attributes.metadata;
		var v = meta ? meta.blockVisibility : undefined;
		if ( v === false ) {
			return config.labels.coreHidden;
		}
		if ( v && typeof v === 'object' ) {
			var source = v.viewport && typeof v.viewport === 'object' ? v.viewport : v;
			var hidden = Object.keys( source ).filter( function ( k ) {
				return source[ k ] === false;
			} );
			if ( hidden.length ) {
				return config.labels.coreHiddenOn.replace( '%s', hidden.join( ', ' ) );
			}
		}
		return '';
	}

	// Extra element settings from add-ons (`stax/visibility/element_settings`, e.g. Pro's Reveal on click): stored
	// under their own key in the block attribute.
	var extraFields = data.elementSettings || [];
	var extraKeys = extraFields.map( function ( f ) {
		return f.key;
	} );

	function extraValues( values ) {
		var out = {};
		extraFields.forEach( function ( f ) {
			out[ f.key ] = values[ f.key ] !== undefined ? values[ f.key ] : ( f.default !== undefined ? f.default : '' );
		} );
		return out;
	}

	function shortOf( values ) {
		return SC.summary.short( treeOf( values ), data.registry, L );
	}

	// ---- Preview as and why hidden: a small store, so the canvas re-renders when results change ----
	var PL = data.preview || {};
	var STORE = 'stax-visibility/preview';
	wp.data.register( wp.data.createReduxStore( STORE, {
		reducer: function ( state, action ) {
			state = state || { ctx: { as: 'me' }, results: {}, open: false };
			if ( action.type === 'SET' ) {
				return Object.assign( {}, state, action.patch );
			}
			return state;
		},
		actions: {
			set: function ( patch ) {
				return { type: 'SET', patch: patch };
			},
		},
		selectors: {
			get: function ( state ) {
				return state;
			},
		},
	} ) );

	function previewActive( ctx ) {
		return ctx && ( ctx.as !== 'me' || ctx.device || ctx.time );
	}

	function collect( blocks, out ) {
		( blocks || [] ).forEach( function ( b ) {
			var v = b.attributes && b.attributes[ ATTR ];
			if ( v && on( v.enabled ) ) {
				out.push( { id: b.clientId, name: b.name, values: v } );
			}
			collect( b.innerBlocks, out );
		} );
		return out;
	}

	var explainTimer = null;
	function runExplain() {
		clearTimeout( explainTimer );
		explainTimer = setTimeout( function () {
			var state = wp.data.select( STORE ).get();
			if ( ! previewActive( state.ctx ) ) {
				wp.data.dispatch( STORE ).set( { results: {} } );
				return;
			}
			wp.apiFetch( {
				url: data.explainUrl,
				method: 'POST',
				data: {
					post_id: wp.data.select( 'core/editor' ) ? wp.data.select( 'core/editor' ).getCurrentPostId() : 0,
					context: state.ctx,
					elements: collect( wp.data.select( 'core/block-editor' ).getBlocks(), [] ),
				},
			} ).then( function ( list ) {
				var results = {};
				( list || [] ).forEach( function ( item ) {
					results[ item.id ] = item;
				} );
				wp.data.dispatch( STORE ).set( { results: results } );
			} ).catch( function () {} );
		}, 250 );
	}

	function PreviewBox( props ) {
		var barRef = useRef( null );
		var traceRef = useRef( null );
		var state = wp.data.useSelect( function ( select ) {
			return select( STORE ).get();
		}, [] );

		useEffect( function () {
			var bar = SC.createPreviewBar( barRef.current, {
				roles: data.roles || [],
				users: !! data.usersUrl,
				country: !! data.previewCountry,
				search: usersSearch,
				value: wp.data.select( STORE ).get().ctx,
				labels: PL,
				onChange: function ( ctx ) {
					wp.data.dispatch( STORE ).set( { ctx: ctx } );
					runExplain();
				},
			} );
			return function () {
				bar.destroy();
			};
		}, [] );

		useEffect( function () {
			var result = state.results[ props.clientId ];
			if ( ! traceRef.current ) {
				return;
			}
			if ( ! previewActive( state.ctx ) || ! result ) {
				traceRef.current.textContent = '';
				return;
			}
			SC.renderTrace( traceRef.current, Object.assign( { who: SC.contextLabel( state.ctx, { roles: data.roles }, PL ) }, result ), PL );
		}, [ state, props.clientId ] );

		return el( 'details', {
			className: 'stax-vl-preview',
			open: state.open,
			onToggle: function ( e ) {
				wp.data.dispatch( STORE ).set( { open: e.target.open } );
			},
		},
			el( 'summary', null, PL.why ),
			el( 'div', { ref: barRef } ),
			el( 'div', { ref: traceRef } )
		);
	}

	var usersSearch = data.usersUrl ? function ( q ) {
		return wp.apiFetch( { url: data.usersUrl + '?search=' + encodeURIComponent( q ) } ).catch( function () {
			return [];
		} );
	} : null;

	function Builder( props ) {
		var mount = useRef( null );
		var api = useRef( null );
		var latest = useRef( props );
		latest.current = props;

		useEffect( function () {
			var values = props.attributes[ ATTR ] || {};
			var timer = null;
			var pending = null;

			function current() {
				return Object.assign( {}, latest.current.attributes[ ATTR ] || {} );
			}

			function write( changes ) {
				if ( ! Object.keys( changes ).length ) {
					return;
				}
				var next = Object.assign( current(), toBlock( changes ) );
				var update = {};
				update[ ATTR ] = next;
				latest.current.setAttributes( update );
			}

			function flush() {
				if ( ! pending ) {
					return;
				}
				var tree = pending;
				pending = null;
				var values2 = current();
				var wasEmpty = SC.tree.isEmpty( treeOf( values2 ) );
				var changes = RuleMap.toStorage( tree, values2, data.registry, data.since );
				if ( changes === null ) {
					return;
				}
				if ( wasEmpty && ! SC.tree.isEmpty( tree ) && ! on( values2.enabled ) ) {
					changes.enabled = true;
					api.current.setSettings( { active: true, when_hidden: on( values2.keep_html ) ? 'css' : 'remove' } );
				}
				write( changes );
				runExplain();
			}

			api.current = SC.createBuilder( mount.current, {
				registry: data.registry,
				value: treeOf( values ),
				labels: L,
				context: data.context,
				proUrl: data.proUrl,
				search: { users: usersSearch, posts: data.postsUrl ? function ( q ) {
					return wp.apiFetch( { path: '/wp/v2/search?type=post&per_page=20&search=' + encodeURIComponent( q ) } ).then( function ( list ) {
						return ( list || [] ).map( function ( p ) {
							return { value: String( p.id ), label: p.title + ' (#' + p.id + ')' };
						} );
					} ).catch( function () {
						return [];
					} );
				} : null },
				settings: {
					fields: [
						{ key: 'active', type: 'switch', label: L.active, help: L.activeHelp },
						{ key: 'when_hidden', type: 'select', label: L.whenHidden, options: [ { value: 'remove', label: L.remove_page }, { value: 'css', label: L.keepHtml }, { value: 'text', label: L.fallbackText } ], show_if: { active: [ 'yes' ] } },
						{ key: 'fallback_text', type: 'textarea', label: L.fallbackMessage, help: L.fallbackHelp, show_if: { active: [ 'yes' ], when_hidden: [ 'text' ] } },
					].concat( extraFields ),
					values: Object.assign( extraValues( values ), { active: on( values.enabled ), when_hidden: on( values.fallback_enabled ) ? 'text' : ( on( values.keep_html ) ? 'css' : 'remove' ), fallback_text: values.fallback_text || '' } ),
					onChange: function ( key, value ) {
						if ( key === 'active' ) {
							write( { enabled: !! value } );
						} else if ( key === 'when_hidden' ) {
							write( { keep_html: value === 'css', fallback_enabled: value === 'text', fallback_type: 'text' } );
						} else if ( key === 'fallback_text' ) {
							write( { fallback_text: value } );
						} else if ( extraKeys.indexOf( key ) !== -1 ) {
							var c = {};
							c[ key ] = value;
							write( c );
						}
					},
				},
				onChange: function ( tree ) {
					pending = tree;
					clearTimeout( timer );
					timer = setTimeout( flush, 250 );
				},
			} );

			return function () {
				clearTimeout( timer );
				flush();
				api.current.destroy();
			};
		}, [ props.clientId ] );

		var values = props.attributes[ ATTR ] || {};
		var core = coreVisibility( props.attributes );
		var tree = treeOf( values );
		var note = ! SC.tree.isEmpty( tree ) && ! RuleMap.analyze( tree, data.registry, data.since ).flat ? L.treeNote : '';

		return el( Fragment, null,
			el( PreviewBox, { clientId: props.clientId } ),
			core ? el( 'p', { className: 'stax-vl-core' }, core ) : null,
			el( 'div', { ref: mount, className: 'stax-vl-builder' } ),
			note ? el( 'p', { className: 'stax-vl-note' }, note ) : null,
			el( 'p', { className: 'stax-vl-note' }, config.labels.notice )
		);
	}

	function Panel( props ) {
		var values = props.attributes[ ATTR ] || {};
		return el( InspectorControls, null,
			el( components.PanelBody, {
				title: config.labels.panel,
				initialOpen: on( values.enabled ),
				className: 'stax-vl-panel',
			}, el( Builder, props ) )
		);
	}

	wp.hooks.addFilter( 'editor.BlockEdit', 'stax-visibility/panel', wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( ! props.isSelected ) {
				return el( BlockEdit, props );
			}
			return el( Fragment, null, el( BlockEdit, props ), el( Panel, props ) );
		};
	}, 'withStaxVisibilityPanel' ) );

	// Canvas: outline and short label on blocks with rules (editor only).
	wp.hooks.addFilter( 'editor.BlockListBlock', 'stax-visibility/badge', wp.compose.createHigherOrderComponent( function ( BlockListBlock ) {
		return function ( props ) {
			var values = props.attributes && props.attributes[ ATTR ];
			var preview = wp.data.useSelect( function ( select ) {
				return select( STORE ).get();
			}, [] );
			if ( ! values || ! on( values.enabled ) ) {
				return el( BlockListBlock, props );
			}
			var result = preview.results[ props.clientId ];
			var ghost = !! ( result && ! result.visible && previewActive( preview.ctx ) );
			var wrapperProps = Object.assign( {}, props.wrapperProps, {
				'data-stax-vl': ghost ? SC.summary.sprintf( PL.hidden || 'Hidden for %s', SC.contextLabel( preview.ctx, { roles: data.roles }, PL ) ) : shortOf( values ),
				'data-stax-vl-title': SC.summary.sentence( treeOf( values ), data.registry, L ),
			} );
			return el( BlockListBlock, Object.assign( {}, props, {
				className: ( props.className ? props.className + ' ' : '' ) + 'stax-vl-has-rules' + ( ghost ? ' stax-vl-ghost' : '' ),
				wrapperProps: wrapperProps,
			} ) );
		};
	}, 'withStaxVisibilityBadge' ) );

	// List View badges (rows are rendered by WordPress: add a small badge after the title).
	var badgeTimer = null;
	function refreshListView() {
		var rows = document.querySelectorAll( '.block-editor-list-view-leaf[data-block]' );
		if ( ! rows.length ) {
			return;
		}
		var select = wp.data.select( 'core/block-editor' );
		rows.forEach( function ( row ) {
			var block = select.getBlock( row.getAttribute( 'data-block' ) );
			var values = block && block.attributes && block.attributes[ ATTR ];
			var title = row.querySelector( '.block-editor-list-view-block-select-button__title' );
			var badge = row.querySelector( '.stax-vl-list-badge' );
			if ( ! values || ! on( values.enabled ) || ! title ) {
				if ( badge ) {
					badge.remove();
				}
				return;
			}
			var text = shortOf( values );
			if ( ! badge ) {
				badge = document.createElement( 'span' );
				badge.className = 'stax-vl-list-badge';
				title.parentNode.insertBefore( badge, title.nextSibling );
			}
			if ( badge.textContent !== text ) {
				badge.textContent = text;
			}
			badge.title = SC.summary.sentence( treeOf( values ), data.registry, L );
		} );
	}
	function scheduleListView() {
		clearTimeout( badgeTimer );
		badgeTimer = setTimeout( refreshListView, 120 );
	}
	wp.data.subscribe( scheduleListView );
	new MutationObserver( function ( mutations ) {
		if ( mutations.some( function ( m ) {
			return m.target.closest && m.target.closest( '.block-editor-list-view-tree' ) && ! m.target.classList.contains( 'stax-vl-list-badge' );
		} ) ) {
			scheduleListView();
		}
	} ).observe( document.body, { childList: true, subtree: true } );
}( window.wp, window.staxVisibilityBlocks, window.StaxConditions, window.StaxVisibilityRuleMap ) );
