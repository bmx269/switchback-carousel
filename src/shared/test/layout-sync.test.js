// The engine only needs createBlock/cloneBlock; a plain-object stand-in keeps
// the test independent of @wordpress/blocks and its ESM-only dependencies.
jest.mock(
	'@wordpress/blocks',
	() => {
		let id = 0;
		const createBlock = ( name, attributes = {}, innerBlocks = [] ) => ( {
			clientId: `b${ ++id }`,
			name,
			attributes: { ...attributes },
			innerBlocks,
		} );
		const cloneBlock = ( block ) =>
			createBlock(
				block.name,
				block.attributes,
				block.innerBlocks.map( cloneBlock )
			);
		return { createBlock, cloneBlock };
	},
	{ virtual: true }
);

import { createBlock } from '@wordpress/blocks';
import { mirrorLayout, layoutFingerprint } from '../layout-sync';

describe( 'mirrorLayout', () => {
	it( 'applies source styles and order while keeping target content', () => {
		const source = [
			createBlock( 'core/heading', {
				content: 'Source title',
				level: 2,
			} ),
			createBlock( 'core/paragraph', {
				content: 'Source text',
				fontSize: 'large',
			} ),
		];
		const target = [
			createBlock( 'core/paragraph', { content: 'Target text' } ),
			createBlock( 'core/heading', {
				content: 'Target title',
				level: 4,
			} ),
		];
		const result = mirrorLayout( source, target );
		expect( result.map( ( b ) => b.name ) ).toEqual( [
			'core/heading',
			'core/paragraph',
		] );
		expect( result[ 0 ].attributes ).toMatchObject( {
			content: 'Target title',
			level: 2,
		} );
		expect( result[ 1 ].attributes ).toMatchObject( {
			content: 'Target text',
			fontSize: 'large',
		} );
	} );

	it( 'clones blocks missing from the target and drops extras', () => {
		const source = [
			createBlock( 'core/paragraph', { content: 'A' } ),
			createBlock( 'core/paragraph', { content: 'B' } ),
		];
		const target = [
			createBlock( 'core/paragraph', { content: 'x' } ),
			createBlock( 'core/heading', { content: 'gone' } ),
		];
		const result = mirrorLayout( source, target );
		expect( result.map( ( b ) => b.attributes.content ) ).toEqual( [
			'x',
			'B',
		] );
	} );

	it( 'recurses into structural blocks, which sync all attributes', () => {
		const source = [
			createBlock( 'core/group', { backgroundColor: 'red' }, [
				createBlock( 'core/paragraph', {
					content: 'S',
					align: 'center',
				} ),
			] ),
		];
		const target = [
			createBlock( 'core/group', { backgroundColor: 'blue' }, [
				createBlock( 'core/paragraph', { content: 'T' } ),
			] ),
		];
		const [ group ] = mirrorLayout( source, target );
		expect( group.attributes.backgroundColor ).toBe( 'red' );
		expect( group.innerBlocks[ 0 ].attributes ).toMatchObject( {
			content: 'T',
			align: 'center',
		} );
	} );

	it( 'keeps every attribute of unknown block types', () => {
		const [ block ] = mirrorLayout(
			[ createBlock( 'test/unknown', { label: 'S', color: 'red' } ) ],
			[ createBlock( 'test/unknown', { label: 'T', color: 'blue' } ) ]
		);
		expect( block.attributes ).toMatchObject( {
			label: 'T',
			color: 'blue',
		} );
	} );
} );

describe( 'layoutFingerprint', () => {
	it( 'ignores content changes but not style changes', () => {
		const a = [
			createBlock( 'core/paragraph', {
				content: 'one',
				fontSize: 'small',
			} ),
		];
		const b = [
			createBlock( 'core/paragraph', {
				content: 'two',
				fontSize: 'small',
			} ),
		];
		const c = [
			createBlock( 'core/paragraph', {
				content: 'two',
				fontSize: 'large',
			} ),
		];
		expect( layoutFingerprint( a ) ).toBe( layoutFingerprint( b ) );
		expect( layoutFingerprint( a ) ).not.toBe( layoutFingerprint( c ) );
	} );
} );
