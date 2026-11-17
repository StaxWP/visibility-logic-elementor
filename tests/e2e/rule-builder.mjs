/**
 * E2E (Playwright): the rule builder in the Elementor panel.
 *
 * Needs a test site with Elementor and the pages of tests/e2e/setup.php:
 *
 *   wp eval-file tests/e2e/setup.php    # elementor=<id> blocks=<id>
 *   VL_BASE=http://vl3.test VL_USER=admin VL_PASS=admin VL_POST=<elementor id> VL_SHOTS=/dir node tests/e2e/rule-builder.mjs
 *
 * Checks: the 2.x rule of a widget opens as a sentence; canvas label and Navigator badge; accessibility (axe-core
 * on the builder, labelled controls, keyboard: picker opens, filters, adds, Escape returns focus); writes: one group
 * = 2.x keys, OR groups of single conditions = "At least one", (A and B) or C = tree + fail-closed keys; presets;
 * draft; classic settings toggle; saved page on the front end for guests on desktop and mobile.
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
const CHROME_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

if ( ! POST ) {
	console.error( 'VL_POST (Elementor page id from tests/e2e/setup.php) is required' );
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
const settings = ( id ) => page.evaluate( ( elId ) => {
	const c = elementor.getContainer( elId );
	const out = {};
	Object.keys( c.settings.attributes ).filter( ( k ) => k.indexOf( 'stax_visibility_' ) === 0 ).forEach( ( k ) => {
		const v = c.settings.get( k );
		out[ k.slice( 16 ) ] = v && v.toJSON ? v.toJSON() : v;
	} );
	return out;
}, id );
const select = async ( id ) => {
	const frame = page.frameLocator( '#elementor-preview-iframe' );
	const el = frame.locator( `[data-id="${ id }"]` ).first();
	await el.waitFor( { timeout: 60000 } );
	for ( let i = 0; i < 5; i++ ) {
		await el.click( { force: true } );
		await page.waitForTimeout( 900 );
		if ( await page.locator( '.elementor-tab-control-stax-visibility' ).count() ) {
			break;
		}
	}
	await page.locator( '.elementor-tab-control-stax-visibility' ).first().click();
	await page.locator( '.elementor-control-stax_visibility_rules .stxc-summary' ).waitFor();
};
const builder = () => page.locator( '.elementor-control-stax_visibility_rules' );
const settle = () => page.waitForTimeout( 700 );

try {
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', process.env.VL_USER || 'admin' );
	await page.fill( '#user_pass', process.env.VL_PASS || 'admin' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
	await page.goto( `${ BASE }/wp-admin/post.php?post=${ POST }&action=elementor` );
	await page.waitForFunction( () => {
		try {
			return elementor.getPreviewContainer().children.length > 0;
		} catch ( e ) {
			return false;
		}
	}, null, { timeout: 90000 } );

	// 1. A 2.x rule opens as a sentence.
	await select( 'e2ew2' );
	const summary = await builder().locator( '.stxc-summary' ).innerText();
	check( '2.x rule shown as a sentence', summary === 'Shown only when User role is Logged in users', summary );
	const label = await page.frameLocator( '#elementor-preview-iframe' ).locator( '[data-id="e2ew2"]' ).getAttribute( 'data-stax-vl' );
	check( 'canvas label with the short summary', label === 'Logged in users', label );
	const before = await settings( 'e2ew2' );
	await shot( 'elementor-1-legacy-rule' );

	// 2. Accessibility of the builder.
	await page.addScriptTag( { content: AXE } );
	const axe = await page.evaluate( async () => {
		const r = await window.axe.run( document.querySelector( '.elementor-control-stax_visibility_rules' ), { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ], rules: { 'color-contrast': { enabled: true } } } );
		return r.violations.map( ( v ) => v.id + ': ' + v.nodes.length );
	} );
	check( 'axe: no WCAG 2.1 A/AA violations in the builder', axe.length === 0, axe );
	const unlabelled = await builder().evaluate( ( root ) => [ ...root.querySelectorAll( 'input, select, textarea, button' ) ].filter( ( el ) => {
		const name = el.getAttribute( 'aria-label' ) || ( el.labels && el.labels.length && el.labels[ 0 ].textContent.trim() ) || ( el.tagName === 'BUTTON' && el.textContent.trim() );
		return ! name;
	} ).map( ( el ) => el.outerHTML.slice( 0, 80 ) ) );
	check( 'every control has an accessible name', unlabelled.length === 0, unlabelled );

	// 3. Keyboard: open the picker, search, add Device Type, tick Mobile.
	await builder().locator( '.stxc-add' ).first().focus();
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 200 );
	const searchFocused = await page.evaluate( () => document.activeElement && document.activeElement.classList.contains( 'stxc-search' ) );
	check( 'keyboard: Enter opens the picker and focuses the search', searchFocused );
	await page.keyboard.type( 'mobile' );
	await page.waitForTimeout( 200 );
	const options = await builder().locator( '.stxc-option' ).allInnerTexts();
	check( 'search "mobile" finds Device Type (keywords)', options.length >= 1 && options[ 0 ].indexOf( 'Device' ) === 0, options );
	await shot( 'elementor-2-picker-search' );
	await builder().locator( '.stxc-option' ).first().focus();
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 200 );
	const mobileBox = builder().locator( '.stxc-cond.is-open .stxc-check', { hasText: 'Mobile' } ).locator( 'input' );
	await mobileBox.focus();
	await page.keyboard.press( 'Space' );
	await settle();
	let s = await settings( 'e2ew2' );
	check( 'one group: written as 2.x keys (All), no tree', s.device_type_enabled === 'yes' && JSON.stringify( s.device_types ) === '["mobile"]' && ( s.condition_type || 'all' ) === 'all' && ! s.rules, s );
	check( 'untouched 2.x keys kept', JSON.stringify( s.user_role_conditions ) === JSON.stringify( before.user_role_conditions ) );

	// 4. Escape closes the picker and gives focus back.
	await builder().locator( '.stxc-add' ).first().click();
	await page.waitForTimeout( 200 );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 200 );
	const back = await page.evaluate( () => document.activeElement && document.activeElement.classList.contains( 'stxc-add' ) );
	check( 'Escape closes the picker and focuses "Add condition"', back && ( await builder().locator( '.stxc-picker' ).count() ) === 0 );

	// 5. Move Device to a new "or" group: one condition per group = "At least one".
	await builder().locator( '.stxc-cond.is-open select.stxc-move' ).selectOption( 'new' );
	await settle();
	s = await settings( 'e2ew2' );
	check( 'OR groups of single conditions: 2.x "At least one"', s.condition_type === 'one' && ! s.rules, s );
	const sentence2 = await builder().locator( '.stxc-summary' ).innerText();
	check( 'sentence with or', sentence2 === 'Shown only when User role is Logged in users or Device is Mobile', sentence2 );

	// 6. (Logged in and Chrome) or Mobile: tree + fail-closed 2.x keys.
	await builder().locator( '.stxc-group' ).first().locator( '.stxc-add' ).click();
	await builder().locator( '.stxc-search' ).fill( 'browser' );
	await builder().locator( '.stxc-option' ).first().click();
	await builder().locator( '.stxc-cond.is-open .stxc-check', { hasText: 'Chrome' } ).locator( 'input' ).check();
	await settle();
	s = await settings( 'e2ew2' );
	check( '(A and B) or C: stored as a tree', !! ( s.rules && s.rules.groups && s.rules.groups.length === 2 ), s.rules );
	check( 'fail-closed 2.x keys: administrators only', s.show_hide === 'yes' && JSON.stringify( s.user_role_conditions ) === '["administrator"]' && s.device_type_enabled === '' && s.browser_type_enabled === '', s );
	const note = await builder().locator( '.stax-vl-rb-status' ).innerText();
	check( 'tree note shown', note.indexOf( '3.0' ) !== -1, note );
	await shot( 'elementor-3-groups' );

	// 7. Preset on an empty element, then draft.
	await select( 'e2ew3' );
	await builder().locator( '.stxc-add' ).first().click();
	const presets = await builder().locator( '.stxc-preset' ).count();
	check( 'presets offered on an empty rule', presets >= 6, presets );
	await shot( 'elementor-4-presets' );
	await builder().locator( '.stxc-preset[data-preset="no_mobile"]' ).click();
	await settle();
	s = await settings( 'e2ew3' );
	check( 'preset "Hide on mobile": enabled, hide, device mobile', s.enabled === 'yes' && s.show_hide === '' && s.device_type_enabled === 'yes' && JSON.stringify( s.device_types ) === '["mobile"]', s );

	await select( 'e2ew1' );
	await builder().locator( '.stxc-action' ).selectOption( 'draft' );
	await settle();
	s = await settings( 'e2ew1' );
	check( 'draft: tree with draft, rules on', s.enabled === 'yes' && s.rules && s.rules.draft === true, s );

	// 7b. B3 text fallback through "When hidden" on the "Hide on mobile" heading.
	await select( 'e2ew3' );
	await builder().locator( '.stxc-settings select' ).first().selectOption( 'text' );
	await page.waitForTimeout( 300 );
	await builder().locator( '.stxc-settings textarea' ).fill( 'Mobile offer soon' );
	await page.waitForTimeout( 900 );
	s = await settings( 'e2ew3' );
	check( 'When hidden: show a message (text fallback)', s.fallback_enabled === 'yes' && s.fallback_type === 'text' && s.fallback_text === 'Mobile offer soon', s );
	await shot( 'elementor-7-fallback-text' );

	// 7c. B7 Page condition from the picker (category "Page and content"), on the draft heading.
	await select( 'e2ew1' );
	await builder().locator( '.stxc-add' ).first().click();
	await builder().locator( '.stxc-cat', { hasText: 'Page and content' } ).click();
	const pageTypes = await builder().locator( '.stxc-option' ).allInnerTexts();
	check( 'picker category "Page and content" lists Page (free) and the Pro page conditions', pageTypes.some( ( t ) => t.trim() === 'Page' ) && pageTypes.length >= 2, pageTypes );
	await shot( 'elementor-8-picker-page-category' );
	await builder().locator( '.stxc-option', { hasText: /^Page$/ } ).first().click();
	await builder().locator( '.stxc-cond.is-open .stxc-check', { hasText: 'Front page' } ).locator( 'input' ).check();
	await page.waitForTimeout( 800 );
	s = await settings( 'e2ew1' );
	check( 'Page condition stored in the tree (3.0 condition)', !! ( s.rules && JSON.stringify( s.rules ).indexOf( '"page_types":["front"]' ) !== -1 ), s.rules );
	await builder().locator( '.stxc-add' ).first().click();
	await builder().locator( '.stxc-cat', { hasText: 'Shop' } ).click();
	await shot( 'elementor-9-picker-shop-pro' );
	await page.keyboard.press( 'Escape' );

	// 8. Classic settings toggle.
	await builder().locator( '.stax-vl-classic-toggle' ).click();
	const classicVisible = await page.locator( '.elementor-control-stax_visibility_show_hide' ).isVisible();
	check( 'classic settings can be shown', classicVisible );
	await page.locator( '.elementor-control-type-section .elementor-panel-heading' ).first().scrollIntoViewIfNeeded();
	await page.locator( '.stax-vl-classic-toggle' ).first().click().catch( () => {} );
	await page.evaluate( () => {
		document.getElementById( 'elementor-panel' ).classList.remove( 'stax-vl-show-classic' );
		window.sessionStorage.removeItem( 'staxVlClassic' );
	} );

	// 9. Navigator badge.
	await page.evaluate( () => $e.run( 'navigator/open' ) );
	await page.waitForTimeout( 1200 );
	const badges = await page.locator( '#elementor-navigator .stax-vl-nav-badge' ).evaluateAll( ( els ) => els.map( ( el ) => el.title ) );
	check( 'Navigator badges for the 3 elements with rules', badges.length === 3, badges );
	await select( 'e2ew2' );
	await shot( 'elementor-5-navigator' );

	// 9b. Preview as guest on desktop: the members heading is ghosted, the trace says why.
	await select( 'e2ew2' );
	await page.locator( '.stax-vl-preview > summary' ).click();
	await page.locator( '.stax-vl-preview select' ).first().selectOption( 'guest' );
	await page.locator( '.stax-vl-preview select' ).nth( 1 ).selectOption( 'desktop' );
	await page.waitForTimeout( 1500 );
	const ghost = await page.frameLocator( '#elementor-preview-iframe' ).locator( '[data-id="e2ew2"]' ).evaluate( ( el ) => [ el.classList.contains( 'stax-vl-ghost' ), el.getAttribute( 'data-stax-vl' ) ] );
	check( 'preview as guest: hidden element ghosted with "Hidden for Guest"', ghost[ 0 ] && ghost[ 1 ].indexOf( 'Hidden for Guest' ) === 0, ghost );
	const trace = await page.locator( '.stax-vl-preview .stxc-trace' ).innerText();
	check( 'why hidden: trace lists each condition', trace.indexOf( 'Hidden for Guest' ) === 0 && trace.indexOf( 'User role is Logged in users: no' ) !== -1 && trace.indexOf( 'Device is Mobile: no' ) !== -1, trace );
	await shot( 'elementor-6-preview-as-why' );
	await page.locator( '.stax-vl-preview select' ).nth( 1 ).selectOption( 'mobile' );
	await page.waitForTimeout( 1500 );
	const ghostMobile = await page.frameLocator( '#elementor-preview-iframe' ).locator( '[data-id="e2ew2"]' ).evaluate( ( el ) => el.classList.contains( 'stax-vl-ghost' ) );
	const ghostOffer = await page.frameLocator( '#elementor-preview-iframe' ).locator( '[data-id="e2ew3"]' ).evaluate( ( el ) => el.classList.contains( 'stax-vl-ghost' ) );
	check( 'preview as guest on mobile: members shown, "hide on mobile" ghosted', ! ghostMobile && ghostOffer, [ ghostMobile, ghostOffer ] );
	await page.locator( '.stax-vl-preview select' ).first().selectOption( 'me' );
	await page.locator( '.stax-vl-preview select' ).nth( 1 ).selectOption( '' );
	await page.waitForTimeout( 1000 );

	// 10. Save and check the live page.
	await page.evaluate( () => $e.run( 'document/save/update' ) );
	await page.waitForTimeout( 4000 );
	const link = await page.evaluate( () => elementor.config.document.urls.permalink );
	for ( const [ ua, name, w2, w3 ] of [ [ CHROME_UA, 'guest desktop', false, true ], [ MOBILE_UA, 'guest mobile', true, false ] ] ) {
		const ctx = await request.newContext( { userAgent: ua } );
		const html = await ( await ctx.get( link ) ).text();
		await ctx.dispose();
		check( `live page, ${ name }: draft heading absent`, html.indexOf( 'data-id="e2ew1"' ) === -1 );
		check( `live page, ${ name }: (logged in and Chrome) or mobile`, w2 === ( html.indexOf( 'data-id="e2ew2"' ) !== -1 ) );
		check( `live page, ${ name }: hide on mobile`, w3 === ( html.indexOf( 'Summer offer' ) !== -1 ) );
		check( `live page, ${ name }: the message replaces the hidden heading`, w3 !== ( html.indexOf( 'Mobile offer soon' ) !== -1 ) );
	}

	// 11. Front end as the administrator: admin bar "why hidden" and "View this page as".
	await page.goto( link );
	const bar = await page.locator( '#wp-admin-bar-stax-vl > .ab-item' ).innerText();
	check( 'admin bar: "Visibility: 1 hidden here" (the draft)', bar.indexOf( 'Visibility: 1 hidden here' ) !== -1, bar );
	const items = await page.locator( '#wp-admin-bar-stax-vl .ab-submenu .ab-item' ).allTextContents();
	check( 'admin bar: the reason of each element', items.some( ( t ) => t.indexOf( 'Hidden: Heading #e2ew1' ) === 0 ) && items.some( ( t ) => t.indexOf( 'Hidden from everyone.' ) !== -1 ), items.slice( 0, 12 ) );
	const asGuest = await page.locator( '#wp-admin-bar-stax-vl-as-guest a' ).getAttribute( 'href' );
	await page.goto( asGuest );
	const html = await page.content();
	const bar2 = await page.locator( '#wp-admin-bar-stax-vl > .ab-item' ).innerText();
	check( 'view as guest: members heading hidden for the admin too', html.indexOf( 'data-id="e2ew2"' ) === -1 && bar2.indexOf( 'viewing as Guest' ) !== -1, bar2 );
	await page.hover( '#wp-admin-bar-stax-vl' );
	await page.waitForTimeout( 400 );
	await shot( 'frontend-admin-bar-why' );
	const forged = await page.goto( link + ( link.indexOf( '?' ) === -1 ? '?' : '&' ) + 'stax_vl_as=guest&_stax_vl=bad' );
	const forgedHtml = await page.content();
	check( 'view as: an invalid nonce changes nothing', forgedHtml.indexOf( 'data-id="e2ew2"' ) !== -1 && forged.ok() );

	check( 'no JavaScript errors', errors.length === 0, errors );
} catch ( e ) {
	check( 'run', false, { error: e.message } );
	await shot( 'elementor-error' );
} finally {
	await browser.close();
}

console.log( `${ passed } passed, ${ failed } failed` );
process.exit( failed ? 1 : 0 );
