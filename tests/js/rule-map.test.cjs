// JS twin of Rule_Tree (assets/js/rule-map.js) against the golden file written by tests/rule-tree-test.php.
//   node --test tests/js/
const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const path = require( 'node:path' );
const map = require( path.join( __dirname, '../../assets/js/rule-map.js' ) );
const golden = require( path.join( __dirname, '../fixtures/rule-map.golden.json' ) );

// PHP JSON-encodes empty arrays and empty objects the same way ([]): compare through the same normalization.
const norm = ( value ) => JSON.parse( JSON.stringify( value, ( k, v ) => ( v && typeof v === 'object' && ! Array.isArray( v ) && ! Object.keys( v ).length ? [] : v ) ) );

for ( const c of golden.cases ) {
	test( c.label, () => {
		const tree = c.edited || map.legacyToTree( c.values, golden.registry );
		if ( c.tree ) {
			assert.deepEqual( norm( tree ), norm( c.tree ) );
		}
		assert.deepEqual( map.analyze( tree, golden.registry, golden.since ), c.analyze );
		assert.deepEqual( norm( map.toStorage( tree, c.values, golden.registry, golden.since ) ), norm( c.storage ) );
	} );
}
