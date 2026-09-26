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

	test( 'switching a track to posts previews the posts', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();
		await insertCarousel( editor );

		// Select the track and switch its content to posts from the sidebar.
		await page.evaluate( () => {
			const [ carousel ] = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks();
			window.wp.data
				.dispatch( 'core/block-editor' )
				.selectBlock( carousel.innerBlocks[ 0 ].clientId );
		} );
		await editor.openDocumentSettingsSidebar();
		await page
			.getByRole( 'region', { name: 'Editor settings' } )
			.getByRole( 'radio', { name: 'From posts' } )
			.click();

		const canvas = editor.canvas;
		await expect( canvas.getByText( 'E2E Alpha' ).first() ).toBeVisible();
		await expect( canvas.getByText( 'E2E Charlie' ).first() ).toBeVisible();

		const [ carousel ] = await editor.getBlocks();
		const track = carousel.innerBlocks[ 0 ];
		expect( track.attributes.mode ).toBe( 'query' );
		// The untouched placeholder slide was swapped for a post design.
		expect(
			track.innerBlocks[ 0 ].innerBlocks.map( ( block ) => block.name )
		).toContain( 'core/post-title' );
	} );
} );
