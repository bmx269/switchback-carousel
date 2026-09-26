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

	/** Posts sorted by title, one slide each. */
	queryCarousel: carousel(
		{
			mode: 'query',
			query: {
				postType: 'post',
				limit: 10,
				sort: { by: 'title', direction: 'asc', field: '' },
			},
			layout: {
				desktop: { columns: 3, step: 1, peek: 0 },
				tablet: {},
				mobile: {},
			},
		},
		slide( '<!-- wp:post-title /-->' ),
		controls( 'arrows' )
	),
};
