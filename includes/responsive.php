<?php
/**
 * Per-device carousel layout: one place to read, write and migrate it.
 *
 * Today the layout is stored in our own attribute:
 *
 *     layout: { desktop: {columns, step, peek}, tablet: {…}, mobile: {…} }
 *
 * WordPress 7.1 added responsive style states, stored on a block's `style`
 * attribute keyed by viewport ("@tablet", "@mobile"). Once third-party blocks
 * get a public API for them (Gutenberg #80388), the tablet and phone values
 * move there, named the way core's Gallery block names its per-viewport
 * `columns`. Desktop is the base value and stays in `layout`, as Gallery's
 * base `columns` stays in its own attribute:
 *
 *     layout: { desktop: {columns, step, peek} }
 *     style:  { "@tablet": {columns, step, peek}, "@mobile": {columns, step, peek} }
 *
 * Readers accept both shapes, with `style` winning, so content keeps
 * rendering the same before, during and after migration. Which shape the
 * editor writes, and which breakpoints the front end uses, depends on
 * uses_core_responsive(). See docs/responsive-styles.md for the switch-over.
 *
 * @package SwitchbackCarousel
 */

namespace SwitchbackCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Device => core viewport state key.
 */
const VIEWPORT_STATES = array(
	'tablet' => '@tablet',
	'mobile' => '@mobile',
);

/**
 * Breakpoints (max-width, px) used until core responsive styles are on.
 */
const LEGACY_BREAKPOINTS = array(
	'tablet' => 781,
	'mobile' => 480,
);

/**
 * Settings that vary per device.
 */
const DEVICE_FIELDS = array( 'columns', 'step', 'peek' );

/**
 * Whether this WordPress has responsive style states (7.1+).
 */
function core_responsive_available(): bool {
	return class_exists( 'WP_Theme_JSON' ) && method_exists( 'WP_Theme_JSON', 'get_viewport_media_queries' );
}

/**
 * Whether carousels store and resolve their layout through core responsive
 * styles. Off until the third-party API is public; flip the filter default
 * when it is.
 */
function uses_core_responsive(): bool {
	/**
	 * Filters whether Switchback stores per-device layout in core responsive
	 * style states instead of its own `layout` attribute.
	 *
	 * @param bool $enabled Default false.
	 */
	// responsive-switch: change the default to true once the API is public.
	return core_responsive_available() && (bool) apply_filters( 'switchback_carousel_core_responsive', false );
}

/**
 * Only the per-device fields, as integers; anything else is dropped.
 *
 * @param mixed $settings Stored settings for one device.
 */
function device_fields( $settings ): array {
	$clean = array();
	foreach ( DEVICE_FIELDS as $field ) {
		if ( is_array( $settings ) && isset( $settings[ $field ] ) && is_numeric( $settings[ $field ] ) ) {
			$clean[ $field ] = (int) $settings[ $field ];
		}
	}
	return $clean;
}

/**
 * Stored per-device settings from either shape, `style` winning per field.
 * Gaps are left as gaps; resolve_device_layouts() fills them.
 *
 * @param array $layout Legacy `layout` value ({desktop, tablet, mobile}).
 * @param array $style  The block's `style` attribute.
 * @return array{desktop: array, tablet: array, mobile: array}
 */
function stored_device_layouts( array $layout, array $style ): array {
	$from_style = array(
		'desktop' => null,
		'tablet'  => $style[ VIEWPORT_STATES['tablet'] ] ?? null,
		'mobile'  => $style[ VIEWPORT_STATES['mobile'] ] ?? null,
	);
	$stored     = array();
	foreach ( $from_style as $device => $settings ) {
		$stored[ $device ] = array_merge( device_fields( $layout[ $device ] ?? null ), device_fields( $settings ) );
	}
	return $stored;
}

/**
 * Complete settings for every device. Tablet falls back to desktop; mobile
 * falls back to one slide, not to desktop.
 *
 * @param array $layout Legacy `layout` value.
 * @param array $style  The block's `style` attribute.
 * @return array{desktop: array, tablet: array, mobile: array}
 */
function resolve_device_layouts( array $layout, array $style ): array {
	$stored   = stored_device_layouts( $layout, $style );
	$complete = static function ( array $settings, array $fallback ): array {
		return array(
			'columns' => bounded_int( $settings['columns'] ?? null, 1, 8, $fallback['columns'] ),
			'step'    => bounded_int( $settings['step'] ?? null, 1, 8, $fallback['step'] ),
			'peek'    => bounded_int( $settings['peek'] ?? null, 0, 45, $fallback['peek'] ),
		);
	};
	$desktop  = $complete(
		$stored['desktop'],
		array(
			'columns' => 3,
			'step'    => 1,
			'peek'    => 0,
		)
	);
	return array(
		'desktop' => $desktop,
		'tablet'  => $complete( $stored['tablet'], $desktop ),
		'mobile'  => $complete(
			$stored['mobile'],
			array(
				'columns' => 1,
				'step'    => 1,
				'peek'    => 0,
			)
		),
	);
}

/**
 * A CSS length in px, or null. em and rem count as 16px, as core does.
 *
 * @param mixed $length For example "782px" or "48rem".
 */
function length_to_px( $length ): ?int {
	if ( ! is_string( $length ) || ! preg_match( '/^(\d+(?:\.\d+)?)(px|r?em)$/', trim( $length ), $m ) ) {
		return null;
	}
	return (int) round( 'px' === $m[2] ? (float) $m[1] : (float) $m[1] * 16 );
}

/**
 * Max-width breakpoints (px) for the tablet and mobile layouts. With core
 * responsive styles on, they follow the theme's `settings.viewport`, the
 * same breakpoints core uses for @tablet and @mobile.
 *
 * @return array{tablet: int, mobile: int}
 */
function viewport_breakpoints(): array {
	if ( ! uses_core_responsive() ) {
		return LEGACY_BREAKPOINTS;
	}
	$viewport = (array) wp_get_global_settings( array( 'viewport' ) );
	$tablet   = length_to_px( $viewport['tablet'] ?? null ) ?? 782;
	$mobile   = length_to_px( $viewport['mobile'] ?? null ) ?? 480;
	if ( $tablet <= $mobile ) {
		$tablet = max( 782, $mobile + 1 );
	}
	return array(
		'tablet' => $tablet,
		'mobile' => $mobile,
	);
}

/**
 * Move tablet and phone settings from `layout` into `style` viewport states.
 * Desktop and other `layout` keys (the query carousel's `fluid`) stay. Values
 * already in `style` win; other keys in a viewport state (core's own styles)
 * are kept.
 *
 * @param array $layout Legacy `layout` value.
 * @param array $style  The block's `style` attribute.
 * @return array{layout: array, style: array, changed: bool}
 */
function migrate_layout_to_style( array $layout, array $style ): array {
	$stored  = stored_device_layouts( $layout, $style );
	$changed = false;
	foreach ( VIEWPORT_STATES as $device => $state ) {
		if ( array_key_exists( $device, $layout ) ) {
			unset( $layout[ $device ] );
			$changed = true;
		}
		if ( $stored[ $device ] ) {
			$style[ $state ] = array_merge( (array) ( $style[ $state ] ?? array() ), $stored[ $device ] );
		}
	}
	return array(
		'layout'  => $layout,
		'style'   => $style,
		'changed' => $changed,
	);
}

/**
 * Migrate one parsed block's attributes, if it is a carousel.
 *
 * Track blocks that never saved a `layout` use the block.json default, which
 * holds desktop and mobile values; both are written out, desktop to `layout`
 * and mobile to `style`, so the result does not depend on that default.
 *
 * @param array $block   A block from parse_blocks().
 * @param bool  $changed Set to true when anything was migrated.
 * @return array The block, migrated or unchanged.
 */
function migrate_block_layout( array $block, bool &$changed ): array {
	$name  = $block['blockName'] ?? '';
	$attrs = (array) ( $block['attrs'] ?? array() );

	if ( 'switchback-carousel/track' === $name ) {
		$layout = isset( $attrs['layout'] ) ? (array) $attrs['layout'] : array(
			'desktop' => array(
				'columns' => 3,
				'step'    => 1,
				'peek'    => 0,
			),
			'mobile'  => array(
				'columns' => 1,
				'step'    => 1,
				'peek'    => 0,
			),
		);
		$result = migrate_layout_to_style( $layout, (array) ( $attrs['style'] ?? array() ) );
		if ( $result['changed'] ) {
			// An empty object, not a missing one, so the old default can't return.
			$attrs['layout'] = $result['layout'] ? $result['layout'] : (object) array();
			$attrs['style']  = $result['style'];
			$changed         = true;
		}
	} elseif ( 'core/query' === $name && carousel_display_enabled( $attrs ) ) {
		$settings = (array) ( $attrs['switchback'] ?? array() );
		$result   = migrate_layout_to_style( (array) ( $settings['layout'] ?? array() ), (array) ( $attrs['style'] ?? array() ) );
		if ( $result['changed'] ) {
			$settings['layout']  = $result['layout'];
			$attrs['switchback'] = $settings;
			$attrs['style']      = $result['style'];
			$changed             = true;
		}
	}

	$block['attrs'] = $attrs;
	foreach ( $block['innerBlocks'] ?? array() as $i => $inner ) {
		$block['innerBlocks'][ $i ] = migrate_block_layout( $inner, $changed );
	}
	return $block;
}

/**
 * Migrate every carousel in a post's content.
 *
 * @param string $content Serialized blocks.
 * @return string|null New content, or null when nothing changed.
 */
function migrate_content_layout( string $content ): ?string {
	if ( false === strpos( $content, 'wp:switchback-carousel/track' ) && false === strpos( $content, '"switchback":' ) ) {
		return null;
	}
	$changed = false;
	$blocks  = array_map(
		static function ( array $block ) use ( &$changed ): array {
			return migrate_block_layout( $block, $changed );
		},
		parse_blocks( $content )
	);
	return $changed ? serialize_blocks( $blocks ) : null;
}
