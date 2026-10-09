<?php
/**
 * Query Loop carousels: core/query with Switchback display turned on.
 *
 * @package SwitchbackCarousel
 */

/**
 * Rendering of the Query Loop carousel variation.
 */
class Test_Query_Carousel extends WP_UnitTestCase {

	/**
	 * Create four posts titled A–D.
	 */
	public function set_up() {
		parent::set_up();
		foreach ( array( 'Post A', 'Post B', 'Post C', 'Post D' ) as $title ) {
			self::factory()->post->create( array( 'post_title' => $title ) );
		}
	}

	/**
	 * Render a core/query block with the given attributes.
	 *
	 * @param array $attrs core/query attributes.
	 */
	private function render_query( array $attrs ): string {
		$attrs += array(
			'query' => array(
				'perPage'  => 10,
				'postType' => 'post',
				'order'    => 'asc',
				'orderBy'  => 'title',
				'inherit'  => false,
			),
		);
		return do_blocks(
			'<!-- wp:query ' . wp_json_encode( $attrs ) . ' --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->'
		);
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

	public function test_plain_query_loops_are_left_alone() {
		$html = $this->render_query( array() );
		$this->assertStringNotContainsString( 'switchback-track', $html );
		$this->assertStringNotContainsString( 'splide__list', $html );
	}

	public function test_enabled_query_loop_renders_as_a_splide_track() {
		$html = $this->render_query( array( 'switchback' => array( 'enabled' => true ) ) );

		$tags = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'switchback-track' ) ) );
		$this->assertTrue( $tags->has_class( 'splide' ) );
		$this->assertTrue( $tags->has_class( 'wp-block-query' ) );
		$this->assertFalse( $tags->has_class( 'is-layout-flow' ) );
		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'splide__track' ) ) );
		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'splide__list' ) ) );

		$this->assertSame( 4, substr_count( $html, 'wp-block-post-title' ) );
		$this->assertTrue( wp_script_is( generate_block_asset_handle( 'switchback-carousel/track', 'viewScript' ), 'enqueued' ) );
	}

	public function test_aql_namespace_and_carousel_display_coexist() {
		$html = $this->render_query(
			array(
				'namespace'  => 'advanced-query-loop',
				'switchback' => array( 'enabled' => true ),
			)
		);
		$this->assertStringContainsString( 'switchback-track', $html );
		$this->assertSame( 4, substr_count( $html, 'wp-block-post-title' ) );
	}

	public function test_legacy_namespace_still_renders_as_carousel() {
		$html = $this->render_query( array( 'namespace' => 'switchback-carousel/query-carousel' ) );
		$this->assertStringContainsString( 'switchback-track', $html );
	}

	public function test_display_settings_become_splide_options() {
		$splide = $this->config(
			$this->render_query(
				array(
					'switchback' => array(
						'enabled' => true,
						'layout'  => array(
							'desktop' => array(
								'columns' => 4,
								'step'    => 2,
							),
							'mobile'  => array( 'columns' => 1 ),
						),
						'motion'  => array( 'loop' => 'rewind' ),
					),
				)
			)
		)['splide'];
		$this->assertSame( 4, $splide['perPage'] );
		$this->assertSame( 2, $splide['perMove'] );
		$this->assertTrue( $splide['rewind'] );
		$this->assertSame( 1, $splide['breakpoints'][480]['perPage'] );
		$this->assertFalse( $splide['arrows'] );
	}

	public function test_fluid_columns_step_through_computed_breakpoints() {
		$html   = $this->render_query(
			array(
				'switchback' => array(
					'enabled'    => true,
					'layout'     => array( 'fluid' => true ),
					'slideWidth' => array( 'min' => 300 ),
				),
				'style'      => array( 'spacing' => array( 'blockGap' => '20px' ) ),
			)
		);
		$splide = $this->config( $html )['splide'];

		$this->assertStringContainsString( 'is-fluid-columns', $html );
		$this->assertStringContainsString( '--switchback-min-width:300px', $html );
		// One slide fits below 2 × 300 + 20 = 620px; two below 940px.
		$this->assertSame( 1, $splide['breakpoints'][619]['perPage'] );
		$this->assertSame( 2, $splide['breakpoints'][939]['perPage'] );
		$this->assertSame( '20px', $splide['gap'] );
	}
}
