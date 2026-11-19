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
	// under their own key in the block attribute. Fields with a `panel` title get their own panel below "Visibility
	// rules" (they are not part of the rule); the others sit in the rule builder's options.
	var allExtra = data.elementSettings || [];
	var extraFields = allExtra.filter( function ( f ) {
		return ! f.panel;
	} );
	var extraKeys = extraFields.map( function ( f ) {
		return f.key;
	} );
	var extraPanels = allExtra.reduce( function ( out, f ) {
		if ( f.panel ) {
			( out[ f.panel ] = out[ f.panel ] || [] ).push( f );
		}
		return out;
	}, {} );

	function extraValues( values, fields ) {
		var out = {};
		( fields || extraFields ).forEach( function ( f ) {
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
		return window.StaxVisibilityPreview ? window.StaxVisibilityPreview.active( ctx ) : !! ( ctx && ( ctx.as !== 'me' || ctx.device || ctx.time ) );
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

	// "Previewing as ..." pill while a preview is on, with a stop button; open preview bars follow the context.
	var bars = [];
	var pill = window.StaxVisibilityPreview && window.StaxVisibilityPreview.pill ? window.StaxVisibilityPreview.pill( {
		labels: PL,
		onStop: function () {
			wp.data.dispatch( STORE ).set( { ctx: { as: 'me' } } );
			bars.forEach( function ( b ) {
				b.setValue( { as: 'me' } );
			} );
			runExplain();
		},
	} ) : null;
	if ( pill ) {
		var lastCtx = null;
		wp.data.subscribe( function () {
			var ctx = wp.data.select( STORE ).get().ctx;
			if ( ctx !== lastCtx ) {
				lastCtx = ctx;
				pill.update( ctx, SC.contextLabel( ctx, { roles: data.roles }, PL ) );
			}
		} );
	}

	function PreviewBox( props ) {
		var barRef = useRef( null );
		var langRef = useRef( null );
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
					wp.data.dispatch( STORE ).set( { ctx: Object.assign( {}, ctx, { language: wp.data.select( STORE ).get().ctx.language || '' } ) } );
					runExplain();
				},
			} );
			// Language (Pro): next to the shared preview bar.
			var lang = window.StaxVisibilityPreview ? window.StaxVisibilityPreview.language( langRef.current, {
				languages: data.previewLanguages || [],
				labels: PL,
				value: wp.data.select( STORE ).get().ctx.language,
				onChange: function ( code ) {
					wp.data.dispatch( STORE ).set( { ctx: Object.assign( {}, wp.data.select( STORE ).get().ctx, { language: code } ) } );
					runExplain();
				},
			} ) : null;
			var handle = { setValue: function ( v ) {
				bar.setValue( v );
				if ( lang ) {
					lang.set( '' );
				}
			} };
			bars.push( handle );
			return function () {
				bars = bars.filter( function ( b ) {
					return b !== handle;
				} );
				bar.destroy();
				if ( lang ) {
					lang.destroy();
				}
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
			el( 'div', { ref: langRef } ),
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

			// Builder settings values of the stored attributes.
			function settingsOf( v ) {
				return Object.assign( extraValues( v ), { active: on( v.enabled ), when_hidden: on( v.fallback_enabled ) ? 'text' : ( on( v.keep_html ) ? 'css' : 'remove' ), fallback_text: v.fallback_text || '' } );
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
					api.current.setSettings( Object.assign( settingsOf( values2 ), { active: true } ) );
					api.current.setActive( true );
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
				pause: {
					active: on( values.enabled ),
					onChange: function ( active ) {
						write( { enabled: active } );
						if ( api.current ) {
							api.current.setSettings( Object.assign( settingsOf( current() ), { active: active } ) );
						}
					},
				},
				settings: {
					fields: [
						{ key: 'when_hidden', type: 'select', label: L.whenHidden, options: [ { value: 'remove', label: L.remove_page }, { value: 'css', label: L.keepHtml }, { value: 'text', label: L.fallbackText } ], show_if: { active: [ 'yes' ] } },
						{ key: 'fallback_text', type: 'textarea', label: L.fallbackMessage, help: L.fallbackHelp, show_if: { active: [ 'yes' ], when_hidden: [ 'text' ] } },
					].concat( extraFields ),
					values: settingsOf( values ),
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

	// One add-on panel (e.g. "Reveal on click"): WordPress controls for select, text, number, textarea and switch fields.
	function ExtraPanel( props ) {
		var fields = extraPanels[ props.title ];
		var stored = props.attributes[ ATTR ] || {};
		var values = extraValues( stored, fields );
		var set = function ( key, value ) {
			var next = Object.assign( {}, props.attributes[ ATTR ] || {} );
			next[ key ] = value;
			var update = {};
			update[ ATTR ] = next;
			props.setAttributes( update );
		};
		var changed = fields.some( function ( f ) {
			return stored[ f.key ] !== undefined && stored[ f.key ] !== ( f.default !== undefined ? f.default : '' );
		} );

		return el( components.PanelBody, { title: props.title, initialOpen: changed, className: 'stax-vl-panel' },
			fields.filter( function ( f ) {
				return SC.summary.shown( f, values, fields );
			} ).map( function ( f ) {
				var common = { key: f.key, label: f.label, help: f.help || undefined, __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };
				var v = values[ f.key ];
				if ( f.type === 'select' ) {
					return el( components.SelectControl, Object.assign( common, { value: String( v ), options: f.options || [], onChange: function ( x ) {
						set( f.key, x );
					} } ) );
				}
				if ( f.type === 'switch' ) {
					return el( components.ToggleControl, Object.assign( common, { checked: v === true || v === 'yes', onChange: function ( x ) {
						set( f.key, !! x );
					} } ) );
				}
				if ( f.type === 'textarea' ) {
					return el( components.TextareaControl, Object.assign( common, { value: v || '', onChange: function ( x ) {
						set( f.key, x );
					} } ) );
				}
				return el( components.TextControl, Object.assign( common, { type: f.type === 'number' ? 'number' : 'text', placeholder: f.placeholder || undefined, value: v === undefined || v === null ? '' : String( v ), onChange: function ( x ) {
					set( f.key, x );
				} } ) );
			} )
		);
	}

	function Panel( props ) {
		var values = props.attributes[ ATTR ] || {};
		return el( InspectorControls, null,
			el( components.PanelBody, {
				title: config.labels.panel,
				initialOpen: on( values.enabled ),
				className: 'stax-vl-panel',
			}, el( Builder, props ) ),
			Object.keys( extraPanels ).map( function ( title ) {
				return el( ExtraPanel, Object.assign( {}, props, { key: title, title: title } ) );
			} )
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
