/**
 * Capture the WordPress.org screenshots from the wp-env dev site.
 *
 * 1. npm run env:start (wp-env installs Advanced Query Loop)
 * 2. node scripts/screenshots/images.js
 * 3. npx wp-env run cli --env-cwd=wp-content/plugins/switchback-carousel wp eval-file scripts/screenshots/seed.php
 * 4. node scripts/screenshots/capture.js
 */
const path = require( 'path' );
const { chromium } = require( '@playwright/test' );

const BASE = process.env.WP_BASE_URL || 'http://localhost:8888';
const out = path.join( __dirname, '..', '..', '.wordpress-org' );
const shot = ( name ) => path.join( out, name );

async function pageId( page, slug ) {
	// Published pages are public; an empty nonce would make REST reject the request.
	const response = await page.request.get( `${ BASE }/?rest_route=/wp/v2/pages&slug=${ slug }&_fields=id` );
	return ( await response.json() )[ 0 ].id;
}

async function openEditor( page, slug ) {
	await page.goto( `${ BASE }/wp-admin/` );
	const id = await pageId( page, slug );
	await page.goto( `${ BASE }/wp-admin/post.php?post=${ id }&action=edit` );
	await page.waitForFunction( () => window.wp?.data?.select( 'core/block-editor' )?.getBlocks().length > 0 );
	await page.evaluate( () => {
		const { dispatch } = window.wp.data;
		dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
		dispatch( 'core/preferences' ).set( 'core', 'fixedToolbar', false );
		dispatch( 'core/edit-post' ).openGeneralSidebar?.( 'edit-post/block' );
	} );
	// Let the editor preview fetch posts and Splide mount.
	await page.waitForTimeout( 2500 );
}

async function select( page, name, index = 0 ) {
	await page.evaluate(
		( [ blockName, at ] ) => {
			const { select: read, dispatch } = window.wp.data;
			const ids = read( 'core/block-editor' ).getBlocksByName( blockName );
			dispatch( 'core/block-editor' ).selectBlock( ids[ at ] );
		},
		[ name, index ]
	);
	await page.waitForTimeout( 800 );
}

async function sidebarScrollTo( page, text ) {
	await page
		.locator( '.interface-complementary-area' )
		.getByText( text, { exact: true } )
		.first()
		.evaluate( ( el ) => {
			el.scrollIntoView( { block: 'start' } );
			// Clear the sticky block card and tabs at the top of the sidebar.
			el.closest( '.interface-complementary-area' )?.scrollBy( 0, -56 );
		} );
}

async function panel( page, title, open = true ) {
	const toggle = page.locator( '.interface-complementary-area .components-panel__body-toggle', { hasText: title } ).first();
	const expanded = ( await toggle.getAttribute( 'aria-expanded' ) ) === 'true';
	if ( expanded !== open ) {
		await toggle.click();
		await toggle.blur();
	}
}

( async () => {
	const browser = await chromium.launch();
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	const page = await context.newPage();

	// Log in.
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );

	// 1. Front end posts carousel with overlay arrows.
	const front = await browser.newPage( { viewport: { width: 1280, height: 720 } } );
	await front.goto( `${ BASE }/?pagename=trail-journal` );
	await front.waitForSelector( '.switchback-track.is-ready' );
	await front.locator( '.switchback-controls__next' ).first().hover();
	await front.waitForTimeout( 400 );
	const journal = await front.locator( 'h1.wp-block-post-title' ).boundingBox();
	await front.screenshot( { path: shot( 'screenshot-1.png' ), clip: { x: 0, y: journal.y - 48, width: 1280, height: 640 } } );

	// 4. Feature cards and quote rotator.
	await front.setViewportSize( { width: 1280, height: 1000 } );
	await front.goto( `${ BASE }/?pagename=trail-tips` );
	await front.waitForSelector( '.switchback-track.is-ready' );
	await front.mouse.move( 0, 0 );
	const tips = await front.locator( 'h1.wp-block-post-title' ).boundingBox();
	await front.screenshot( { path: shot( 'screenshot-4.png' ), clip: { x: 0, y: tips.y - 48, width: 1280, height: 760 } } );
	await front.close();

	// 2. A Query Loop shown as a carousel: the Carousel display panel.
	await openEditor( page, 'trail-journal' );
	await select( page, 'core/query' );
	await panel( page, 'Carousel display', true );
	await sidebarScrollTo( page, 'Carousel display' );
	await page.waitForTimeout( 500 );
	await page.screenshot( { path: shot( 'screenshot-2.png' ) } );

	// 3. Advanced Query Loop's meta query filtering the same carousel.
	await panel( page, 'Carousel display', false );
	await page.getByRole( 'button', { name: 'Open Post Meta query builder' } ).click();
	await page.waitForTimeout( 800 );
	await page.screenshot( { path: shot( 'screenshot-3.png' ) } );
	await page.keyboard.press( 'Escape' );

	// 5. Controls and the icon picker.
	await openEditor( page, 'trail-tips' );
	await select( page, 'switchback-carousel/controls' );
	await page.screenshot( { path: shot( 'screenshot-5.png' ) } );

	// 6. Layout per device.
	await select( page, 'switchback-carousel/track' );
	await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).setDeviceType( 'Tablet' ) );
	await page.waitForTimeout( 1500 );
	await panel( page, 'Slides', false );
	await panel( page, 'Layout', true );
	await page.screenshot( { path: shot( 'screenshot-6.png' ) } );
	await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).setDeviceType( 'Desktop' ) );

	await browser.close();
} )();
