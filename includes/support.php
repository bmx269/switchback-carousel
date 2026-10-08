<?php
/**
 * Shared utilities.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a spacing value from block attributes into CSS.
 *
 * Accepts a preset reference ("var:preset|spacing|40"), a CSS length, or a
 * custom property; anything else is dropped.
 *
 * @param mixed $value Raw value.
 */
function css_length( $value ): string {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = trim( $value );

	$preset = 'var:preset|spacing|';
	if ( 0 === strpos( $value, $preset ) ) {
		$slug = strtolower( (string) preg_replace( '/[^a-zA-Z0-9]+/', '-', substr( $value, strlen( $preset ) ) ) );
		return '' === trim( $slug, '-' ) ? '' : 'var(--wp--preset--spacing--' . trim( $slug, '-' ) . ')';
	}

	$is_length = (bool) preg_match( '/^-?(\d+|\d*\.\d+)(px|r?em|%|vw|vh|ch)$/', $value );
	$is_var    = (bool) preg_match( '/^var\(--[\w-]+\)$/', $value );

	return '0' === $value || $is_length || $is_var ? $value : '';
}

/**
 * The horizontal gap a block's spacing support stores in style.spacing.blockGap.
 *
 * @param array $attributes Block attributes.
 */
function horizontal_gap( array $attributes ): string {
	$gap = $attributes['style']['spacing']['blockGap'] ?? '';
	if ( is_array( $gap ) ) {
		$gap = $gap['left'] ?? $gap['top'] ?? '';
	}
	return css_length( $gap );
}

/**
 * Read an integer attribute within bounds.
 *
 * @param mixed $value   Raw value.
 * @param int   $lowest  Lower bound.
 * @param int   $highest Upper bound.
 * @param int   $fallback Value used when $value isn't numeric.
 */
function bounded_int( $value, int $lowest, int $highest, int $fallback ): int {
	return is_numeric( $value ) ? max( $lowest, min( $highest, (int) round( (float) $value ) ) ) : $fallback;
}

/**
 * Read a float attribute within bounds.
 *
 * @param mixed $value   Raw value.
 * @param float $lowest  Lower bound.
 * @param float $highest Upper bound.
 * @param float $fallback Value used when $value isn't numeric.
 */
function bounded_float( $value, float $lowest, float $highest, float $fallback ): float {
	return is_numeric( $value ) ? max( $lowest, min( $highest, (float) $value ) ) : $fallback;
}

/**
 * Pick $value when it's one of $allowed, otherwise the first allowed value.
 *
 * @param mixed    $value   Raw value.
 * @param string[] $allowed Allowed values.
 */
function one_of( $value, array $allowed ): string {
	return in_array( $value, $allowed, true ) ? $value : $allowed[0];
}
