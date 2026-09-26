<?php
/**
 * Slide sources.
 *
 * @package SwitchbackCarousel
 */

use SwitchbackCarousel\Sources;

/**
 * Source registry tests.
 */
class Test_Sources extends WP_UnitTestCase {

	/**
	 * Titles of the posts a source returns.
	 *
	 * @param string $source  Source.
	 * @param array  $query   Query.
	 * @param int    $context Context post.
	 */
	private function titles( string $source, array $query, int $context = 0 ): array {
		return wp_list_pluck( Sources::posts( $source, $query, $context, null ), 'post_title' );
	}

	public function test_builtin_sources_are_registered() {
		$names = wp_list_pluck( Sources::for_editor(), 'name' );
		$this->assertContains( 'posts', $names );
		$this->assertContains( 'related', $names );
		$this->assertContains( 'relationship', $names );
	}

	public function test_editor_list_has_no_callbacks() {
		foreach ( Sources::for_editor() as $source ) {
			$this->assertArrayNotHasKey( 'callback', $source );
		}
	}

	public function test_unknown_source_falls_back_to_posts() {
		$this->assertSame( 'posts', Sources::get( 'nope' )['name'] );
	}

	public function test_posts_source_orders_and_filters_by_field() {
		foreach ( array( 'Cherry' => 3, 'Apple' => 1, 'Banana' => 2 ) as $title => $rank ) {
			$id = self::factory()->post->create( array( 'post_title' => $title ) );
			update_post_meta( $id, 'rank', $rank );
			update_post_meta( $id, 'colour', 'Apple' === $title ? 'red' : 'yellow' );
		}

		$by_rank = $this->titles(
			'posts',
			array(
				'sort' => array(
					'by'        => 'field_number',
					'field'     => 'rank',
					'direction' => 'asc',
				),
			)
		);
		$this->assertSame( array( 'Apple', 'Banana', 'Cherry' ), $by_rank );

		$yellow = $this->titles(
			'posts',
			array(
				'sort'   => array(
					'by'        => 'title',
					'direction' => 'asc',
				),
				'fields' => array(
					'rules' => array(
						array(
							'field'    => 'colour',
							'operator' => 'is',
							'value'    => 'yellow',
						),
					),
				),
			)
		);
		$this->assertSame( array( 'Banana', 'Cherry' ), $yellow );
	}

	public function test_related_source_shares_a_term_and_skips_the_current_post() {
		$shared  = self::factory()->category->create();
		$other   = self::factory()->category->create();
		$current = self::factory()->post->create( array( 'post_title' => 'Current', 'post_category' => array( $shared ) ) );
		self::factory()->post->create( array( 'post_title' => 'Sibling', 'post_category' => array( $shared ) ) );
		self::factory()->post->create( array( 'post_title' => 'Stranger', 'post_category' => array( $other ) ) );

		$this->assertSame( array( 'Sibling' ), $this->titles( 'related', array(), $current ) );
	}

	public function test_relationship_source_keeps_the_chosen_order() {
		$first  = self::factory()->post->create( array( 'post_title' => 'First pick' ) );
		$second = self::factory()->post->create( array( 'post_title' => 'Second pick', 'post_type' => 'page' ) );
		$owner  = self::factory()->post->create();
		update_post_meta( $owner, 'featured', array( $second, $first ) );

		$this->assertSame( array( 'Second pick', 'First pick' ), $this->titles( 'relationship', array( 'relationshipField' => 'featured' ), $owner ) );
	}

	public function test_relationship_source_without_context_is_empty() {
		$this->assertSame( array(), $this->titles( 'relationship', array( 'relationshipField' => 'featured' ) ) );
	}

	public function test_custom_source_and_query_args_filter() {
		self::factory()->post->create( array( 'post_title' => 'Alpha' ) );
		self::factory()->post->create( array( 'post_title' => 'Zulu' ) );

		$this->assertTrue(
			switchback_carousel_register_source(
				'test-reverse-title',
				array(
					'label'    => 'Test',
					'callback' => static fn(): array => array(
						'post_type' => 'post',
						'orderby'   => 'title',
						'order'     => 'DESC',
					),
				)
			)
		);
		$this->assertSame( array( 'Zulu', 'Alpha' ), $this->titles( 'test-reverse-title', array() ) );

		$limit_one = static function ( array $args, string $source ): array {
			return 'test-reverse-title' === $source ? array( 'posts_per_page' => 1 ) + $args : $args;
		};
		add_filter( 'switchback_carousel_query_args', $limit_one, 10, 2 );
		$this->assertSame( array( 'Zulu' ), $this->titles( 'test-reverse-title', array() ) );
		remove_filter( 'switchback_carousel_query_args', $limit_one, 10 );
	}

	/**
	 * @expectedIncorrectUsage SwitchbackCarousel\Sources::add
	 */
	public function test_invalid_source_is_rejected() {
		$this->assertFalse( switchback_carousel_register_source( 'broken', array( 'label' => 'No callback' ) ) );
	}
}
