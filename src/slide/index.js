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
	const { others, isQuery } = useSelect(
		( select ) => {
			const editor = select( 'core/block-editor' );
			const track = editor.getBlockRootClientId( clientId );
			return {
				others: editor
					.getBlockOrder( track )
					.filter( ( id ) => id !== clientId ),
				isQuery: 'query' === editor.getBlockAttributes( track )?.mode,
			};
		},
		[ clientId ]
	);

	if ( isQuery || ! others.length ) {
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

function SlideEdit( { clientId } ) {
	const innerBlocksProps = useInnerBlocksProps( useBlockProps(), {
		template: TEMPLATE,
	} );
	return (
		<>
			<InspectorControls>
				<CopyLayout clientId={ clientId } />
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
			<path d="M6 5h12a1.5 1.5 0 0 1 1.5 1.5v11A1.5 1.5 0 0 1 18 19H6a1.5 1.5 0 0 1-1.5-1.5v-11A1.5 1.5 0 0 1 6 5Zm0 1.5v11h12v-11H6Z" />
		</svg>
	),
	edit: SlideEdit,
	save: () => <div { ...useInnerBlocksProps.save( useBlockProps.save() ) } />,
} );
