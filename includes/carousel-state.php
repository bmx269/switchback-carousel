<?php
/**
 * Carousel state derived from a parsed block tree.
 *
 * Lets descendant blocks (controls) render accurate initial markup server-side
 * instead of waiting for view.js to populate placeholders.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Find the first direct-child track block inside a parsed carousel.
 *
 * @param array $parsed_carousel Parsed carousel block.
 * @return array|null Parsed track block, or null if none.
 */
function find_track( array $parsed_carousel ): ?array {
	foreach ( $parsed_carousel['innerBlocks'] ?? array() as $inner ) {
		if ( 'switchback-carousel/track' === ( $inner['blockName'] ?? '' ) ) {
			return $inner;
		}
	}
	return null;
}

/**
 * Derive the state controls need to render themselves correctly before JS boots.
 * Reads carousel settings from the inner track block and counts its slides.
 *
 * @param array $parsed_carousel Parsed carousel block.
 * @return array{slideCount:int,perPage:int,perMove:int,loop:string,autoplay:bool} State.
 */
function derive_state( array $parsed_carousel ): array {
	$state = array(
		'slideCount' => 0,
		'perPage'    => 1,
		'perMove'    => 1,
		'loop'       => 'none',
		'autoplay'   => false,
	);

	$track = find_track( $parsed_carousel );
	if ( ! $track ) {
		return $state;
	}

	$attrs  = (array) ( $track['attrs'] ?? array() );
	$motion = (array) ( $attrs['motion'] ?? array() );
	$auto   = (array) ( $attrs['autoplay'] ?? array() );
	$layout = (array) ( $attrs['layout'] ?? array() );

	$state['loop']     = one_of( $motion['loop'] ?? 'none', array( 'none', 'loop', 'rewind' ) );
	$state['autoplay'] = ! empty( $auto['enabled'] );

	// Fade shows one slide at a time regardless of columns.
	if ( 'fade' !== ( $motion['transition'] ?? 'slide' ) ) {
		$desktop          = (array) ( $layout['desktop'] ?? array() );
		$state['perPage'] = bounded_int( $desktop['columns'] ?? null, 1, 8, 3 );
		$state['perMove'] = min( bounded_int( $desktop['step'] ?? null, 1, 8, 1 ), $state['perPage'] );
	}

	foreach ( $track['innerBlocks'] ?? array() as $inner ) {
		if ( 'switchback-carousel/slide' === ( $inner['blockName'] ?? '' ) ) {
			++$state['slideCount'];
		}
	}

	return $state;
}

/**
 * How many pages (dots) the carousel exposes. Mirrors view.js's pageCount().
 *
 * @param int    $slide_count Total slides.
 * @param int    $per_page    Visible slides per view.
 * @param int    $per_move    Slides advanced per step.
 * @param string $loop        'none' | 'loop' | 'rewind'.
 */
function page_count( int $slide_count, int $per_page, int $per_move, string $loop ): int {
	if ( $slide_count < 1 ) {
		return 1;
	}
	if ( 'loop' === $loop ) {
		// Looping treats every slide as the start of a page.
		return (int) max( 1, ceil( $slide_count / max( 1, $per_move ) ) );
	}
	$end = max( 0, $slide_count - $per_page );
	return (int) ( floor( $end / max( 1, $per_move ) ) + 1 );
}

/**
 * Inject derived state as hidden attrs on carousel blocks before any descendant
 * renders, so providesContext can broadcast them down the tree.
 */
add_filter(
	'render_block_data',
	static function ( array $parsed ): array {
		if ( 'switchback-carousel/carousel' !== ( $parsed['blockName'] ?? '' ) ) {
			return $parsed;
		}
		$state                         = derive_state( $parsed );
		$parsed['attrs']['slideCount'] = $state['slideCount'];
		$parsed['attrs']['perPage']    = $state['perPage'];
		$parsed['attrs']['perMove']    = $state['perMove'];
		$parsed['attrs']['loop']       = $state['loop'];
		$parsed['attrs']['autoplay']   = $state['autoplay'];
		return $parsed;
	}
);
