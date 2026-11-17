import {
	deviceKey,
	layoutFor,
	storedLayouts,
	writeDeviceLayout,
} from '../responsive';

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

describe( 'storedLayouts', () => {
	it( 'reads tablet and phone style states over the legacy layout, per field', () => {
		const layout = {
			desktop: { columns: 4, step: 2 },
			tablet: { columns: 3 },
		};
		const style = {
			'@tablet': { step: 1, spacing: { blockGap: '0' } },
			'@mobile': { peek: 10 },
		};
		expect( storedLayouts( layout, style ) ).toEqual( {
			desktop: { columns: 4, step: 2 },
			tablet: { columns: 3, step: 1 },
			mobile: { peek: 10 },
		} );
	} );

	it( 'resolves style states like the legacy layout', () => {
		const layout = { desktop: { columns: 5, step: 2, peek: 10 } };
		const style = { '@tablet': { columns: 3 } };
		expect( layoutFor( layout, 'Tablet', false, style ) ).toEqual( {
			columns: 3,
			step: 2,
			peek: 10,
		} );
		expect( layoutFor( layout, 'Mobile', false, style ) ).toEqual( {
			columns: 1,
			step: 1,
			peek: 0,
		} );
	} );
} );

describe( 'writeDeviceLayout', () => {
	const style = { spacing: { blockGap: '1rem' } };

	it( 'writes the legacy layout and keeps other keys', () => {
		const next = writeDeviceLayout(
			{ layout: { fluid: true, tablet: { columns: 3 } }, style },
			'tablet',
			{ step: 2 },
			false
		);
		expect( next.layout ).toEqual( {
			fluid: true,
			tablet: { columns: 3, step: 2 },
		} );
		expect( next.style ).toEqual( style );
	} );

	it( 'writes core style states, Gallery-style, and clears the legacy device', () => {
		const next = writeDeviceLayout(
			{
				layout: { tablet: { columns: 3 } },
				style: { ...style, '@tablet': { spacing: { margin: '0' } } },
			},
			'tablet',
			{ step: 2 },
			true
		);
		expect( next.layout ).toEqual( {} );
		expect( next.style ).toEqual( {
			spacing: { blockGap: '1rem' },
			'@tablet': { spacing: { margin: '0' }, columns: 3, step: 2 },
		} );
	} );

	it( 'keeps desktop in the layout even with core storage on', () => {
		const next = writeDeviceLayout(
			{ layout: {}, style },
			'desktop',
			{ columns: 4 },
			true
		);
		expect( next.layout ).toEqual( { desktop: { columns: 4 } } );
		expect( next.style ).toEqual( style );
	} );

	it( 'clears a style value that would shadow a legacy write', () => {
		const next = writeDeviceLayout(
			{ layout: {}, style: { '@mobile': { columns: 2 } } },
			'mobile',
			{ step: 2 },
			false
		);
		expect( next.layout ).toEqual( { mobile: { columns: 2, step: 2 } } );
		expect( next.style ).toBeUndefined();
	} );

	it( 'resets a field and removes empty viewport states', () => {
		const next = writeDeviceLayout(
			{ layout: {}, style: { '@mobile': { columns: 2 } } },
			'mobile',
			{ columns: undefined },
			true
		);
		expect( next.style ).toBeUndefined();
	} );
} );
