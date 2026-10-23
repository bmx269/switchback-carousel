/**
 * Track settings.
 */
import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
/* eslint-disable @wordpress/no-unsafe-wp-apis -- no stable toggle group control yet; core blocks use this one. */
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */

import { useDevice } from '../shared/device';
import { deviceKey, layoutFor } from '../shared/responsive';

const DEVICES = {
	desktop: __( 'desktop', 'switchback-carousel' ),
	tablet: __( 'tablet', 'switchback-carousel' ),
	mobile: __( 'phones', 'switchback-carousel' ),
};

function Choice( { label, value, options, onChange, help } ) {
	return (
		<ToggleGroupControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			isBlock
			label={ label }
			value={ value }
			onChange={ onChange }
			help={ help }
		>
			{ options.map( ( [ optionValue, optionLabel ] ) => (
				<ToggleGroupControlOption
					key={ optionValue }
					value={ optionValue }
					label={ optionLabel }
				/>
			) ) }
		</ToggleGroupControl>
	);
}

function LayoutControls( { layout, fade, setLayout } ) {
	const key = deviceKey( useDevice() );
	const current = layoutFor(
		layout,
		key.charAt( 0 ).toUpperCase() + key.slice( 1 ),
		fade
	);
	const stored = layout?.[ key ] ?? {};
	const inherits = 'desktop' !== key;
	const set = ( changes ) => setLayout( key, changes );

	return (
		<>
			<p className="components-base-control__help">
				{ sprintf(
					/* translators: %s: device, e.g. "tablet". */
					__(
						'Editing the %s layout. Switch the editor preview (Desktop, Tablet, Mobile) to change the others.',
						'switchback-carousel'
					),
					DEVICES[ key ]
				) }
			</p>
			<RangeControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Slides side by side', 'switchback-carousel' ) }
				value={ stored.columns }
				initialPosition={ current.columns }
				allowReset={ inherits }
				min={ 1 }
				max={ 8 }
				disabled={ fade }
				onChange={ ( columns ) => set( { columns } ) }
			/>
			<RangeControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Slides per move', 'switchback-carousel' ) }
				value={ stored.step }
				initialPosition={ current.step }
				allowReset={ inherits }
				min={ 1 }
				max={ Math.max( 1, current.columns ) }
				disabled={ fade }
				onChange={ ( step ) => set( { step } ) }
			/>
			<RangeControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __(
					'Peek at the next slide (%)',
					'switchback-carousel'
				) }
				help={ __(
					'Shows part of the next slide as a hint that there is more.',
					'switchback-carousel'
				) }
				value={ stored.peek }
				initialPosition={ current.peek }
				allowReset={ inherits }
				min={ 0 }
				max={ 45 }
				disabled={ fade }
				onChange={ ( peek ) => set( { peek } ) }
			/>
		</>
	);
}

export default function Inspector( { attributes, setAttributes } ) {
	const {
		layout,
		slideWidth,
		focus,
		motion,
		autoplay,
		edges,
		direction,
		linkedLayout,
		lazyImages,
	} = attributes;
	const fade = 'fade' === motion.transition;

	const merge = ( name ) => ( changes ) =>
		setAttributes( { [ name ]: { ...attributes[ name ], ...changes } } );
	const setMotion = merge( 'motion' );
	const setAutoplay = merge( 'autoplay' );
	const setEdges = merge( 'edges' );
	const setWidth = merge( 'slideWidth' );
	const setLayout = ( key, changes ) => {
		const next = { ...( layout?.[ key ] ?? {} ), ...changes };
		Object.keys( next ).forEach(
			( name ) => next[ name ] === undefined && delete next[ name ]
		);
		setAttributes( { layout: { ...layout, [ key ]: next } } );
	};

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Slides', 'switchback-carousel' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Linked layout', 'switchback-carousel' ) }
					help={ __(
						'Rearranging or restyling one slide updates the others. Each keeps its own content.',
						'switchback-carousel'
					) }
					checked={ linkedLayout }
					onChange={ ( value ) =>
						setAttributes( { linkedLayout: value } )
					}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Lazy-load images', 'switchback-carousel' ) }
					help={ __(
						'Images in later slides load as the carousel reaches them, not with the page. Without JavaScript they don’t load.',
						'switchback-carousel'
					) }
					checked={ lazyImages }
					onChange={ ( value ) =>
						setAttributes( { lazyImages: value } )
					}
				/>
			</PanelBody>

			<PanelBody title={ __( 'Layout', 'switchback-carousel' ) }>
				<LayoutControls
					layout={ layout }
					fade={ fade }
					setLayout={ setLayout }
				/>
				<Choice
					label={ __( 'Align slides', 'switchback-carousel' ) }
					value={ focus }
					options={ [
						[
							'start',
							__( 'From the start', 'switchback-carousel' ),
						],
						[ 'center', __( 'Centered', 'switchback-carousel' ) ],
					] }
					onChange={ ( value ) => setAttributes( { focus: value } ) }
				/>
				<RangeControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __(
						'Narrowest slide (px)',
						'switchback-carousel'
					) }
					help={ __(
						'Fewer slides fit side by side when they would get narrower than this. 0 = off.',
						'switchback-carousel'
					) }
					value={ slideWidth.min }
					min={ 0 }
					max={ 800 }
					step={ 10 }
					onChange={ ( min ) => setWidth( { min: min ?? 0 } ) }
				/>
				<RangeControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Widest slide (px)', 'switchback-carousel' ) }
					help={ __( '0 = no limit.', 'switchback-carousel' ) }
					value={ slideWidth.max }
					min={ 0 }
					max={ 1600 }
					step={ 10 }
					onChange={ ( max ) => setWidth( { max: max ?? 0 } ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Motion', 'switchback-carousel' ) }
				initialOpen={ false }
			>
				<Choice
					label={ __( 'Transition', 'switchback-carousel' ) }
					value={ motion.transition }
					options={ [
						[ 'slide', __( 'Slide', 'switchback-carousel' ) ],
						[ 'fade', __( 'Fade', 'switchback-carousel' ) ],
					] }
					onChange={ ( transition ) => setMotion( { transition } ) }
				/>
				<RangeControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __(
						'Transition time (ms)',
						'switchback-carousel'
					) }
					value={ motion.duration }
					min={ 0 }
					max={ 2000 }
					step={ 50 }
					onChange={ ( duration ) =>
						setMotion( { duration: duration ?? 400 } )
					}
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'At the last slide', 'switchback-carousel' ) }
					value={ motion.loop }
					options={ [
						{
							value: 'none',
							label: __( 'Stop', 'switchback-carousel' ),
						},
						{
							value: 'rewind',
							label: __(
								'Rewind to the first slide',
								'switchback-carousel'
							),
						},
						{
							value: 'loop',
							label: __(
								'Keep going (endless)',
								'switchback-carousel'
							),
						},
					] }
					onChange={ ( loop ) => setMotion( { loop } ) }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __(
						'Dragging and swiping',
						'switchback-carousel'
					) }
					value={ motion.drag }
					options={ [
						{
							value: 'snap',
							label: __(
								'Snap to slides',
								'switchback-carousel'
							),
						},
						{
							value: 'free',
							label: __( 'Glide freely', 'switchback-carousel' ),
						},
						{
							value: 'none',
							label: __( 'Off', 'switchback-carousel' ),
						},
					] }
					onChange={ ( drag ) => setMotion( { drag } ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Scroll with mouse wheel or trackpad',
						'switchback-carousel'
					) }
					checked={ motion.wheel }
					onChange={ ( wheel ) => setMotion( { wheel } ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Hand cursor while dragging',
						'switchback-carousel'
					) }
					checked={ motion.grabCursor }
					onChange={ ( grabCursor ) => setMotion( { grabCursor } ) }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Direction', 'switchback-carousel' ) }
					value={ direction }
					options={ [
						{
							value: 'auto',
							label: __(
								'Same as the site language',
								'switchback-carousel'
							),
						},
						{
							value: 'ltr',
							label: __( 'Left to right', 'switchback-carousel' ),
						},
						{
							value: 'rtl',
							label: __( 'Right to left', 'switchback-carousel' ),
						},
					] }
					onChange={ ( value ) =>
						setAttributes( { direction: value } )
					}
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Autoplay', 'switchback-carousel' ) }
				initialOpen={ false }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Play automatically', 'switchback-carousel' ) }
					help={ __(
						'Stops while a visitor focuses a slide and for visitors who ask for reduced motion. Add a play/pause control so anyone can stop it.',
						'switchback-carousel'
					) }
					checked={ autoplay.enabled }
					onChange={ ( enabled ) => setAutoplay( { enabled } ) }
				/>
				{ autoplay.enabled && (
					<>
						<Choice
							label={ __( 'How', 'switchback-carousel' ) }
							value={ autoplay.mode }
							options={ [
								[
									'interval',
									__( 'Step by step', 'switchback-carousel' ),
								],
								[
									'continuous',
									__(
										'Continuous flow',
										'switchback-carousel'
									),
								],
							] }
							onChange={ ( value ) =>
								setAutoplay( { mode: value } )
							}
						/>
						{ 'continuous' === autoplay.mode ? (
							<RangeControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Flow speed',
									'switchback-carousel'
								) }
								value={ autoplay.speed }
								min={ 0.1 }
								max={ 10 }
								step={ 0.1 }
								onChange={ ( speed ) =>
									setAutoplay( { speed: speed ?? 1 } )
								}
							/>
						) : (
							<RangeControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Time on each slide (ms)',
									'switchback-carousel'
								) }
								value={ autoplay.interval }
								min={ 1000 }
								max={ 20000 }
								step={ 250 }
								onChange={ ( interval ) =>
									setAutoplay( {
										interval: interval ?? 5000,
									} )
								}
							/>
						) }
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Pause while the pointer is over it',
								'switchback-carousel'
							) }
							checked={ autoplay.pauseOnHover }
							onChange={ ( pauseOnHover ) =>
								setAutoplay( { pauseOnHover } )
							}
						/>
					</>
				) }
			</PanelBody>

			<PanelBody
				title={ __( 'Edges', 'switchback-carousel' ) }
				initialOpen={ false }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Show slides past the edges',
						'switchback-carousel'
					) }
					help={ __(
						'Slides outside the track stay visible, for carousels that bleed to the screen edge.',
						'switchback-carousel'
					) }
					checked={ edges.overflow }
					onChange={ ( overflow ) => setEdges( { overflow } ) }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Fade the edges', 'switchback-carousel' ) }
					value={ edges.fade }
					options={ [
						{
							value: 'none',
							label: __( 'No', 'switchback-carousel' ),
						},
						{
							value: 'both',
							label: __( 'Both sides', 'switchback-carousel' ),
						},
						{
							value: 'end',
							label: __(
								'Trailing side only',
								'switchback-carousel'
							),
						},
					] }
					onChange={ ( value ) => setEdges( { fade: value } ) }
				/>
			</PanelBody>
		</InspectorControls>
	);
}
