/**
 * Visibility Logic: page-level rule (Page_Rules) in Elementor's page settings and in the block editor's document
 * sidebar. Both hosts use the same rule builder (window.StaxConditions) and the same value:
 * { enabled, rules, hidden, message, redirect, template } (post meta `_stax_visibility_page`).
 */
( function ( win, page, SC ) {
	'use strict';

	if ( ! page || ! SC ) {
		return;
	}

	// Builder data of the host editor (registry, labels, search URLs).
	function hostData() {
		if ( win.staxVisibilityRules ) {
			return win.staxVisibilityRules;
		}
		return win.staxVisibilityBlocks ? win.staxVisibilityBlocks.rules : null;
	}

	function normalize( value ) {
		var out = Object.assign( {}, page.defaults, value && typeof value === 'object' && ! Array.isArray( value ) ? value : {} );
		out.enabled = out.enabled === true || out.enabled === 'yes' || out.enabled === 1 || out.enabled === '1';
		return out;
	}

	function panelValues( value ) {
		return {
			active: value.enabled,
			hidden: value.hidden || 'message',
			message: value.message || '',
			redirect: value.redirect || '',
			template: value.template || '',
		};
	}

	function search( data ) {
		var users = data.usersUrl ? function ( q ) {
			return win.fetch( data.usersUrl + '?search=' + encodeURIComponent( q ), { credentials: 'same-origin', headers: { 'X-WP-Nonce': data.restNonce } } ).then( function ( r ) {
				return r.ok ? r.json() : [];
			} ).catch( function () {
				return [];
			} );
		} : null;
		var posts = data.postsUrl ? function ( q ) {
			var url = data.postsUrl + ( data.postsUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'type=post&per_page=20&search=' + encodeURIComponent( q );
			return win.fetch( url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': data.restNonce } } ).then( function ( r ) {
				return r.ok ? r.json() : [];
			} ).then( function ( list ) {
				return ( list || [] ).map( function ( p ) {
					return { value: String( p.id ), label: p.title + ' (#' + p.id + ')' };
				} );
			} ).catch( function () {
				return [];
			} );
		} : null;
		return { users: users, posts: posts };
	}

	/**
	 * Mount the builder.
	 *
	 * @param {Element}  mount Mount node.
	 * @param {Function} read  Returns the current value.
	 * @param {Function} write Stores a new value.
	 * @return {Object|null} Builder API.
	 */
	function mountBuilder( mount, read, write, kind ) {
		var data = hostData();
		if ( ! data || ! mount ) {
			return null;
		}
		var popup = kind === 'popup';
		var activeField = page.fields.filter( function ( f ) {
			return f.key === 'active';
		} )[ 0 ] || {};
		// The switch is ours (above the builder); the builder gets the "When hidden" fields (popups: none, a hidden
		// popup is not printed).
		var fields = popup ? [] : page.fields.filter( function ( f ) {
			return f.key !== 'active';
		} );
		var value = normalize( read() );
		var timer = null;
		var textTimer = null;
		var update = function ( changes ) {
			write( Object.assign( normalize( read() ), changes ) );
		};

		var intro = document.createElement( 'p' );
		intro.className = 'stax-vl-note';
		intro.textContent = popup ? page.labels.popup : page.labels.intro;
		mount.parentNode.insertBefore( intro, mount );

		// "Limit who can see this page": the rule builder shows only while it is on.
		var switchId = 'stax-vl-page-switch-' + Math.random().toString( 36 ).slice( 2, 8 );
		var toggle = document.createElement( 'div' );
		toggle.className = 'stax-vl-page-switch';
		var box = document.createElement( 'input' );
		box.type = 'checkbox';
		box.id = switchId;
		box.checked = value.enabled;
		var label = document.createElement( 'label' );
		label.setAttribute( 'for', switchId );
		label.textContent = popup ? page.labels.use : activeField.label;
		toggle.appendChild( box );
		toggle.appendChild( label );
		mount.parentNode.insertBefore( toggle, mount );

		mount.classList.add( 'stax-vl-page' );
		var setOff = function ( on ) {
			mount.classList.toggle( 'is-off', ! on );
		};
		setOff( value.enabled );

		var builder = SC.createBuilder( mount, {
			registry: data.registry,
			value: value.rules ? SC.tree.normalize( value.rules ) : SC.tree.make( 'show' ),
			labels: Object.assign( {}, data.labels, page.builder && page.builder[ popup ? 'popup' : 'page' ] ),
			context: data.context,
			proUrl: data.proUrl,
			search: search( data ),
			settings: fields.length ? {
				fields: fields,
				values: panelValues( value ),
				onChange: function ( key, v ) {
					if ( key === 'message' || key === 'redirect' ) {
						clearTimeout( textTimer );
						textTimer = setTimeout( function () {
							var c = {};
							c[ key ] = v;
							update( c );
						}, 300 );
						return;
					}
					var c = {};
					c[ key ] = v;
					update( c );
				},
			} : null,
			onChange: function ( tree ) {
				clearTimeout( timer );
				timer = setTimeout( function () {
					update( { rules: tree } );
				}, 250 );
			},
		} );

		box.addEventListener( 'change', function () {
			setOff( box.checked );
			update( { enabled: box.checked } );
			if ( fields.length ) {
				builder.setSettings( Object.assign( panelValues( normalize( read() ) ), { active: box.checked } ) );
			}
		} );

		if ( data.users && Object.keys( data.users ).length ) {
			builder.cacheUsers( data.users );
		}

		return {
			destroy: function () {
				clearTimeout( timer );
				clearTimeout( textTimer );
				builder.destroy();
				[ intro, toggle ].forEach( function ( node ) {
					if ( node.parentNode ) {
						node.parentNode.removeChild( node );
					}
				} );
				mount.classList.remove( 'stax-vl-page', 'is-off' );
			},
		};
	}

	// ---- Elementor: page settings control ----
	if ( win.jQuery ) {
		win.jQuery( win ).on( 'elementor:init', function () {
			win.elementor.addControlView( 'stax_page_rules', win.elementor.modules.controls.BaseData.extend( {
				onRender: function () {
					var view = this;
					var mount = this.el.querySelector( '.stax-vl-page-rb' );
					this.pageBuilder = mountBuilder( mount, function () {
						return view.getControlValue();
					}, function ( next ) {
						view.setValue( next );
					}, mount ? mount.getAttribute( 'data-kind' ) : 'page' );
				},
				onBeforeDestroy: function () {
					if ( this.pageBuilder ) {
						this.pageBuilder.destroy();
					}
				},
			} ) );
		} );
	}

	// ---- Block editor: document sidebar panel ----
	var wp = win.wp;
	if ( wp && wp.plugins && wp.element && wp.data && ( ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel ) ) ) {
		var el = wp.element.createElement;
		var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || wp.editPost.PluginDocumentSettingPanel;

		var Body = function () {
			var ref = wp.element.useRef( null );
			var meta = wp.data.useSelect( function ( select ) {
				return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			}, [] );
			var hasMeta = !! meta && Object.prototype.hasOwnProperty.call( meta, page.meta );

			wp.element.useEffect( function () {
				if ( ! hasMeta ) {
					return;
				}
				var api = mountBuilder( ref.current, function () {
					var m = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
					return m[ page.meta ];
				}, function ( next ) {
					var edit = {};
					edit[ page.meta ] = next;
					wp.data.dispatch( 'core/editor' ).editPost( { meta: edit } );
				} );
				return function () {
					if ( api ) {
						api.destroy();
					}
				};
			}, [ hasMeta ] );

			return hasMeta ? el( 'div', null, el( 'div', { ref: ref, className: 'stax-vl-builder' } ) ) : null;
		};

		wp.plugins.registerPlugin( 'stax-visibility-page', {
			render: function () {
				return el( Panel, { name: 'stax-visibility-page', title: page.labels.panel, className: 'stax-vl-panel' }, el( Body ) );
			},
		} );
	}
}( window, window.staxVisibilityPage, window.StaxConditions ) );
