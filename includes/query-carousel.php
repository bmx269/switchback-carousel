<?php
/**
 * Query Loop carousel variation.
 *
 * Lets a core/query block (optionally powered by Advanced Query Loop) render
 * as a Splide carousel by wrapping its core/post-template output in our track
 * markup. The variation is identified by the block's `namespace` attribute
 * being `switchback-carousel/query-carousel`.
 *
 * Carousel display settings (per-device columns, motion, autoplay, edges)
 * live under a `switchback` attribute added to core/query from JS.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

use WP_HTML_Tag_Processor;

defined( 'ABSPATH' ) || exit;

const QUERY_NAMESPACE = 'switchback-carousel/query-carousel';

/**
 * Translate carousel settings into Splide options. Mirrors track/render.php's
 * logic so the two render paths produce identical runtime behaviour.
 *
 * @param array $settings The `switchback` attribute on core/query.
 */
function splide_options_from_settings( array $settings ): array {
	$motion   = (array) ( $settings['motion'] ?? array() );
	$autoplay = (array) ( $settings['autoplay'] ?? array() );
	$layout   = (array) ( $settings['layout'] ?? array() );
	$width    = (array) ( $settings['slideWidth'] ?? array() );

	$fade = 'fade' === ( $motion['transition'] ?? 'slide' );
	$loop = one_of( $motion['loop'] ?? 'none', array( 'none', 'loop', 'rewind' ) );
	$rtl  = 'auto' === ( $settings['direction'] ?? 'auto' ) ? is_rtl() : 'rtl' === ( $settings['direction'] ?? '' );

	$device = static function ( array $d, array $fallback ) {
		return array(
			'columns' => bounded_int( $d['columns'] ?? null, 1, 8, $fallback['columns'] ),
			'step'    => bounded_int( $d['step'] ?? null, 1, 8, $fallback['step'] ),
			'peek'    => bounded_int( $d['peek'] ?? null, 0, 45, $fallback['peek'] ),
		);
	};
	$desktop = $device(
		(array) ( $layout['desktop'] ?? array() ),
		array(
			'columns' => 3,
			'step'    => 1,
			'peek'    => 0,
		)
	);
	$tablet  = $device( (array) ( $layout['tablet'] ?? array() ), $desktop );
	$mobile  = $device(
		(array) ( $layout['mobile'] ?? array() ),
		array(
			'columns' => 1,
			'step'    => 1,
			'peek'    => 0,
		)
	);

	$per_device = static function ( array $d ) use ( $fade, $rtl ) {
		if ( $fade ) {
			return array(
				'perPage' => 1,
				'perMove' => 1,
				'padding' => 0,
			);
		}
		$edge = $rtl ? 'left' : 'right';
		return array(
			'perPage' => $d['columns'],
			'perMove' => min( $d['step'], $d['columns'] ),
			'padding' => $d['peek'] ? array( $edge => $d['peek'] . '%' ) : 0,
		);
	};

	$autoplays   = ! empty( $autoplay['enabled'] );
	$interval    = 'continuous' !== ( $autoplay['mode'] ?? 'interval' );
	$drag        = one_of( $motion['drag'] ?? 'snap', array( 'snap', 'free', 'none' ) );
	$type        = 'slide';
	if ( $fade ) {
		$type = 'fade';
	} elseif ( 'loop' === $loop || ( $autoplays && ! $interval ) ) {
		$type = 'loop';
	}

	$options = array_merge(
		$per_device( $desktop ),
		array(
			'type'          => $type,
			'rewind'        => 'rewind' === $loop,
			'focus'         => 'center' === ( $settings['focus'] ?? 'start' ) ? 'center' : 0,
			'trimSpace'     => 'center' !== ( $settings['focus'] ?? 'start' ),
			'omitEnd'       => 'slide' === $type,
			'speed'         => bounded_int( $motion['duration'] ?? 400, 0, 2000, 400 ),
			'drag'          => 'none' === $drag ? false : ( 'free' === $drag ? 'free' : true ),
			'snap'          => true,
			'wheel'         => ! empty( $motion['wheel'] ),
			'keyboard'      => 'focused',
			'direction'     => $rtl ? 'rtl' : 'ltr',
			'arrows'        => false,
			'pagination'    => false,
			'autoplay'      => $autoplays && $interval,
			'interval'      => bounded_int( $autoplay['interval'] ?? 5000, 1000, 60000, 5000 ),
			'pauseOnHover'  => ! empty( $autoplay['pauseOnHover'] ),
			'pauseOnFocus'  => true,
			'reducedMotion' => array(
				'speed'       => 0,
				'rewindSpeed' => 0,
				'autoplay'    => 'pause',
			),
			'breakpoints'   => array(
				781 => $per_device( $tablet ),
				480 => $per_device( $mobile ),
			),
		)
	);

	if ( $autoplays && ! $interval ) {
		$options['autoScroll'] = array(
			'speed'        => bounded_float( $autoplay['speed'] ?? 1, 0.1, 10, 1 ),
			'pauseOnHover' => ! empty( $autoplay['pauseOnHover'] ),
			'pauseOnFocus' => true,
		);
		$options['drag']       = 'free';
	}

	return array(
		'splide'   => $options,
		'minWidth' => bounded_int( $width['min'] ?? 0, 0, 800, 0 ),
		'rtl'      => $rtl,
		'edges'    => array(
			'overflow' => ! empty( ( (array) ( $settings['edges'] ?? array() ) )['overflow'] ),
			'fade'     => one_of( ( (array) ( $settings['edges'] ?? array() ) )['fade'] ?? 'none', array( 'none', 'both', 'end' ) ),
		),
		'grabCursor' => ! empty( $motion['grabCursor'] ),
	);
}

/**
 * Add Splide classes and wrappers to a rendered core/query output.
 *
 * core/query normally returns:
 *   <div class="wp-block-query">
 *     <ul class="wp-block-post-template …"><li>…</li>…</ul>
 *     <!-- optional pagination / no-results -->
 *   </div>
 *
 * We add `splide__list` to the <ul>, wrap it in `<div class="splide__track">`,
 * and give the outer div the `.switchback-track.splide` classes plus a
 * `data-switchback` config blob that track/view.js consumes.
 *
 * @param string $html Rendered HTML.
 * @param array  $cfg  Config from splide_options_from_settings().
 */
function wrap_as_track( string $html, array $cfg ): string {
	// Add `splide__list` to the post-template <ul> and remember where to splice
	// the <div class="splide__track"> wrappers.
	$processor = new WP_HTML_Tag_Processor( $html );
	$found     = false;
	while ( $processor->next_tag( array( 'tag_name' => 'ul' ) ) ) {
		if ( false !== strpos( (string) $processor->get_attribute( 'class' ), 'wp-block-post-template' ) ) {
			$processor->add_class( 'splide__list' );
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		// Nothing to wrap (empty results or markup we don't recognise).
		return $html;
	}
	$html = $processor->get_updated_html();

	// Splice <div class="splide__track"> around the <ul>. Done with string
	// matching because WP_HTML_Tag_Processor can't insert new elements.
	$html = (string) preg_replace(
		'#(<ul\b[^>]*\bwp-block-post-template\b[^>]*>)#',
		'<div class="splide__track">$1',
		$html,
		1
	);
	$html = (string) preg_replace( '#(</ul>)#', '$1</div>', $html, 1 );

	// Give the outer wp-block-query div the track classes and data-switchback
	// config so track/view.js finds and mounts it.
	$classes = array( 'switchback-track', 'splide' );
	if ( ! empty( $cfg['edges']['overflow'] ) ) {
		$classes[] = 'has-visible-overflow';
	}
	if ( 'none' !== $cfg['edges']['fade'] ) {
		$classes[] = 'has-faded-edges-' . $cfg['edges']['fade'];
	}
	if ( ! empty( $cfg['grabCursor'] ) ) {
		$classes[] = 'has-grab-cursor';
	}
	$processor = new WP_HTML_Tag_Processor( $html );
	if ( $processor->next_tag( array( 'tag_name' => 'div', 'class_name' => 'wp-block-query' ) ) ) {
		foreach ( $classes as $c ) {
			$processor->add_class( $c );
		}
		$processor->set_attribute(
			'data-switchback',
			(string) wp_json_encode( array( 'splide' => $cfg['splide'], 'minWidth' => $cfg['minWidth'] ) )
		);
		$processor->set_attribute( 'aria-label', __( 'Carousel', 'switchback-carousel' ) );
		if ( ! empty( $cfg['rtl'] ) ) {
			$processor->set_attribute( 'dir', 'rtl' );
		}
		return $processor->get_updated_html();
	}

	return $html;
}

/**
 * Catch rendered core/query output when it carries our namespace and
 * transform it into a Splide track.
 */
add_filter(
	'render_block_core/query',
	static function ( string $html, array $block ): string {
		if ( QUERY_NAMESPACE !== ( $block['attrs']['namespace'] ?? '' ) ) {
			return $html;
		}
		$settings = (array) ( $block['attrs']['switchback'] ?? array() );
		$config   = splide_options_from_settings( $settings );
		return wrap_as_track( $html, $config );
	},
	10,
	2
);

/**
 * Hint to editor assets that AQL is present. Switchback adapts no behaviour on
 * this flag today — Query Loop + AQL both work transparently because our
 * render filter only looks at the final HTML, not the query path.
 */
add_action(
	'enqueue_block_editor_assets',
	static function (): void {
		$handle = generate_block_asset_handle( 'switchback-carousel/carousel', 'editorScript' );
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}
		wp_add_inline_script(
			$handle,
			'window.switchbackQueryCarousel = ' . wp_json_encode(
				array(
					'namespace' => QUERY_NAMESPACE,
					'hasAQL'    => defined( 'AdvancedQueryLoop\\BUILD_DIR_PATH' ),
				)
			) . ';',
			'before'
		);
	},
	9
);
