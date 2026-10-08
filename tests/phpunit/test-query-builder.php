<?php
/**
 * Tokens.
 */

use SwitchbackCarousel\Tokens;

/**
 * Token expansion (date relative, current post ID, unknown passthrough).
 */
class Test_Tokens extends WP_UnitTestCase {

	public function test_tokens_expand_dates_and_ids() {
		$this->assertSame( wp_date( 'Y-m-d' ), Tokens::expand( '{today}' ) );
		$this->assertSame( wp_date( 'Ymd', time() + 7 * DAY_IN_SECONDS ), Tokens::expand( '{today_ymd+7}' ) );
		$this->assertSame( wp_date( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ), Tokens::expand( '{today-30}' ) );
		$this->assertSame( '15', Tokens::expand( '{current_post_id}', 15 ) );
		$this->assertSame( 'plain {unknown}', Tokens::expand( 'plain {unknown}' ) );
	}
}
