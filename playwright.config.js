/**
 * End-to-end tests: the WordPress Playwright preset, pointed at tests/e2e and
 * this repo's wp-env script. Runs against the wp-env test site (port 8889).
 */
const { defineConfig } = require( '@playwright/test' );
const preset = require( '@wordpress/scripts/config/playwright.config.js' );

module.exports = defineConfig( {
	...preset,
	testDir: './tests/e2e',
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	webServer: { ...preset.webServer, command: 'npm run env:start' },
} );
