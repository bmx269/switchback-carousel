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

/**
 * Hand the images of later slides to Splide's lazy loader.
 *
 * Images in the first `$eager` slides load with the page. In every later
 * slide, each image's src and srcset move to data-splide-lazy and
 * data-splide-lazy-srcset, so the browser fetches nothing until Splide (with
 * `lazyLoad: 'nearby'`) brings the slide close. Native loading="lazy" can't do
 * this: browsers start loading images up to a couple of thousand pixels away,
 * sideways included, which covers most of a carousel's hidden slides.
 *
 * Without JavaScript those later images don't load at all, which is why this
 * is a per-track setting rather than the default.
 *
 * @param string $html  Rendered slides.
 * @param int    $eager Slides whose images load with the page.
 */
function lazy_slide_images( string $html, int $eager ): string {
	$processor = \WP_HTML_Processor::create_fragment( $html );
	if ( ! $processor ) {
		return $html;
	}

	$slide       = -1;
	$slide_depth = null;
	while ( $processor->next_tag() ) {
		$depth = $processor->get_current_depth();

		// A slide is a top-level slide wrapper; slides nested in a slide's content don't count.
		if ( 'DIV' === $processor->get_tag() && $processor->has_class( 'wp-block-switchback-carousel-slide' )
			&& ( null === $slide_depth || $depth <= $slide_depth ) ) {
			$slide_depth = $depth;
			++$slide;
			continue;
		}

		if ( 'IMG' !== $processor->get_tag() || $slide < $eager ) {
			continue;
		}
		$src = $processor->get_attribute( 'src' );
		if ( ! is_string( $src ) || '' === $src ) {
			continue;
		}
		$processor->set_attribute( 'data-splide-lazy', $src );
		$processor->remove_attribute( 'src' );
		$srcset = $processor->get_attribute( 'srcset' );
		if ( is_string( $srcset ) && '' !== $srcset ) {
			$processor->set_attribute( 'data-splide-lazy-srcset', $srcset );
			$processor->remove_attribute( 'srcset' );
		}
		// Splide sets src itself; native lazy-loading would only delay it further.
		$processor->remove_attribute( 'loading' );
	}

	return $processor->get_updated_html();
}
