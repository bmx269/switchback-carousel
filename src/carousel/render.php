<?php
/**
 * Carousel: a wrapper around the track and any controls or other blocks.
 *
 * @package SwitchbackCarousel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks.
 * @var WP_Block $block      Block.
 */

defined( 'ABSPATH' ) || exit;

$switchback_carousel_class = 'switchback' . ( empty( $attributes['overlayControls'] ) ? '' : ' has-overlay-controls' );
$switchback_carousel_wrap  = array( 'class' => $switchback_carousel_class );

// The Carousel name setting. view.js gives it to the track as its accessible
// name; without it, view.js falls back to the carousel's heading.
$switchback_carousel_label = trim( wp_strip_all_tags( (string) ( $attributes['label'] ?? '' ) ) );
if ( '' !== $switchback_carousel_label ) {
	$switchback_carousel_wrap['data-switchback-label'] = $switchback_carousel_label;
}

printf(
	'<div %s>%s</div>',
	get_block_wrapper_attributes( $switchback_carousel_wrap ), // phpcs:ignore WordPress.Security.EscapeOutput
	$content // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks.
);
