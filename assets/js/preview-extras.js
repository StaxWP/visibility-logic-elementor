/**
 * Visibility Logic: "Preview as" helpers shared by the Elementor and block editor adapters.
 *
 * - active( ctx ): whether a context simulates anything (who, device, time, country, language).
 * - language( container, opts ): a language select next to the shared preview bar, when an add-on lists languages
 *   (`stax/visibility/preview_languages`, Visibility Logic Pro). opts: languages [ { value, label } ], labels,
 *   value, onChange( code ).
 * - pill( opts ): a small floating "Previewing as ..." pill with a stop button, shown while a preview is on, so editors
 *   know the editor shows the page as someone else. opts: labels (previewing, stopPreview), onStop(). Returns
 *   { update( ctx, who ), destroy }.
 */
( function ( root ) {
	'use strict';

	function active( ctx ) {
		return !! ctx && ( ( ctx.as && ctx.as !== 'me' ) || !! ctx.device || !! ctx.time || !! ctx.country || !! ctx.language );
	}

	function language( container, opts ) {
		var list = ( opts && opts.languages ) || [];
		if ( ! container || ! list.length ) {
			return null;
		}
		var L = opts.labels || {};
		var id = 'stax-vl-lang-' + Math.random().toString( 36 ).slice( 2, 8 );
		var wrap = document.createElement( 'div' );
		wrap.className = 'stxc-field stax-vl-preview-language';
		var label = document.createElement( 'label' );
		label.className = 'stxc-label';
		label.setAttribute( 'for', id );
		label.textContent = L.language || 'Language';
		var select = document.createElement( 'select' );
		select.id = id;
		[ { value: '', label: L.anyLanguage || 'Current language' } ].concat( list ).forEach( function ( o ) {
			var option = document.createElement( 'option' );
			option.value = o.value;
			option.textContent = o.label;
			select.appendChild( option );
		} );
		select.value = opts.value || '';
		select.addEventListener( 'change', function () {
			if ( opts.onChange ) {
				opts.onChange( select.value );
			}
		} );
		wrap.appendChild( label );
		wrap.appendChild( select );
		// Same box as the shared preview bar (.stxc-preview), right under it.
		container.classList.add( 'stxc', 'stxc-preview', 'stax-vl-preview-lang' );
		container.style.marginTop = '6px';
		container.appendChild( wrap );
		return {
			set: function ( value ) {
				select.value = value || '';
			},
			destroy: function () {
				wrap.remove();
			},
		};
	}

	function pill( opts ) {
		var L = ( opts && opts.labels ) || {};
		var box = document.createElement( 'div' );
		box.className = 'stax-vl-preview-pill';
		box.setAttribute( 'role', 'status' );
		box.hidden = true;
		var text = document.createElement( 'span' );
		var stop = document.createElement( 'button' );
		stop.type = 'button';
		stop.textContent = L.stopPreview || 'Stop preview';
		stop.addEventListener( 'click', function () {
			if ( opts.onStop ) {
				opts.onStop();
			}
		} );
		box.appendChild( text );
		box.appendChild( stop );
		document.body.appendChild( box );
		return {
			update: function ( ctx, who ) {
				box.hidden = ! active( ctx );
				text.textContent = ( L.previewing || 'Previewing as %s' ).replace( '%s', ( who || '' ) + ( ctx && ctx.language ? ' · ' + ctx.language : '' ) );
			},
			destroy: function () {
				box.remove();
			},
		};
	}

	// Styles of the pill (both editors load this file, not a stylesheet of ours).
	var css = document.createElement( 'style' );
	css.textContent = '.stax-vl-preview-pill{position:fixed;z-index:99999;left:50%;bottom:44px;transform:translateX(-50%);display:flex;align-items:center;gap:10px;max-width:calc(100vw - 32px);padding:6px 6px 6px 14px;border-radius:999px;background:#1e1e1e;color:#fff;font:600 12px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;box-shadow:0 4px 16px rgba(0,0,0,.25)}'
		+ '.stax-vl-preview-pill[hidden]{display:none}'
		+ '.stax-vl-preview-pill span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
		+ '.stax-vl-preview-pill button{flex:none;padding:3px 10px;border:0;border-radius:999px;background:#fff;color:#1e1e1e;font:inherit;cursor:pointer}'
		+ '.stax-vl-preview-pill button:focus-visible{outline:2px solid #3858e9;outline-offset:2px}';
	( document.head || document.documentElement ).appendChild( css );

	root.StaxVisibilityPreview = { active: active, language: language, pill: pill };
}( window ) );
