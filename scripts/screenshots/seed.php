<?php
/**
 * Demo content for WordPress.org screenshots. Run in wp-env:
 * npx wp-env run cli --env-cwd=wp-content/plugins/switchback-carousel wp eval-file scripts/screenshots/seed.php
 *
 * @package SwitchbackCarousel
 */

// phpcs:disable WordPress.WP.AlternativeFunctions

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$dir = dirname( __DIR__, 2 ) . '/artifacts/screenshots/images';

// Start clean.
foreach ( get_posts( array( 'post_type' => array( 'post', 'page', 'attachment', 'acf-field-group', 'acf-field' ), 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
update_option( 'blogname', 'Switchback Trails' );
update_option( 'blogdescription', 'Notes from the high country' );

// SCF field group: distance (number) and hike date (date picker).
if ( function_exists( 'acf_import_field_group' ) ) {
	acf_import_field_group(
		array(
			'key'      => 'group_trail_details',
			'title'    => 'Trail details',
			'fields'   => array(
				array( 'key' => 'field_trail_distance', 'name' => 'distance', 'label' => 'Distance (km)', 'type' => 'number' ),
				array( 'key' => 'field_trail_date', 'name' => 'hike_date', 'label' => 'Hike date', 'type' => 'date_picker', 'return_format' => 'F j, Y', 'display_format' => 'F j, Y' ),
				array( 'key' => 'field_trail_region', 'name' => 'region', 'label' => 'Region', 'type' => 'text' ),
			),
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
		)
	);
}

$trails = array(
	array( 'Ridge Loop at Dawn', 12.4, 'Coast Mountains', 'Headlamps on at five, summit coffee at seven.' ),
	array( 'Lakeside Switchbacks', 8.2, 'Interior Plateau', 'Twenty-two turns up from the water, every one worth it.' ),
	array( 'Blue Hour on the Col', 15.1, 'Rockies', 'A long day that ended with the best light of the summer.' ),
	array( 'Meadow Traverse', 6.5, 'Cascades', 'Wildflowers, marmots and one very curious grouse.' ),
	array( 'Canyon Rim Trail', 10.8, 'Desert Southwest', 'Red rock, thin shade and a view down a thousand metres.' ),
	array( 'Glacier Viewpoint', 18.3, 'Coast Mountains', 'The ice has pulled back since our last visit.' ),
	array( 'Night Hike to Pine Ridge', 7.4, 'Kootenays', 'Stars, owls and the long way back to the car.' ),
	array( 'Golden Hills Circuit', 11.6, 'Okanagan', 'Late-season grass and a warm wind all afternoon.' ),
);

$categories = array(
	'Day hikes'   => wp_create_category( 'Day hikes' ),
	'Overnights'  => wp_create_category( 'Overnights' ),
);

foreach ( $trails as $index => list( $title, $distance, $region, $excerpt ) ) {
	$id = wp_insert_post(
		array(
			'post_title'    => $title,
			'post_excerpt'  => $excerpt,
			'post_content'  => '<!-- wp:paragraph --><p>' . esc_html( $excerpt ) . '</p><!-- /wp:paragraph -->',
			'post_status'   => 'publish',
			'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $index * 9 + 2 ) . ' days' ) ),
			'post_category' => array( $distance > 12 ? $categories['Overnights'] : $categories['Day hikes'] ),
		)
	);
	update_field( 'distance', $distance, $id );
	update_field( 'hike_date', gmdate( 'Ymd', strtotime( '-' . ( $index * 9 + 4 ) . ' days' ) ), $id );
	update_field( 'region', $region, $id );

	$source = $dir . '/trail-' . ( $index + 1 ) . '.jpg';
	$tmp    = wp_tempnam( $source );
	copy( $source, $tmp );
	$image = media_handle_sideload( array( 'name' => sanitize_title( $title ) . '.jpg', 'tmp_name' => $tmp ), $id, $title );
	if ( ! is_wp_error( $image ) ) {
		set_post_thumbnail( $id, $image );
	}
}

$registry = WP_Block_Patterns_Registry::get_instance();
$pattern  = static fn( string $slug ): string => $registry->get_registered( 'switchback-carousel/' . $slug )['content'];

// Screenshot 1 & 2: posts carousel with overlay arrows, filtered by distance.
$journal = str_replace(
	'{"mode":"query",',
	'{"mode":"query","query":{"postType":"post","limit":8,"sort":{"by":"date","direction":"desc"},"fields":{"match":"all","rules":[{"field":"distance","operator":"gt","value":"6","cast":"decimal"}]}},',
	$pattern( 'latest-posts' )
);
$journal = str_replace( '<!-- wp:post-date /-->', '<!-- wp:post-excerpt {"moreText":"Read the notes","excerptLength":14} /-->', $journal );

wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Trail journal',
		'post_name'    => 'trail-journal',
		'post_content' => '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Every hike longer than six kilometres, newest first.</p><!-- /wp:paragraph -->' . $journal,
	)
);

// Screenshot 4: feature cards and quote rotator.
$cards = str_replace(
	'<!-- wp:switchback-carousel/slide --><div class="wp-block-switchback-carousel-slide">',
	'<!-- wp:switchback-carousel/slide {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"12px"}},"backgroundColor":"accent-5"} --><div class="wp-block-switchback-carousel-slide has-accent-5-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">',
	$pattern( 'feature-cards' )
);
$cards  = str_replace(
	array( 'Listen', 'Plan', 'Act', 'Review', 'Refine', 'Describe this step in a sentence or two.' ),
	array( 'Pick a route', 'Check the weather', 'Pack light', 'Start early', 'Leave no trace', 'Small habits that make a long day on the trail easier.' ),
	$cards
);
$quotes = str_replace(
	array( 'They listened first, then planned with us.', 'Straight answers and steady guidance.', 'We finally feel ahead of things.', 'Name, role' ),
	array( 'The best trail notes we have found anywhere.', 'Honest distances and honest times.', 'We planned our whole summer from this journal.', 'A reader' ),
	$pattern( 'quote-rotator' )
);

wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Trail tips',
		'post_name'    => 'trail-tips',
		'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Before you go</h2><!-- /wp:heading -->' . $cards
			. '<!-- wp:spacer {"height":"48px"} --><div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->'
			. '<!-- wp:heading --><h2 class="wp-block-heading">What readers say</h2><!-- /wp:heading -->' . $quotes,
	)
);

WP_CLI::success( 'Screenshot demo content created.' );
