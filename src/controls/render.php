<?php
/**
 * Carousel controls. Renders accurate initial state from the parent carousel's
 * block context (slide count, perPage, loop, autoplay) so counters, arrow
 * disabled states, pagination lists and progress widths are correct before
 * view.js hydrates. view.js re-syncs on every Splide event afterwards.
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
use function SwitchbackCarousel\page_count;

defined( 'ABSPATH' ) || exit;

$switchback_carousel_control = one_of( $attributes['control'] ?? 'arrows', array( 'arrows', 'pagination', 'progress', 'counter', 'playpause' ) );
$switchback_carousel_classes = array( 'switchback-controls', 'is-' . $switchback_carousel_control );
$switchback_carousel_styles  = array();
// Unset size keeps each control's own default from the stylesheet.
if ( isset( $attributes['size'] ) && is_numeric( $attributes['size'] ) ) {
	$switchback_carousel_styles[] = '--switchback-control-size:' . bounded_int( $attributes['size'], 2, 160, 32 ) . 'px';
}

// State broadcast by the parent carousel via providesContext. All optional:
// zero slideCount means the state wasn't computed (query mode or missing
// track) — fall back to the pre-SSR placeholders view.js will populate.
$switchback_carousel_slide_count = (int) ( $block->context['switchback-carousel/slideCount'] ?? 0 );
$switchback_carousel_per_page    = max( 1, (int) ( $block->context['switchback-carousel/perPage'] ?? 1 ) );
$switchback_carousel_per_move    = max( 1, (int) ( $block->context['switchback-carousel/perMove'] ?? 1 ) );
$switchback_carousel_loop        = (string) ( $block->context['switchback-carousel/loop'] ?? 'none' );
$switchback_carousel_autoplay    = (bool) ( $block->context['switchback-carousel/autoplay'] ?? false );
$switchback_carousel_endless     = in_array( $switchback_carousel_loop, array( 'loop', 'rewind' ), true );
$switchback_carousel_has_state   = $switchback_carousel_slide_count > 0;
$switchback_carousel_pages       = $switchback_carousel_has_state
	? page_count( $switchback_carousel_slide_count, $switchback_carousel_per_page, $switchback_carousel_per_move, $switchback_carousel_loop )
	: 1;
// With fewer slides than fit, the control has nothing to do.
$switchback_carousel_inactive = $switchback_carousel_has_state && ! $switchback_carousel_endless && $switchback_carousel_slide_count <= $switchback_carousel_per_page;

switch ( $switchback_carousel_control ) {
	case 'arrows':
		$switchback_carousel_icon     = (array) ( $attributes['icon'] ?? array() );
		$switchback_carousel_glyph    = icon_svg(
			(string) ( $switchback_carousel_icon['slug'] ?? 'caret-right' ),
			one_of( $switchback_carousel_icon['weight'] ?? 'regular', array( 'regular', 'thin', 'light', 'bold', 'fill' ) ),
			(string) ( $switchback_carousel_icon['svg'] ?? '' )
		);
		$switchback_carousel_styles[] = '--switchback-overlay-inset:' . intval( $attributes['overlayInset'] ?? 0 ) . 'px';
		// Initial index is 0; prev is disabled unless we loop/rewind. Next is
		// disabled only if there's a single page and no loop.
		$switchback_carousel_prev_disabled = $switchback_carousel_has_state && ! $switchback_carousel_endless ? ' disabled' : '';
		$switchback_carousel_next_disabled = $switchback_carousel_has_state && ! $switchback_carousel_endless && $switchback_carousel_pages <= 1 ? ' disabled' : '';
		if ( $switchback_carousel_inactive ) {
			$switchback_carousel_classes[] = 'is-inactive';
		}
		$switchback_carousel_markup = sprintf(
			'<button type="button" class="switchback-controls__button switchback-controls__prev" aria-label="%1$s"%4$s>%3$s</button>'
			. '<button type="button" class="switchback-controls__button switchback-controls__next" aria-label="%2$s"%5$s>%3$s</button>',
			esc_attr__( 'Previous slide', 'switchback-carousel' ),
			esc_attr__( 'Next slide', 'switchback-carousel' ),
			$switchback_carousel_glyph,
			$switchback_carousel_prev_disabled,
			$switchback_carousel_next_disabled
		);
		break;

	case 'pagination':
		$switchback_carousel_classes[] = 'is-style-' . one_of( $attributes['pagination'] ?? 'dots', array( 'dots', 'bars', 'segments' ) );
		if ( $switchback_carousel_has_state && $switchback_carousel_pages < 2 ) {
			$switchback_carousel_classes[] = 'is-inactive';
		}
		if ( $switchback_carousel_has_state ) {
			/* translators: %s: page number. */
			$switchback_carousel_label_each = $switchback_carousel_per_move > 1 ? __( 'Go to page %s', 'switchback-carousel' ) : __( 'Go to slide %s', 'switchback-carousel' );
			$switchback_carousel_items      = '';
			for ( $switchback_carousel_i = 1; $switchback_carousel_i <= $switchback_carousel_pages; $switchback_carousel_i++ ) {
				$switchback_carousel_items .= sprintf(
					'<li><button type="button" class="switchback-controls__page%1$s" aria-label="%2$s"%3$s></button></li>',
					1 === $switchback_carousel_i ? ' is-active' : '',
					esc_attr( sprintf( $switchback_carousel_label_each, $switchback_carousel_i ) ),
					1 === $switchback_carousel_i ? ' aria-current="true"' : ''
				);
			}
			$switchback_carousel_markup = sprintf(
				'<ul class="switchback-controls__pages" aria-label="%1$s">%2$s</ul>',
				esc_attr__( 'Choose a slide', 'switchback-carousel' ),
				$switchback_carousel_items
			);
		} else {
			// view.js builds the buttons when state wasn't SSR'd.
			$switchback_carousel_markup = sprintf( '<ul class="switchback-controls__pages" aria-label="%s"></ul>', esc_attr__( 'Choose a slide', 'switchback-carousel' ) );
		}
		break;

	case 'progress':
		// Visible fraction of the track; mirrors view.js's `visible` calculation.
		$switchback_carousel_visible = $switchback_carousel_has_state
			? max( 0.08, min( 1, $switchback_carousel_per_page / max( 1, $switchback_carousel_slide_count ) ) )
			: 0.08;
		$switchback_carousel_width   = (int) round( $switchback_carousel_visible * 100 );
		$switchback_carousel_markup  = sprintf(
			'<div class="switchback-controls__progress"%1$s><span class="switchback-controls__progress-bar" style="width:%2$d%%;inset-inline-start:0%%"></span></div>',
			$switchback_carousel_has_state ? ' role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0" aria-label="' . esc_attr__( 'Carousel position', 'switchback-carousel' ) . '"' : '',
			$switchback_carousel_width
		);
		if ( $switchback_carousel_inactive ) {
			$switchback_carousel_classes[] = 'is-inactive';
		}
		break;

	case 'counter':
		$switchback_carousel_total  = $switchback_carousel_has_state ? $switchback_carousel_pages : 1;
		$switchback_carousel_markup = '<p class="switchback-controls__counter"><span class="switchback-controls__current">1</span><span class="switchback-controls__divider" aria-hidden="true"> / </span><span class="switchback-sr">'
			. esc_html__( 'of', 'switchback-carousel' ) . ' </span><span class="switchback-controls__total">' . esc_html( (string) $switchback_carousel_total ) . '</span></p>';
		break;

	default:
		// playpause: unhide server-side when autoplay is on; otherwise stays
		// hidden and view.js keeps it hidden too.
		$switchback_carousel_hidden = $switchback_carousel_autoplay ? '' : ' hidden';
		$switchback_carousel_markup = sprintf(
			'<div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button switchback-controls__playpause" aria-pressed="false"%2$s>%1$s</button></div>',
			esc_html__( 'Pause', 'switchback-carousel' ),
			$switchback_carousel_hidden
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
