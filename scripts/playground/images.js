/**
 * Generate the landscape images the Playground demo uses for featured images,
 * hero covers and the gallery. Output is committed to playground/images so
 * blueprint.json can fetch it from GitHub.
 *
 * Usage: node scripts/playground/images.js
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( '@playwright/test' );

const out = path.join( __dirname, '..', '..', 'playground', 'images' );
const WIDTH = 1600;
const HEIGHT = 1000;

// [ sky top, sky bottom, far hills, near hills, sun ]
const SCENES = [
	[ '#F6B77A', '#F7E3C3', '#9A6B5B', '#4E3B3F', '#FFF3D6' ],
	[ '#7FB3D5', '#D6EAF8', '#5D8A76', '#2F5446', '#FFFFFF' ],
	[ '#2C3E66', '#E58E73', '#6B4E71', '#2B2440', '#FFD6A0' ],
	[ '#A9D3C4', '#EEF6E8', '#7BA487', '#3F6B53', '#FFF8E1' ],
	[ '#F3C6A5', '#FBEDE1', '#C47F5E', '#7A3F31', '#FFF1E0' ],
	[ '#9DB8E0', '#E7EEF8', '#8494B5', '#48557A', '#FFFFFF' ],
	[ '#1F3A4D', '#557A8C', '#2E5A5E', '#16312F', '#F4E3B1' ],
	[ '#F7D488', '#FCEFD0', '#D08C4B', '#8E5130', '#FFFBEA' ],
	[ '#121826', '#3B3F6B', '#2A2D4F', '#14162B', '#E8E6FF' ],
	[ '#E7B7C8', '#F8E6EC', '#A77A9A', '#5E4060', '#FFF4F7' ],
];

function scene( [ top, bottom, far, near, sun ], seed ) {
	const r = ( n ) => ( ( seed * 97 + n * 31 ) % 100 ) / 100;
	const ridge = ( base, amp, count, offset ) => {
		let d = `M0 ${ HEIGHT } L0 ${ base }`;
		for ( let i = 0; i <= count; i++ ) {
			const x = ( WIDTH / count ) * i;
			const y =
				base -
				amp * ( 0.4 + r( i + offset ) * 0.6 ) * ( i % 2 ? 1 : 0.35 );
			d += ` L${ x.toFixed( 0 ) } ${ y.toFixed( 0 ) }`;
		}
		return d + ` L${ WIDTH } ${ HEIGHT } Z`;
	};
	const trailX = 520 + r( 5 ) * 400;
	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${ WIDTH } ${ HEIGHT }" width="${ WIDTH }" height="${ HEIGHT }">
		<defs><linearGradient id="s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${ top }"/><stop offset="1" stop-color="${ bottom }"/></linearGradient></defs>
		<rect width="${ WIDTH }" height="${ HEIGHT }" fill="url(#s)"/>
		<circle cx="${ 300 + r( 1 ) * 1000 }" cy="${ 240 + r( 2 ) * 150 }" r="90" fill="${ sun }" opacity="0.9"/>
		<path d="${ ridge( 650, 330, 7, 3 ) }" fill="${ far }"/>
		<path d="${ ridge( 820, 250, 9, 11 ) }" fill="${ near }"/>
		<path d="M${ trailX } ${ HEIGHT } L${ trailX + 420 } 925 L${ trailX + 180 } 880 L${ trailX + 400 } 845" fill="none" stroke="${ sun }" stroke-width="9" stroke-linecap="round" stroke-linejoin="round" opacity="0.75"/>
	</svg>`;
}

( async () => {
	fs.mkdirSync( out, { recursive: true } );
	const browser = await chromium.launch();
	const page = await browser.newPage( {
		viewport: { width: WIDTH, height: HEIGHT },
	} );
	for ( const [ index, colours ] of SCENES.entries() ) {
		await page.setContent(
			`<style>html,body{margin:0}</style>${ scene( colours, index + 3 ) }`
		);
		await page.screenshot( {
			path: path.join( out, `scene-${ index + 1 }.jpg` ),
			type: 'jpeg',
			quality: 78,
		} );
	}
	await browser.close();
} )();
