<?php
/**
 * Control icons.
 *
 * The icon data in includes/icons.json is generated from the Phosphor Icons package (MIT) by
 * scripts/build-icons.mjs. Every glyph points right; the previous-slide control
 * mirrors it with CSS.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Icon definitions keyed by slug.
 *
 * @return array<string, array{slug:string,label:string,viewBox:string,weights:array<string,string>}>
 */
function icon_library(): array {
	static $library = null;
	if ( null !== $library ) {
		return $library;
	}
	$library = array();
	$data    = json_decode( (string) file_get_contents( DIR . '/includes/icons.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	foreach ( (array) ( $data['icons'] ?? array() ) as $icon ) {
		$library[ $icon['slug'] ] = $icon;
	}
	return $library;
}

/**
 * Markup allowed in a pasted custom icon.
 */
function custom_icon_kses(): array {
	$paint  = array_fill_keys( array( 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'opacity', 'transform', 'fill-rule', 'clip-rule' ), true );
	$shapes = array(
		'path'     => array( 'd' ),
		'circle'   => array( 'cx', 'cy', 'r' ),
		'ellipse'  => array( 'cx', 'cy', 'rx', 'ry' ),
		'rect'     => array( 'x', 'y', 'width', 'height', 'rx', 'ry' ),
		'line'     => array( 'x1', 'y1', 'x2', 'y2' ),
		'polyline' => array( 'points' ),
		'polygon'  => array( 'points' ),
		'g'        => array(),
	);

	$allowed = array(
		'svg' => array_fill_keys( array( 'xmlns', 'viewbox', 'width', 'height', 'fill', 'stroke', 'class' ), true ),
	);
	foreach ( $shapes as $tag => $geometry ) {
		$allowed[ $tag ] = $paint + array_fill_keys( $geometry, true );
	}
	return $allowed;
}

/**
 * SVG for a control icon.
 *
 * @param string $slug   Icon slug from the library, or 'custom'.
 * @param string $weight thin | light | regular | bold | fill.
 * @param string $svg    Custom SVG markup, used when $slug is 'custom'.
 */
function icon_svg( string $slug, string $weight = 'regular', string $svg = '' ): string {
	if ( 'custom' === $slug ) {
		$clean = trim( wp_kses( $svg, custom_icon_kses() ) );
		if ( '' !== $clean ) {
			return '<span class="switchback-icon switchback-icon--custom" aria-hidden="true">' . $clean . '</span>';
		}
		$slug = 'caret-right';
	}

	$library = icon_library();
	$icon    = $library[ $slug ] ?? $library['caret-right'] ?? null;
	if ( ! $icon ) {
		return '';
	}
	$drawing = $icon['weights'][ $weight ] ?? $icon['weights']['regular'];

	return sprintf(
		'<svg class="switchback-icon" xmlns="http://www.w3.org/2000/svg" viewBox="%1$s" fill="currentColor" aria-hidden="true" focusable="false">%2$s</svg>',
		esc_attr( $icon['viewBox'] ),
		wp_kses( $drawing, custom_icon_kses() )
	);
}
