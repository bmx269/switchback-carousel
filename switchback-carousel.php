<?php
/**
 * Plugin Name:       Switchback Carousel
 * Plugin URI:        https://github.com/bmx269/switchback-carousel
 * Description:       Carousel blocks for WordPress. Build slides by hand, or turn any Query Loop into a carousel. Works with Advanced Query Loop. Built on Splide.
 * Version:           1.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            Trent Stromkins
 * Author URI:        https://github.com/bmx269
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       switchback-carousel
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';
const DIR     = __DIR__;
const BLOCKS  = array( 'carousel', 'track', 'slide', 'controls' );

require_once DIR . '/includes/support.php';
require_once DIR . '/includes/icons.php';
require_once DIR . '/includes/class-tokens.php';
require_once DIR . '/includes/patterns.php';
require_once DIR . '/includes/carousel-state.php';
require_once DIR . '/includes/query-carousel.php';
require_once DIR . '/includes/token-expansion.php';

add_action(
	'init',
	static function (): void {
		foreach ( BLOCKS as $block ) {
			register_block_type( DIR . '/build/' . $block );
		}
	}
);

add_action(
	'enqueue_block_editor_assets',
	static function (): void {
		$handle = generate_block_asset_handle( 'switchback-carousel/track', 'editorScript' );
		if ( wp_script_is( $handle, 'registered' ) ) {
			wp_set_script_translations( $handle, 'switchback-carousel' );
		}
	}
);

/**
 * Front end: Splide's accessibility labels, translated.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$handle = generate_block_asset_handle( 'switchback-carousel/track', 'viewScript' );
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}
		wp_localize_script(
			$handle,
			'switchbackStrings',
			array(
				'carousel'   => _x( 'carousel', 'accessibility role description', 'switchback-carousel' ),
				'slide'      => _x( 'slide', 'accessibility role description', 'switchback-carousel' ),
				/* translators: 1: slide number, 2: number of slides. */
				'slideLabel' => __( '%1$s of %2$s', 'switchback-carousel' ),
				/* translators: %s: page number. */
				'pageX'      => __( 'Go to page %s', 'switchback-carousel' ),
				/* translators: %s: slide number. */
				'slideX'     => __( 'Go to slide %s', 'switchback-carousel' ),
				'prev'       => __( 'Previous slide', 'switchback-carousel' ),
				'next'       => __( 'Next slide', 'switchback-carousel' ),
				'play'       => __( 'Play', 'switchback-carousel' ),
				'pause'      => __( 'Pause', 'switchback-carousel' ),
				'progress'   => __( 'Carousel position', 'switchback-carousel' ),
			)
		);
	},
	20
);
