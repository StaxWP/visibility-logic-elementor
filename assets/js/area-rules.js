/**
 * Visibility Logic: rule builder for menu items and classic widgets. Each form has a .stax-vl-area field (the rule
 * in words, an "Edit visibility rules" button and a hidden JSON input); the button opens the builder in a dialog,
 * Apply writes { enabled, rules } back to the input and fires `change`, so WordPress enables its Save button.
 * Delegated events: works for forms added later (new menu items, widgets, the Customizer, the Legacy Widget block).
 */
( function ( win, doc, area, SC ) {
	'use strict';

	if ( ! area || ! SC || ! win.HTMLDialogElement ) {
		return;
	}

	var data = area.rules;
	var L = area.labels;

	function labelsFor( kind ) {
		return Object.assign( {}, data.labels, area.builder[ kind ] || {} );
	}

	function read( input ) {
		try {
			var value = input.value ? JSON.parse( input.value ) : null;
			return value && value.rules ? value : null;
		} catch ( e ) {
			return null;
		}
	}

	function summary( value, kind ) {
		if ( ! value ) {
			return L.everyone;
		}
		var sentence = SC.summary.sentence( SC.tree.normalize( value.rules ), data.registry, labelsFor( kind ) );
		return value.enabled ? sentence : L.off.replace( '%s', sentence );
	}

	var dialog = null;
	var builder = null;
	var state = null;

	function close() {
		if ( builder ) {
			builder.destroy();
			builder = null;
		}
		state = null;
		if ( dialog && dialog.open ) {
			dialog.close();
		}
	}

	function makeDialog() {
		dialog = doc.createElement( 'dialog' );
		dialog.className = 'stax-vl-area-dialog';
		dialog.setAttribute( 'aria-labelledby', 'stax-vl-area-dialog-title' );
		dialog.innerHTML = '<div class="stax-vl-area-dialog__head"><h2 id="stax-vl-area-dialog-title"></h2></div>' +
			'<div class="stax-vl-area-dialog__body"><div class="stax-vl-builder"></div></div>' +
			'<div class="stax-vl-area-dialog__foot"><button type="button" class="button" data-act="cancel"></button>' +
			'<button type="button" class="button button-primary" data-act="apply"></button></div>';
		dialog.querySelector( 'h2' ).textContent = L.title;
		dialog.querySelector( '[data-act="cancel"]' ).textContent = L.cancel;
		dialog.querySelector( '[data-act="apply"]' ).textContent = L.apply;
		dialog.addEventListener( 'click', function ( event ) {
			var act = event.target.getAttribute && event.target.getAttribute( 'data-act' );
			if ( act === 'cancel' ) {
				close();
			} else if ( act === 'apply' ) {
				apply();
			}
		} );
		dialog.addEventListener( 'cancel', function ( event ) {
			event.preventDefault();
			close();
		} );
		doc.body.appendChild( dialog );
	}

	function open( field ) {
		if ( ! dialog ) {
			makeDialog();
		}
		close();

		var input = field.querySelector( '.stax-vl-area__value' );
		var kind = field.getAttribute( 'data-kind' ) || 'menu';
		var value = read( input );

		state = {
			field: field,
			input: input,
			kind: kind,
			enabled: value ? value.enabled !== false : true,
			tree: value ? SC.tree.normalize( value.rules ) : SC.tree.make( 'show' ),
		};

		var mount = dialog.querySelector( '.stax-vl-builder' );
		mount.innerHTML = '';
		dialog.showModal();

		builder = SC.createBuilder( mount, {
			registry: data.registry,
			value: state.tree,
			labels: labelsFor( kind ),
			context: data.context,
			proUrl: data.proUrl,
			search: win.staxVisibilitySearch( data ),
			pause: {
				active: state.enabled,
				onChange: function ( active ) {
					state.enabled = !! active;
				},
			},
			onChange: function ( tree ) {
				state.tree = tree;
			},
		} );

		if ( data.users && Object.keys( data.users ).length ) {
			builder.cacheUsers( data.users );
		}
	}

	function apply() {
		if ( ! state ) {
			return;
		}
		var empty = SC.tree.isEmpty( state.tree ) && ! state.tree.draft;
		var value = empty ? null : { enabled: state.enabled, rules: state.tree };
		var text = state.field.querySelector( '.stax-vl-area__summary' );

		state.input.value = value ? JSON.stringify( value ) : '';
		text.textContent = summary( value, state.kind );
		text.classList.toggle( 'is-on', !! ( value && value.enabled ) );
		text.setAttribute( 'title', L.saveNote );

		// Native events reach jQuery handlers too (widget Save button, Customizer, Legacy Widget block).
		state.input.dispatchEvent( new win.Event( 'input', { bubbles: true } ) );
		state.input.dispatchEvent( new win.Event( 'change', { bubbles: true } ) );
		if ( win.jQuery ) {
			win.jQuery( state.input ).trigger( 'change' );
		}

		var button = state.field.querySelector( '.stax-vl-area__edit' );
		close();
		if ( button ) {
			button.focus();
		}
	}

	doc.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '.stax-vl-area__edit' ) : null;
		if ( button ) {
			event.preventDefault();
			open( button.closest( '.stax-vl-area' ) );
		}
	} );
}( window, document, window.staxVisibilityArea, window.StaxConditions ) );
