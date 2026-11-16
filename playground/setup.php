<?php
/**
 * Builds the WordPress Playground demo: sample trail notes and events with
 * featured images, then a showcase page (the front page) with one carousel
 * per feature.
 *
 * The blueprint (blueprint.json) copies this file and playground/images/ into
 * wp-content/switchback-demo/, then runs it once Twenty Twenty-Five,
 * Switchback Carousel and Advanced Query Loop are active.
 *
 * Block markup is written out by hand, so it has to match what each block's
 * save() produces or the editor flags the block as invalid. The helpers below
 * cover only the supports this page uses.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel\Demo;

// phpcs:disable WordPress.WP.AlternativeFunctions

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

const SHOWCASE_ID = 1000;

const INK   = '#15171c';
const PAPER = '#f6f3ec';
const CORAL = '#ff5a3c';
const LIME  = '#d9f36a';
const LILAC = '#d8ccff';

/*
 * Markup helpers.
 */

/**
 * Block comment around optional inner HTML.
 *
 * @param string      $name  Block name; "core/" may be omitted.
 * @param array       $attrs Attributes.
 * @param string|null $html  Inner HTML, or null for a self-closing block.
 */
function block( string $name, array $attrs = array(), ?string $html = null ): string {
	$json = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
	if ( null === $html ) {
		return "<!-- wp:{$name}{$json} /-->";
	}
	return "<!-- wp:{$name}{$json} -->{$html}<!-- /wp:{$name} -->";
}

/**
 * `var:preset|spacing|50` to `var(--wp--preset--spacing--50)`.
 *
 * @param string $value Style value.
 */
function css_value( string $value ): string {
	return str_starts_with( $value, 'var:' ) ? 'var(--wp--' . str_replace( '|', '--', substr( $value, 4 ) ) . ')' : $value;
}

/**
 * Classes and inline styles that block supports save for the given attributes.
 *
 * @param array $attrs Block attributes.
 * @return array{0: string[], 1: string[]}
 */
function presentation( array $attrs ): array {
	$style   = $attrs['style'] ?? array();
	$classes = array();
	$styles  = array();

	if ( isset( $attrs['textColor'] ) ) {
		$classes[] = 'has-' . $attrs['textColor'] . '-color';
	}
	if ( isset( $attrs['textColor'] ) || isset( $style['color']['text'] ) ) {
		$classes[] = 'has-text-color';
	}
	if ( isset( $attrs['backgroundColor'] ) ) {
		$classes[] = 'has-' . $attrs['backgroundColor'] . '-background-color';
	}
	if ( isset( $attrs['backgroundColor'] ) || isset( $style['color']['background'] ) || isset( $style['color']['gradient'] ) ) {
		$classes[] = 'has-background';
	}
	if ( isset( $style['elements']['link']['color']['text'] ) ) {
		$classes[] = 'has-link-color';
	}
	if ( isset( $attrs['fontSize'] ) ) {
		$classes[] = 'has-' . $attrs['fontSize'] . '-font-size';
	}

	if ( isset( $style['color']['text'] ) ) {
		$styles[] = 'color:' . $style['color']['text'];
	}
	if ( isset( $style['color']['background'] ) ) {
		$styles[] = 'background-color:' . $style['color']['background'];
	}
	if ( isset( $style['color']['gradient'] ) ) {
		$styles[] = 'background:' . $style['color']['gradient'];
	}
	if ( isset( $style['border']['radius'] ) ) {
		$styles[] = 'border-radius:' . $style['border']['radius'];
	}
	foreach ( array( 'padding', 'margin' ) as $box ) {
		foreach ( (array) ( $style['spacing'][ $box ] ?? array() ) as $side => $value ) {
			$styles[] = "{$box}-{$side}:" . css_value( $value );
		}
	}
	if ( isset( $style['dimensions']['minHeight'] ) ) {
		$styles[] = 'min-height:' . $style['dimensions']['minHeight'];
	}
	$typography = array(
		'fontSize'      => 'font-size',
		'fontStyle'     => 'font-style',
		'fontWeight'    => 'font-weight',
		'letterSpacing' => 'letter-spacing',
		'lineHeight'    => 'line-height',
		'textTransform' => 'text-transform',
	);
	foreach ( $typography as $key => $property ) {
		if ( isset( $style['typography'][ $key ] ) ) {
			$styles[] = "{$property}:" . $style['typography'][ $key ];
		}
	}
	if ( isset( $style['shadow'] ) ) {
		$styles[] = 'box-shadow:' . css_value( $style['shadow'] );
	}

	return array( $classes, $styles );
}

/**
 * HTML attributes from class and style lists.
 *
 * @param string[] $classes Classes.
 * @param string[] $styles  Declarations.
 */
function html_attrs( array $classes, array $styles = array() ): string {
	$out = '';
	if ( $classes ) {
		$out .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
	}
	if ( $styles ) {
		$out .= ' style="' . esc_attr( implode( ';', $styles ) ) . '"';
	}
	return $out;
}

/**
 * An element carrying the block's support classes and styles.
 *
 * @param string   $tag   Tag name.
 * @param string[] $base  Block classes.
 * @param array    $attrs Block attributes.
 * @param string   $inner Inner HTML.
 */
function element( string $tag, array $base, array $attrs, string $inner ): string {
	[ $classes, $styles ] = presentation( $attrs );
	if ( ! empty( $attrs['align'] ) ) {
		$base[] = 'align' . $attrs['align'];
	}
	if ( ! empty( $attrs['className'] ) ) {
		$base[] = $attrs['className'];
	}
	$id = empty( $attrs['anchor'] ) ? '' : ' id="' . esc_attr( $attrs['anchor'] ) . '"';
	return "<{$tag}{$id}" . html_attrs( array_merge( $base, $classes ), $styles ) . ">{$inner}</{$tag}>";
}

/**
 * Paragraph.
 *
 * @param string $html  Inline HTML.
 * @param array  $attrs Attributes.
 */
function paragraph( string $html, array $attrs = array() ): string {
	return block( 'paragraph', $attrs, element( 'p', array(), $attrs, $html ) );
}

/**
 * Heading.
 *
 * @param string $html  Inline HTML.
 * @param int    $level Level.
 * @param array  $attrs Attributes.
 */
function heading( string $html, int $level = 2, array $attrs = array() ): string {
	if ( 2 !== $level ) {
		$attrs = array( 'level' => $level ) + $attrs;
	}
	return block( 'heading', $attrs, element( "h{$level}", array( 'wp-block-heading' ), $attrs, $html ) );
}

/**
 * Group.
 *
 * @param string $inner  Inner blocks.
 * @param array  $attrs  Attributes.
 * @param array  $layout Layout attribute.
 */
function group( string $inner, array $attrs = array(), array $layout = array( 'type' => 'constrained' ) ): string {
	$attrs += array( 'layout' => $layout );
	return block( 'group', $attrs, element( $attrs['tagName'] ?? 'div', array( 'wp-block-group' ), $attrs, $inner ) );
}

/**
 * A horizontal row.
 *
 * @param string $inner   Inner blocks.
 * @param string $justify justifyContent.
 * @param array  $attrs   Attributes.
 */
function row( string $inner, string $justify = 'space-between', array $attrs = array() ): string {
	return group(
		$inner,
		$attrs,
		array(
			'type'              => 'flex',
			'flexWrap'          => 'wrap',
			'justifyContent'    => $justify,
			'verticalAlignment' => 'center',
		)
	);
}

/**
 * Buttons.
 *
 * @param string $inner Button blocks.
 */
function buttons( string $inner ): string {
	return block( 'buttons', array(), '<div class="wp-block-buttons">' . $inner . '</div>' );
}

/**
 * Button.
 *
 * @param string $text  Label.
 * @param string $url   Link.
 * @param array  $attrs Attributes. className goes on the wrapper, the rest on the link.
 */
function button( string $text, string $url, array $attrs = array() ): string {
	[ $classes, $styles ] = presentation( $attrs );
	$wrapper              = array_filter( array( 'wp-block-button', $attrs['className'] ?? '' ) );
	$link                 = array_merge( array( 'wp-block-button__link' ), $classes, array( 'wp-element-button' ) );
	return block(
		'button',
		$attrs,
		'<div' . html_attrs( $wrapper ) . '><a' . html_attrs( $link, $styles ) . ' href="' . esc_url( $url ) . '">' . $text . '</a></div>'
	);
}

/**
 * Image.
 *
 * @param int    $id     Attachment ID.
 * @param string $radius Corner radius.
 */
function image( int $id, string $radius = '20px' ): string {
	$attrs = array(
		'id'              => $id,
		'aspectRatio'     => '4/3',
		'scale'           => 'cover',
		'sizeSlug'        => 'large',
		'linkDestination' => 'none',
		'style'           => array( 'border' => array( 'radius' => $radius ) ),
	);
	$img   = sprintf(
		'<img src="%s" alt="%s" class="wp-image-%d" style="border-radius:%s;aspect-ratio:4/3;object-fit:cover"/>',
		esc_url( wp_get_attachment_image_url( $id, 'large' ) ),
		esc_attr( get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
		$id,
		$radius
	);
	return block( 'image', $attrs, '<figure class="wp-block-image size-large has-custom-border">' . $img . '</figure>' );
}

/**
 * Cover with an image background.
 *
 * @param int    $id      Attachment ID.
 * @param string $inner   Inner blocks.
 * @param array  $options minHeight (vh), dim (0–100), position, radius.
 */
function cover( int $id, string $inner, array $options = array() ): string {
	$options      += array(
		'minHeight' => 80,
		'dim'       => 50,
		'position'  => 'bottom left',
	);
	$url           = wp_get_attachment_image_url( $id, 'full' );
	$position      = str_replace( ' ', '-', $options['position'] );
	$attrs         = array(
		'align'              => 'full',
		'url'                => $url,
		'id'                 => $id,
		'dimRatio'           => $options['dim'],
		'customOverlayColor' => INK,
		'isUserOverlayColor' => true,
		'minHeight'          => $options['minHeight'],
		'minHeightUnit'      => 'vh',
		'contentPosition'    => $options['position'],
		'isDark'             => true,
		'style'              => array(
			'spacing' => array(
				'padding' => array(
					'top'    => 'clamp(2rem, 5vw, 4rem)',
					'right'  => 'clamp(1.5rem, 7vw, 7rem)',
					'bottom' => 'clamp(2rem, 5vw, 4rem)',
					'left'   => 'clamp(1.5rem, 7vw, 7rem)',
				),
			),
		),
		'layout'             => array( 'type' => 'constrained' ),
	);
	[ , $padding ] = presentation( array( 'style' => $attrs['style'] ) );
	// Core leaves out the ratio class at its default of 50.
	$dim  = 50 === $options['dim'] ? '' : ' has-background-dim-' . ( 10 * (int) round( $options['dim'] / 10 ) );
	$html = sprintf(
		'<div class="wp-block-cover alignfull has-custom-content-position is-position-%1$s" style="%8$s;min-height:%2$dvh"><img class="wp-block-cover__image-background wp-image-%3$d" alt="" src="%4$s" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background%5$s has-background-dim" style="background-color:%6$s"></span><div class="wp-block-cover__inner-container">%7$s</div></div>',
		esc_attr( $position ),
		$options['minHeight'],
		$id,
		esc_url( $url ),
		$dim,
		INK,
		$inner,
		esc_attr( implode( ';', $padding ) )
	);
	return block( 'cover', $attrs, $html );
}

/**
 * Columns.
 *
 * @param string[] $columns Column inner blocks, keyed by width (e.g. "55%").
 */
function columns( array $columns ): string {
	$inner = '';
	foreach ( $columns as $width => $content ) {
		$attrs  = array(
			'verticalAlignment' => 'center',
			'width'             => $width,
		);
		$inner .= block( 'column', $attrs, '<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:' . esc_attr( $width ) . '">' . $content . '</div>' );
	}
	return block(
		'columns',
		array(
			'verticalAlignment' => 'center',
			'align'             => 'wide',
		),
		'<div class="wp-block-columns alignwide are-vertically-aligned-center">' . $inner . '</div>'
	);
}

/**
 * Quote.
 *
 * @param string $text Quote.
 * @param string $cite Citation.
 */
function quote( string $text, string $cite ): string {
	return block(
		'quote',
		array(),
		'<blockquote class="wp-block-quote">' . paragraph( esc_html( $text ), array( 'fontSize' => 'x-large' ) ) . '<cite>' . esc_html( $cite ) . '</cite></blockquote>'
	);
}

/**
 * Switchback slide.
 *
 * @param string $inner Inner blocks.
 * @param array  $attrs Slide attributes.
 */
function slide( string $inner, array $attrs = array() ): string {
	return block( 'switchback-carousel/slide', $attrs, element( 'div', array( 'wp-block-switchback-carousel-slide' ), $attrs, $inner ) );
}

/**
 * Switchback carousel wrapper (dynamic: saves only its inner blocks).
 *
 * @param string $inner Inner blocks.
 * @param array  $attrs Attributes.
 */
function carousel( string $inner, array $attrs = array() ): string {
	return block( 'switchback-carousel/carousel', $attrs + array( 'align' => 'wide' ), $inner );
}

/**
 * Switchback track (dynamic).
 *
 * @param string $slides Slides.
 * @param array  $attrs  Attributes.
 */
function track( string $slides, array $attrs ): string {
	return block( 'switchback-carousel/track', $attrs, $slides );
}

/**
 * Switchback controls (dynamic, self-closing).
 *
 * @param string $control Control type.
 * @param array  $attrs   Attributes.
 */
function controls( string $control, array $attrs = array() ): string {
	return block( 'switchback-carousel/controls', array( 'control' => $control ) + $attrs );
}

/**
 * Arrow icon attribute.
 *
 * @param string $slug   Phosphor icon.
 * @param string $weight Weight.
 */
function icon( string $slug, string $weight = 'regular' ): array {
	return array(
		'slug'   => $slug,
		'weight' => $weight,
		'svg'    => '',
	);
}

/**
 * Flex-child "Fill" width, for progress and segment controls that share a row:
 * they size to their container and would otherwise shrink to nothing.
 */
function fill(): array {
	return array( 'layout' => array( 'selfStretch' => 'fill' ) );
}

/**
 * Per-device layout.
 *
 * @param array $desktop [columns, step, peek].
 * @param array $tablet  [columns, step, peek] or empty to follow desktop.
 * @param array $mobile  [columns, step, peek].
 */
function layout( array $desktop, array $tablet, array $mobile ): array {
	$device = static fn( array $d ): array => $d ? array_combine( array( 'columns', 'step', 'peek' ), $d ) : array();
	return array(
		'desktop' => $device( $desktop ),
		'tablet'  => $device( $tablet ),
		'mobile'  => $device( $mobile ),
	);
}

/**
 * Query Loop shown as a carousel.
 *
 * @param array  $query      `query` attribute (merged over defaults).
 * @param array  $switchback Carousel display settings.
 * @param string $template   Post Template inner blocks.
 * @param array  $attrs      Other core/query attributes.
 */
function query_carousel( array $query, array $switchback, string $template, array $attrs = array() ): string {
	static $query_id = 0;
	$attrs           = array(
		'queryId'    => ++$query_id,
		'query'      => $query + array(
			'perPage'  => 8,
			'pages'    => 0,
			'offset'   => 0,
			'postType' => 'post',
			'order'    => 'desc',
			'orderBy'  => 'date',
			'inherit'  => false,
		),
		'switchback' => array( 'enabled' => true ) + $switchback,
	) + $attrs + array( 'align' => 'wide' );
	return block( 'query', $attrs, element( 'div', array( 'wp-block-query' ), $attrs, block( 'post-template', array(), $template ) ) );
}

/**
 * Full-width page section with an intro.
 *
 * @param string $eyebrow Small label.
 * @param string $title   Heading.
 * @param string $intro   One or two sentences (inline HTML).
 * @param string $body    Blocks after the intro.
 * @param array  $colors  [background, text, accent].
 * @param string $anchor  Section id.
 */
function section( string $eyebrow, string $title, string $intro, string $body, array $colors = array( '#ffffff', INK, CORAL ), string $anchor = '' ): string {
	[ $background, $text, $accent ] = $colors;

	$attrs = array(
		'tagName' => 'section',
		'align'   => 'full',
		'style'   => array(
			'color'   => array(
				'background' => $background,
				'text'       => $text,
			),
			'spacing' => array(
				'padding' => array(
					'top'    => 'clamp(3.5rem, 8vw, 7rem)',
					'bottom' => 'clamp(3.5rem, 8vw, 7rem)',
					'left'   => 'var:preset|spacing|50',
					'right'  => 'var:preset|spacing|50',
				),
			),
		),
	);
	if ( $anchor ) {
		$attrs = array( 'anchor' => $anchor ) + $attrs;
	}

	$intro_blocks = paragraph(
		esc_html( $eyebrow ),
		array(
			'style'    => array(
				'color'      => array( 'text' => $accent ),
				'typography' => array(
					'fontWeight'    => '700',
					'letterSpacing' => '0.14em',
					'textTransform' => 'uppercase',
				),
			),
			'fontSize' => 'small',
		)
	)
	. heading( esc_html( $title ), 2, array( 'fontSize' => 'xx-large' ) )
	. paragraph( $intro, array( 'fontSize' => 'large' ) );

	return group(
		// Left-aligned at reading width, lined up with the wide carousel below.
		group(
			$intro_blocks,
			array( 'align' => 'wide' ),
			array(
				'type'           => 'constrained',
				'justifyContent' => 'left',
			)
		) . $body,
		$attrs
	);
}

/*
 * Content.
 */

/**
 * Import the scene images; returns attachment IDs keyed 1–10.
 */
function import_images(): array {
	$alts = array(
		1  => 'Peach sunrise over layered ridges',
		2  => 'Blue sky over green foothills',
		3  => 'Moon over violet mountains at dusk',
		4  => 'Mint sky over rolling hills',
		5  => 'Warm desert ridges in the afternoon',
		6  => 'Pale blue ridgelines in winter light',
		7  => 'Deep teal mountains at night',
		8  => 'Golden hills in late summer',
		9  => 'Starry indigo sky over dark peaks',
		10 => 'Pink dawn over soft ridges',
	);
	$ids  = array();
	foreach ( $alts as $number => $alt ) {
		$source = __DIR__ . "/images/scene-{$number}.jpg";
		$tmp    = wp_tempnam( $source );
		copy( $source, $tmp );
		$id = media_handle_sideload(
			array(
				'name'     => "scene-{$number}.jpg",
				'tmp_name' => $tmp,
			),
			0,
			$alt
		);
		if ( is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		$ids[ $number ] = $id;
	}
	return $ids;
}

/**
 * Create a published post.
 *
 * @param array $args    wp_insert_post() arguments.
 * @param array $meta    Post meta.
 * @param int   $picture Featured image ID.
 */
function add_post( array $args, array $meta, int $picture ): int {
	$id = wp_insert_post(
		$args + array(
			'post_status'  => 'publish',
			'post_content' => paragraph( esc_html( $args['post_excerpt'] ) ),
		)
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	if ( $picture ) {
		set_post_thumbnail( $id, $picture );
	}
	return $id;
}

/**
 * Build the demo. A function, not top-level code: blueprint.json requires
 * this file at global scope, where names like $page would clobber globals.
 */
function run(): void {
	wp_set_current_user( 1 );
	// The blueprint runs without a logged-in user, so kses would strip the
	// inline styles and data attributes the block markup needs.
	kses_remove_filters();

	update_option( 'blogname', 'Switchback Trails' );
	update_option( 'blogdescription', 'A carousel showcase for WordPress' );

	foreach ( get_posts(
		array(
			'post_type'   => array( 'post', 'page' ),
			'numberposts' => -1,
			'post_status' => 'any',
			'fields'      => 'ids',
		)
	) as $existing ) {
		wp_delete_post( $existing, true );
	}

	$pictures = import_images();
	$picture  = static fn( int $n ): int => $pictures[ $n ] ?? 0;

	$trail_notes = wp_create_category( 'Trail notes' );
	$events      = wp_create_category( 'Events' );

	$trails = array(
		array( 'Ridge Loop at Dawn', 12.4, 'Coast Mountains', 'Headlamps on at five, summit coffee at seven.' ),
		array( 'Lakeside Switchbacks', 8.2, 'Interior Plateau', 'Twenty-two turns up from the water, every one worth it.' ),
		array( 'Blue Hour on the Col', 15.1, 'Rockies', 'A long day that ended with the best light of the summer.' ),
		array( 'Meadow Traverse', 6.5, 'Cascades', 'Wildflowers, marmots and one very curious grouse.' ),
		array( 'Canyon Rim Trail', 10.8, 'Desert Southwest', 'Red rock, thin shade and a view down a thousand metres.' ),
		array( 'Glacier Viewpoint', 18.3, 'Coast Mountains', 'The ice has pulled back since our last visit.' ),
		array( 'Night Hike to Pine Ridge', 7.4, 'Kootenays', 'Stars, owls and the long way back to the car.' ),
		array( 'Golden Hills Circuit', 11.6, 'Okanagan', 'Late-season grass and a warm wind all afternoon.' ),
		array( 'Starlight Saddle', 13.9, 'Chilcotin', 'We camped below the saddle and counted satellites.' ),
		array( 'Rosy Dawn Ramble', 5.3, 'Gulf Islands', 'A short loop with a long breakfast at the end.' ),
	);
	foreach ( $trails as $index => list( $title, $distance, $region, $line ) ) {
		add_post(
			array(
				'post_title'    => $title,
				'post_excerpt'  => sprintf( '%s km · %s. %s', number_format_i18n( $distance, 1 ), $region, $line ),
				'post_date'     => wp_date( 'Y-m-d H:i:s', strtotime( '-' . ( $index * 6 + 2 ) . ' days' ) ),
				'post_category' => array( $trail_notes ),
			),
			array(
				'distance' => $distance,
				'region'   => $region,
			),
			$picture( $index + 1 )
		);
	}

	// Two of these are already over: the {today_ymd} meta query hides them.
	$gatherings = array(
		array( -14, 'Spring Thaw Meetup', 'Lighthouse Park', 6 ),
		array( -3, 'Trail Crew Work Party', 'Seymour Trailhead', 2 ),
		array( 4, 'Full Moon Ridge Walk', 'Cypress Lodge', 3 ),
		array( 11, 'Beginner Navigation Clinic', 'North Shore Library', 4 ),
		array( 19, 'Larch Season Day Trip', 'Frosty Mountain', 8 ),
		array( 33, 'Winter Gear Swap', 'Community Hall', 6 ),
		array( 47, 'First Snow Snowshoe', 'Hollyburn Cabin', 9 ),
	);
	foreach ( $gatherings as $index => list( $days, $title, $place, $image ) ) {
		$when = strtotime( "{$days} days 09:00" );
		add_post(
			array(
				'post_title'    => $title,
				'post_excerpt'  => wp_date( 'l, F j', $when ) . ' · ' . $place,
				'post_date'     => wp_date( 'Y-m-d H:i:s', strtotime( '-' . ( 30 + $index ) . ' days' ) ),
				'post_category' => array( $events ),
			),
			array( 'event_date' => wp_date( 'Ymd', $when ) ),
			$picture( $image )
		);
	}

	/*
	* The showcase page.
	*/

	$white_text = array( 'color' => array( 'text' => '#ffffff' ) );
	$page       = '';

	// 1. Hero: full width, one slide at a time, fading covers, controls inside the slides.
	$hero_slides = array(
		array( 3, 'Carousel blocks for WordPress', 'Switchback Carousel', 1, 'Build slides from any blocks, or turn any Query Loop into a carousel. Every carousel on this page is a few blocks you can open in the editor and change.' ),
		array( 9, 'Your posts, sliding', 'Any Query Loop becomes a carousel', 2, 'Switch on Display as carousel and every post becomes a slide. Pair it with Advanced Query Loop to filter by meta fields, dates and more.' ),
		array( 1, 'Controls go anywhere', 'Arrows, bars, dots, progress, counters', 2, 'The bars and arrows below live inside each slide. Put controls above, below, beside or inside the carousel.' ),
	);
	$hero        = '';
	foreach ( $hero_slides as $index => list( $image, $eyebrow, $title, $level, $text ) ) {
		$inner = paragraph(
			esc_html( $eyebrow ),
			array(
				'style'    => array(
					'color'      => array( 'text' => LIME ),
					'typography' => array(
						'fontWeight'    => '700',
						'letterSpacing' => '0.14em',
						'textTransform' => 'uppercase',
					),
				),
				'fontSize' => 'small',
			)
		)
		. heading( esc_html( $title ), $level, array( 'fontSize' => 'xx-large' ) )
		. paragraph( esc_html( $text ), array( 'fontSize' => 'large' ) );
		if ( 0 === $index ) {
			$inner .= buttons(
				button(
					'Explore the showcase',
					'#showcase',
					array(
						'style' => array(
							'color' => array(
								'background' => LIME,
								'text'       => INK,
							),
						),
					)
				)
				. button(
					'Open this page in the editor',
					admin_url( 'post.php?post=' . SHOWCASE_ID . '&action=edit' ),
					array( 'className' => 'is-style-outline' )
				)
			);
		}
		$inner .= row(
			controls(
				'pagination',
				array(
					'pagination' => 'bars',
					'style'      => $white_text + fill(),
					'layout'     => array(
						'type'           => 'flex',
						'justifyContent' => 'left',
					),
				)
			)
			. controls(
				'arrows',
				array(
					'icon'  => icon( 'arrow-right', 'light' ),
					'size'  => 44,
					'style' => $white_text,
				)
			),
			'space-between',
			array( 'style' => array( 'spacing' => array( 'margin' => array( 'top' => 'var:preset|spacing|50' ) ) ) )
		);
		$hero  .= slide( cover( $picture( $image ), $inner, array( 'minHeight' => 82 ) ) );
	}
	$page .= carousel(
		track(
			$hero,
			array(
				'align'    => 'full',
				'layout'   => layout( array( 1, 1, 0 ), array(), array( 1, 1, 0 ) ),
				'motion'   => array(
					'transition' => 'fade',
					'duration'   => 900,
					'loop'       => 'rewind',
					'drag'       => 'snap',
					'wheel'      => false,
					'grabCursor' => false,
				),
				'autoplay' => array(
					'enabled'      => true,
					'mode'         => 'interval',
					'interval'     => 6500,
					'speed'        => 1,
					'pauseOnHover' => true,
				),
			)
		),
		array( 'align' => 'full' )
	);

	// 2. Hand-built feature cards: linked layout, header row with arrows, progress line.
	$features = array(
		array( 'Any block, any layout', 'Slides hold whatever you put in them: images, columns, buttons, even another Query Loop.', 'linear-gradient(135deg, #ffd8c9 0%, #ffb59e 100%)' ),
		array( 'Layout per device', 'Columns, slides per move and a peek at the next slide, set separately for desktop, tablet and phone.', 'linear-gradient(135deg, #e9f7b0 0%, #c9ec5d 100%)' ),
		array( 'Controls go anywhere', 'Arrows, dots, bars, a progress line, a counter or play/pause, above, below, beside or inside a slide.', 'linear-gradient(135deg, #e6defe 0%, #c4b2ff 100%)' ),
		array( 'Linked layout', 'Restyle one slide and the rest follow, while each keeps its own content. These cards use it.', 'linear-gradient(135deg, #d2ecff 0%, #a5d4ff 100%)' ),
		array( 'Accessible by default', 'Labelled slides, keyboard arrows, live announcements, visible focus and a real pause button.', 'linear-gradient(135deg, #ffe7b3 0%, #ffcf6b 100%)' ),
		array( 'Works without JavaScript', 'Before the script loads, or if it never does, slides are a native scrolling row.', 'linear-gradient(135deg, #d3f5e4 0%, #9fe3c1 100%)' ),
	);
	$cards    = '';
	foreach ( $features as $index => list( $title, $text, $gradient ) ) {
		$cards .= slide(
			paragraph(
				sprintf( '%02d', $index + 1 ),
				array(
					'style'    => array( 'typography' => array( 'fontWeight' => '800' ) ),
					'fontSize' => 'xx-large',
				)
			)
			. heading( esc_html( $title ), 3, array( 'fontSize' => 'large' ) )
			. paragraph( esc_html( $text ) ),
			array(
				'style' => array(
					'color'      => array(
						'gradient' => $gradient,
						'text'     => INK,
					),
					'border'     => array( 'radius' => '28px' ),
					'spacing'    => array(
						'padding' => array(
							'top'    => '2.25rem',
							'right'  => '2rem',
							'bottom' => '2.25rem',
							'left'   => '2rem',
						),
					),
					'dimensions' => array( 'minHeight' => '340px' ),
				),
			)
		);
	}
	$page .= section(
		'Hand-built slides',
		'Six things to know',
		'Cards built from ordinary blocks, with <strong>linked layout</strong> on. The arrows sit in the header row and a progress line tracks the way.',
		carousel(
			row(
				paragraph( 'Drag, swipe, use the arrows, or focus the track and press the arrow keys.', array( 'style' => array( 'color' => array( 'text' => '#5b5e66' ) ) ) )
				. controls(
					'arrows',
					array(
						'icon' => icon( 'arrow-right', 'bold' ),
						'size' => 44,
					)
				),
				'space-between',
				array( 'align' => 'wide' )
			)
			. track(
				$cards,
				array(
					'layout'       => layout( array( 3, 1, 6 ), array( 2, 1, 10 ), array( 1, 1, 14 ) ),
					'motion'       => array(
						'transition' => 'slide',
						'duration'   => 500,
						'loop'       => 'none',
						'drag'       => 'snap',
						'wheel'      => true,
						'grabCursor' => true,
					),
					'linkedLayout' => true,
				)
			)
			. controls(
				'progress',
				array(
					'layout' => array(
						'type'           => 'flex',
						'justifyContent' => 'stretch',
					),
				)
			)
		),
		array( '#ffffff', INK, CORAL ),
		'showcase'
	);

	// 3. Latest posts from a plain Query Loop.
	$page .= section(
		'Query Loop',
		'Latest trail notes',
		'A core Query Loop with <strong>Display as carousel</strong> on. Design one post in the Post Template and every post becomes a slide. Endless loop, dots and arrows share a row below.',
		carousel(
			query_carousel(
				array(
					'perPage'  => 10,
					'taxQuery' => array( 'category' => array( $trail_notes ) ),
				),
				array(
					'layout' => layout( array( 3, 1, 0 ), array( 2, 1, 0 ), array( 1, 1, 12 ) ),
					'motion' => array(
						'transition' => 'slide',
						'duration'   => 450,
						'loop'       => 'loop',
						'drag'       => 'snap',
						'wheel'      => false,
						'grabCursor' => true,
					),
				),
				block(
					'post-featured-image',
					array(
						'isLink'      => true,
						'aspectRatio' => '4/3',
						'style'       => array( 'border' => array( 'radius' => '20px' ) ),
					)
				)
				. block(
					'post-title',
					array(
						'level'    => 3,
						'isLink'   => true,
						'fontSize' => 'large',
					)
				)
				. block( 'post-excerpt', array( 'excerptLength' => 20 ) )
			)
			. row(
				controls(
					'pagination',
					array(
						'layout' => array(
							'type'           => 'flex',
							'justifyContent' => 'left',
						),
					)
				)
				. controls(
					'arrows',
					array(
						'icon' => icon( 'caret-circle-right', 'regular' ),
						'size' => 44,
					)
				),
				'space-between',
				array( 'align' => 'wide' )
			)
		),
		array( PAPER, INK, CORAL )
	);

	// 4. Advanced Query Loop meta query, fluid columns, slides spilling off the edge.
	$page .= section(
		'Advanced Query Loop · meta query',
		'Big days out',
		'Advanced Query Loop filters to hikes where the <code>distance</code> field is over 10 km, longest first. <strong>Fluid columns</strong> fit as many 300px slides as the screen allows, and they spill past the edge.',
		carousel(
			query_carousel(
				array(
					'perPage'          => 10,
					'orderBy'          => 'meta_value_num',
					'orderby_meta_key' => 'distance',
					'taxQuery'         => array( 'category' => array( $trail_notes ) ),
					'meta_query'       => array(
						'relation' => 'AND',
						'queries'  => array(
							array(
								'id'           => 'distance',
								'meta_key'     => 'distance',
								'meta_value'   => '10',
								'meta_compare' => '>',
								'meta_type'    => 'DECIMAL(5,1)',
							),
						),
					),
				),
				array(
					'layout'     => array( 'fluid' => true ) + layout( array( 3, 1, 0 ), array(), array( 1, 1, 0 ) ),
					'slideWidth' => array(
						'min' => 300,
						'max' => 0,
					),
					'motion'     => array(
						'transition' => 'slide',
						'duration'   => 500,
						'loop'       => 'none',
						'drag'       => 'free',
						'wheel'      => true,
						'grabCursor' => true,
					),
					'edges'      => array(
						'overflow' => true,
						'fade'     => 'none',
					),
				),
				block(
					'post-featured-image',
					array(
						'isLink'      => true,
						'aspectRatio' => '3/4',
						'style'       => array( 'border' => array( 'radius' => '24px' ) ),
					)
				)
				. block(
					'post-title',
					array(
						'level'    => 3,
						'isLink'   => true,
						'fontSize' => 'large',
					)
				)
				. block( 'post-excerpt', array( 'excerptLength' => 6 ) ),
				array( 'namespace' => 'advanced-query-loop' )
			)
			. controls(
				'arrows',
				array(
					'icon'  => icon( 'arrow-circle-right', 'fill' ),
					'size'  => 52,
					'style' => array( 'color' => array( 'text' => LIME ) ),
				)
			),
			array(
				'overlayControls' => true,
				'style'           => array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ffffff' ) ) ) ),
			)
		),
		array( INK, '#e9e7e1', LIME )
	);

	// 5. Advanced Query Loop with the {today_ymd} token: upcoming events only.
	$page .= section(
		'Advanced Query Loop · relative dates',
		'Upcoming events',
		'Two of these events are already over and never show. The meta query compares <code>event_date</code> with <code>{today_ymd}</code>, which Switchback replaces with today’s date on every request, so the list stays current without edits.',
		carousel(
			query_carousel(
				array(
					'perPage'          => 8,
					'order'            => 'asc',
					'orderBy'          => 'meta_value_num',
					'orderby_meta_key' => 'event_date',
					'taxQuery'         => array( 'category' => array( $events ) ),
					'meta_query'       => array(
						'relation' => 'AND',
						'queries'  => array(
							array(
								'id'           => 'event_date',
								'meta_key'     => 'event_date',
								'meta_value'   => '{today_ymd}',
								'meta_compare' => '>=',
								'meta_type'    => 'NUMERIC',
							),
						),
					),
				),
				array(
					'layout' => layout( array( 4, 1, 0 ), array( 2, 1, 8 ), array( 1, 1, 15 ) ),
					'motion' => array(
						'transition' => 'slide',
						'duration'   => 400,
						'loop'       => 'rewind',
						'drag'       => 'snap',
						'wheel'      => false,
						'grabCursor' => true,
					),
				),
				group(
					block(
						'post-featured-image',
						array(
							'aspectRatio' => '16/9',
							'style'       => array( 'border' => array( 'radius' => '14px' ) ),
						)
					)
					. block(
						'post-excerpt',
						array(
							'style'    => array(
								'typography' => array(
									'fontWeight'    => '700',
									'textTransform' => 'uppercase',
									'letterSpacing' => '0.06em',
								),
								'color'      => array( 'text' => CORAL ),
							),
							'fontSize' => 'small',
						)
					)
					. block(
						'post-title',
						array(
							'level'    => 3,
							'fontSize' => 'large',
						)
					),
					array(
						'style' => array(
							'color'   => array( 'background' => '#ffffff' ),
							'border'  => array( 'radius' => '22px' ),
							'spacing' => array(
								'padding' => array(
									'top'    => '0.75rem',
									'right'  => '0.75rem',
									'bottom' => '1.5rem',
									'left'   => '0.75rem',
								),
							),
							'shadow'  => '0 18px 40px -24px rgba(21,23,28,0.45)',
						),
					)
				),
				array( 'namespace' => 'advanced-query-loop' )
			)
			. row(
				controls(
					'pagination',
					array(
						'pagination' => 'segments',
						'style'      => fill(),
						'layout'     => array(
							'type'           => 'flex',
							'justifyContent' => 'left',
						),
					)
				)
				. controls(
					'arrows',
					array(
						'icon' => icon( 'arrow-line-right', 'bold' ),
						'size' => 40,
					)
				),
				'space-between',
				array( 'align' => 'wide' )
			)
		),
		array( LIME, INK, INK )
	);

	// 6. Split slides with the counter and arrows inside each slide.
	$stories = array(
		array( 4, 'The meadow route', 'Gentle grades and wide views. A good first overnight, with water at every camp.' ),
		array( 7, 'After dark', 'Headlamps, layers and a plan for the way back. What we pack for night hikes.' ),
		array( 5, 'Desert light', 'Start before sunrise, rest at midday, finish in the gold. Pacing a hot-weather hike.' ),
	);
	$story   = '';
	foreach ( $stories as list( $image, $title, $text ) ) {
		$story .= slide(
			columns(
				array(
					'55%' => image( $picture( $image ), '24px' ),
					'45%' => controls(
						'counter',
						array(
							'layout' => array(
								'type'           => 'flex',
								'justifyContent' => 'left',
							),
						)
					)
					. heading( esc_html( $title ), 3, array( 'fontSize' => 'xx-large' ) )
					. paragraph( esc_html( $text ), array( 'fontSize' => 'large' ) )
					. controls(
						'arrows',
						array(
							'icon'   => icon( 'arrow-fat-right', 'fill' ),
							'size'   => 40,
							'style'  => array( 'color' => array( 'text' => CORAL ) ),
							'layout' => array(
								'type'           => 'flex',
								'justifyContent' => 'left',
							),
						)
					),
				)
			)
		);
	}
	$page .= section(
		'Controls inside slides',
		'Stories, one at a time',
		'Each slide is a Columns block. The counter and arrows sit inside the slide, next to the text they belong with.',
		carousel(
			track(
				$story,
				array(
					'layout'       => layout( array( 1, 1, 0 ), array(), array( 1, 1, 0 ) ),
					'motion'       => array(
						'transition' => 'slide',
						'duration'   => 700,
						'loop'       => 'rewind',
						'drag'       => 'snap',
						'wheel'      => false,
						'grabCursor' => true,
					),
					'linkedLayout' => true,
				)
			)
		),
		array( '#ffffff', INK, CORAL )
	);

	// 7. Quote rotator: fade, autoplay, dots and a pause button.
	$quotes = array(
		array( 'We built the whole events page in an afternoon. The upcoming list has not needed a single edit since.', 'Maya, Ridge Runners Club' ),
		array( 'Finally a carousel that keeps its hands off our markup. The editor looks like the site.', 'Dev, agency lead' ),
		array( 'Arrows inside the slide, dots underneath, a pause button for the people who need it. Exactly what we wanted.', 'Sam, accessibility reviewer' ),
	);
	$quote  = '';
	foreach ( $quotes as list( $text, $cite ) ) {
		$quote .= slide( quote( $text, $cite ) );
	}
	$page .= section(
		'Fade and autoplay',
		'Kind words',
		'One quote at a time, fading every seven seconds. Autoplay pauses on hover, on focus and for visitors who prefer reduced motion, and the play/pause button is always there.',
		carousel(
			track(
				$quote,
				array(
					'layout'   => layout( array( 1, 1, 0 ), array(), array( 1, 1, 0 ) ),
					'motion'   => array(
						'transition' => 'fade',
						'duration'   => 700,
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
				)
			)
			. row(
				controls( 'pagination' ) . controls( 'playpause' ),
				'center',
				array( 'align' => 'wide' )
			),
			array( 'align' => 'wide' )
		),
		array( LILAC, INK, '#5a3fd1' )
	);

	// 8. Centred gallery: peek, free drag, lazy-loaded images, progress and counter.
	$gallery = '';
	foreach ( array( 2, 6, 10, 8, 3, 1, 9, 4 ) as $image ) {
		$gallery .= slide( image( $picture( $image ), '20px' ) );
	}
	$page .= section(
		'Gallery',
		'Centred, with a peek',
		'The active image sits in the middle with its neighbours peeking in. Drag freely or scroll with a trackpad. Images load as the carousel reaches them.',
		carousel(
			track(
				$gallery,
				array(
					'layout'     => layout( array( 2, 1, 18 ), array(), array( 1, 1, 18 ) ),
					'focus'      => 'center',
					'motion'     => array(
						'transition' => 'slide',
						'duration'   => 500,
						'loop'       => 'loop',
						'drag'       => 'free',
						'wheel'      => true,
						'grabCursor' => true,
					),
					'edges'      => array(
						'overflow' => false,
						'fade'     => 'both',
					),
					'lazyImages' => true,
				)
			)
			. row(
				controls(
					'progress',
					array(
						'style'  => fill(),
						'layout' => array(
							'type'           => 'flex',
							'justifyContent' => 'stretch',
						),
					)
				)
				. controls( 'counter' ),
				'space-between',
				array( 'align' => 'wide' )
			)
		),
		array( PAPER, INK, CORAL )
	);

	// 9. Logo marquee: continuous autoplay, faded edges.
	$marks = array(
		array(
			'NORTHLINE',
			array(
				'fontWeight'    => '800',
				'letterSpacing' => '0.12em',
			),
		),
		array(
			'alpenglow',
			array(
				'fontStyle'  => 'italic',
				'fontWeight' => '600',
			),
		),
		array(
			'TALUS',
			array(
				'fontWeight'    => '300',
				'letterSpacing' => '0.3em',
			),
		),
		array( 'Fernway', array( 'fontWeight' => '700' ) ),
		array(
			'CAIRN/CO',
			array(
				'fontWeight'    => '800',
				'letterSpacing' => '0.02em',
			),
		),
		array(
			'larchmont',
			array(
				'fontWeight'    => '500',
				'letterSpacing' => '-0.02em',
			),
		),
		array(
			'SUMMIT CLUB',
			array(
				'fontWeight'    => '600',
				'letterSpacing' => '0.18em',
			),
		),
		array(
			'Scree &amp; Co',
			array(
				'fontStyle'  => 'italic',
				'fontWeight' => '800',
			),
		),
	);
	$logos = '';
	foreach ( $marks as list( $mark, $type ) ) {
		$logos .= slide(
			paragraph(
				$mark,
				array(
					'style'    => array( 'typography' => $type ),
					'fontSize' => 'x-large',
				)
			)
		);
	}
	$page .= section(
		'Continuous autoplay',
		'Trusted on the trail',
		'A marquee that never stops: continuous autoplay, endless loop and both edges faded out.',
		carousel(
			track(
				$logos,
				array(
					'layout'     => layout( array( 5, 1, 0 ), array( 4, 1, 0 ), array( 2, 1, 0 ) ),
					'slideWidth' => array(
						'min' => 160,
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
				)
			)
		),
		array( INK, '#e9e7e1', LIME )
	);

	wp_insert_post(
		array(
			'import_id'     => SHOWCASE_ID,
			'post_type'     => 'page',
			'post_status'   => 'publish',
			'post_title'    => 'Switchback Carousel showcase',
			'post_name'     => 'showcase',
			'page_template' => 'page-no-title',
			// wp_insert_post() unslashes; keep the JSON's escapes intact.
			'post_content'  => wp_slash( $page ),
		)
	);

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', SHOWCASE_ID );
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules();
}

run();
