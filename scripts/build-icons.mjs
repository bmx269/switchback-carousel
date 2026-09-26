/**
 * Build includes/icons.json from the Phosphor Icons package (MIT).
 *
 * Switchback Carousel's own arrow selection, in five weights. Each glyph points right;
 * the "previous" control mirrors it in CSS. Run: npm run build:icons
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// The package doesn't export its files, so read them from node_modules.
const root = fileURLToPath( new URL( '../node_modules/@phosphor-icons/core', import.meta.url ) );

const ICONS = [
	[ 'arrow-right', 'Arrow' ],
	[ 'arrow-line-right', 'Arrow to line' ],
	[ 'arrow-fat-right', 'Bold arrow' ],
	[ 'arrow-circle-right', 'Circled arrow' ],
	[ 'caret-right', 'Caret' ],
	[ 'caret-circle-right', 'Circled caret' ],
	[ 'caret-double-right', 'Double caret' ],
];
const WEIGHTS = [ 'thin', 'light', 'regular', 'bold', 'fill' ];

const icons = ICONS.map( ( [ slug, label ] ) => {
	const weights = {};
	for ( const weight of WEIGHTS ) {
		const file = 'regular' === weight ? `${ slug }.svg` : `${ slug }-${ weight }.svg`;
		const svg = readFileSync( join( root, 'assets', weight, file ), 'utf8' );
		// Keep only the drawing; the <svg> wrapper is rebuilt at render time.
		weights[ weight ] = svg.replace( /^<svg[^>]*>/, '' ).replace( /<\/svg>\s*$/, '' ).trim();
	}
	return { slug, label, viewBox: '0 0 256 256', weights };
} );

writeFileSync(
	new URL( '../includes/icons.json', import.meta.url ),
	JSON.stringify( { source: 'Phosphor Icons (MIT) https://phosphoricons.com', icons }, null, '\t' ) + '\n'
);
console.log( `Wrote ${ icons.length } icons × ${ WEIGHTS.length } weights.` );
