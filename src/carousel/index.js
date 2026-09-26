/**
 * Carousel: wraps the track plus any controls or other blocks.
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';

import metadata from './block.json';
import './style.scss';
import './editor.scss';

const slide = [
	'switchback-carousel/slide',
	{},
	[
		[
			'core/paragraph',
			{
				placeholder: __(
					'Write or add blocks…',
					'switchback-carousel'
				),
			},
		],
	],
];

const TEMPLATE = [
	[
		'switchback-carousel/track',
		{ lock: { move: false, remove: true } },
		[ slide, slide, slide ],
	],
	[ 'switchback-carousel/controls', { control: 'arrows' } ],
];

function Edit( { attributes: { overlayControls, layout }, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: overlayControls
			? 'switchback has-overlay-controls'
			: 'switchback',
	} );
	// Pass the layout explicitly: without it, WordPress 7.1 gives inner blocks
	// the flow layout instead of the constrained default, and block themes
	// then can't offer wide alignment to the track and controls.
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: TEMPLATE,
		layout: layout?.type ? layout : { ...layout, type: 'constrained' },
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Controls', 'switchback-carousel' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Arrows over the slides',
							'switchback-carousel'
						) }
						help={ __(
							'Previous and next arrows sit on either side of the slides, halfway down.',
							'switchback-carousel'
						) }
						checked={ overlayControls }
						onChange={ ( value ) =>
							setAttributes( { overlayControls: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
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
			<path d="M8 3.5h8A1.5 1.5 0 0 1 17.5 5v14a1.5 1.5 0 0 1-1.5 1.5H8A1.5 1.5 0 0 1 6.5 19V5A1.5 1.5 0 0 1 8 3.5ZM8 5v14h8V5H8ZM2.5 7H5v10H2.5V7ZM19 7h2.5v10H19V7Z" />
			<path
				d="m9.5 16.5 5-2-4-2 4-2"
				fill="none"
				stroke="currentColor"
				strokeWidth="1.5"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	),
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
