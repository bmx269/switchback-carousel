import { layoutFor, deviceKey } from '../responsive';

describe( 'layoutFor', () => {
	const layout = {
		desktop: { columns: 5, step: 2, peek: 10 },
		tablet: { columns: 3 },
		mobile: {},
	};

	it( 'returns desktop settings as stored', () => {
		expect( layoutFor( layout, 'Desktop' ) ).toEqual( {
			columns: 5,
			step: 2,
			peek: 10,
		} );
	} );

	it( 'fills tablet gaps from desktop', () => {
		expect( layoutFor( layout, 'Tablet' ) ).toEqual( {
			columns: 3,
			step: 2,
			peek: 10,
		} );
	} );

	it( 'fills mobile gaps with a single column, not desktop values', () => {
		expect( layoutFor( layout, 'Mobile' ) ).toEqual( {
			columns: 1,
			step: 1,
			peek: 0,
		} );
	} );

	it( 'shows one slide when fading', () => {
		expect( layoutFor( layout, 'Desktop', true ) ).toEqual( {
			columns: 1,
			step: 1,
			peek: 0,
		} );
	} );

	it( 'uses defaults for an empty layout', () => {
		expect( layoutFor( {}, 'Desktop' ) ).toEqual( {
			columns: 3,
			step: 1,
			peek: 0,
		} );
	} );
} );

describe( 'deviceKey', () => {
	it( 'maps preview devices to layout keys', () => {
		expect( deviceKey( 'Tablet' ) ).toBe( 'tablet' );
		expect( deviceKey( undefined ) ).toBe( 'desktop' );
	} );
} );
