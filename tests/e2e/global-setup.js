/**
 * Global setup: the WordPress preset's login/state setup, then restore the
 * test site. PHPUnit reinstalls the database wp-env's test site shares, which
 * resets the active theme and plugins, so the two suites can run in any order.
 */
const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );
const presetSetup = require( '@wordpress/scripts/config/playwright/global-setup.js' );

module.exports = async function globalSetup( config ) {
	await presetSetup( config );

	const { baseURL } = config.projects[ 0 ].use;
	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = await RequestUtils.setup( {
		baseURL,
		storageStatePath: process.env.STORAGE_STATE_PATH,
	} );

	await requestUtils.activateTheme( 'twentytwentyfive' );
	await requestUtils.activatePlugin( 'switchback-carousel' );
	await requestContext.dispose();
};
