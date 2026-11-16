/**
 * Visibility Logic: "Preview as" helpers shared by the Elementor and block editor adapters.
 *
 * - active( ctx ): whether a context simulates anything (who, device, time, country, language).
 * - language( container, opts ): a language select next to the shared preview bar, when an add-on lists languages
 *   (`stax/visibility/preview_languages`, Visibility Logic Pro). opts: languages [ { value, label } ], labels,
 *   value, onChange( code ).
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

	root.StaxVisibilityPreview = { active: active, language: language };
}( window ) );
