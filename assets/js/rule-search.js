/**
 * Visibility Logic: user and post search for the rule builder outside the block editor (page panel in Elementor,
 * menu items, widgets). Returns { users, posts } search functions for StaxConditions.createBuilder().
 */
( function ( win ) {
	'use strict';

	function get( url, nonce ) {
		return win.fetch( url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } } ).then( function ( r ) {
			return r.ok ? r.json() : [];
		} ).catch( function () {
			return [];
		} );
	}

	win.staxVisibilitySearch = function ( data ) {
		var users = data.usersUrl ? function ( q ) {
			return get( data.usersUrl + '?search=' + encodeURIComponent( q ), data.restNonce );
		} : null;
		var posts = data.postsUrl ? function ( q ) {
			var url = data.postsUrl + ( data.postsUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'type=post&per_page=20&search=' + encodeURIComponent( q );
			return get( url, data.restNonce ).then( function ( list ) {
				return ( list || [] ).map( function ( p ) {
					return { value: String( p.id ), label: p.title + ' (#' + p.id + ')' };
				} );
			} );
		} : null;

		return { users: users, posts: posts };
	};
}( window ) );
