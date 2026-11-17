<?php
/**
 * Per-device layout storage and migration.
 *
 * @package SwitchbackCarousel
 */

use function SwitchbackCarousel\migrate_content_layout;
use function SwitchbackCarousel\migrate_layout_to_style;
use function SwitchbackCarousel\resolve_device_layouts;
use function SwitchbackCarousel\viewport_breakpoints;

/**
 * Legacy `layout` and core responsive `style` storage resolve the same way,
 * and migration moves one to the other without changing the result.
 */
class Test_Responsive extends WP_UnitTestCase {

	const LAYOUT = array(
		'desktop' => array(
			'columns' => 4,
			'step'    => 2,
			'peek'    => 10,
		),
		'tablet'  => array( 'columns' => 2 ),
		'mobile'  => array(),
	);

	public function tear_down() {
		remove_all_filters( 'switchback_carousel_core_responsive' );
		parent::tear_down();
	}

	public function test_style_wins_over_legacy_layout_per_field() {
		$devices = resolve_device_layouts(
			self::LAYOUT,
			array(
				'@tablet' => array(
					'columns' => 3,
					'spacing' => array( 'blockGap' => '0' ),
				),
			)
		);
		$this->assertSame(
			array(
				'columns' => 4,
				'step'    => 2,
				'peek'    => 10,
			),
			$devices['desktop']
		);
		$this->assertSame( 3, $devices['tablet']['columns'] );
		$this->assertSame( 2, $devices['tablet']['step'], 'Tablet gaps fill from desktop.' );
		$this->assertSame( 1, $devices['mobile']['columns'], 'Mobile gaps fill with one slide, not desktop.' );
	}

	public function test_migration_keeps_the_resolved_layout() {
		$style  = array( 'spacing' => array( 'blockGap' => '1rem' ) );
		$result = migrate_layout_to_style( self::LAYOUT + array( 'fluid' => true ), $style );

		$this->assertTrue( $result['changed'] );
		$this->assertSame(
			array(
				'desktop' => self::LAYOUT['desktop'],
				'fluid'   => true,
			),
			$result['layout'],
			'Desktop is the base value and stays in the layout.'
		);
		$this->assertSame( '1rem', $result['style']['spacing']['blockGap'] );
		$this->assertSame( array( 'columns' => 2 ), $result['style']['@tablet'] );
		$this->assertArrayNotHasKey( '@mobile', $result['style'], 'Empty devices are not written.' );
		$this->assertSame(
			resolve_device_layouts( self::LAYOUT, $style ),
			resolve_device_layouts( $result['layout'], $result['style'] )
		);

		$again = migrate_layout_to_style( $result['layout'], $result['style'] );
		$this->assertFalse( $again['changed'], 'Migrating twice changes nothing.' );
	}

	public function test_content_migration_covers_tracks_and_query_carousels() {
		$content = implode(
			"\n",
			array(
				'<!-- wp:switchback-carousel/carousel --><div class="wp-block-switchback-carousel-carousel">',
				'<!-- wp:switchback-carousel/track {"layout":{"desktop":{"columns":4},"tablet":{"columns":2},"mobile":{"columns":1}}} /-->',
				'<!-- wp:switchback-carousel/track /-->',
				'</div><!-- /wp:switchback-carousel/carousel -->',
				'<!-- wp:query {"queryId":1,"switchback":{"enabled":true,"layout":{"fluid":false,"desktop":{"columns":3},"mobile":{"columns":2}}}} /-->',
				'<!-- wp:query {"queryId":2} /-->',
			)
		);

		$migrated = migrate_content_layout( $content );
		$this->assertNotNull( $migrated );

		$blocks = parse_blocks( $migrated );
		$tracks = $blocks[0]['innerBlocks'];

		$this->assertSame( array( 'desktop' => array( 'columns' => 4 ) ), $tracks[0]['attrs']['layout'] );
		$this->assertSame( array( 'columns' => 2 ), $tracks[0]['attrs']['style']['@tablet'] );
		$this->assertSame( array( 'columns' => 1 ), $tracks[0]['attrs']['style']['@mobile'] );

		// A track that relied on the block.json default keeps that layout.
		$this->assertSame(
			array(
				'columns' => 3,
				'step'    => 1,
				'peek'    => 0,
			),
			$tracks[1]['attrs']['layout']['desktop']
		);
		$this->assertSame( 1, $tracks[1]['attrs']['style']['@mobile']['columns'] );

		$query = $blocks[2]['attrs'];
		$this->assertSame(
			array(
				'fluid'   => false,
				'desktop' => array( 'columns' => 3 ),
			),
			$query['switchback']['layout']
		);
		$this->assertSame( array( 'columns' => 2 ), $query['style']['@mobile'] );

		$this->assertArrayNotHasKey( 'style', $blocks[4]['attrs'], 'Plain Query Loops are left alone.' );
		$this->assertNull( migrate_content_layout( $migrated ), 'Migrating twice changes nothing.' );
		$this->assertNull( migrate_content_layout( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ) );
	}

	public function test_breakpoints_follow_the_theme_only_with_core_storage_on() {
		$this->assertSame(
			array(
				'tablet' => 781,
				'mobile' => 480,
			),
			viewport_breakpoints()
		);

		if ( ! SwitchbackCarousel\core_responsive_available() ) {
			$this->markTestSkipped( 'Responsive style states need WordPress 7.1.' );
		}
		add_filter( 'switchback_carousel_core_responsive', '__return_true' );
		$breakpoints = viewport_breakpoints();
		$this->assertGreaterThan( $breakpoints['mobile'], $breakpoints['tablet'] );
	}
}
