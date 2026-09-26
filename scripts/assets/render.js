/**
 * Render WordPress.org icon and banner PNGs from the SVG/HTML sources.
 * Usage: node scripts/assets/render.js
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( '@playwright/test' );

const here = __dirname;
const out = path.join( here, '..', '..', '.wordpress-org' );

( async () => {
	const browser = await chromium.launch();

	for ( const size of [ 128, 256 ] ) {
		const page = await browser.newPage( { viewport: { width: size, height: size } } );
		await page.setContent( `<style>html,body{margin:0;background:transparent}svg{display:block;width:${ size }px;height:${ size }px}</style>${ fs.readFileSync( path.join( here, 'icon.svg' ), 'utf8' ) }` );
		await page.waitForLoadState( 'networkidle' );
		await page.screenshot( { path: path.join( out, `icon-${ size }x${ size }.png` ), omitBackground: true } );
		await page.close();
	}

	for ( const [ width, scale ] of [ [ 772, 0.5 ], [ 1544, 1 ] ] ) {
		const page = await browser.newPage( { viewport: { width: 1544, height: 500 }, deviceScaleFactor: scale } );
		await page.goto( 'file://' + path.join( here, 'banner.html' ) );
		await page.locator( '#banner' ).screenshot( { path: path.join( out, `banner-${ width }x${ Math.round( 500 * scale ) }.png` ) } );
		await page.close();
	}

	await browser.close();
} )();
