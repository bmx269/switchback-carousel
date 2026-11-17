/**
 * PostCSS for wp-scripts. Stylesheets in src/ use native CSS nesting;
 * postcss-nesting flattens it so browsers without nesting support (before
 * Chrome 120 / Safari 17.2 / Firefox 117) still get every rule.
 *
 * A project config replaces wp-scripts' built-in one, so this repeats
 * WordPress's preset (postcss-import, autoprefixer with the WordPress
 * browserslist) and its production minification.
 */
const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
	plugins: [
		require( 'postcss-nesting' )(),
		...require( '@wordpress/postcss-plugins-preset' ),
		...( isProduction
			? [
					require( 'cssnano' )( {
						preset: [ 'default', { discardComments: { removeAll: true } } ],
					} ),
			  ]
			: [] ),
	],
};
