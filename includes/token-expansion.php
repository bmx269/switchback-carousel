<?php
/**
 * Expand `{today}`, `{today+N}`, `{current_post_id}` and the rest of the
 * Switchback tokens inside WP_Query arguments produced by core/query (the
 * Query Loop block) and Advanced Query Loop. Lets authors write a relative
 * date such as `{today_ymd}` as an AQL meta query value instead of a literal
 * date.
 *
 * AQL's date_query panel can't use tokens: AQL splits date_primary into
 * year/month/day with substr() before aql_query_vars runs, and its UI is a
 * date picker. Its before/after-current options cover relative post dates.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Walk any array, expanding tokens in every string leaf. Non-string scalars
 * and non-array values pass through untouched.
 *
 * @param array $args         WP_Query arguments or sub-tree.
 * @param int   $context_post Singular post being viewed (used by
 *                            {current_post_id}).
 */
function expand_tokens_deep( array $args, int $context_post ): array {
	foreach ( $args as $key => $value ) {
		if ( is_array( $value ) ) {
			$args[ $key ] = expand_tokens_deep( $value, $context_post );
		} elseif ( is_string( $value ) && false !== strpos( $value, '{' ) ) {
			$args[ $key ] = Tokens::expand( $value, $context_post );
		}
	}
	return $args;
}

/**
 * Resolve the context post for token expansion. Prefers an explicit postId on
 * the block (set by core/post-template iterations) over the queried object.
 *
 * @param array|null $parsed_block Parsed block (optional).
 */
function token_context_post( ?array $parsed_block = null ): int {
	if ( is_array( $parsed_block ) && isset( $parsed_block['context']['postId'] ) ) {
		return (int) $parsed_block['context']['postId'];
	}
	return is_singular() ? (int) get_queried_object_id() : 0;
}

/**
 * Core Query Loop block. The $block parameter is a WP_Block instance.
 */
add_filter(
	'query_loop_block_query_vars',
	static function ( array $query, $block ): array {
		$context = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
		if ( ! $context ) {
			$context = token_context_post();
		}
		return expand_tokens_deep( $query, $context );
	},
	20,
	2
);

/**
 * Advanced Query Loop. We run after AQL has composed its date_query so our
 * tokens expand inside the final argument array.
 */
add_filter(
	'aql_query_vars',
	static function ( array $args ): array {
		return expand_tokens_deep( $args, token_context_post() );
	},
	20
);
