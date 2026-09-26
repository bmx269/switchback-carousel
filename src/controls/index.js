/**
 * Carousel controls. The editor shows a static preview; view.js wires the
 * front end. Colour and justification come from core block supports.
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	RangeControl,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';

import metadata from './block.json';
import { ICONS, WEIGHTS, iconMarkup } from '../shared/icons';

const CONTROLS = [
	{
		value: 'arrows',
		label: __( 'Previous / next arrows', 'switchback-carousel' ),
	},
	{ value: 'pagination', label: __( 'Pagination', 'switchback-carousel' ) },
	{ value: 'progress', label: __( 'Progress bar', 'switchback-carousel' ) },
	{
		value: 'counter',
		label: __( 'Slide counter (1 / 5)', 'switchback-carousel' ),
	},
	{
		value: 'playpause',
		label: __( 'Play / pause button', 'switchback-carousel' ),
	},
];

/** Matches the stylesheet defaults used when no size is set. */
const DEFAULT_SIZES = { arrows: 32, pagination: 8, progress: 3 };

const WEIGHT_LABELS = {
	thin: __( 'Thin', 'switchback-carousel' ),
	light: __( 'Light', 'switchback-carousel' ),
	regular: __( 'Regular', 'switchback-carousel' ),
	bold: __( 'Bold', 'switchback-carousel' ),
	fill: __( 'Filled', 'switchback-carousel' ),
};

function Glyph( { icon } ) {
	if ( 'custom' === icon.slug && icon.svg ) {
		// Preview only; the SVG is sanitized with wp_kses() when rendered.
		return (
			<span
				className="switchback-icon switchback-icon--custom"
				dangerouslySetInnerHTML={ { __html: icon.svg } }
			/>
		);
	}
	const data = iconMarkup( icon.slug, icon.weight );
	return (
		<svg
			className="switchback-icon"
			xmlns="http://www.w3.org/2000/svg"
			viewBox={ data.viewBox }
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
			dangerouslySetInnerHTML={ { __html: data.markup } }
		/>
	);
}

function Preview( { control, icon } ) {
	switch ( control ) {
		case 'pagination':
			return (
				<ul className="switchback-controls__pages">
					{ [ 0, 1, 2, 3 ].map( ( page ) => (
						<li key={ page }>
							<span
								className={ `switchback-controls__page${ page ? '' : ' is-active' }` }
							/>
						</li>
					) ) }
				</ul>
			);
		case 'progress':
			return (
				<div className="switchback-controls__progress">
					<span
						className="switchback-controls__progress-bar"
						style={ { width: '30%' } }
					/>
				</div>
			);
		case 'counter':
			return (
				<p className="switchback-controls__counter">
					1<span className="switchback-controls__divider"> / </span>5
				</p>
			);
		case 'playpause':
			return (
				<div className="wp-block-button">
					<span className="wp-block-button__link wp-element-button">
						{ __( 'Pause', 'switchback-carousel' ) }
					</span>
				</div>
			);
		default:
			return (
				<>
					<span className="switchback-controls__button switchback-controls__prev">
						<Glyph icon={ icon } />
					</span>
					<span className="switchback-controls__button switchback-controls__next">
						<Glyph icon={ icon } />
					</span>
				</>
			);
	}
}

function IconPicker( { icon, onChange } ) {
	return (
		<>
			<div
				className="switchback-editor__icons"
				role="group"
				aria-label={ __( 'Arrow icon', 'switchback-carousel' ) }
			>
				{ ICONS.map( ( item ) => (
					<Button
						key={ item.slug }
						label={ item.label }
						isPressed={ icon.slug === item.slug }
						onClick={ () => onChange( { slug: item.slug } ) }
					>
						<Glyph
							icon={ { slug: item.slug, weight: icon.weight } }
						/>
					</Button>
				) ) }
				<Button
					isPressed={ 'custom' === icon.slug }
					onClick={ () => onChange( { slug: 'custom' } ) }
				>
					{ __( 'SVG', 'switchback-carousel' ) }
				</Button>
			</div>
			{ 'custom' === icon.slug ? (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Your SVG', 'switchback-carousel' ) }
					help={ __(
						'Draw it pointing right; the previous arrow is flipped automatically. Scripts and unknown tags are removed.',
						'switchback-carousel'
					) }
					value={ icon.svg }
					rows={ 4 }
					onChange={ ( svg ) => onChange( { svg } ) }
				/>
			) : (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Line weight', 'switchback-carousel' ) }
					value={ icon.weight }
					options={ WEIGHTS.map( ( weight ) => ( {
						value: weight,
						label: WEIGHT_LABELS[ weight ],
					} ) ) }
					onChange={ ( weight ) => onChange( { weight } ) }
				/>
			) }
		</>
	);
}

function Edit( {
	attributes,
	setAttributes,
	context,
	__unstableLayoutClassNames: layoutClassNames,
} ) {
	const { control, icon, size, pagination, overlayInset } = attributes;
	const overlay = !! context[ 'switchback-carousel/overlayControls' ];
	// Core hands layout classes to useInnerBlocksProps; this block has no
	// inner blocks, so apply them here to match the server-rendered flex row.
	const blockProps = useBlockProps( {
		className: [
			'switchback-controls',
			`is-${ control }`,
			'pagination' === control && `is-style-${ pagination }`,
			layoutClassNames,
		]
			.filter( Boolean )
			.join( ' ' ),
		style: {
			...( size && { '--switchback-control-size': `${ size }px` } ),
			'--switchback-overlay-inset': `${ overlayInset }px`,
		},
	} );
	const setIcon = ( changes ) =>
		setAttributes( { icon: { ...icon, ...changes } } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Control', 'switchback-carousel' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Show', 'switchback-carousel' ) }
						value={ control }
						options={ CONTROLS }
						onChange={ ( value ) =>
							setAttributes( { control: value } )
						}
						help={
							'playpause' === control
								? __(
										'Appears only when autoplay is turned on for the track.',
										'switchback-carousel'
									)
								: undefined
						}
					/>
					{ 'pagination' === control && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Style', 'switchback-carousel' ) }
							value={ pagination }
							options={ [
								{
									value: 'dots',
									label: __( 'Dots', 'switchback-carousel' ),
								},
								{
									value: 'bars',
									label: __( 'Bars', 'switchback-carousel' ),
								},
								{
									value: 'segments',
									label: __(
										'Segments filling the width',
										'switchback-carousel'
									),
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { pagination: value } )
							}
						/>
					) }
					{ [ 'arrows', 'pagination', 'progress' ].includes(
						control
					) && (
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={
								'arrows' === control
									? __( 'Arrow size', 'switchback-carousel' )
									: __( 'Thickness', 'switchback-carousel' )
							}
							value={ size }
							initialPosition={ DEFAULT_SIZES[ control ] }
							allowReset
							min={ 'arrows' === control ? 12 : 2 }
							max={ 'arrows' === control ? 120 : 24 }
							onChange={ ( value ) =>
								setAttributes( { size: value } )
							}
						/>
					) }
				</PanelBody>
				{ 'arrows' === control && (
					<PanelBody
						title={ __( 'Arrow icon', 'switchback-carousel' ) }
					>
						<IconPicker icon={ icon } onChange={ setIcon } />
						{ overlay && (
							<RangeControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Distance from the slides’ edge',
									'switchback-carousel'
								) }
								help={ __(
									'Positive values move the arrows outward, negative values over the slides.',
									'switchback-carousel'
								) }
								value={ overlayInset }
								min={ -80 }
								max={ 80 }
								onChange={ ( value ) =>
									setAttributes( {
										overlayInset: value ?? 0,
									} )
								}
							/>
						) }
					</PanelBody>
				) }
			</InspectorControls>
			<div { ...blockProps }>
				<Preview control={ control } icon={ icon } />
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	icon: (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
		>
			<path d="m9.5 6.5-5 5.5 5 5.5 1.1-1-4.1-4.5 4.1-4.5-1.1-1Zm5 0-1.1 1 4.1 4.5-4.1 4.5 1.1 1 5-5.5-5-5.5Z" />
		</svg>
	),
	edit: Edit,
	save: () => null,
} );
