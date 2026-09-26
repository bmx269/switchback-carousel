<?php
/**
 * Query_Builder and Tokens.
 *
 * @package SwitchbackCarousel
 */

use SwitchbackCarousel\Query_Builder;
use SwitchbackCarousel\Tokens;

/**
 * Query builder tests.
 */
class Test_Query_Builder extends WP_UnitTestCase {

	/**
	 * Arguments for a query.
	 *
	 * @param array $query Query attribute.
	 * @param int   $post  Context post.
	 */
	private function args( array $query, int $post = 0 ): array {
		return ( new Query_Builder( $query, $post ) )->args();
	}

	public function test_defaults_query_latest_published_posts() {
		$args = $this->args( array() );

		$this->assertSame( 'post', $args['post_type'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame( 6, $args['posts_per_page'] );
		$this->assertSame( 'date', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );
		$this->assertTrue( $args['no_found_rows'] );
	}

	public function test_private_or_unknown_post_types_match_nothing() {
		$this->assertSame( 'switchback_carousel_no_results', $this->args( array( 'postType' => 'wp_block' ) )['post_type'] );
		$this->assertSame( 'switchback_carousel_no_results', $this->args( array( 'postType' => 'does-not-exist' ) )['post_type'] );
		$this->assertSame( 'page', $this->args( array( 'postType' => 'page' ) )['post_type'] );
	}

	public function test_limit_and_skip_are_bounded() {
		$args = $this->args(
			array(
				'limit' => 500,
				'skip'  => -4,
			)
		);
		$this->assertSame( Query_Builder::MAX_POSTS, $args['posts_per_page'] );
		$this->assertSame( 0, $args['offset'] );
	}

	public function test_sorting_maps_to_wp_query_orderby() {
		$args = $this->args(
			array(
				'sort' => array(
					'by'        => 'menu_order',
					'direction' => 'asc',
				),
			)
		);
		$this->assertSame( 'menu_order', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );

		$this->assertSame( 'rand', $this->args( array( 'sort' => array( 'by' => 'random' ) ) )['orderby'] );
		$this->assertSame( 'date', $this->args( array( 'sort' => array( 'by' => 'nonsense' ) ) )['orderby'] );
	}

	public function test_sorting_by_a_field_sets_meta_key() {
		$args = $this->args(
			array(
				'sort' => array(
					'by'    => 'field_number',
					'field' => 'price',
				),
			)
		);
		$this->assertSame( 'meta_value_num', $args['orderby'] );
		$this->assertSame( 'price', $args['meta_key'] );
	}

	public function test_sorting_by_a_protected_field_falls_back_to_date() {
		$args = $this->args(
			array(
				'sort' => array(
					'by'    => 'field',
					'field' => '_edit_lock',
				),
			)
		);
		$this->assertSame( 'date', $args['orderby'] );
		$this->assertArrayNotHasKey( 'meta_key', $args );
	}

	public function test_terms_become_an_and_tax_query_of_viewable_taxonomies() {
		$category = self::factory()->category->create();
		$tag      = self::factory()->tag->create();

		$args = $this->args(
			array(
				'terms' => array(
					'category'      => array( $category ),
					'post_tag'      => array( $tag ),
					'nav_menu'      => array( 1 ),
					'not_a_taxonomy' => array( 2 ),
				),
			)
		);

		$this->assertSame( 'AND', $args['tax_query']['relation'] );
		$this->assertCount( 3, $args['tax_query'] );
		$this->assertSame( array( $category ), $args['tax_query'][0]['terms'] );
	}

	/**
	 * @dataProvider operators
	 *
	 * @param string $operator Rule operator.
	 * @param string $compare  Expected meta compare.
	 * @param mixed  $value    Rule value.
	 * @param mixed  $expected Expected clause value (null = no value key).
	 */
	public function test_field_rule_operators( string $operator, string $compare, $value, $expected ) {
		$clauses = ( new Query_Builder(
			array(
				'fields' => array(
					'rules' => array(
						array(
							'field'    => 'price',
							'operator' => $operator,
							'value'    => $value,
							'cast'     => 'number',
						),
					),
				),
			)
		) )->field_clauses();

		$this->assertCount( 1, $clauses );
		$this->assertSame( $compare, $clauses[0]['compare'] );
		$this->assertSame( 'NUMERIC', $clauses[0]['type'] );
		if ( null === $expected ) {
			$this->assertArrayNotHasKey( 'value', $clauses[0] );
		} else {
			$this->assertSame( $expected, $clauses[0]['value'] );
		}
	}

	/**
	 * Operator → compare cases.
	 */
	public static function operators(): array {
		return array(
			'is'           => array( 'is', '=', '10', '10' ),
			'is not'       => array( 'is_not', '!=', '10', '10' ),
			'more than'    => array( 'gt', '>', '10', '10' ),
			'at least'     => array( 'gte', '>=', '10', '10' ),
			'less than'    => array( 'lt', '<', '10', '10' ),
			'at most'      => array( 'lte', '<=', '10', '10' ),
			'contains'     => array( 'contains', 'LIKE', '10', '10' ),
			'one of'       => array( 'in', 'IN', '1, 2 ,3', array( '1', '2', '3' ) ),
			'none of'      => array( 'not_in', 'NOT IN', '4,5', array( '4', '5' ) ),
			'between'      => array( 'between', 'BETWEEN', '10, 20', array( '10', '20' ) ),
			'filled in'    => array( 'exists', 'EXISTS', 'ignored', null ),
			'empty'        => array( 'not_exists', 'NOT EXISTS', '', null ),
			'unknown op'   => array( 'bogus', '=', '7', '7' ),
		);
	}

	public function test_between_needs_exactly_two_values() {
		$builder = new Query_Builder(
			array(
				'fields' => array(
					'rules' => array(
						array(
							'field'    => 'price',
							'operator' => 'between',
							'value'    => '10',
						),
					),
				),
			)
		);
		$this->assertSame( array(), $builder->field_clauses() );
	}

	public function test_several_rules_use_the_match_setting() {
		$rule = array(
			'field'    => 'colour',
			'operator' => 'is',
			'value'    => 'red',
		);

		$all = ( new Query_Builder( array( 'fields' => array( 'rules' => array( $rule, $rule ) ) ) ) )->field_clauses();
		$any = ( new Query_Builder(
			array(
				'fields' => array(
					'match' => 'any',
					'rules' => array( $rule, $rule ),
				),
			)
		) )->field_clauses();

		$this->assertSame( 'AND', $all['relation'] );
		$this->assertSame( 'OR', $any['relation'] );
	}

	public function test_protected_and_empty_fields_are_ignored() {
		$builder = new Query_Builder(
			array(
				'fields' => array(
					'rules' => array(
						array(
							'field'    => '_thumbnail_id',
							'operator' => 'exists',
						),
						array(
							'field'    => '',
							'operator' => 'is',
							'value'    => 'x',
						),
					),
				),
			)
		);
		$this->assertSame( array(), $builder->field_clauses() );
	}

	public function test_field_allowed_filter_can_open_protected_keys() {
		add_filter( 'switchback_carousel_field_allowed', '__return_true' );
		$this->assertTrue( Query_Builder::field_allowed( '_thumbnail_id' ) );
		remove_filter( 'switchback_carousel_field_allowed', '__return_true' );
		$this->assertFalse( Query_Builder::field_allowed( '_thumbnail_id' ) );
	}

	public function test_excluding_the_current_post() {
		$args = $this->args( array( 'excludeCurrentPost' => true ), 42 );
		$this->assertSame( array( 42 ), $args['post__not_in'] );

		$this->assertArrayNotHasKey( 'post__not_in', $this->args( array( 'excludeCurrentPost' => true ) ) );
	}

	public function test_sticky_posts() {
		$sticky = self::factory()->post->create();
		stick_post( $sticky );

		$this->assertSame( array( $sticky ), $this->args( array( 'stickies' => 'only' ) )['post__in'] );
		$this->assertContains( $sticky, $this->args( array( 'stickies' => 'exclude' ) )['post__not_in'] );

		// "Only" with the sticky post as the current post leaves nothing to show.
		$this->assertSame( array( 0 ), $this->args( array( 'stickies' => 'only', 'excludeCurrentPost' => true ), $sticky )['post__in'] );
	}

	public function test_parent_filter_for_hierarchical_types() {
		$args = $this->args(
			array(
				'postType' => 'page',
				'parent'   => 'current',
			),
			7
		);
		$this->assertSame( 7, $args['post_parent'] );
		$this->assertArrayNotHasKey( 'post_parent', $this->args( array( 'parent' => '3' ) ) );
	}

	public function test_tokens() {
		$this->assertSame( wp_date( 'Y-m-d' ), Tokens::expand( '{today}' ) );
		$this->assertSame( wp_date( 'Ymd', time() + 7 * DAY_IN_SECONDS ), Tokens::expand( '{today_ymd+7}' ) );
		$this->assertSame( wp_date( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ), Tokens::expand( '{today-30}' ) );
		$this->assertSame( '15', Tokens::expand( '{current_post_id}', 15 ) );
		$this->assertSame( 'plain {unknown}', Tokens::expand( 'plain {unknown}' ) );
	}

	public function test_token_values_reach_the_meta_query() {
		$clauses = ( new Query_Builder(
			array(
				'fields' => array(
					'rules' => array(
						array(
							'field'    => 'event_date',
							'operator' => 'gte',
							'value'    => '{today_ymd}',
							'cast'     => 'date',
						),
					),
				),
			)
		) )->field_clauses();

		$this->assertSame( wp_date( 'Ymd' ), $clauses[0]['value'] );
		$this->assertSame( 'DATE', $clauses[0]['type'] );
	}
}
