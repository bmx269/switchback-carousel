<?php
/**
 * WP-CLI commands.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Switchback Carousel maintenance.
 */
class CLI {

	/**
	 * Move every carousel's per-device layout into core responsive style states.
	 *
	 * Rewrites posts, pages, synced patterns, templates and template parts saved
	 * in the database. Each change goes through wp_update_post(), so post types
	 * with revisions keep the previous version. Theme files on disk are not
	 * touched; they keep working because both storage shapes are read.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : List the posts that would change, without saving.
	 *
	 * [--post_type=<types>]
	 * : Comma-separated post types to limit the run to.
	 *
	 * [--force]
	 * : Run even though the switchback_carousel_core_responsive filter is off.
	 *
	 * ## EXAMPLES
	 *
	 *     wp switchback migrate-responsive --dry-run
	 *     wp switchback migrate-responsive --post_type=page,wp_block
	 *
	 * @subcommand migrate-responsive
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Flags.
	 */
	public function migrate_responsive( array $args, array $assoc_args ): void {
		if ( ! core_responsive_available() ) {
			WP_CLI::error( 'This WordPress has no responsive style states. They arrived in 7.1.' );
		}
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		if ( ! uses_core_responsive() && ! $dry_run && ! WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ) ) {
			WP_CLI::error( 'Core responsive storage is off. Enable the switchback_carousel_core_responsive filter first, or pass --force.' );
		}

		$types = array_filter( array_map( 'trim', explode( ',', (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'post_type', '' ) ) ) );
		$ids   = $this->candidate_ids( $types );

		// Saving as no user would run content through kses and strip markup.
		kses_remove_filters();

		$changed = 0;
		foreach ( $ids as $id ) {
			$post    = get_post( $id );
			$content = $post ? migrate_content_layout( $post->post_content ) : null;
			if ( null === $content ) {
				continue;
			}
			++$changed;
			$label = sprintf( '#%d %s "%s"', $post->ID, $post->post_type, $post->post_title );
			if ( $dry_run ) {
				WP_CLI::log( 'Would migrate ' . $label );
				continue;
			}
			$result = wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => wp_slash( $content ),
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( $label . ': ' . $result->get_error_message() );
				--$changed;
				continue;
			}
			WP_CLI::log( 'Migrated ' . $label );
		}

		WP_CLI::success(
			sprintf(
				'%1$s %2$d of %3$d posts containing carousels.',
				$dry_run ? 'Would migrate' : 'Migrated',
				$changed,
				count( $ids )
			)
		);
	}

	/**
	 * IDs of saved posts whose content may hold a carousel.
	 *
	 * @param string[] $types Post types, or empty for all.
	 * @return int[]
	 */
	private function candidate_ids( array $types ): array {
		global $wpdb;
		$where = $wpdb->prepare(
			"post_status NOT IN ( 'auto-draft', 'trash', 'inherit' ) AND ( post_content LIKE %s OR post_content LIKE %s )",
			'%' . $wpdb->esc_like( 'wp:switchback-carousel/track' ) . '%',
			'%' . $wpdb->esc_like( '"switchback":' ) . '%'
		);
		if ( $types ) {
			$where .= $wpdb->prepare(
				' AND post_type IN ( ' . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . ' )',
				$types
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off maintenance query, prepared above.
		return array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE {$where} ORDER BY ID" ) );
	}
}
