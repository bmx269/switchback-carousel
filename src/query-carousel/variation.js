/**
 * Registers a `core/query` block variation that renders as a Switchback
 * carousel. The variation lives on the Query Loop block (not a new block), so
 * it inherits core/query's query panel plus any extensions Advanced Query Loop
 * contributes. We only add carousel display settings and transform the output
 * on the server.
 */
import { addFilter } from '@wordpress/hooks';
import { registerBlockVariation } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	SelectControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export const QUERY_NAMESPACE = 'switchback-carousel/query-carousel';

const DEFAULT_SWITCHBACK = {
	enabled: false,
	layout: {
		fluid: false,
		desktop: { columns: 3, step: 1, peek: 0 },
		tablet: {},
		mobile: { columns: 1, step: 1, peek: 0 },
	},
	slideWidth: { min: 240, max: 0 },
	focus: 'start',
	motion: {
		transition: 'slide',
		duration: 400,
		loop: 'none',
		drag: 'snap',
		wheel: false,
		grabCursor: false,
	},
	autoplay: {
		enabled: false,
		mode: 'interval',
		interval: 5000,
		speed: 1,
		pauseOnHover: true,
	},
	edges: { overflow: false, fade: 'none' },
	direction: 'auto',
};

/**
 * Extend core/query's attribute schema with a `switchback` object for
 * carousel display settings.
 */
addFilter(
	'blocks.registerBlockType',
	'switchback-carousel/query-attributes',
	( settings, name ) => {
		if ( name !== 'core/query' ) {
			return settings;
		}
		return {
			...settings,
			attributes: {
				...settings.attributes,
				switchback: {
					type: 'object',
					default: DEFAULT_SWITCHBACK,
				},
			},
			// core/query ships without a blockGap control. Carousels need it
			// to let authors change the horizontal gap between slides, and
			// our server-side render reads it already via horizontal_gap().
			supports: {
				...( settings.supports || {} ),
				spacing: {
					...( settings.supports?.spacing || {} ),
					blockGap: true,
				},
			},
		};
	}
);

/**
 * The variation itself. Users pick it from the Query Loop block's variation
 * picker. Inner blocks match a bare Query Loop; the parent carousel wrapper
 * and controls are added by the user inside a `switchback-carousel/carousel`.
 */
registerBlockVariation( 'core/query', {
	name: 'switchback-carousel-query',
	title: __( 'Switchback Carousel (Query)', 'switchback-carousel' ),
	description: __(
		'A Query Loop that renders as a Splide carousel. Use inside a Switchback Carousel block to add arrows, pagination and other controls.',
		'switchback-carousel'
	),
	category: 'media',
	keywords: [ 'carousel', 'slider', 'posts', 'query' ],
	// Match either our namespace (classic activation) or any Query Loop that
	// has opted into carousel display via the `switchback.enabled` flag.
	// Second form lets Advanced Query Loop and Switchback coexist on one
	// block: AQL claims `namespace: "advanced-query-loop"`, we claim the
	// `switchback` attribute.
	isActive: ( { namespace, switchback } ) =>
		namespace === QUERY_NAMESPACE || !! switchback?.enabled,
	attributes: {
		namespace: QUERY_NAMESPACE,
		switchback: { ...DEFAULT_SWITCHBACK, enabled: true },
		query: {
			perPage: 6,
			pages: 0,
			offset: 0,
			postType: 'post',
			order: 'desc',
			orderBy: 'date',
			author: '',
			search: '',
			exclude: [],
			sticky: '',
			inherit: false,
		},
	},
	innerBlocks: [
		[
			'core/post-template',
			{},
			[ [ 'core/post-title' ], [ 'core/post-excerpt' ] ],
		],
	],
	scope: [ 'block', 'inserter' ],
} );

/**
 * Shallow merge a nested path (e.g. `motion.loop`) into the current
 * switchback object.
 */
function mergeSwitchback( current, path, value ) {
	const next = { ...( current || DEFAULT_SWITCHBACK ) };
	const [ head, tail ] = path;
	if ( tail ) {
		next[ head ] = { ...( next[ head ] || {} ), [ tail ]: value };
	} else {
		next[ head ] = value;
	}
	return next;
}

/**
 * Add a Carousel Display inspector panel to the Query Loop block when the
 * carousel variation is active. Keeps the surface small for the first cut:
 * columns per device, motion, autoplay, drag, edges. Everything else runs on
 * sensible defaults until users ask for the knobs.
 */
const CarouselPanel = ( { attributes, setAttributes } ) => {
	const sb = attributes.switchback || DEFAULT_SWITCHBACK;
	const set = ( path, value ) =>
		setAttributes( {
			switchback: mergeSwitchback( sb, path, value ),
		} );

	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Carousel display', 'switchback-carousel' ) }
			>
				<ToggleControl
					label={ __(
						'Display as carousel',
						'switchback-carousel'
					) }
					help={ __(
						'Render this Query Loop as a Switchback carousel. Works alongside Advanced Query Loop.',
						'switchback-carousel'
					) }
					checked={ !! sb.enabled }
					onChange={ ( enabled ) => set( [ 'enabled' ], enabled ) }
				/>
				<ToggleControl
					label={ __(
						'Fluid columns',
						'switchback-carousel'
					) }
					help={ __(
						'Columns automatically fit the carousel width based on a minimum slide width. No fixed breakpoints.',
						'switchback-carousel'
					) }
					checked={ !! sb.layout?.fluid }
					onChange={ ( fluid ) =>
						set( [ 'layout' ], { ...sb.layout, fluid } )
					}
				/>
				{ sb.layout?.fluid ? (
					<RangeControl
						label={ __(
							'Minimum slide width (px)',
							'switchback-carousel'
						) }
						help={ __(
							'As many slides as fit at this width will show side by side; the rest scroll.',
							'switchback-carousel'
						) }
						min={ 100 }
						max={ 800 }
						step={ 10 }
						value={ sb.slideWidth?.min ?? 240 }
						onChange={ ( min ) =>
							set(
								[ 'slideWidth' ],
								{
									...( sb.slideWidth || {} ),
									min: min ?? 240,
								}
							)
						}
					/>
				) : (
					<>
						<RangeControl
							label={ __(
								'Columns (desktop)',
								'switchback-carousel'
							) }
							min={ 1 }
							max={ 8 }
							value={ sb.layout?.desktop?.columns ?? 3 }
							onChange={ ( columns ) =>
								set(
									[ 'layout' ],
									{
										...sb.layout,
										desktop: {
											...( sb.layout?.desktop || {} ),
											columns,
										},
									}
								)
							}
						/>
						<RangeControl
							label={ __(
								'Columns (tablet)',
								'switchback-carousel'
							) }
							min={ 1 }
							max={ 8 }
							allowReset
							value={ sb.layout?.tablet?.columns }
							onChange={ ( columns ) =>
								set(
									[ 'layout' ],
									{
										...sb.layout,
										tablet: {
											...( sb.layout?.tablet || {} ),
											columns,
										},
									}
								)
							}
						/>
						<RangeControl
							label={ __(
								'Columns (mobile)',
								'switchback-carousel'
							) }
							min={ 1 }
							max={ 4 }
							value={ sb.layout?.mobile?.columns ?? 1 }
							onChange={ ( columns ) =>
								set(
									[ 'layout' ],
									{
										...sb.layout,
										mobile: {
											...( sb.layout?.mobile || {} ),
											columns,
										},
									}
								)
							}
						/>
					</>
				) }
				<SelectControl
					label={ __( 'At the end', 'switchback-carousel' ) }
					value={ sb.motion?.loop ?? 'none' }
					options={ [
						{
							value: 'none',
							label: __( 'Stop', 'switchback-carousel' ),
						},
						{
							value: 'rewind',
							label: __( 'Rewind', 'switchback-carousel' ),
						},
						{
							value: 'loop',
							label: __( 'Endless loop', 'switchback-carousel' ),
						},
					] }
					onChange={ ( loop ) => set( [ 'motion', 'loop' ], loop ) }
				/>
				<SelectControl
					label={ __( 'Dragging', 'switchback-carousel' ) }
					value={ sb.motion?.drag ?? 'snap' }
					options={ [
						{
							value: 'snap',
							label: __( 'Snap', 'switchback-carousel' ),
						},
						{
							value: 'free',
							label: __(
								'Glide freely',
								'switchback-carousel'
							),
						},
						{
							value: 'none',
							label: __( 'Off', 'switchback-carousel' ),
						},
					] }
					onChange={ ( drag ) => set( [ 'motion', 'drag' ], drag ) }
				/>
				<ToggleControl
					label={ __( 'Autoplay', 'switchback-carousel' ) }
					checked={ !! sb.autoplay?.enabled }
					onChange={ ( enabled ) =>
						set( [ 'autoplay', 'enabled' ], enabled )
					}
				/>
				{ sb.autoplay?.enabled && (
					<>
						<SelectControl
							label={ __( 'Mode', 'switchback-carousel' ) }
							value={ sb.autoplay?.mode ?? 'interval' }
							options={ [
								{
									value: 'interval',
									label: __(
										'Step by step',
										'switchback-carousel'
									),
								},
								{
									value: 'continuous',
									label: __(
										'Continuous',
										'switchback-carousel'
									),
								},
							] }
							onChange={ ( mode ) =>
								set( [ 'autoplay', 'mode' ], mode )
							}
						/>
						<RangeControl
							label={ __(
								'Interval (ms)',
								'switchback-carousel'
							) }
							min={ 1000 }
							max={ 20000 }
							step={ 500 }
							value={ sb.autoplay?.interval ?? 5000 }
							onChange={ ( interval ) =>
								set( [ 'autoplay', 'interval' ], interval )
							}
						/>
					</>
				) }
				<ToggleControl
					label={ __(
						'Let slides spill past the edge',
						'switchback-carousel'
					) }
					checked={ !! sb.edges?.overflow }
					onChange={ ( overflow ) =>
						set( [ 'edges', 'overflow' ], overflow )
					}
				/>
				<SelectControl
					label={ __( 'Edge fade', 'switchback-carousel' ) }
					value={ sb.edges?.fade ?? 'none' }
					options={ [
						{
							value: 'none',
							label: __( 'None', 'switchback-carousel' ),
						},
						{
							value: 'end',
							label: __(
								'Trailing edge',
								'switchback-carousel'
							),
						},
						{
							value: 'both',
							label: __( 'Both edges', 'switchback-carousel' ),
						},
					] }
					onChange={ ( fade ) => set( [ 'edges', 'fade' ], fade ) }
				/>
			</PanelBody>
		</InspectorControls>
	);
};

const withCarouselInspector = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== 'core/query' ) {
			return <BlockEdit { ...props } />;
		}
		return (
			<Fragment>
				<BlockEdit { ...props } />
				<CarouselPanel { ...props } />
			</Fragment>
		);
	};
}, 'withCarouselInspector' );

addFilter(
	'editor.BlockEdit',
	'switchback-carousel/query-inspector',
	withCarouselInspector
);

/**
 * Mark enabled Query Loops in the editor so stylesheets can lay out the preview
 * as a horizontal carousel. We can't call our render transform here — the Edit
 * component doesn't go through render_block — so we rely on CSS instead.
 */
/**
 * Translate the block's `style.spacing.blockGap` into a CSS value usable in the
 * editor preview. Picks up both preset references and raw lengths; mirrors
 * horizontal_gap() in includes/support.php.
 *
 * @param {Object|string|undefined} gap The blockGap setting (string or {left,top}).
 * @return {string|undefined} CSS value, or undefined when unset.
 */
function gapToCss( gap ) {
	const value =
		gap && typeof gap === 'object' ? ( gap.left ?? gap.top ) : gap;
	if ( typeof value !== 'string' || ! value ) {
		return undefined;
	}
	const preset = 'var:preset|spacing|';
	return value.startsWith( preset )
		? `var(--wp--preset--spacing--${ value.slice( preset.length ) })`
		: value;
}

const withCarouselPreviewClass = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) => {
		if (
			props.name !== 'core/query' ||
			! props.attributes?.switchback?.enabled
		) {
			return <BlockListBlock { ...props } />;
		}
		const sb = props.attributes.switchback;
		const fluid = !! sb.layout?.fluid;
		const desktop = sb.layout?.desktop?.columns ?? 3;
		const tablet = sb.layout?.tablet?.columns ?? desktop;
		const mobile = sb.layout?.mobile?.columns ?? 1;
		const minWidth = sb.slideWidth?.min ?? 240;
		const gap = gapToCss( props.attributes?.style?.spacing?.blockGap );
		const className = [
			props.className,
			'is-switchback-carousel-preview',
			fluid && 'is-fluid-columns',
		]
			.filter( Boolean )
			.join( ' ' );
		const wrapperProps = {
			...( props.wrapperProps || {} ),
			style: {
				...( props.wrapperProps?.style || {} ),
				'--switchback-columns': desktop,
				'--switchback-columns-desktop': desktop,
				'--switchback-columns-tablet': tablet,
				'--switchback-columns-mobile': mobile,
				...( fluid && { '--switchback-min-width': `${ minWidth }px` } ),
				...( gap && { '--switchback-gap': gap } ),
			},
		};
		return (
			<BlockListBlock
				{ ...props }
				className={ className }
				wrapperProps={ wrapperProps }
			/>
		);
	},
	'withCarouselPreviewClass'
);

addFilter(
	'editor.BlockListBlock',
	'switchback-carousel/query-preview-class',
	withCarouselPreviewClass
);
