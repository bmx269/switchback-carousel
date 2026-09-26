<?php
/**
 * REST endpoints used by the editor.
 *
 *     POST switchback-carousel/v1/preview  Posts a query-mode track would show (same code path as the front end).
 *     GET  switchback-carousel/v1/fields   Fields a post type can be filtered or sorted by: registered meta and SCF/ACF fields.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes.
 */
final class Rest {

	/**
	 * Hook up route registration.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public static function routes(): void {
		$editors = static fn(): bool => current_user_can( 'edit_posts' );

		register_rest_route(
			'switchback-carousel/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => $editors,
				'callback'            => array( self::class, 'preview' ),
				'args'                => array(
					'source'  => array(
						'type'              => 'string',
						'default'           => 'posts',
						'sanitize_callback' => 'sanitize_key',
					),
					'query'   => array(
						'type'    => 'object',
						'default' => array(),
					),
					'context' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			'switchback-carousel/v1',
			'/fields',
			array(
				'methods'             => 'GET',
				'permission_callback' => $editors,
				'callback'            => array( self::class, 'fields' ),
				'args'                => array(
					'postType' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Preview posts: IDs and types only; the editor renders them itself.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function preview( WP_REST_Request $request ): WP_REST_Response {
		$context = (int) $request['context'];
		if ( $context && ! current_user_can( 'edit_post', $context ) ) {
			$context = 0;
		}

		$posts = Sources::posts( (string) $request['source'], (array) $request['query'], $context, null );

		return new WP_REST_Response(
			array(
				'posts' => array_map(
					static fn( \WP_Post $post ): array => array(
						'id'   => $post->ID,
						'type' => $post->post_type,
					),
					$posts
				),
			)
		);
	}

	/**
	 * Filterable fields for a post type.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function fields( WP_REST_Request $request ): WP_REST_Response {
		$type   = (string) $request['postType'];
		$fields = array();

		foreach ( get_registered_meta_keys( 'post', $type ) + get_registered_meta_keys( 'post' ) as $key => $meta ) {
			if ( Query_Builder::field_allowed( $key ) ) {
				$fields[ $key ] = array(
					'key'    => $key,
					'label'  => $key,
					'origin' => 'meta',
					'cast'   => in_array( $meta['type'] ?? '', array( 'integer', 'number', 'boolean' ), true ) ? 'number' : 'text',
				);
			}
		}

		// SCF/ACF field groups shown on this post type. Their labels win over raw keys.
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			$casts     = array(
				'number'           => 'number',
				'range'            => 'number',
				'true_false'       => 'number',
				'date_picker'      => 'date',
				'date_time_picker' => 'datetime',
				'time_picker'      => 'time',
			);
			$container = array( 'tab', 'message', 'accordion', 'group', 'repeater', 'flexible_content', 'clone', 'gallery' );

			foreach ( acf_get_field_groups( array( 'post_type' => $type ) ) as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $field ) {
					$key = (string) ( $field['name'] ?? '' );
					if ( '' === $key || in_array( $field['type'], $container, true ) || ! Query_Builder::field_allowed( $key ) ) {
						continue;
					}
					$fields[ $key ] = array(
						'key'    => $key,
						'label'  => '' !== (string) $field['label'] ? (string) $field['label'] : $key,
						'origin' => 'scf',
						'cast'   => $casts[ $field['type'] ] ?? 'text',
						'group'  => (string) $group['title'],
						'type'   => (string) $field['type'],
					);
				}
			}
		}

		/**
		 * Filter the fields offered for filtering and sorting in the editor.
		 *
		 * @param array  $fields Keyed by meta key: { key, label, origin, cast }.
		 * @param string $type   Post type.
		 */
		$fields = (array) apply_filters( 'switchback_carousel_fields', $fields, $type );

		return new WP_REST_Response( array( 'fields' => array_values( $fields ) ) );
	}
}
