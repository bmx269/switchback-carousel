<?php
/**
 * Turns a track's `query` attribute into WP_Query arguments.
 *
 * The front end and the editor preview (REST) both go through this class, so
 * what editors see is what visitors get.
 *
 * Query shape:
 *
 *     postType            string   Viewable post type.
 *     limit, skip         int      How many posts, and how many to skip.
 *     sort                object   { by, direction, field } — field is the meta key when by = "field" / "field_number".
 *     terms               object   { taxonomy: [ termId, … ] } — posts must match every taxonomy listed.
 *     authors             int[]
 *     keyword             string
 *     parent              string   "" (any), "current" (children of the viewed page) or a post ID.
 *     stickies            string   include | exclude | only (posts only).
 *     excludeCurrentPost  bool
 *     fields              object   { match: all|any, rules: [ { field, operator, value, cast } ] }
 *     relationshipField   string   Used by the "relationship" source.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Query builder.
 */
final class Query_Builder {

	public const MAX_POSTS = 50;

	/** Rule operators → WP_Meta_Query compare values. */
	public const OPERATORS = array(
		'is'           => '=',
		'is_not'       => '!=',
		'gt'           => '>',
		'gte'          => '>=',
		'lt'           => '<',
		'lte'          => '<=',
		'contains'     => 'LIKE',
		'not_contains' => 'NOT LIKE',
		'in'           => 'IN',
		'not_in'       => 'NOT IN',
		'between'      => 'BETWEEN',
		'not_between'  => 'NOT BETWEEN',
		'exists'       => 'EXISTS',
		'not_exists'   => 'NOT EXISTS',
	);

	/** Rule casts → WP_Meta_Query types. */
	public const CASTS = array(
		'text'     => 'CHAR',
		'number'   => 'NUMERIC',
		'decimal'  => 'DECIMAL(20,6)',
		'date'     => 'DATE',
		'datetime' => 'DATETIME',
		'time'     => 'TIME',
	);

	/** Sort options → WP_Query orderby values. */
	public const SORTS = array(
		'date'         => 'date',
		'modified'     => 'modified',
		'title'        => 'title',
		'menu_order'   => 'menu_order',
		'random'       => 'rand',
		'comments'     => 'comment_count',
		'field'        => 'meta_value',
		'field_number' => 'meta_value_num',
	);

	/**
	 * Normalized query attribute.
	 *
	 * @var array
	 */
	private array $query;

	/**
	 * ID of the singular post being viewed, 0 elsewhere.
	 *
	 * @var int
	 */
	private int $context_post;

	/**
	 * Constructor.
	 *
	 * @param array $query        Query attribute.
	 * @param int   $context_post Singular post being viewed.
	 */
	public function __construct( array $query, int $context_post = 0 ) {
		$this->query        = self::normalize( $query );
		$this->context_post = $context_post;
	}

	/**
	 * Fill in defaults for missing keys.
	 *
	 * @param array $query Query attribute.
	 */
	public static function normalize( array $query ): array {
		$defaults        = array(
			'postType'           => 'post',
			'limit'              => 6,
			'skip'               => 0,
			'sort'               => array(),
			'terms'              => array(),
			'authors'            => array(),
			'keyword'            => '',
			'parent'             => '',
			'stickies'           => 'include',
			'excludeCurrentPost' => false,
			'fields'             => array(),
			'relationshipField'  => '',
		);
		$query           = array_merge( $defaults, $query );
		$query['sort']   = array_merge(
			array(
				'by'        => 'date',
				'direction' => 'desc',
				'field'     => '',
			),
			(array) $query['sort']
		);
		$query['fields'] = array_merge(
			array(
				'match' => 'all',
				'rules' => array(),
			),
			(array) $query['fields']
		);
		return $query;
	}

	/**
	 * The normalized query.
	 */
	public function query(): array {
		return $this->query;
	}

	/**
	 * Whether a meta key may be used in filters or sorting.
	 *
	 * Protected keys (leading underscore: plugin internals, SCF field
	 * references) are excluded unless a developer allows them.
	 *
	 * @param string $key Meta key.
	 */
	public static function field_allowed( string $key ): bool {
		$allowed = '' !== $key && ! is_protected_meta( $key, 'post' );

		/**
		 * Filter whether a meta key can be used to filter or sort slides.
		 *
		 * @param bool   $allowed Whether the key is allowed.
		 * @param string $key     Meta key.
		 */
		return (bool) apply_filters( 'switchback_carousel_field_allowed', $allowed, $key );
	}

	/**
	 * WP_Query args for the built-in posts source.
	 */
	public function args(): array {
		$args = array(
			'post_type'           => $this->post_type(),
			'post_status'         => 'publish',
			'posts_per_page'      => bounded_int( $this->query['limit'], 1, self::MAX_POSTS, 6 ),
			'offset'              => bounded_int( $this->query['skip'], 0, 10000, 0 ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		$args = array_merge( $args, $this->sorting(), $this->audience(), $this->hierarchy() );

		$taxonomy = $this->taxonomy_clauses();
		if ( $taxonomy ) {
			$args['tax_query'] = $taxonomy; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$fields = $this->field_clauses();
		if ( $fields ) {
			$args['meta_query'] = $fields; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $this->with_exclusions( $args );
	}

	/**
	 * A viewable post type, or a type that matches nothing.
	 */
	public function post_type(): string {
		$type = sanitize_key( (string) $this->query['postType'] );
		return post_type_exists( $type ) && is_post_type_viewable( $type ) ? $type : 'switchback_carousel_no_results';
	}

	/**
	 * WP_Query orderby, order and meta_key.
	 */
	public function sorting(): array {
		$sort = $this->query['sort'];
		$by   = self::SORTS[ $sort['by'] ] ?? 'date';

		$args = array(
			'orderby' => $by,
			'order'   => 'asc' === strtolower( (string) $sort['direction'] ) ? 'ASC' : 'DESC',
		);

		if ( 0 === strpos( $by, 'meta_value' ) ) {
			$key = sanitize_text_field( (string) $sort['field'] );
			if ( ! self::field_allowed( $key ) ) {
				return array( 'orderby' => 'date' ) + $args;
			}
			$args['meta_key'] = $key; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}
		return $args;
	}

	/**
	 * Keyword and author filters.
	 */
	private function audience(): array {
		$args    = array();
		$keyword = trim( (string) $this->query['keyword'] );
		if ( '' !== $keyword ) {
			$args['s'] = mb_substr( $keyword, 0, 200 );
		}
		$authors = array_slice( wp_parse_id_list( $this->query['authors'] ), 0, 100 );
		if ( $authors ) {
			$args['author__in'] = $authors;
		}
		return $args;
	}

	/**
	 * Parent filter for hierarchical types.
	 */
	private function hierarchy(): array {
		$parent = (string) $this->query['parent'];
		if ( '' === $parent || ! is_post_type_hierarchical( $this->post_type() ) ) {
			return array();
		}
		return array( 'post_parent' => 'current' === $parent ? $this->context_post : absint( $parent ) );
	}

	/**
	 * One clause per taxonomy; posts must match all of them.
	 */
	private function taxonomy_clauses(): array {
		$clauses = array();
		foreach ( (array) $this->query['terms'] as $taxonomy => $ids ) {
			$ids = array_slice( wp_parse_id_list( $ids ), 0, 100 );
			if ( ! $ids || ! taxonomy_exists( $taxonomy ) || ! is_taxonomy_viewable( $taxonomy ) ) {
				continue;
			}
			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'terms'    => $ids,
			);
			if ( 10 === count( $clauses ) ) {
				break;
			}
		}
		return count( $clauses ) > 1 ? array( 'relation' => 'AND' ) + $clauses : $clauses;
	}

	/**
	 * Meta query from field rules.
	 */
	public function field_clauses(): array {
		$clauses = array();
		foreach ( array_slice( (array) $this->query['fields']['rules'], 0, 10 ) as $rule ) {
			$clause = is_array( $rule ) ? $this->field_clause( $rule ) : null;
			if ( $clause ) {
				$clauses[] = $clause;
			}
		}
		if ( count( $clauses ) < 2 ) {
			return $clauses;
		}
		$relation = 'any' === $this->query['fields']['match'] ? 'OR' : 'AND';
		return array( 'relation' => $relation ) + $clauses;
	}

	/**
	 * One meta clause, or null when the rule is incomplete or not allowed.
	 *
	 * @param array $rule { field, operator, value, cast }.
	 */
	private function field_clause( array $rule ): ?array {
		$key = sanitize_text_field( (string) ( $rule['field'] ?? '' ) );
		if ( ! self::field_allowed( $key ) ) {
			return null;
		}

		$operator = (string) ( $rule['operator'] ?? 'is' );
		$compare  = self::OPERATORS[ $operator ] ?? '=';
		$clause   = array(
			'key'     => $key,
			'compare' => $compare,
			'type'    => self::CASTS[ $rule['cast'] ?? 'text' ] ?? 'CHAR',
		);

		if ( 'EXISTS' === $compare || 'NOT EXISTS' === $compare ) {
			return $clause;
		}

		$value = Tokens::expand( sanitize_text_field( (string) ( $rule['value'] ?? '' ) ), $this->context_post );

		if ( in_array( $operator, array( 'in', 'not_in', 'between', 'not_between' ), true ) ) {
			$value = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
			$needs = in_array( $operator, array( 'between', 'not_between' ), true ) ? 2 : 1;
			if ( count( $value ) < $needs || ( 2 === $needs && count( $value ) !== 2 ) ) {
				return null;
			}
		}

		$clause['value'] = $value;
		return $clause;
	}

	/**
	 * Sticky-post and current-post exclusions.
	 *
	 * WP_Query ignores post__not_in once post__in is set, so "only stickies"
	 * removes the current post from the include list instead.
	 *
	 * @param array $args WP_Query args so far.
	 */
	private function with_exclusions( array $args ): array {
		$skip_current = ! empty( $this->query['excludeCurrentPost'] ) && $this->context_post > 0;
		$exclude      = $skip_current ? array( $this->context_post ) : array();
		$stickies     = 'post' === $args['post_type'] ? (string) $this->query['stickies'] : 'include';

		if ( 'only' === $stickies ) {
			$sticky           = array_diff( array_map( 'intval', (array) get_option( 'sticky_posts', array() ) ), $exclude );
			$args['post__in'] = $sticky ? array_values( $sticky ) : array( 0 );
			return $args;
		}

		if ( 'exclude' === $stickies ) {
			$exclude = array_merge( $exclude, array_map( 'intval', (array) get_option( 'sticky_posts', array() ) ) );
		}
		if ( $exclude ) {
			// Only the current post and sticky posts: a short list, and the
			// query is already capped at MAX_POSTS.
			$args['post__not_in'] = array_values( array_unique( $exclude ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}
		return $args;
	}
}
