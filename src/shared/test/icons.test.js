import { ICONS, WEIGHTS, iconMarkup } from '../icons';

describe( 'icon library', () => {
	it( 'has every icon in every weight', () => {
		expect( ICONS.length ).toBeGreaterThan( 0 );
		ICONS.forEach( ( icon ) => {
			WEIGHTS.forEach( ( weight ) => {
				expect( icon.weights[ weight ] ).toMatch( /^<path / );
			} );
		} );
	} );

	it( 'returns markup for a known icon and weight', () => {
		const icon = iconMarkup( 'arrow-right', 'bold' );
		expect( icon.viewBox ).toBe( '0 0 256 256' );
		expect( icon.markup ).toBe(
			ICONS.find( ( item ) => item.slug === 'arrow-right' ).weights.bold
		);
	} );

	it( 'falls back to the caret and to the regular weight', () => {
		expect( iconMarkup( 'missing' ) ).toEqual(
			iconMarkup( 'caret-right' )
		);
		expect( iconMarkup( 'caret-right', 'unknown' ).markup ).toBe(
			iconMarkup( 'caret-right', 'regular' ).markup
		);
	} );
} );
