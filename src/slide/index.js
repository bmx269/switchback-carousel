/**
 * Slide.
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { Button, PanelBody } from '@wordpress/components';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';

import metadata from './block.json';
import { mirrorLayout } from '../shared/layout-sync';
import AddSlideButton from '../shared/add-slide';

const TEMPLATE = [
	[
		'core/paragraph',
		{ placeholder: __( 'Write or add blocks…', 'switchback-carousel' ) },
	],
];

/**
 * "Copy this layout to the other slides" for manual tracks.
 *
 * @param {Object} props          Props.
 * @param {string} props.clientId Slide client ID.
 */
function CopyLayout( { clientId } ) {
	const registry = useRegistry();
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );
	const others = useSelect(
		( select ) => {
			const editor = select( 'core/block-editor' );
			const track = editor.getBlockRootClientId( clientId );
			return editor
				.getBlockOrder( track )
				.filter( ( id ) => id !== clientId );
		},
		[ clientId ]
	);

	if ( ! others.length ) {
		return null;
	}

	const copy = () => {
		const editor = registry.select( 'core/block-editor' );
		const source = editor.getBlock( clientId ).innerBlocks;
		registry.batch( () =>
			others.forEach( ( id ) =>
				replaceInnerBlocks(
					id,
					mirrorLayout( source, editor.getBlock( id ).innerBlocks ),
					false
				)
			)
		);
	};

	return (
		<PanelBody title={ __( 'Layout', 'switchback-carousel' ) }>
			<p>
				{ __(
					'Give the other slides this slide’s arrangement and styles. Their text, images and links stay as they are.',
					'switchback-carousel'
				) }
			</p>
			<Button variant="secondary" onClick={ copy }>
				{ __( 'Copy layout to other slides', 'switchback-carousel' ) }
			</Button>
		</PanelBody>
	);
}

function SlideEdit( { attributes: { layout }, clientId } ) {
	// Pass the layout explicitly, as the carousel does: without a saved
	// layout attribute, WordPress 7.1 gives inner blocks the flow layout, so
	// a full-width cover or wide group in a slide loses its alignment and
	// shrinks to the content width in the editor (the front end is fine).
	const innerBlocksProps = useInnerBlocksProps( useBlockProps(), {
		template: TEMPLATE,
		layout: layout?.type ? layout : { ...layout, type: 'constrained' },
	} );
	return (
		<>
			<AddSlideButton clientId={ clientId } />
			<InspectorControls>
				<CopyLayout clientId={ clientId } />
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}

registerBlockType( metadata.name, {
	// One slide card above carousel pagination dots, the current one larger.
	icon: (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M5 3h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 1.5a.5.5 0 0 0-.5.5v9a.5.5 0 0 0 .5.5h14a.5.5 0 0 0 .5-.5V5a.5.5 0 0 0-.5-.5H5Z" />
			<path d="M6.5 7h7v1.5h-7V7Zm0 3h11v1.5h-11V10Z" />
			<circle cx="12" cy="19.5" r="1.5" />
			<circle cx="7.5" cy="19.5" r="1" />
			<circle cx="16.5" cy="19.5" r="1" />
		</svg>
	),
	edit: SlideEdit,
	save: () => <div { ...useInnerBlocksProps.save( useBlockProps.save() ) } />,
} );
