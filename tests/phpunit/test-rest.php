<?php
/**
 * REST endpoints.
 *
 * @package SwitchbackCarousel
 */

/**
 * REST tests.
 */
class Test_Rest extends WP_Test_REST_TestCase {

	/**
	 * Editor user.
	 *
	 * @var int
	 */
	private static int $editor;

	/**
	 * Subscriber user.
	 *
	 * @var int
	 */
	private static int $subscriber;

	/**
	 * Create users once.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$editor     = $factory->user->create( array( 'role' => 'editor' ) );
		self::$subscriber = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Dispatch a request.
	 *
	 * @param string $method Method.
	 * @param string $route  Route.
	 * @param array  $params Params.
	 */
	private function request( string $method, string $route, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_body_params( $params );
		}
		return rest_get_server()->dispatch( $request );
	}

	public function test_routes_are_registered() {
		$routes = rest_get_server()->get_routes( 'switchback-carousel/v1' );
		$this->assertArrayHasKey( '/switchback-carousel/v1/preview', $routes );
		$this->assertArrayHasKey( '/switchback-carousel/v1/fields', $routes );
	}

	public function test_preview_requires_an_editor() {
		wp_set_current_user( 0 );
		$this->assertErrorResponse( 'rest_forbidden', $this->request( 'POST', '/switchback-carousel/v1/preview' ), 401 );

		wp_set_current_user( self::$subscriber );
		$this->assertErrorResponse( 'rest_forbidden', $this->request( 'POST', '/switchback-carousel/v1/preview' ), 403 );
	}

	public function test_preview_returns_post_ids_and_types() {
		$ids = self::factory()->post->create_many( 3 );
		wp_set_current_user( self::$editor );

		$response = $this->request(
			'POST',
			'/switchback-carousel/v1/preview',
			array(
				'source' => 'posts',
				'query'  => array( 'limit' => 2 ),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$posts = $response->get_data()['posts'];
		$this->assertCount( 2, $posts );
		$this->assertSame( 'post', $posts[0]['type'] );
		$this->assertContains( $posts[0]['id'], $ids );
		$this->assertSame( array( 'id', 'type' ), array_keys( $posts[0] ) );
	}

	public function test_preview_ignores_context_posts_the_user_cannot_edit() {
		$others = self::factory()->post->create( array( 'post_author' => self::factory()->user->create( array( 'role' => 'administrator' ) ) ) );
		update_post_meta( $others, 'picks', array( self::factory()->post->create() ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$response = $this->request(
			'POST',
			'/switchback-carousel/v1/preview',
			array(
				'source'  => 'relationship',
				'query'   => array( 'relationshipField' => 'picks' ),
				'context' => $others,
			)
		);
		$this->assertSame( array(), $response->get_data()['posts'] );
	}

	public function test_fields_lists_registered_public_meta_only() {
		register_post_meta(
			'post',
			'price',
			array(
				'type'         => 'number',
				'single'       => true,
				'show_in_rest' => true,
			)
		);
		register_post_meta( 'post', '_secret', array( 'single' => true ) );
		wp_set_current_user( self::$editor );

		$fields = wp_list_pluck( $this->request( 'GET', '/switchback-carousel/v1/fields', array( 'postType' => 'post' ) )->get_data()['fields'], 'cast', 'key' );

		$this->assertSame( 'number', $fields['price'] );
		$this->assertArrayNotHasKey( '_secret', $fields );
	}

	public function test_fields_include_scf_fields_with_casts() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			$this->markTestSkipped( 'Secure Custom Fields is not active.' );
		}
		acf_add_local_field_group(
			array(
				'key'      => 'group_switchback_test',
				'title'    => 'Event details',
				'fields'   => array(
					array(
						'key'   => 'field_switchback_date',
						'name'  => 'event_date',
						'label' => 'Event date',
						'type'  => 'date_picker',
					),
					array(
						'key'   => 'field_switchback_tab',
						'name'  => '',
						'label' => 'A tab',
						'type'  => 'tab',
					),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			)
		);
		wp_set_current_user( self::$editor );

		$fields = $this->request( 'GET', '/switchback-carousel/v1/fields', array( 'postType' => 'post' ) )->get_data()['fields'];
		$event  = current( wp_list_filter( $fields, array( 'key' => 'event_date' ) ) );

		$this->assertSame( 'scf', $event['origin'] );
		$this->assertSame( 'date', $event['cast'] );
		$this->assertSame( 'Event date', $event['label'] );
		$this->assertCount( 0, wp_list_filter( $fields, array( 'type' => 'tab' ) ) );
	}
}
