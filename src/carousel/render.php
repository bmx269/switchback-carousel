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

printf(
	'<div %s>%s</div>',
	get_block_wrapper_attributes( array( 'class' => $switchback_carousel_class ) ), // phpcs:ignore WordPress.Security.EscapeOutput
	$content // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks.
);
