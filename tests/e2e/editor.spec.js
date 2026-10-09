/**
 * Editor behaviour.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Insert a carousel and wait for its template (track, slides, controls).
 *
 * @param {Object} editor Editor fixture.
 */
async function insertCarousel( editor ) {
	await editor.insertBlock( { name: 'switchback-carousel/carousel' } );
	await expect
		.poll(
			async () =>
				( await editor.getBlocks() )[ 0 ]?.innerBlocks[ 0 ]?.innerBlocks
					.length
		)
		.toBe( 3 );
}

test.describe( 'Carousel in the editor', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		for ( const title of [ 'E2E Alpha', 'E2E Bravo', 'E2E Charlie' ] ) {
			await requestUtils.createPost( { title, status: 'publish' } );
		}
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'inserting a carousel adds a track with slides and arrows', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost();
		await insertCarousel( editor );

		const [ carousel ] = await editor.getBlocks();
		expect( carousel.name ).toBe( 'switchback-carousel/carousel' );
		expect( carousel.innerBlocks.map( ( block ) => block.name ) ).toEqual( [
			'switchback-carousel/track',
			'switchback-carousel/controls',
		] );
		expect( carousel.innerBlocks[ 0 ].innerBlocks ).toHaveLength( 3 );
		expect( carousel.innerBlocks[ 0 ].innerBlocks[ 0 ].name ).toBe(
			'switchback-carousel/slide'
		);
	} );

	test( 'blocks are grouped under a Switchback Carousel collection', async ( {
		admin,
		page,
	} ) => {
		await admin.createNewPost();
		const collection = await page.evaluate(
			() =>
				window.wp.data.select( 'core/blocks' ).getCollections()[
					'switchback-carousel'
				]?.title
		);
		expect( collection ).toBe( 'Switchback Carousel' );
	} );

	test( 'track and controls line up wide, and controls lay out as a row', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost();
		await insertCarousel( editor );

		const track = editor.canvas.locator(
			'.wp-block-switchback-carousel-track'
		);
		const controls = editor.canvas.locator(
			'.wp-block-switchback-carousel-controls'
		);
		await expect( track ).toHaveClass( /\balignwide\b/ );
		await expect( controls ).toHaveClass( /\balignwide\b/ );
		await expect( controls ).toHaveClass( /\bis-layout-flex\b/ );
	} );
} );
