<?php
/**
 * Carousel track. Hand-built slides sit side by side; view.js mounts Splide on
 * the output using the config stored in data-switchback.
 *
 * For post-driven carousels, use a `core/query` block with
 * `switchback.enabled: true` — see includes/query-carousel.php.
 *
 * @package SwitchbackCarousel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks.
 * @var WP_Block $block      Block.
 */

use function SwitchbackCarousel\bounded_float;
use function SwitchbackCarousel\bounded_int;
use function SwitchbackCarousel\horizontal_gap;
use function SwitchbackCarousel\lazy_slide_images;
use function SwitchbackCarousel\one_of;

defined( 'ABSPATH' ) || exit;

$switchback_carousel_motion   = (array) ( $attributes['motion'] ?? array() );
$switchback_carousel_autoplay = (array) ( $attributes['autoplay'] ?? array() );
$switchback_carousel_edges    = (array) ( $attributes['edges'] ?? array() );
$switchback_carousel_layout   = (array) ( $attributes['layout'] ?? array() );
$switchback_carousel_width    = (array) ( $attributes['slideWidth'] ?? array() );

$switchback_carousel_fade = 'fade' === ( $switchback_carousel_motion['transition'] ?? 'slide' );
$switchback_carousel_loop = one_of( $switchback_carousel_motion['loop'] ?? 'none', array( 'none', 'loop', 'rewind' ) );
$switchback_carousel_rtl  = 'auto' === ( $attributes['direction'] ?? 'auto' ) ? is_rtl() : 'rtl' === $attributes['direction'];

$switchback_carousel_device = static function ( array $settings, array $fallback ): array {
	return array(
		'columns' => bounded_int( $settings['columns'] ?? null, 1, 8, $fallback['columns'] ),
		'step'    => bounded_int( $settings['step'] ?? null, 1, 8, $fallback['step'] ),
		'peek'    => bounded_int( $settings['peek'] ?? null, 0, 45, $fallback['peek'] ),
	);
};

$switchback_carousel_desktop = $switchback_carousel_device(
	(array) ( $switchback_carousel_layout['desktop'] ?? array() ),
	array(
		'columns' => 3,
		'step'    => 1,
		'peek'    => 0,
	)
);
$switchback_carousel_tablet  = $switchback_carousel_device( (array) ( $switchback_carousel_layout['tablet'] ?? array() ), $switchback_carousel_desktop );
$switchback_carousel_mobile  = $switchback_carousel_device(
	(array) ( $switchback_carousel_layout['mobile'] ?? array() ),
	array(
		'columns' => 1,
		'step'    => 1,
		'peek'    => 0,
	)
);

$switchback_carousel_splide_layout = static function ( array $device ) use ( $switchback_carousel_fade, $switchback_carousel_rtl ): array {
	if ( $switchback_carousel_fade ) {
		return array(
			'perPage' => 1,
			'perMove' => 1,
			'padding' => 0,
		);
	}
	$edge = $switchback_carousel_rtl ? 'left' : 'right';
	return array(
		'perPage' => $device['columns'],
		'perMove' => min( $device['step'], $device['columns'] ),
		'padding' => $device['peek'] ? array( $edge => $device['peek'] . '%' ) : 0,
	);
};

$switchback_carousel_gap       = horizontal_gap( $attributes );
$switchback_carousel_interval  = 'continuous' !== ( $switchback_carousel_autoplay['mode'] ?? 'interval' );
$switchback_carousel_autoplays = ! empty( $switchback_carousel_autoplay['enabled'] );
$switchback_carousel_drag      = one_of( $switchback_carousel_motion['drag'] ?? 'snap', array( 'snap', 'free', 'none' ) );

$switchback_carousel_type = 'slide';
if ( $switchback_carousel_fade ) {
	$switchback_carousel_type = 'fade';
} elseif ( 'loop' === $switchback_carousel_loop || ( $switchback_carousel_autoplays && ! $switchback_carousel_interval ) ) {
	$switchback_carousel_type = 'loop';
}

$switchback_carousel_options = array_merge(
	$switchback_carousel_splide_layout( $switchback_carousel_desktop ),
	array(
		'type'          => $switchback_carousel_type,
		'rewind'        => 'rewind' === $switchback_carousel_loop,
		'gap'           => '' !== $switchback_carousel_gap ? $switchback_carousel_gap : '1.5rem',
		'focus'         => 'center' === ( $attributes['focus'] ?? 'start' ) ? 'center' : 0,
		'trimSpace'     => 'center' !== ( $attributes['focus'] ?? 'start' ),
		'omitEnd'       => 'slide' === $switchback_carousel_type,
		'speed'         => bounded_int( $switchback_carousel_motion['duration'] ?? 400, 0, 2000, 400 ),
		'drag'          => 'none' === $switchback_carousel_drag ? false : ( 'free' === $switchback_carousel_drag ? 'free' : true ),
		'snap'          => true,
		'wheel'         => ! empty( $switchback_carousel_motion['wheel'] ),
		'keyboard'      => 'focused',
		'direction'     => $switchback_carousel_rtl ? 'rtl' : 'ltr',
		'arrows'        => false,
		'pagination'    => false,
		'autoplay'      => $switchback_carousel_autoplays && $switchback_carousel_interval,
		'interval'      => bounded_int( $switchback_carousel_autoplay['interval'] ?? 5000, 1000, 60000, 5000 ),
		'pauseOnHover'  => ! empty( $switchback_carousel_autoplay['pauseOnHover'] ),
		'pauseOnFocus'  => true,
		'reducedMotion' => array(
			'speed'       => 0,
			'rewindSpeed' => 0,
			'autoplay'    => 'pause',
		),
		'breakpoints'   => array(
			781 => $switchback_carousel_splide_layout( $switchback_carousel_tablet ),
			480 => $switchback_carousel_splide_layout( $switchback_carousel_mobile ),
		),
	)
);

if ( $switchback_carousel_autoplays && ! $switchback_carousel_interval ) {
	$switchback_carousel_options['autoScroll'] = array(
		'speed'        => bounded_float( $switchback_carousel_autoplay['speed'] ?? 1, 0.1, 10, 1 ),
		'pauseOnHover' => ! empty( $switchback_carousel_autoplay['pauseOnHover'] ),
		'pauseOnFocus' => true,
	);
	$switchback_carousel_options['drag']       = 'free';
}

// Lazy-loaded images: Splide fills in later slides' images as they near the view.
$switchback_carousel_slides = $content;
if ( ! empty( $attributes['lazyImages'] ) ) {
	$switchback_carousel_options['lazyLoad'] = 'nearby';
	$switchback_carousel_slides              = lazy_slide_images( $content, (int) $switchback_carousel_options['perPage'] );
}

$switchback_carousel_config = array(
	'splide'   => $switchback_carousel_options,
	'minWidth' => bounded_int( $switchback_carousel_width['min'] ?? 0, 0, 800, 0 ),
);

$switchback_carousel_classes = array( 'switchback-track', 'splide' );
if ( ! empty( $switchback_carousel_edges['overflow'] ) ) {
	$switchback_carousel_classes[] = 'has-visible-overflow';
}
$switchback_carousel_fade_edges = one_of( $switchback_carousel_edges['fade'] ?? 'none', array( 'none', 'both', 'end' ) );
if ( 'none' !== $switchback_carousel_fade_edges ) {
	$switchback_carousel_classes[] = 'has-faded-edges-' . $switchback_carousel_fade_edges;
}
if ( ! empty( $switchback_carousel_motion['grabCursor'] ) ) {
	$switchback_carousel_classes[] = 'has-grab-cursor';
}

$switchback_carousel_styles = array(
	'--switchback-columns:' . $switchback_carousel_desktop['columns'],
	'--switchback-columns-desktop:' . $switchback_carousel_desktop['columns'],
	'--switchback-columns-tablet:' . $switchback_carousel_tablet['columns'],
	'--switchback-columns-mobile:' . $switchback_carousel_mobile['columns'],
	'--switchback-gap:' . $switchback_carousel_options['gap'],
);
$switchback_carousel_max    = bounded_int( $switchback_carousel_width['max'] ?? 0, 0, 1600, 0 );
if ( $switchback_carousel_max ) {
	$switchback_carousel_styles[] = '--switchback-max-width:' . $switchback_carousel_max . 'px';
}

printf(
	'<div %1$s><div class="splide__track"><div class="splide__list">%2$s</div></div></div>',
	get_block_wrapper_attributes( // phpcs:ignore WordPress.Security.EscapeOutput
		array(
			'class'           => implode( ' ', $switchback_carousel_classes ),
			'style'           => implode( ';', $switchback_carousel_styles ),
			'dir'             => $switchback_carousel_rtl ? 'rtl' : 'ltr',
			'aria-label'      => __( 'Carousel', 'switchback-carousel' ),
			'data-switchback' => wp_json_encode( $switchback_carousel_config ),
		)
	),
	$switchback_carousel_slides // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks.
);
