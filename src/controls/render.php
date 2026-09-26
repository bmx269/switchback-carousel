<?php
/**
 * Carousel controls. Markup only; view.js connects it to the carousel's track.
 *
 * @package SwitchbackCarousel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused.
 * @var WP_Block $block      Block.
 */

use function SwitchbackCarousel\bounded_int;
use function SwitchbackCarousel\icon_svg;
use function SwitchbackCarousel\one_of;

defined( 'ABSPATH' ) || exit;

$switchback_carousel_control = one_of( $attributes['control'] ?? 'arrows', array( 'arrows', 'pagination', 'progress', 'counter', 'playpause' ) );
$switchback_carousel_classes = array( 'switchback-controls', 'is-' . $switchback_carousel_control );
$switchback_carousel_styles  = array();
// Unset size keeps each control's own default from the stylesheet.
if ( isset( $attributes['size'] ) && is_numeric( $attributes['size'] ) ) {
	$switchback_carousel_styles[] = '--switchback-control-size:' . bounded_int( $attributes['size'], 2, 160, 32 ) . 'px';
}

switch ( $switchback_carousel_control ) {
	case 'arrows':
		$switchback_carousel_icon     = (array) ( $attributes['icon'] ?? array() );
		$switchback_carousel_glyph    = icon_svg(
			(string) ( $switchback_carousel_icon['slug'] ?? 'caret-right' ),
			one_of( $switchback_carousel_icon['weight'] ?? 'regular', array( 'regular', 'thin', 'light', 'bold', 'fill' ) ),
			(string) ( $switchback_carousel_icon['svg'] ?? '' )
		);
		$switchback_carousel_styles[] = '--switchback-overlay-inset:' . intval( $attributes['overlayInset'] ?? 0 ) . 'px';
		$switchback_carousel_markup   = sprintf(
			'<button type="button" class="switchback-controls__button switchback-controls__prev" aria-label="%1$s">%3$s</button>'
			. '<button type="button" class="switchback-controls__button switchback-controls__next" aria-label="%2$s">%3$s</button>',
			esc_attr__( 'Previous slide', 'switchback-carousel' ),
			esc_attr__( 'Next slide', 'switchback-carousel' ),
			$switchback_carousel_glyph
		);
		break;

	case 'pagination':
		$switchback_carousel_classes[] = 'is-style-' . one_of( $attributes['pagination'] ?? 'dots', array( 'dots', 'bars', 'segments' ) );
		// view.js adds one button per page.
		$switchback_carousel_markup = sprintf( '<ul class="switchback-controls__pages" aria-label="%s"></ul>', esc_attr__( 'Choose a slide', 'switchback-carousel' ) );
		break;

	case 'progress':
		$switchback_carousel_markup = '<div class="switchback-controls__progress"><span class="switchback-controls__progress-bar"></span></div>';
		break;

	case 'counter':
		$switchback_carousel_markup = '<p class="switchback-controls__counter"><span class="switchback-controls__current">1</span><span class="switchback-controls__divider" aria-hidden="true"> / </span><span class="switchback-sr">'
			. esc_html__( 'of', 'switchback-carousel' ) . ' </span><span class="switchback-controls__total">1</span></p>';
		break;

	default:
		// Hidden until view.js finds autoplay on the track.
		$switchback_carousel_markup = sprintf(
			'<div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button switchback-controls__playpause" hidden>%s</button></div>',
			esc_html__( 'Pause', 'switchback-carousel' )
		);
}

printf(
	'<div %1$s>%2$s</div>',
	get_block_wrapper_attributes( // phpcs:ignore WordPress.Security.EscapeOutput
		array(
			'class' => implode( ' ', $switchback_carousel_classes ),
			'style' => implode( ';', $switchback_carousel_styles ),
		)
	),
	$switchback_carousel_markup // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
);
