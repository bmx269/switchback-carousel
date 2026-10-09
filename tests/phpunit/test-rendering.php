<?php
/**
 * Block registration and server rendering.
 *
 * @package SwitchbackCarousel
 */

use function SwitchbackCarousel\icon_svg;

/**
 * Rendering tests.
 */
class Test_Rendering extends WP_UnitTestCase {

	/**
	 * Track markup around the given slides.
	 *
	 * @param array  $attrs  Track attributes.
	 * @param string $slides Slide markup.
	 */
	private function track( array $attrs, string $slides ): string {
		$json   = $attrs ? wp_json_encode( $attrs ) : '{}';
		$markup = '<!-- wp:switchback-carousel/carousel --><!-- wp:switchback-carousel/track ' . $json . ' -->'
			. $slides . '<!-- /wp:switchback-carousel/track --><!-- /wp:switchback-carousel/carousel -->';
		return do_blocks( $markup );
	}

	/**
	 * A slide.
	 *
	 * @param string $inner Inner blocks.
	 */
	private function slide( string $inner ): string {
		return '<!-- wp:switchback-carousel/slide --><div class="wp-block-switchback-carousel-slide">' . $inner . '</div><!-- /wp:switchback-carousel/slide -->';
	}

	/**
	 * Number of rendered slide elements.
	 *
	 * @param string $html Rendered HTML.
	 */
	private function count_slides( string $html ): int {
		$tags  = new WP_HTML_Tag_Processor( $html );
		$count = 0;
		while ( $tags->next_tag( array( 'class_name' => 'wp-block-switchback-carousel-slide' ) ) ) {
			++$count;
		}
		return $count;
	}

	/**
	 * Decoded data-switchback config from markup.
	 *
	 * @param string $html Rendered HTML.
	 */
	private function config( string $html ): array {
		$tags = new WP_HTML_Tag_Processor( $html );
		$tags->next_tag( array( 'class_name' => 'switchback-track' ) );
		return json_decode( (string) $tags->get_attribute( 'data-switchback' ), true );
	}

	public function test_blocks_are_registered() {
		foreach ( array( 'carousel', 'track', 'slide', 'controls' ) as $block ) {
			$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'switchback-carousel/' . $block ), $block );
		}
	}

	public function test_manual_track_renders_splide_structure_and_slides() {
		$html = $this->track( array(), $this->slide( '<p>One</p>' ) . $this->slide( '<p>Two</p>' ) );

		$root = new WP_HTML_Tag_Processor( $html );
		$root->next_tag();
		$this->assertTrue( $root->has_class( 'switchback' ) );
		$this->assertTrue( $root->has_class( 'wp-block-switchback-carousel-carousel' ) );
		$this->assertStringContainsString( 'switchback-track splide', $html );
		$this->assertStringContainsString( '<div class="splide__track"><div class="splide__list">', $html );
		$this->assertSame( 2, $this->count_slides( $html ) );
		// Saved content never carries engine classes; the script adds them.
		$this->assertStringNotContainsString( 'splide__slide', $html );
	}

	public function test_layout_becomes_splide_options_and_breakpoints() {
		$config = $this->config(
			$this->track(
				array(
					'layout' => array(
						'desktop' => array( 'columns' => 4, 'step' => 2, 'peek' => 10 ),
						'tablet'  => array( 'columns' => 2 ),
						'mobile'  => array(),
					),
					'motion' => array( 'transition' => 'slide', 'duration' => 600, 'loop' => 'rewind', 'drag' => 'free' ),
				),
				$this->slide( '<p>One</p>' )
			)
		);
		$splide = $config['splide'];

		$this->assertSame( 4, $splide['perPage'] );
		$this->assertSame( 2, $splide['perMove'] );
		$this->assertSame( array( 'right' => '10%' ), $splide['padding'] );
		$this->assertSame( 'slide', $splide['type'] );
		$this->assertTrue( $splide['rewind'] );
		$this->assertSame( 'free', $splide['drag'] );
		$this->assertSame( 600, $splide['speed'] );
		$this->assertFalse( $splide['arrows'] );
		// Tablet inherits step/peek from desktop; mobile falls back to one slide.
		$this->assertSame( array( 'perPage' => 2, 'perMove' => 2, 'padding' => array( 'right' => '10%' ) ), $splide['breakpoints']['781'] );
		$this->assertSame( array( 'perPage' => 1, 'perMove' => 1, 'padding' => 0 ), $splide['breakpoints']['480'] );
	}

	public function test_fade_and_continuous_autoplay() {
		$fade = $this->config( $this->track( array( 'motion' => array( 'transition' => 'fade' ) ), $this->slide( '<p>x</p>' ) ) )['splide'];
		$this->assertSame( 'fade', $fade['type'] );
		$this->assertSame( 1, $fade['perPage'] );

		$flow = $this->config(
			$this->track(
				array(
					'autoplay' => array(
						'enabled' => true,
						'mode'    => 'continuous',
						'speed'   => 2,
					),
				),
				$this->slide( '<p>x</p>' )
			)
		)['splide'];
		$this->assertSame( 'loop', $flow['type'] );
		$this->assertSame( 2.0, (float) $flow['autoScroll']['speed'] );
		$this->assertFalse( $flow['autoplay'] );
	}

	public function test_rtl_peek_goes_on_the_left() {
		$splide = $this->config(
			$this->track(
				array(
					'direction' => 'rtl',
					'layout'    => array( 'desktop' => array( 'columns' => 2, 'step' => 1, 'peek' => 20 ) ),
				),
				$this->slide( '<p>x</p>' )
			)
		)['splide'];
		$this->assertSame( 'rtl', $splide['direction'] );
		$this->assertSame( array( 'left' => '20%' ), $splide['padding'] );
	}


	/**
	 * @dataProvider controls
	 *
	 * @param string $control  Control type.
	 * @param string $expected Markup that must appear.
	 */
	public function test_controls_render_their_markup( string $control, string $expected ) {
		$html = do_blocks( '<!-- wp:switchback-carousel/controls ' . wp_json_encode( array( 'control' => $control ) ) . ' /-->' );
		$this->assertStringContainsString( 'switchback-controls is-' . $control, $html );
		$this->assertStringContainsString( $expected, $html );
	}

	/**
	 * Control cases.
	 */
	public static function controls(): array {
		return array(
			'arrows'     => array( 'arrows', 'aria-label="Previous slide"' ),
			'pagination' => array( 'pagination', 'switchback-controls__pages' ),
			'progress'   => array( 'progress', 'switchback-controls__progress-bar' ),
			'counter'    => array( 'counter', 'switchback-controls__total' ),
			'playpause'  => array( 'playpause', 'switchback-controls__playpause" aria-pressed="false" hidden' ),
		);
	}

	public function test_controls_line_up_with_the_wide_track_by_default() {
		$html = do_blocks( '<!-- wp:switchback-carousel/controls /-->' );
		$this->assertStringContainsString( 'alignwide', $html );
		$this->assertStringContainsString( 'alignfull', do_blocks( '<!-- wp:switchback-carousel/controls {"align":"full"} /-->' ) );
	}

	public function test_control_size_is_only_set_when_chosen() {
		$default = do_blocks( '<!-- wp:switchback-carousel/controls {"control":"progress"} /-->' );
		$sized   = do_blocks( '<!-- wp:switchback-carousel/controls {"control":"progress","size":6} /-->' );
		$this->assertStringNotContainsString( '--switchback-control-size', $default );
		$this->assertStringContainsString( '--switchback-control-size:6px', $sized );
	}

	public function test_icons() {
		$this->assertStringContainsString( '<svg class="switchback-icon"', icon_svg( 'arrow-right', 'bold' ) );
		// Unknown icons fall back to a caret rather than an empty button.
		$this->assertSame( icon_svg( 'caret-right' ), icon_svg( 'no-such-icon' ) );
	}

	public function test_custom_icons_are_sanitized() {
		$svg = icon_svg( 'custom', 'regular', '<svg viewBox="0 0 10 10" onload="alert(1)"><script>alert(2)</script><path d="M0 0h10" onclick="x"/></svg>' );
		$this->assertStringContainsString( '<path d="M0 0h10"', $svg );
		$this->assertStringNotContainsString( 'script', $svg );
		$this->assertStringNotContainsString( 'onload', $svg );
		$this->assertStringNotContainsString( 'onclick', $svg );
	}

	public function test_patterns_are_registered_and_valid() {
		$registry = WP_Block_Patterns_Registry::get_instance();
		foreach ( array( 'feature-cards', 'quote-rotator', 'latest-posts', 'logo-marquee', 'gallery-strip' ) as $slug ) {
			$pattern = $registry->get_registered( 'switchback-carousel/' . $slug );
			$this->assertIsArray( $pattern, $slug );
			$this->assertSame( array( 'switchback-carousel/carousel' ), $pattern['blockTypes'] );

			$blocks = parse_blocks( $pattern['content'] );
			$this->assertSame( 'switchback-carousel/carousel', $blocks[0]['blockName'], $slug );

			$inner = $blocks[0]['innerBlocks'][0];
			if ( 'latest-posts' === $slug ) {
				// Post carousels are a Query Loop with carousel display on.
				$this->assertSame( 'core/query', $inner['blockName'], $slug );
				$this->assertTrue( $inner['attrs']['switchback']['enabled'], $slug );
			} else {
				$this->assertSame( 'switchback-carousel/track', $inner['blockName'], $slug );
			}
		}
	}
}
