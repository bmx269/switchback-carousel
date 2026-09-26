<?php
/**
 * Public API.
 *
 * @package SwitchbackCarousel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'switchback_carousel_register_source' ) ) {
	/**
	 * Register a slide source for query-mode carousels. Call it on the
	 * `switchback_carousel_register_sources` action; see includes/sources.php.
	 *
	 * @param string $name Unique source name.
	 * @param array  $args { label, description, controls, callback }.
	 */
	function switchback_carousel_register_source( string $name, array $args ): bool {
		return \SwitchbackCarousel\Sources::add( $name, $args );
	}
}
