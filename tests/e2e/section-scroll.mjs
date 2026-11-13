/**
 * E2E (Playwright): opening a section of the Visibility tab keeps the panel where it was and the opened section in view.
 *
 * Needs a test site with Elementor and a page whose first heading widget has "Enable Visibility Logic" on (the
 * General section open). Run from any directory that can import `playwright`:
 *
 *   VL_BASE=http://vlfix.test VL_USER=admin VL_PASS=admin VL_POST=259 node tests/e2e/section-scroll.mjs
 *
 * Optional: VL_SECTIONS="WooCommerce Users,Fallback,ACF Field" (sections to open, default: the last three listed),
 * VL_SHOTS=/path/to/dir (screenshots). Exit code 1 when a check fails.
 */
import { chromium } from 'playwright';

const BASE = process.env.VL_BASE || 'http://localhost';
const POST = process.env.VL_POST;
const SHOTS = process.env.VL_SHOTS || '';

if ( ! POST ) {
	console.error( 'VL_POST (page id) is required' );
	process.exit( 2 );
}

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1400, height: 800 } } );
let failed = 0;

const check = ( label, ok, details ) => {
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ label }${ details ? ' ' + JSON.stringify( details ) : '' }` );
	failed += ok ? 0 : 1;
};

try {
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', process.env.VL_USER || 'admin' );
	await page.fill( '#user_pass', process.env.VL_PASS || 'admin' );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );

	await page.goto( `${ BASE }/wp-admin/post.php?post=${ POST }&action=elementor` );
	const frame = page.frameLocator( '#elementor-preview-iframe' );
	const widget = frame.locator( '.elementor-widget-heading' ).first();

	await widget.waitFor( { timeout: 60000 } );

	for ( let i = 0; i < 5 && ! ( await page.locator( '.elementor-tab-control-stax-visibility' ).count() ); i++ ) {
		await widget.click( { force: true } );
		await page.waitForTimeout( 1500 );
	}

	await page.locator( '.elementor-tab-control-stax-visibility' ).first().click();
	await page.waitForTimeout( 800 );

	const wrapper = page.locator( '#elementor-panel-content-wrapper' );
	const headings = page.locator( '#elementor-controls .elementor-control-type-section .elementor-panel-heading' );
	const titles = await page.locator( '#elementor-controls .elementor-control-type-section .elementor-panel-heading-title' ).allInnerTexts();
	const wanted = process.env.VL_SECTIONS ? process.env.VL_SECTIONS.split( ',' ).map( ( s ) => s.trim() ) : titles.slice( -3 );
	const panelBox = await wrapper.boundingBox();

	for ( const title of wanted ) {
		const heading = headings.filter( { hasText: title } ).first();

		if ( ! ( await heading.count() ) ) {
			check( `section "${ title }" exists`, false );
			continue;
		}

		// Reopen General first so a long section collapses above the clicked one (the case that jumped to the top).
		const general = headings.first();
		if ( ! ( await general.evaluate( ( el ) => el.closest( '.elementor-control-type-section' ).classList.contains( 'e-open' ) ) ) ) {
			await general.click();
			await page.waitForTimeout( 300 );
		}

		await heading.scrollIntoViewIfNeeded();
		const before = await wrapper.evaluate( ( el ) => el.scrollTop );
		const yBefore = ( await heading.boundingBox() ).y;

		await heading.click();
		await page.waitForTimeout( 600 );

		const box = await heading.boundingBox();
		const after = await wrapper.evaluate( ( el ) => el.scrollTop );
		const open = await heading.evaluate( ( el ) => el.closest( '.elementor-control-type-section' ).classList.contains( 'e-open' ) );
		const inView = box && box.y >= panelBox.y && box.y + box.height <= panelBox.y + panelBox.height;

		check( `"${ title }" opens`, open );
		check( `"${ title }" heading stays in view`, inView, { before, after, yBefore, yAfter: box && box.y } );
		check( `"${ title }" panel did not jump to the top`, before === 0 || after > 0, { before, after } );

		if ( SHOTS ) {
			await page.screenshot( { path: `${ SHOTS }/section-scroll-${ title.replace( /\W+/g, '-' ).toLowerCase() }.png` } );
		}
	}
} catch ( e ) {
	check( 'run', false, { error: e.message } );
} finally {
	await browser.close();
}

console.log( failed ? `${ failed } failed` : 'all passed' );
process.exit( failed ? 1 : 0 );
