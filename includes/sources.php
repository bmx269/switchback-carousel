<?php
/**
 * Slide sources for query-mode tracks.
 *
 * A source turns the track's `query` attribute into WP_Query arguments.
 * Switchback Carousel ships three; add more with switchback_carousel_register_source() on the
 * `switchback_carousel_register_sources` action:
 *
 *     add_action( 'switchback_carousel_register_sources', function () {
 *         switchback_carousel_register_source( 'upcoming-events', array(
 *             'label'       => __( 'Upcoming events', 'my-plugin' ),
 *             'description' => __( 'Events from today onward, soonest first.', 'my-plugin' ),
 *             'controls'    => array( 'limit' ),
 *             'callback'    => function ( array $query, int $context_post, ?WP_Block $block ): array {
 *                 return array(
 *                     'post_type'      => 'event',
 *                     'posts_per_page' => $query['limit'],
 *                     'meta_key'       => 'start_date',
 *                     'orderby'        => 'meta_value',
 *                     'order'          => 'ASC',
 *                     'meta_query'     => array( array( 'key' => 'start_date', 'compare' => '>=', 'value' => wp_date( 'Ymd' ) ) ),
 *                 );
 *             },
 *         ) );
 *     } );
 *
 * `controls` names the editor controls the source uses: postType, limit, skip,
 * sort, terms, authors, keyword, parent, stickies, excludeCurrentPost, fields,
 * relationshipField. Leave it out to show the general set.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Source registry.
 */
final class Sources {

	/**
	 * Registered sources.
	 *
	 * @var array<string, array>|null
	 */
	private static ?array $registry = null;

	/**
	 * All sources, registering the built-ins and running the action on first use.
	 */
	public static function all(): array {
		if ( null === self::$registry ) {
			self::$registry = array();
			self::builtins();
			/**
			 * Register custom slide sources with switchback_carousel_register_source().
			 */
			do_action( 'switchback_carousel_register_sources' );
		}
		return self::$registry;
	}

	/**
	 * Add or replace a source.
	 *
	 * @param string $name Source name.
	 * @param array  $args { label, description, controls, callback }.
	 */
	public static function add( string $name, array $args ): bool {
		$name = sanitize_key( $name );
		if ( '' === $name || ! isset( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'A slide source needs a name and a callable callback.', 'switchback-carousel' ), '1.0.0' );
			return false;
		}
		if ( null === self::$registry ) {
			self::all();
		}
		self::$registry[ $name ] = array(
			'name'        => $name,
			'label'       => (string) ( $args['label'] ?? $name ),
			'description' => (string) ( $args['description'] ?? '' ),
			'controls'    => isset( $args['controls'] ) ? array_values( (array) $args['controls'] ) : null,
			'callback'    => $args['callback'],
		);
		return true;
	}

	/**
	 * One source (falls back to "posts").
	 *
	 * @param string $name Source name.
	 */
	public static function get( string $name ): array {
		$all = self::all();
		return $all[ $name ] ?? $all['posts'];
	}

	/**
	 * Source list for the editor (without callbacks).
	 */
	public static function for_editor(): array {
		return array_values(
			array_map(
				static function ( array $source ): array {
					unset( $source['callback'] );
					return $source;
				},
				self::all()
			)
		);
	}

	/**
	 * Request-scoped cache of query results so carousel-state's slide count and
	 * the track's render share a single WP_Query per carousel.
	 *
	 * @var array<string, \WP_Post[]>
	 */
	private static array $post_cache = array();

	/**
	 * Posts for a query-mode track.
	 *
	 * Memoised per request on source + query + context_post. The $block argument
	 * only reaches third-party `switchback_carousel_query_args` filter callbacks;
	 * two WP_Block instances for the same parsed data produce the same results,
	 * so caching across them is safe.
	 *
	 * @param string        $source       Source name.
	 * @param array         $query        Query attribute.
	 * @param int           $context_post Singular post being viewed.
	 * @param WP_Block|null $block        Track block (null in editor previews).
	 * @return \WP_Post[]
	 */
	public static function posts( string $source, array $query, int $context_post, ?WP_Block $block ): array {
		$definition = self::get( $source );
		$query      = Query_Builder::normalize( $query );
		$cache_key  = md5( $definition['name'] . '|' . wp_json_encode( $query ) . '|' . $context_post );
		if ( isset( self::$post_cache[ $cache_key ] ) ) {
			return self::$post_cache[ $cache_key ];
		}

		$args = call_user_func( $definition['callback'], $query, $context_post, $block );
		if ( ! is_array( $args ) ) {
			return self::$post_cache[ $cache_key ] = array();
		}

		/**
		 * Filter the WP_Query arguments for a query-mode carousel.
		 *
		 * @param array         $args   WP_Query arguments.
		 * @param string        $source Source name.
		 * @param array         $query  Normalized query attribute.
		 * @param WP_Block|null $block  Track block, or null in editor previews.
		 */
		$args = (array) apply_filters( 'switchback_carousel_query_args', $args, $definition['name'], $query, $block );

		$args['no_found_rows'] = true;
		unset( $args['fields'] );

		return self::$post_cache[ $cache_key ] = ( new \WP_Query( $args ) )->posts;
	}

	/**
	 * Built-in sources.
	 */
	private static function builtins(): void {
		self::$registry['posts'] = array(
			'name'        => 'posts',
			'label'       => __( 'Posts', 'switchback-carousel' ),
			'description' => __( 'Any post type, filtered by terms, authors, keyword and field values (core meta or SCF/ACF fields).', 'switchback-carousel' ),
			'controls'    => null,
			'callback'    => static fn( array $query, int $context_post ): array => ( new Query_Builder( $query, $context_post ) )->args(),
		);

		self::$registry['related'] = array(
			'name'        => 'related',
			'label'       => __( 'Related to this post', 'switchback-carousel' ),
			'description' => __( 'Posts that share a term with the post being viewed. Elsewhere it shows the latest posts.', 'switchback-carousel' ),
			'controls'    => array( 'postType', 'limit', 'sort', 'fields' ),
			'callback'    => array( self::class, 'related' ),
		);

		self::$registry['relationship'] = array(
			'name'        => 'relationship',
			'label'       => __( 'Relationship field', 'switchback-carousel' ),
			'description' => __( 'The posts picked in a Relationship or Post Object field (SCF/ACF), or any field storing post IDs, on the post being viewed. Keeps the order chosen there.', 'switchback-carousel' ),
			'controls'    => array( 'relationshipField', 'limit' ),
			'callback'    => array( self::class, 'relationship' ),
		);
	}

	/**
	 * "Related" source.
	 *
	 * @param array $query        Query attribute.
	 * @param int   $context_post Post being viewed.
	 */
	public static function related( array $query, int $context_post ): array {
		$query['excludeCurrentPost'] = true;
		$args                        = ( new Query_Builder( $query, $context_post ) )->args();
		if ( ! $context_post ) {
			return $args;
		}

		$shared = array();
		foreach ( get_object_taxonomies( get_post_type( $context_post ), 'objects' ) as $taxonomy ) {
			$terms = $taxonomy->public ? wp_get_post_terms( $context_post, $taxonomy->name, array( 'fields' => 'ids' ) ) : array();
			if ( $terms && ! is_wp_error( $terms ) ) {
				$shared[] = array(
					'taxonomy' => $taxonomy->name,
					'terms'    => $terms,
				);
			}
		}
		if ( $shared ) {
			$args['tax_query'] = array( 'relation' => 'OR' ) + $shared; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		return $args;
	}

	/**
	 * "Relationship field" source.
	 *
	 * @param array $query        Query attribute.
	 * @param int   $context_post Post being viewed.
	 */
	public static function relationship( array $query, int $context_post ): array {
		$field = sanitize_text_field( (string) $query['relationshipField'] );
		$ids   = array();

		if ( $context_post && Query_Builder::field_allowed( $field ) ) {
			// Unformatted SCF/ACF value: the raw ID list, whatever the return format.
			$stored = function_exists( 'get_field' ) ? get_field( $field, $context_post, false ) : get_post_meta( $context_post, $field, true );
			$ids    = wp_parse_id_list( is_array( $stored ) ? $stored : (string) $stored );
		}

		return array(
			'post_type'           => array_values( array_filter( get_post_types(), 'is_post_type_viewable' ) ),
			'post_status'         => 'publish',
			'post__in'            => $ids ? $ids : array( 0 ),
			'orderby'             => 'post__in',
			'posts_per_page'      => bounded_int( $query['limit'], 1, Query_Builder::MAX_POSTS, 6 ),
			'ignore_sticky_posts' => true,
			'has_password'        => false,
		);
	}
}
