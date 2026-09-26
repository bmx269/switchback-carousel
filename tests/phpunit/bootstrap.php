<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite (provided by wp-env's
 * tests-cli container at WP_TESTS_DIR) and this plugin.
 *
 * @package SwitchbackCarousel
 */

$switchback_carousel_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

if ( ! file_exists( $switchback_carousel_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test suite in {$switchback_carousel_tests_dir}. Run the tests with `npm run test:php` (wp-env).\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	exit( 1 );
}

// Polyfills for PHPUnit 9 with the WordPress test suite.
require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

require_once $switchback_carousel_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		$plugins = dirname( __DIR__, 3 );
		// Secure Custom Fields, when installed (wp-env adds it), for the SCF field tests.
		if ( file_exists( $plugins . '/secure-custom-fields/secure-custom-fields.php' ) ) {
			require $plugins . '/secure-custom-fields/secure-custom-fields.php';
		}
		require dirname( __DIR__, 2 ) . '/switchback-carousel.php';
	}
);

require $switchback_carousel_tests_dir . '/includes/bootstrap.php';
