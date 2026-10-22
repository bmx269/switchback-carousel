/**
 * Front-end behaviour.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	manualCarousel,
	centeredCarousel,
	queryCarousel,
	aqlCarousel,
} = require( './fixtures' );

let manualPage;
let centeredPage;
let queryPage;
let aqlPage;

test.describe( 'Carousel on the front end', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		for ( const title of [ 'Post A', 'Post B', 'Post C', 'Post D' ] ) {
			await requestUtils.createPost( { title, status: 'publish' } );
		}
		manualPage = await requestUtils.createPage( {
			title: 'Manual carousel',
			status: 'publish',
			content: manualCarousel,
		} );
		centeredPage = await requestUtils.createPage( {
			title: 'Centred carousel',
			status: 'publish',
			content: centeredCarousel,
		} );
		queryPage = await requestUtils.createPage( {
			title: 'Query carousel',
			status: 'publish',
			content: queryCarousel,
		} );
		aqlPage = await requestUtils.createPage( {
			title: 'AQL carousel',
			status: 'publish',
			content: aqlCarousel,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
	} );

	test( 'mounts, labels slides and navigates with every control', async ( {
		page,
	} ) => {
		const errors = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );

		await page.goto( manualPage.link );
		const root = page.locator( '.switchback' );
		const track = root.locator( '.switchback-track' );

		await expect( track ).toHaveClass( /is-ready/ );
		await expect(
			track.locator( '.splide__slide' ).first()
		).toHaveAttribute( 'aria-label', '1 of 5' );

		const prev = root.getByRole( 'button', { name: 'Previous slide' } );
		const next = root.getByRole( 'button', { name: 'Next slide' } );
		const current = root.locator( '.switchback-controls__current' );

		// Two per view, one per move: four positions.
		await expect( prev ).toBeDisabled();
		await expect(
			root.locator( '.switchback-controls__page' )
		).toHaveCount( 4 );
		await expect(
			root.locator( '.switchback-controls__total' )
		).toHaveText( '4' );

		await next.click();
		await expect( current ).toHaveText( '2' );
		await expect( prev ).toBeEnabled();

		await root.locator( '.switchback-controls__page' ).nth( 3 ).click();
		await expect( current ).toHaveText( '4' );
		await expect( next ).toBeDisabled();

		await track.focus();
		await page.keyboard.press( 'ArrowLeft' );
		await expect( current ).toHaveText( '3' );

		await expect(
			root.locator( '.switchback-controls__progress' )
		).toHaveAttribute( 'role', 'slider' );
		expect( errors ).toEqual( [] );
	} );

	test( 'a centred one-up carousel shows exactly one slide', async ( {
		page,
	} ) => {
		await page.goto( centeredPage.link );
		const root = page.locator( '.switchback' );
		const track = root.locator( '.switchback-track' );
		await expect( track ).toHaveClass( /is-ready/ );

		// The active slide fills the viewport edge to edge, with no part of a
		// neighbour showing, and the viewport has no native scroll offset.
		const fit = () =>
			track.evaluate( ( el ) => {
				const viewport = el.querySelector( '.splide__track' );
				const active = el.querySelector(
					'.splide__slide.is-active:not(.splide__slide--clone)'
				);
				const v = viewport.getBoundingClientRect();
				const a = active.getBoundingClientRect();
				return {
					scrollLeft: viewport.scrollLeft,
					left: Math.round( a.left - v.left ),
					right: Math.round( a.right - v.right ),
				};
			} );

		expect( await fit() ).toEqual( { scrollLeft: 0, left: 0, right: 0 } );

		await root.getByRole( 'button', { name: 'Next slide' } ).click();
		await expect(
			track.locator(
				'.splide__slide.is-active:not(.splide__slide--clone)'
			)
		).toHaveText( 'Centred Two' );
		await expect
			.poll( fit )
			.toEqual( { scrollLeft: 0, left: 0, right: 0 } );
	} );

	for ( const [ name, getPage ] of [
		[ 'Query Loop', () => queryPage ],
		[ 'Advanced Query Loop', () => aqlPage ],
	] ) {
		test( `${ name } carousels show one slide per post and navigate`, async ( {
			page,
		} ) => {
			const errors = [];
			page.on( 'pageerror', ( error ) => errors.push( error.message ) );

			await page.goto( getPage().link );
			const root = page.locator( '.switchback' );
			const track = root.locator( '.switchback-track' );
			await expect( track ).toHaveClass( /is-ready/ );
			await expect(
				track.locator(
					'.splide__slide:not(.splide__slide--clone) .wp-block-post-title'
				)
			).toHaveText( [ 'Post A', 'Post B', 'Post C', 'Post D' ] );

			// Three per view, one per move: two positions.
			const prev = root.getByRole( 'button', { name: 'Previous slide' } );
			const next = root.getByRole( 'button', { name: 'Next slide' } );
			await expect( prev ).toBeDisabled();
			await next.click();
			await expect( prev ).toBeEnabled();
			await expect( next ).toBeDisabled();
			expect( errors ).toEqual( [] );
		} );
	}

	test.describe( 'without JavaScript', () => {
		test.use( { javaScriptEnabled: false } );

		test( 'slides stay visible as a scrollable row', async ( { page } ) => {
			await page.goto( manualPage.link );
			const track = page.locator( '.switchback-track' );
			await expect( track ).not.toHaveClass( /is-ready/ );
			await expect( page.getByText( 'Slide One' ) ).toBeVisible();
			const overflow = await track
				.locator( '.splide__track' )
				.evaluate(
					( el ) =>
						el.ownerDocument.defaultView.getComputedStyle( el )
							.overflowX
				);
			expect( overflow ).toBe( 'auto' );
		} );
	} );
} );
