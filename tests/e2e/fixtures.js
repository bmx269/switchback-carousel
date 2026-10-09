/**
 * Block markup used by the end-to-end tests.
 */

const slide = ( inner ) =>
	`<!-- wp:switchback-carousel/slide --><div class="wp-block-switchback-carousel-slide">${ inner }</div><!-- /wp:switchback-carousel/slide -->`;

const paragraph = ( text ) =>
	`<!-- wp:paragraph --><p>${ text }</p><!-- /wp:paragraph -->`;

/**
 * A carousel.
 *
 * @param {Object} track    Track attributes.
 * @param {string} slides   Slide markup.
 * @param {string} controls Controls markup.
 * @return {string} Block markup.
 */
const carousel = ( track, slides, controls ) =>
	`<!-- wp:switchback-carousel/carousel --><!-- wp:switchback-carousel/track ${ JSON.stringify( track ) } -->${ slides }<!-- /wp:switchback-carousel/track -->${ controls }<!-- /wp:switchback-carousel/carousel -->`;

const controls = ( ...types ) =>
	types
		.map(
			( control ) =>
				`<!-- wp:switchback-carousel/controls {"control":"${ control }"} /-->`
		)
		.join( '' );

/**
 * A Switchback Carousel holding a Query Loop with carousel display on, three
 * posts per view, and arrows.
 *
 * @param {Object} attrs Extra core/query attributes.
 * @return {string} Block markup.
 */
const queryLoopCarousel = ( attrs ) => {
	const query = {
		...attrs,
		query: {
			perPage: 10,
			postType: 'post',
			order: 'asc',
			orderBy: 'title',
			inherit: false,
		},
		switchback: {
			enabled: true,
			layout: { desktop: { columns: 3, step: 1, peek: 0 } },
		},
	};
	return `<!-- wp:switchback-carousel/carousel --><!-- wp:query ${ JSON.stringify(
		query
	) } --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->${ controls(
		'arrows'
	) }<!-- /wp:switchback-carousel/carousel -->`;
};

module.exports = {
	/** Five hand-built slides, two per view, with every kind of control. */
	manualCarousel: carousel(
		{
			layout: {
				desktop: { columns: 2, step: 1, peek: 0 },
				tablet: {},
				mobile: { columns: 1, step: 1, peek: 0 },
			},
		},
		[ 'One', 'Two', 'Three', 'Four', 'Five' ]
			.map( ( text ) => slide( paragraph( `Slide ${ text }` ) ) )
			.join( '' ),
		controls( 'arrows', 'pagination', 'counter', 'progress' )
	),

	/** A Query Loop with carousel display on: posts sorted by title. */
	queryCarousel: queryLoopCarousel( {} ),

	/** The same Query Loop owned by Advanced Query Loop's namespace. */
	aqlCarousel: queryLoopCarousel( { namespace: 'advanced-query-loop' } ),
};
