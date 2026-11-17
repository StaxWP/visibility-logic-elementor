/**
 * E2E (Playwright): Settings > Visibility Logic opens the Rules overview; filters; cache check; accessibility; with
 * Visibility Logic Pro 3.0 also the Rule sets tab (create a set with the builder, list with "where used").
 *
 *   VL_BASE=http://vl3.test VL_SHOTS=/dir node tests/e2e/admin-screens.mjs
 *
 * Run after tests/e2e/rule-builder.mjs (it saves rules on the e2e page). Exit code 1 when a check fails.
 */
import { chromium } from 'playwright';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const require = createRequire( import.meta.url );
const BASE = process.env.VL_BASE || 'http://localhost';
const SHOTS = process.env.VL_SHOTS || '';
const AXE = readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );

let failed = 0;
let passed = 0;
const check = ( label, ok, details ) => {
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ label }${ ok || details === undefined ? '' : ' ' + JSON.stringify( details ) }` );
	ok ? passed++ : failed++;
};

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 1000 } } );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );
const shot = async ( name ) => SHOTS && page.screenshot( { path: `${ SHOTS }/${ name }.png`, fullPage: true } );
const axe = async ( selector ) => {
	await page.addScriptTag( { content: AXE } );
	return page.evaluate( async ( sel ) => ( await window.axe.run( document.querySelector( sel ), { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] } ) ).violations.map( ( v ) => v.id + ': ' + v.nodes.length ), selector );
};

try {
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', process.env.VL_USER || 'admin' );
	await page.fill( '#user_pass', process.env.VL_PASS || 'admin' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );

	// Settings > Visibility Logic lands on Rules.
	await page.goto( `${ BASE }/wp-admin/options-general.php` );
	const href = await page.locator( '#adminmenu a', { hasText: 'Visibility Logic' } ).first().getAttribute( 'href' );
	check( 'Settings > Visibility Logic opens the Rules tab', href.indexOf( 'page=stax-visibility-usage' ) !== -1, href );
	await page.goto( `${ BASE }/wp-admin/options-general.php?page=stax-visibility-usage` );
	const firstTab = ( await page.locator( '.stax-vl-panel, body' ).first().evaluate( () => {
		const tabs = [ ...document.querySelectorAll( 'a[href*="page=stax-visibility-"]' ) ].map( ( a ) => a.textContent.trim() ).filter( Boolean );
		return tabs;
	} ) );
	check( 'Rules is the first tab', firstTab.indexOf( 'Rules' ) !== -1 && firstTab.indexOf( 'Rules' ) < firstTab.indexOf( 'Conditions' ), firstTab );
	const rows = await page.locator( '.stax-vl-usage tbody tr' ).count();
	check( 'one row per element with a rule', rows >= 3, rows );
	const words = await page.locator( '.stax-vl-usage .stax-vl-rule' ).allInnerTexts();
	check( 'rules in plain words', words.some( ( w ) => w.indexOf( 'Shown only when (User role is Logged in users and Browser is Google Chrome) or Device is Mobile' ) === 0 ), words.slice( 0, 5 ) );
	check( 'cache check present', ( await page.locator( '.stax-vl-cache' ).count() ) === 1 );
	check( 'axe: Rules screen', ( await axe( '#wpbody-content' ) ).length === 0, await axe( '#wpbody-content' ) );
	await shot( 'admin-1-rules-overview' );

	await page.selectOption( '#stax-vl-f-status', 'draft' );
	await page.click( '.stax-vl-filters button' );
	await page.waitForLoadState( 'networkidle' );
	const draftRows = await page.locator( '.stax-vl-usage tbody tr' ).allInnerTexts();
	check( 'status filter: drafts only', draftRows.length >= 1 && draftRows.every( ( r ) => r.indexOf( 'Hidden from everyone' ) !== -1 ), draftRows );

	// Pro 3.0: Rule sets.
	await page.goto( `${ BASE }/wp-admin/options-general.php?page=stax-visibility-rule-sets` );
	if ( await page.locator( 'h2', { hasText: 'Rule sets' } ).count() ) {
		await page.click( 'text=Add a rule set' );
		await page.fill( '#stax-vl-rs-name', 'Members only (e2e)' );
		await page.locator( '#stax-vl-rule-set-builder .stxc-add' ).first().click();
		await page.locator( '#stax-vl-rule-set-builder .stxc-search' ).fill( 'role' );
		await page.locator( '#stax-vl-rule-set-builder .stxc-option', { hasText: 'User Role' } ).first().click();
		await page.locator( '#stax-vl-rule-set-builder .stxc-check', { hasText: 'Logged in users' } ).locator( 'input' ).check();
		check( 'axe: rule set editor', ( await axe( '#wpbody-content' ) ).length === 0, await axe( '#wpbody-content' ) );
		await shot( 'admin-2-rule-set-editor' );
		await page.click( '.stax-vl-rule-set-form button[type=submit]' );
		await page.waitForLoadState( 'networkidle' );
		const list = await page.locator( '.stax-vl-panel table' ).innerText();
		check( 'rule set saved and listed with its conditions', list.indexOf( 'Members only (e2e)' ) !== -1 && list.indexOf( 'User role is Logged in users' ) !== -1 && list.indexOf( 'Not used yet' ) !== -1, list );
		await shot( 'admin-3-rule-sets' );
		page.once( 'dialog', ( d ) => d.accept() );
		await page.locator( 'tr', { hasText: 'Members only (e2e)' } ).locator( '.stax-vl-rs-delete button' ).click();
		await page.waitForLoadState( 'networkidle' );
		check( 'rule set deleted after confirmation', ( await page.locator( 'tr', { hasText: 'Members only (e2e)' } ).count() ) === 0 );
	} else {
		console.log( 'SKIP Rule sets (Visibility Logic Pro 3.0 not active)' );
	}

	check( 'no JavaScript errors', errors.length === 0, errors );
} catch ( e ) {
	check( 'run', false, { error: e.message } );
	await shot( 'admin-error' );
} finally {
	await browser.close();
}

console.log( `${ passed } passed, ${ failed } failed` );
process.exit( failed ? 1 : 0 );
