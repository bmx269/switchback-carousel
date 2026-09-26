<?php
/**
 * Dynamic values in field rules.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic values in field rules.
 *
 *     {today}             2026-09-26   (Y-m-d)
 *     {today_ymd}         20260926     (SCF/ACF date picker storage)
 *     {now}               2026-09-26 14:05:00
 *     {current_post_id}   ID of the singular post being viewed
 *     {current_user_id}   ID of the logged-in user
 *
 * Date tokens take a day offset: {today+7}, {today_ymd-30}.
 */
final class Tokens {

	/**
	 * Replace tokens in a value.
	 *
	 * @param string $value        Rule value.
	 * @param int    $context_post Singular post being viewed.
	 */
	public static function expand( string $value, int $context_post = 0 ): string {
		if ( false === strpos( $value, '{' ) ) {
			return $value;
		}
		return (string) preg_replace_callback(
			'/\{(today_ymd|today|now|current_post_id|current_user_id)([+-]\d{1,4})?\}/',
			static function ( array $token ) use ( $context_post ): string {
				$when = time() + (int) ( $token[2] ?? 0 ) * DAY_IN_SECONDS;
				switch ( $token[1] ) {
					case 'today':
						return wp_date( 'Y-m-d', $when );
					case 'today_ymd':
						return wp_date( 'Ymd', $when );
					case 'now':
						return wp_date( 'Y-m-d H:i:s', $when );
					case 'current_post_id':
						return (string) $context_post;
					default:
						return (string) get_current_user_id();
				}
			},
			$value
		);
	}
}
