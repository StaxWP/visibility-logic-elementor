/**
 * Settings > Visibility Logic: quick search, filters that apply on change, unsaved changes in the save bar.
 * Everything works without this script; it only saves clicks.
 */
( function () {
	'use strict';

	// Quick search: hides rows (tables) or cards ([data-stax-vl-item]) that do not contain the text.
	document.querySelectorAll( '[data-stax-vl-search]' ).forEach( function ( input ) {
		var scope = document.querySelector( input.getAttribute( 'data-stax-vl-search' ) );

		if ( ! scope ) {
			return;
		}

		input.addEventListener( 'input', function () {
			var query = input.value.trim().toLowerCase();
			var items = scope.querySelectorAll( 'tbody tr, [data-stax-vl-item]' );

			items.forEach( function ( item ) {
				item.classList.toggle( 'stax-vl-row-hidden', '' !== query && -1 === item.textContent.toLowerCase().indexOf( query ) );
			} );

			// Hide condition groups with no visible card.
			scope.querySelectorAll( '.stax-vl-group' ).forEach( function ( group ) {
				group.classList.toggle( 'stax-vl-row-hidden', ! group.querySelector( '[data-stax-vl-item]:not(.stax-vl-row-hidden)' ) );
			} );
		} );

		// Enter in the search box must not submit the filters form.
		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
			}
		} );
	} );

	// Filters apply as soon as a select changes.
	document.querySelectorAll( '[data-stax-vl-autosubmit]' ).forEach( function ( form ) {
		form.querySelectorAll( '.stax-vl-js-hide' ).forEach( function ( el ) {
			el.hidden = true;
		} );

		form.querySelectorAll( 'select' ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				form.submit();
			} );
		} );
	} );

	// Save bar: "Unsaved changes" once a field differs from the page load; on/off style of condition cards.
	document.querySelectorAll( '[data-stax-vl-dirty]' ).forEach( function ( form ) {
		var bar = form.querySelector( '.stax-vl-savebar' );
		var state = form.querySelector( '[data-stax-vl-state]' );
		var fields = form.querySelectorAll( 'input[type="checkbox"]:not(:disabled)' );
		var saving = false;

		function changed() {
			var dirty = false;

			fields.forEach( function ( field ) {
				var card = field.closest( '.stax-vl-condition' );

				if ( card ) {
					card.classList.toggle( 'is-off', ! field.checked );
				}

				dirty = dirty || field.checked !== field.defaultChecked;
			} );

			if ( bar ) {
				bar.classList.toggle( 'is-dirty', dirty );
			}

			if ( state ) {
				state.textContent = dirty ? state.getAttribute( 'data-dirty' ) : '';
			}

			return dirty;
		}

		form.addEventListener( 'change', changed );
		form.addEventListener( 'submit', function () {
			saving = true;
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! saving && changed() ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	} );
}() );
