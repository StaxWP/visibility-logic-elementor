/**
 * Visibility Logic: rule builder in the Elementor panel (classic elements), canvas labels and Navigator badges.
 *
 * The builder (window.StaxConditions, shared package) edits a rule tree. Saving goes through
 * window.StaxVisibilityRuleMap: rules that the 2.x settings can express are written to the 2.x keys (nothing is
 * written when nothing changed), other rules to `stax_visibility_rules` with fail-closed 2.x keys.
 */
( function ( $, data, SC, RuleMap, win ) {
	'use strict';

	if ( ! data || ! SC || ! RuleMap ) {
		return;
	}

	var P = 'stax_visibility_';
	var L = data.labels;
	var CLASSIC_KEY = 'staxVlClassic';
	var api = window.StaxVisibilityElementor = { renders: 0 };

	function plain( value ) {
		if ( value && typeof value.toJSON === 'function' ) {
			return value.toJSON();
		}
		return value;
	}

	function on( value ) {
		return value === true || value === 'yes' || value === 1 || value === '1';
	}

	function valuesOf( container ) {
		var attrs = ( container && container.settings && container.settings.attributes ) || {};
		var out = {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( key.indexOf( P ) === 0 ) {
				out[ key.slice( P.length ) ] = plain( attrs[ key ] );
			}
		} );
		return out;
	}

	function treeOf( values ) {
		var stored = values[ RuleMap.KEY ] ? SC.tree.normalize( values[ RuleMap.KEY ] ) : null;
		if ( stored && ! SC.tree.isEmpty( stored ) ) {
			return stored;
		}
		return RuleMap.legacyToTree( values, data.registry );
	}

	function shortOf( values ) {
		return SC.summary.short( treeOf( values ), data.registry, L );
	}

	function sentenceOf( values ) {
		return SC.summary.sentence( treeOf( values ), data.registry, L );
	}

	function apply( container, changes ) {
		var settings = {};
		Object.keys( changes ).forEach( function ( key ) {
			settings[ P + key ] = changes[ key ];
		} );
		if ( ! Object.keys( settings ).length ) {
			return;
		}
		if ( window.$e && $e.run ) {
			$e.run( 'document/elements/settings', { container: container, settings: settings } );
		} else {
			container.settings.set( settings );
		}
		scheduleLabels();
	}

	function isContainerLike( container ) {
		var type = container && container.model ? container.model.get( 'elType' ) : '';
		return type === 'section' || type === 'container';
	}

	function settingsFields( container ) {
		var fields = [
			{ key: 'active', type: 'switch', label: L.active, help: L.activeHelp },
			{ key: 'when_hidden', type: 'select', label: L.whenHidden, options: [
				{ value: 'remove', label: L.remove_page },
				{ value: 'css', label: L.keepHtml },
				{ value: 'text', label: L.fallbackText },
				{ value: 'template', label: data.fallback ? L.fallbackTpl : L.fallbackTplPro, disabled: ! data.fallback },
			], show_if: { active: [ 'yes' ] } },
			{ key: 'fallback_text', type: 'textarea', label: L.fallbackMessage, help: L.fallbackHelp, show_if: { active: [ 'yes' ], when_hidden: [ 'text' ] } },
		];
		if ( data.fallback && ( data.templates || [] ).length ) {
			fields.push( { key: 'fallback_template', type: 'select', label: L.fallbackChoose, options: [ { value: '', label: L.fallbackChoose } ].concat( data.templates ), show_if: { active: [ 'yes' ], when_hidden: [ 'template' ] } } );
		}
		if ( isContainerLike( container ) ) {
			fields.push( { key: 'hide_when_empty', type: 'switch', label: L.hideEmpty, show_if: { active: [ '' ] } } );
		}
		return fields;
	}

	function settingsValues( values ) {
		return {
			active: on( values.enabled ),
			when_hidden: on( values.fallback_enabled ) ? ( values.fallback_type === 'template' ? 'template' : 'text' ) : ( on( values.keep_html ) ? 'css' : 'remove' ),
			hide_when_empty: on( values.hide_when_empty ),
			fallback_text: values.fallback_text || '',
			fallback_template: values.fallback_template ? String( values.fallback_template ) : '',
		};
	}

	function setClassic( show ) {
		var panel = document.getElementById( 'elementor-panel' );
		if ( panel ) {
			panel.classList.toggle( 'stax-vl-show-classic', !! show );
		}
		try {
			window.sessionStorage.setItem( CLASSIC_KEY, show ? '1' : '' );
		} catch ( e ) {}
	}

	function classicShown() {
		try {
			return window.sessionStorage.getItem( CLASSIC_KEY ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	var postsSearch = data.postsUrl ? function ( q ) {
		return window.fetch( data.postsUrl + '?type=post&per_page=20&search=' + encodeURIComponent( q ), {
			headers: { 'X-WP-Nonce': data.restNonce },
			credentials: 'same-origin',
		} ).then( function ( r ) {
			return r.ok ? r.json() : [];
		} ).then( function ( list ) {
			return ( list || [] ).map( function ( p ) {
				return { value: String( p.id ), label: p.title + ' (#' + p.id + ')' };
			} );
		} );
	} : null;

	// Columns with Visibility Logic Pro before 2.5: Pro conditions are not registered on columns.
	var columnRegistry = null;
	function registryFor( container ) {
		var type = container && container.model ? container.model.get( 'elType' ) : '';
		if ( type !== 'column' || ! data.proColumnsLocked ) {
			return data.registry;
		}
		if ( ! columnRegistry ) {
			columnRegistry = Object.assign( {}, data.registry, {
				types: data.registry.types.map( function ( t ) {
					return t.pro ? Object.assign( {}, t, { locked: true, lock: { title: L.proColumns, text: '', actions: [] } } ) : t;
				} ),
			} );
		}
		return columnRegistry;
	}

	var usersSearch = data.usersUrl ? function ( q ) {
		return window.fetch( data.usersUrl + ( data.usersUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'search=' + encodeURIComponent( q ), {
			headers: { 'X-WP-Nonce': data.restNonce },
			credentials: 'same-origin',
		} ).then( function ( r ) {
			return r.ok ? r.json() : [];
		} );
	} : null;

	// ---- Preview as and why hidden ----
	var preview = { ctx: { as: 'me' }, results: {}, open: false, timer: null, views: [] };
	var PL = data.preview || {};

	function previewActive() {
		return win.StaxVisibilityPreview ? win.StaxVisibilityPreview.active( preview.ctx ) : !! ( preview.ctx && ( preview.ctx.as !== 'me' || preview.ctx.device || preview.ctx.time ) );
	}

	function allWithRules() {
		var list = [];
		try {
			eachContainer( elementor.getPreviewContainer(), function ( c ) {
				if ( c.settings && c.model && on( c.settings.get( P + 'enabled' ) ) ) {
					list.push( c );
				}
			} );
		} catch ( e ) {}
		return list;
	}

	function runExplain() {
		clearTimeout( preview.timer );
		preview.timer = setTimeout( function () {
			var containers = allWithRules();
			if ( ! previewActive() ) {
				preview.results = {};
				applyGhosts( containers );
				return;
			}
			window.fetch( data.explainUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.restNonce },
				body: JSON.stringify( {
					post_id: elementor.config.document.id,
					context: preview.ctx,
					elements: containers.map( function ( c ) {
						return { id: c.id, name: c.model.get( 'widgetType' ) || c.model.get( 'elType' ), values: valuesOf( c ) };
					} ),
				} ),
			} ).then( function ( r ) {
				return r.ok ? r.json() : [];
			} ).then( function ( list ) {
				preview.results = {};
				( list || [] ).forEach( function ( item ) {
					preview.results[ item.id ] = item;
				} );
				applyGhosts( containers );
				preview.views.forEach( function ( v ) {
					v.renderTrace && v.renderTrace();
				} );
			} );
		}, 200 );
	}

	function applyGhosts( containers ) {
		var who = SC.contextLabel( preview.ctx, { roles: data.roles }, PL );
		containers.forEach( function ( c ) {
			var result = preview.results[ c.id ];
			var hidden = !! ( result && ! result.visible && previewActive() );
			if ( c.view && c.view.el ) {
				c.view.el.classList.toggle( 'stax-vl-ghost', hidden );
				if ( hidden ) {
					c.view.el.setAttribute( 'data-stax-vl', SC.summary.sprintf( PL.hidden || 'Hidden for %s', who ) );
				}
			}
		} );
		if ( ! previewActive() ) {
			scheduleLabels();
		}
	}

	function makeView() {
		return elementor.modules.controls.BaseData.extend( {
			onRender: function () {
				var view = this;
				api.renders++;
				var mount = this.el.querySelector( '.stax-vl-rb' );
				var container = this.container || ( this.options && this.options.container );

				if ( ! mount || ! container ) {
					return;
				}

				var panel = document.getElementById( 'elementor-panel' );
				if ( panel ) {
					panel.classList.add( 'stax-vl-has-builder' );
					panel.classList.toggle( 'stax-vl-show-classic', classicShown() );
				}

				var values = valuesOf( container );
				var timer = null;
				var pending = null;

				// Preview as and why (shared across elements while the editor is open).
				var details = document.createElement( 'details' );
				details.className = 'stax-vl-preview';
				details.open = preview.open;
				var summaryEl = document.createElement( 'summary' );
				summaryEl.textContent = PL.why || 'Preview as and why';
				var barBox = document.createElement( 'div' );
				var traceBox = document.createElement( 'div' );
				details.appendChild( summaryEl );
				details.appendChild( barBox );
				details.appendChild( traceBox );
				details.addEventListener( 'toggle', function () {
					preview.open = details.open;
				} );
				mount.parentNode.insertBefore( details, mount );

				this.previewBar = SC.createPreviewBar( barBox, {
					roles: data.roles || [],
					users: !! data.usersUrl,
					country: !! data.previewCountry,
					search: usersSearch,
					value: preview.ctx,
					labels: PL,
					onChange: function ( ctx ) {
						preview.ctx = Object.assign( {}, ctx, { language: preview.ctx.language || '' } );
						runExplain();
					},
				} );

				// Language (Pro): next to the shared preview bar.
				var langBox = document.createElement( 'div' );
				details.insertBefore( langBox, traceBox );
				this.languageField = win.StaxVisibilityPreview ? win.StaxVisibilityPreview.language( langBox, {
					languages: data.previewLanguages || [],
					labels: PL,
					value: preview.ctx.language,
					onChange: function ( code ) {
						preview.ctx = Object.assign( {}, preview.ctx, { language: code } );
						runExplain();
					},
				} ) : null;

				this.renderTrace = function () {
					var result = preview.results[ container.id ];
					if ( ! previewActive() || ! result ) {
						traceBox.textContent = '';
						return;
					}
					if ( result.reason === 'off' ) {
						traceBox.textContent = PL.off || '';
						return;
					}
					SC.renderTrace( traceBox, Object.assign( { who: SC.contextLabel( preview.ctx, { roles: data.roles }, PL ) }, result ), PL );
				};
				preview.views.push( this );
				this.renderTrace();
				var status = document.createElement( 'p' );
				status.className = 'stax-vl-rb-status';
				status.setAttribute( 'role', 'status' );

				var flush = function () {
					if ( ! pending ) {
						return;
					}
					var tree = pending;
					pending = null;
					var current = valuesOf( container );
					var wasEmpty = SC.tree.isEmpty( treeOf( current ) );
					var changes = RuleMap.toStorage( tree, current, data.registry, data.since );

					if ( changes === null ) {
						status.textContent = L.readonlyBlock;
						return;
					}

					if ( wasEmpty && ! SC.tree.isEmpty( tree ) && ! on( current.enabled ) ) {
						changes.enabled = 'yes';
						view.builder.setSettings( settingsValues( Object.assign( {}, current, { enabled: 'yes' } ) ) );
					}

					status.textContent = RuleMap.analyze( tree, data.registry, data.since ).flat ? '' : L.treeNote;
					apply( container, changes );
					if ( previewActive() ) {
						runExplain();
					}
				};

				this.flush = flush;

				this.builder = SC.createBuilder( mount, {
					registry: registryFor( container ),
					value: treeOf( values ),
					labels: L,
					context: data.context,
					proUrl: data.proUrl,
					search: { users: usersSearch, posts: postsSearch },
					settings: {
						fields: settingsFields( container ),
						values: settingsValues( values ),
						onChange: function ( key, value ) {
							var changes = {};
							if ( key === 'active' ) {
								changes.enabled = value ? 'yes' : '';
							} else if ( key === 'when_hidden' ) {
								changes.keep_html = value === 'css' ? 'yes' : '';
								changes.fallback_enabled = value === 'text' || value === 'template' ? 'yes' : '';
								if ( value === 'text' || value === 'template' ) {
									changes.fallback_type = value;
								}
							} else if ( key === 'fallback_text' || key === 'fallback_template' ) {
								clearTimeout( view.textTimer );
								view.textTimer = setTimeout( function () {
									var c = {};
									c[ key ] = value;
									apply( container, c );
								}, 300 );
								return;
							} else if ( key === 'hide_when_empty' ) {
								changes.hide_when_empty = value ? 'yes' : '';
							}
							var current = valuesOf( container );
							Object.keys( changes ).forEach( function ( k ) {
								if ( ( current[ k ] || '' ) === changes[ k ] ) {
									delete changes[ k ];
								}
							} );
							apply( container, changes );
						},
					},
					onChange: function ( tree ) {
						pending = tree;
						clearTimeout( timer );
						timer = setTimeout( flush, 300 );
					},
				} );

				if ( data.users && Object.keys( data.users ).length ) {
					this.builder.cacheUsers( data.users );
				}

				if ( ! RuleMap.analyze( treeOf( values ), data.registry, data.since ).flat ) {
					status.textContent = L.treeNote;
				}

				var toggle = document.createElement( 'button' );
				toggle.type = 'button';
				toggle.className = 'stax-vl-classic-toggle';
				var label = function () {
					var shown = classicShown();
					toggle.textContent = shown ? L.classicHide : L.classicShow;
					toggle.setAttribute( 'aria-pressed', shown ? 'true' : 'false' );
				};
				label();
				toggle.addEventListener( 'click', function () {
					setClassic( ! classicShown() );
					label();
				} );

				mount.parentNode.appendChild( status );
				mount.parentNode.appendChild( toggle );
			},

			onBeforeDestroy: function () {
				var self = this;
				preview.views = preview.views.filter( function ( v ) {
					return v !== self;
				} );
				if ( this.flush ) {
					this.flush();
				}
				if ( this.builder ) {
					this.builder.destroy();
				}
			},
		} );
	}

	// ---- canvas labels and Navigator badges ----
	var NAV_ICON = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 4h18l-7 8.5V19l-4 2v-8.5z"/></svg>';

	var labelTimer = null;

	function scheduleLabels() {
		clearTimeout( labelTimer );
		labelTimer = setTimeout( refreshLabels, 150 );
	}

	function eachContainer( container, callback ) {
		if ( ! container ) {
			return;
		}
		callback( container );
		( container.children || [] ).forEach( function ( child ) {
			eachContainer( child, callback );
		} );
	}

	function navItem( container ) {
		var id = container.id;
		var nav = document.getElementById( 'elementor-navigator' ) || document.querySelector( '.elementor-navigator, #elementor-navigator__elements' );
		if ( ! nav ) {
			return null;
		}
		var row = nav.querySelector( '[data-id="' + id + '"] .elementor-navigator__item' ) || nav.querySelector( '.elementor-navigator__element-' + id + ' > .elementor-navigator__item' );
		if ( row ) {
			return row;
		}
		if ( container.navView && container.navView.el ) {
			return container.navView.el.querySelector( '.elementor-navigator__item' );
		}
		return null;
	}

	function refreshLabels() {
		if ( ! window.elementor || ! elementor.getPreviewContainer ) {
			return;
		}
		var root;
		try {
			root = elementor.getPreviewContainer();
		} catch ( e ) {
			return;
		}

		eachContainer( root, function ( container ) {
			if ( ! container.settings || ! container.view || ! container.view.el ) {
				return;
			}
			var values = valuesOf( container );
			var el = container.view.el;
			var row = navItem( container );
			var badge = row ? row.querySelector( '.stax-vl-nav-badge' ) : null;

			if ( ! on( values.enabled ) ) {
				el.removeAttribute( 'data-stax-vl' );
				if ( badge ) {
					badge.remove();
				}
				return;
			}

			var short = shortOf( values );
			var full = sentenceOf( values );
			var warnings = SC.warnings.check( treeOf( values ), data.registry, data.context ).filter( function ( w ) {
				return w.code === 'expired' || w.code === 'never';
			} );
			var text = ( warnings.length ? '⚠ ' : '' ) + short;

			if ( ! el.classList.contains( 'stax-vl-ghost' ) ) {
				el.setAttribute( 'data-stax-vl', text );
			}
			el.setAttribute( 'data-stax-vl-title', full );

			if ( row ) {
				if ( ! badge ) {
					badge = document.createElement( 'span' );
					badge.className = 'stax-vl-nav-badge';
					var title = row.querySelector( '.elementor-navigator__element__title' ) || row.lastElementChild;
					( title && title.parentNode === row ? row.insertBefore( badge, title.nextSibling ) : row.appendChild( badge ) );
				}
				// A small icon (Elementor's own eye is the hide button next to it); the rule shows on hover.
				if ( ! badge.firstChild ) {
					badge.innerHTML = NAV_ICON;
				}
				badge.classList.toggle( 'is-warning', warnings.length > 0 );
				badge.title = ( warnings.length ? '⚠ ' : '' ) + full;
				badge.setAttribute( 'role', 'img' );
				badge.setAttribute( 'aria-label', L.builder + ': ' + full );
			}
		} );
	}

	$( window ).on( 'elementor:init', function () {
		elementor.addControlView( 'stax_rule_builder', makeView() );

		elementor.on( 'document:loaded', scheduleLabels );
		elementor.on( 'preview:loaded', scheduleLabels );

		if ( window.$e && $e.commands && typeof $e.commands.on === 'function' ) {
			$e.commands.on( 'run:after', function ( component, command ) {
				if ( typeof command === 'string' && command.indexOf( 'document/' ) === 0 ) {
					scheduleLabels();
				}
			} );
		}

		// The Navigator renders its rows lazily: re-apply the badges when it changes.
		var observer = new MutationObserver( function ( mutations ) {
			var own = mutations.every( function ( m ) {
				return m.target.classList && m.target.classList.contains( 'stax-vl-nav-badge' );
			} );
			if ( ! own ) {
				scheduleLabels();
			}
		} );
		var watch = function () {
			var nav = document.getElementById( 'elementor-navigator' );
			if ( nav ) {
				observer.observe( nav, { childList: true, subtree: true } );
				return true;
			}
			return false;
		};
		if ( ! watch() ) {
			var tries = 0;
			var timer = setInterval( function () {
				if ( watch() || ++tries > 40 ) {
					clearInterval( timer );
				}
			}, 500 );
		}
	} );

	api.refreshLabels = refreshLabels;
	api.treeOf = treeOf;
	api.valuesOf = valuesOf;
}( jQuery, window.staxVisibilityRules, window.StaxConditions, window.StaxVisibilityRuleMap, window ) );
