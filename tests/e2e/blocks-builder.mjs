/**
 * E2E (Playwright): the rule builder in the block editor ("Visibility rules" panel), List View badges, saving.
 *
 *   wp eval-file tests/e2e/setup.php    # elementor=<id> blocks=<id>
 *   VL_BASE=http://vl3.test VL_POST=<blocks id> VL_SHOTS=/dir node tests/e2e/blocks-builder.mjs
 *
 * Exit code 1 when a check fails. Needs `playwright` and `axe-core` importable.
 */
import { chromium, request } from 'playwright';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const require = createRequire( import.meta.url );
const BASE = process.env.VL_BASE || 'http://localhost';
const POST = process.env.VL_POST;
const SHOTS = process.env.VL_SHOTS || '';
const AXE = readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );
const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

if ( ! POST ) {
	console.error( 'VL_POST (block editor page id from tests/e2e/setup.php) is required' );
	process.exit( 2 );
}

let failed = 0;
let passed = 0;
const check = ( label, ok, details ) => {
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ label }${ ok || details === undefined ? '' : ' ' + JSON.stringify( details ) }` );
	ok ? passed++ : failed++;
};

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );
const shot = async ( name ) => SHOTS && page.screenshot( { path: `${ SHOTS }/${ name }.png` } );
const attrs = ( index ) => page.evaluate( ( i ) => {
	const blocks = wp.data.select( 'core/block-editor' ).getBlocks();
	return blocks[ i ].attributes.staxVisibility || null;
}, index );
const selectBlock = async ( index ) => {
	await page.evaluate( ( i ) => {
		const blocks = wp.data.select( 'core/block-editor' ).getBlocks();
		wp.data.dispatch( 'core/block-editor' ).selectBlock( blocks[ i ].clientId );
	}, index );
	await page.waitForTimeout( 600 );
	const panel = page.locator( '.stax-vl-panel' );
	if ( ! ( await panel.locator( '.stxc-summary' ).count() ) ) {
		await panel.locator( '.components-panel__body-toggle' ).first().click();
	}
	await panel.locator( '.stxc-summary' ).waitFor();
	return panel;
};

try {
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', process.env.VL_USER || 'admin' );
	await page.fill( '#user_pass', process.env.VL_PASS || 'admin' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
	await page.goto( `${ BASE }/wp-admin/post.php?post=${ POST }&action=edit` );
	await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/block-editor' ).getBlocks().length > 2, null, { timeout: 90000 } );
	await page.evaluate( () => {
		try {
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
		} catch ( e ) {}
		wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
	} );

	// 1. The 2.x block rule opens as a sentence, panel name does not clash with core "Hide".
	let panel = await selectBlock( 1 );
	const title = await panel.locator( '.components-panel__body-toggle' ).first().innerText();
	check( 'panel named "Visibility rules"', title.trim() === 'Visibility rules', title );
	const summary = await panel.locator( '.stxc-summary' ).innerText();
	check( '2.x block rule as a sentence', summary === 'Shown only when User role is Logged in users', summary );
	await shot( 'blocks-1-panel' );

	// 2. Accessibility.
	await page.addScriptTag( { content: AXE } );
	const axe = await page.evaluate( async () => ( await window.axe.run( document.querySelector( '.stax-vl-panel' ), { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] } ) ).violations.map( ( v ) => v.id + ': ' + v.nodes.length ) );
	check( 'axe: no WCAG 2.1 A/AA violations in the panel', axe.length === 0, axe );

	// 3. Add a second group (device mobile): "At least one".
	await panel.locator( '.stxc-add-group' ).click();
	await panel.locator( '.stxc-search' ).fill( 'device' );
	await panel.locator( '.stxc-option' ).first().click();
	await panel.locator( '.stxc-cond.is-open .stxc-check', { hasText: 'Mobile' } ).locator( 'input' ).check();
	await page.waitForTimeout( 600 );
	let a = await attrs( 1 );
	check( 'block: OR groups of single conditions written as 2.x keys', a.condition_type === 'one' && a.device_type_enabled === true && ! a.rules, a );

	// 4. Yearly period (3.0 option): tree with fail-closed keys.
	panel = await selectBlock( 2 );
	await panel.locator( '.stxc-add' ).first().click();
	await panel.locator( '.stxc-search' ).fill( 'season' );
	await panel.locator( '.stxc-option' ).first().click();
	await panel.locator( '.stxc-cond.is-open select' ).first().selectOption( 'yearly' );
	await panel.locator( '.stxc-cond.is-open input[placeholder="12-20"]' ).fill( '01-01' );
	await panel.locator( '.stxc-cond.is-open input[placeholder="01-11"]' ).fill( '12-31' );
	await page.waitForTimeout( 700 );
	a = await attrs( 2 );
	check( 'block: yearly period stored as a tree, rules on', a.enabled === true && a.rules && a.rules.groups[ 0 ].conditions[ 0 ].values.date_time_type === 'yearly', a );
	check( 'block: fail-closed keys next to the tree', a.show_hide === 'yes' && a.user_role_enabled === true && JSON.stringify( a.user_role_conditions ) === '["administrator"]', a );
	const s2 = await panel.locator( '.stxc-summary' ).innerText();
	check( 'yearly summary', s2 === 'Shown only when Every year from 01-01 to 12-31', s2 );
	await shot( 'blocks-2-yearly' );

	// 4b. B3 text fallback on the members paragraph.
	panel = await selectBlock( 1 );
	await panel.locator( '.stxc-settings select' ).first().selectOption( 'text' );
	await page.waitForTimeout( 300 );
	await panel.locator( '.stxc-settings textarea' ).fill( 'Log in to read more' );
	await page.waitForTimeout( 700 );
	a = await attrs( 1 );
	check( 'block: text fallback', a.fallback_enabled === true && a.fallback_text === 'Log in to read more', a );
	await shot( 'blocks-4-fallback-text' );

	// 5. List View badges.
	await page.evaluate( () => wp.data.dispatch( 'core/editor' ).setIsListViewOpened( true ) );
	await page.waitForTimeout( 1200 );
	const badges = await page.locator( '.stax-vl-list-badge' ).allInnerTexts();
	check( 'List View badges on the 2 blocks with rules', badges.length === 2, badges );
	await shot( 'blocks-3-list-view' );

	// 6. Save and check the live page.
	await page.evaluate( () => wp.data.dispatch( 'core/editor' ).savePost() );
	await page.waitForFunction( () => ! wp.data.select( 'core/editor' ).isSavingPost(), null, { timeout: 30000 } );
	await page.waitForTimeout( 1000 );
	const link = await page.evaluate( () => wp.data.select( 'core/editor' ).getPermalink() );
	for ( const [ ua, name, members ] of [ [ undefined, 'guest desktop', false ], [ MOBILE_UA, 'guest mobile', true ] ] ) {
		const ctx = await request.newContext( ua ? { userAgent: ua } : {} );
		const html = await ( await ctx.get( link ) ).text();
		await ctx.dispose();
		check( `live page, ${ name }: logged in or mobile`, members === ( html.indexOf( 'Members paragraph' ) !== -1 ) );
		check( `live page, ${ name }: message instead of the hidden paragraph`, members !== ( html.indexOf( 'Log in to read more' ) !== -1 ) );
		check( `live page, ${ name }: yearly whole-year range shown`, html.indexOf( 'Offer paragraph' ) !== -1 );
		check( `live page, ${ name }: plain paragraph`, html.indexOf( 'Plain paragraph' ) !== -1 );
	}

	check( 'no JavaScript errors', errors.length === 0, errors );
} catch ( e ) {
	check( 'run', false, { error: e.message } );
	await shot( 'blocks-error' );
} finally {
	await browser.close();
}

console.log( `${ passed } passed, ${ failed } failed` );
process.exit( failed ? 1 : 0 );
