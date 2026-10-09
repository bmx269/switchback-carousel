<?php
/**
 * Starter carousel designs, offered when inserting or replacing a carousel.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Block comment with JSON attributes.
 *
 * @param string $name  Block name without namespace.
 * @param array  $attrs Attributes.
 * @param string $inner Inner markup (null for a self-closing block).
 */
function design_block( string $name, array $attrs = array(), ?string $inner = null ): string {
	$json = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
	if ( null === $inner ) {
		return "<!-- wp:switchback-carousel/{$name}{$json} /-->";
	}
	return "<!-- wp:switchback-carousel/{$name}{$json} -->{$inner}<!-- /wp:switchback-carousel/{$name} -->";
}

/**
 * A slide with inner markup.
 *
 * @param string $inner Inner blocks.
 */
function design_slide( string $inner ): string {
	return design_block( 'slide', array(), '<div class="wp-block-switchback-carousel-slide">' . $inner . '</div>' );
}

/**
 * A carousel: track plus whatever surrounds it.
 *
 * @param array  $track  Track attributes.
 * @param string $slides Slides.
 * @param string $after  Blocks after the track (controls).
 * @param array  $attrs  Carousel attributes.
 */
function design_carousel( array $track, string $slides, string $after, array $attrs = array() ): string {
	$track['lock'] = array(
		'move'   => false,
		'remove' => true,
	);
	return design_block( 'carousel', $attrs + array( 'align' => 'wide' ), design_block( 'track', $track, $slides ) . $after );
}

/**
 * A Query Loop carousel: core/query with the Switchback variation enabled.
 *
 * @param array  $query_attrs   `query` sub-attribute (postType, perPage, etc.).
 * @param array  $switchback    Carousel display settings (layout, motion, etc.).
 * @param string $post_template Markup inside <!-- wp:post-template --> (one post).
 * @param string $after         Controls block markup placed after the query.
 * @param array  $carousel_attrs Outer carousel wrapper attributes.
 */
function design_query_carousel( array $query_attrs, array $switchback, string $post_template, string $after, array $carousel_attrs = array() ): string {
	$query        = array(
		'namespace'  => 'advanced-query-loop',
		'align'      => 'wide',
		'query'      => $query_attrs + array(
			'perPage' => 6,
			'pages'   => 0,
			'offset'  => 0,
			'order'   => 'desc',
			'orderBy' => 'date',
			'inherit' => false,
		),
		'switchback' => $switchback + array( 'enabled' => true ),
	);
	$query_markup = sprintf(
		'<!-- wp:query %s --><div class="wp-block-query alignwide"><!-- wp:post-template -->%s<!-- /wp:post-template --></div><!-- /wp:query -->',
		wp_json_encode( $query, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		$post_template
	);
	return design_block(
		'carousel',
		$carousel_attrs + array( 'align' => 'wide' ),
		$query_markup . $after
	);
}

/**
 * Register the designs.
 */
function register_designs(): void {
	register_block_pattern_category( 'switchback-carousel', array( 'label' => __( 'Carousels', 'switchback-carousel' ) ) );

	$heading   = static fn( string $text ): string => '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html( $text ) . '</h3><!-- /wp:heading -->';
	$paragraph = static fn( string $text ): string => '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';

	$designs = array();

	// Feature cards: three across, peek on tablet, arrows on the right.
	$cards = '';
	foreach ( array( __( 'Listen', 'switchback-carousel' ), __( 'Plan', 'switchback-carousel' ), __( 'Act', 'switchback-carousel' ), __( 'Review', 'switchback-carousel' ), __( 'Refine', 'switchback-carousel' ) ) as $title ) {
		$cards .= design_slide( $heading( $title ) . $paragraph( __( 'Describe this step in a sentence or two.', 'switchback-carousel' ) ) );
	}
	$designs['feature-cards'] = array(
		__( 'Feature cards', 'switchback-carousel' ),
		design_carousel(
			array(
				'layout' => array(
					'desktop' => array(
						'columns' => 3,
						'step'    => 1,
						'peek'    => 0,
					),
					'tablet'  => array(
						'columns' => 2,
						'peek'    => 15,
					),
					'mobile'  => array(
						'columns' => 1,
						'step'    => 1,
						'peek'    => 15,
					),
				),
			),
			$cards,
			design_block(
				'controls',
				array(
					'control' => 'arrows',
					'icon'    => array(
						'slug'   => 'arrow-right',
						'weight' => 'light',
						'svg'    => '',
					),
					'layout'  => array(
						'type'           => 'flex',
						'justifyContent' => 'right',
					),
				)
			)
		),
	);

	// Quote rotator: fading quotes, dots and a pause button.
	$quotes = '';
	foreach ( array( __( 'They listened first, then planned with us.', 'switchback-carousel' ), __( 'Straight answers and steady guidance.', 'switchback-carousel' ), __( 'We finally feel ahead of things.', 'switchback-carousel' ) ) as $quote ) {
		$quotes .= design_slide(
			'<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>' . esc_html( $quote ) . '</p><!-- /wp:paragraph --><cite>' . esc_html__( 'Name, role', 'switchback-carousel' ) . '</cite></blockquote><!-- /wp:quote -->'
		);
	}
	$designs['quote-rotator'] = array(
		__( 'Quote rotator', 'switchback-carousel' ),
		design_carousel(
			array(
				'motion'   => array(
					'transition' => 'fade',
					'duration'   => 600,
					'loop'       => 'rewind',
					'drag'       => 'snap',
					'wheel'      => false,
					'grabCursor' => false,
				),
				'autoplay' => array(
					'enabled'      => true,
					'mode'         => 'interval',
					'interval'     => 7000,
					'speed'        => 1,
					'pauseOnHover' => true,
				),
			),
			$quotes,
			design_block( 'controls', array( 'control' => 'pagination' ) ) . design_block( 'controls', array( 'control' => 'playpause' ) )
		),
	);

	// Latest posts: from a Query Loop with Switchback display on, arrows over.
	$designs['latest-posts'] = array(
		__( 'Latest posts', 'switchback-carousel' ),
		design_query_carousel(
			array(
				'postType' => 'post',
				'perPage'  => 6,
			),
			array(
				'layout' => array(
					'desktop' => array(
						'columns' => 3,
						'step'    => 3,
						'peek'    => 0,
					),
					'tablet'  => array(
						'columns' => 2,
						'step'    => 2,
					),
					'mobile'  => array(
						'columns' => 1,
						'step'    => 1,
						'peek'    => 12,
					),
				),
			),
			'<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
				. '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2"} /-->'
				. '<!-- wp:post-title {"level":3,"isLink":true} /-->'
				. '<!-- wp:post-date /-->'
				. '</div><!-- /wp:group -->',
			design_block(
				'controls',
				array(
					'control' => 'arrows',
					'size'    => 44,
					'icon'    => array(
						'slug'   => 'caret-circle-right',
						'weight' => 'fill',
						'svg'    => '',
					),
				)
			),
			array( 'overlayControls' => true )
		),
	);

	// Logo marquee: continuous flow, faded edges.
	$logos = '';
	foreach ( range( 1, 8 ) as $number ) {
		/* translators: %d: logo number. */
		$logos .= design_slide( $paragraph( sprintf( __( 'Logo %d', 'switchback-carousel' ), $number ) ) );
	}
	$designs['logo-marquee'] = array(
		__( 'Logo marquee', 'switchback-carousel' ),
		design_carousel(
			array(
				'layout'     => array(
					'desktop' => array(
						'columns' => 6,
						'step'    => 1,
						'peek'    => 0,
					),
					'tablet'  => array( 'columns' => 4 ),
					'mobile'  => array(
						'columns' => 2,
						'step'    => 1,
						'peek'    => 0,
					),
				),
				'slideWidth' => array(
					'min' => 110,
					'max' => 0,
				),
				'autoplay'   => array(
					'enabled'      => true,
					'mode'         => 'continuous',
					'interval'     => 5000,
					'speed'        => 0.6,
					'pauseOnHover' => true,
				),
				'edges'      => array(
					'overflow' => false,
					'fade'     => 'both',
				),
			),
			$logos,
			''
		),
	);

	// Gallery strip: centred, peek, progress bar and counter.
	$panels = '';
	foreach ( range( 1, 7 ) as $number ) {
		/* translators: %d: panel number. */
		$panels .= design_slide( $heading( sprintf( __( 'Panel %d', 'switchback-carousel' ), $number ) ) );
	}
	$designs['gallery-strip'] = array(
		__( 'Gallery strip with progress', 'switchback-carousel' ),
		design_carousel(
			array(
				'layout' => array(
					'desktop' => array(
						'columns' => 2,
						'step'    => 1,
						'peek'    => 20,
					),
					'mobile'  => array(
						'columns' => 1,
						'step'    => 1,
						'peek'    => 20,
					),
				),
				'focus'  => 'center',
				'motion' => array(
					'transition' => 'slide',
					'duration'   => 500,
					'loop'       => 'none',
					'drag'       => 'free',
					'wheel'      => true,
					'grabCursor' => true,
				),
			),
			$panels,
			design_block(
				'controls',
				array(
					'control' => 'progress',
					'layout'  => array(
						'type'           => 'flex',
						'justifyContent' => 'stretch',
					),
				)
			)
			. design_block(
				'controls',
				array(
					'control' => 'counter',
					'layout'  => array(
						'type'           => 'flex',
						'justifyContent' => 'right',
					),
				)
			)
		),
	);

	foreach ( $designs as $slug => [ $title, $content ] ) {
		register_block_pattern(
			'switchback-carousel/' . $slug,
			array(
				'title'      => $title,
				'categories' => array( 'switchback-carousel' ),
				'blockTypes' => array( 'switchback-carousel/carousel' ),
				'content'    => $content,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\register_designs' );
