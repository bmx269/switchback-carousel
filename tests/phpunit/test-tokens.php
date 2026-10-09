<?php
/**
 * Tokens.
 *
 * @package SwitchbackCarousel
 */

use SwitchbackCarousel\Tokens;

/**
 * Token expansion, directly and inside Query Loop / Advanced Query Loop
 * query arguments.
 */
class Test_Tokens extends WP_UnitTestCase {

	public function test_tokens_expand_dates_and_ids() {
		$this->assertSame( wp_date( 'Y-m-d' ), Tokens::expand( '{today}' ) );
		$this->assertSame( wp_date( 'Ymd', time() + 7 * DAY_IN_SECONDS ), Tokens::expand( '{today_ymd+7}' ) );
		$this->assertSame( wp_date( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ), Tokens::expand( '{today-30}' ) );
		$this->assertSame( '15', Tokens::expand( '{current_post_id}', 15 ) );
		$this->assertSame( 'plain {unknown}', Tokens::expand( 'plain {unknown}' ) );
	}

	public function test_tokens_expand_in_nested_aql_meta_queries() {
		$args = apply_filters(
			'aql_query_vars',
			array(
				'post_type'  => 'event',
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => 'event_date',
						'value'   => '{today_ymd}',
						'compare' => '>=',
					),
				),
			),
			array(),
			false
		);
		$this->assertSame( wp_date( 'Ymd' ), $args['meta_query'][0]['value'] );
		$this->assertSame( 'event', $args['post_type'] );
	}

	public function test_tokens_expand_in_core_query_loop_vars() {
		$post  = self::factory()->post->create();
		$block = new WP_Block(
			array(
				'blockName' => 'core/post-template',
				'attrs'     => array(),
			),
			array( 'postId' => $post )
		);
		$args  = apply_filters( 'query_loop_block_query_vars', array( 'post__not_in' => array( '{current_post_id}' ) ), $block, 1 );
		$this->assertSame( array( (string) $post ), $args['post__not_in'] );
	}
}
