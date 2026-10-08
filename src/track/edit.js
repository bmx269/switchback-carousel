/**
 * Track editor. Slides sit side by side in a scrollable strip. For
 * post-driven carousels, insert a core/query block with the Switchback
 * Carousel variation beside the track block instead.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { cloneBlock, createBlock } from '@wordpress/blocks';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

import Inspector from './inspector';
import { useDevice } from '../shared/device';
import { layoutFor } from '../shared/responsive';
import { layoutFingerprint, mirrorLayout } from '../shared/layout-sync';

const SLIDES = [ 'switchback-carousel/slide' ];

/**
 * CSS for the block gap attribute.
 *
 * @param {string|Object|undefined} gap style.spacing.blockGap.
 * @return {string|undefined} CSS value.
 */
function gapStyle( gap ) {
	const value =
		gap && 'object' === typeof gap ? ( gap.left ?? gap.top ) : gap;
	if ( 'string' !== typeof value || ! value ) {
		return undefined;
	}
	const preset = 'var:preset|spacing|';
	return value.startsWith( preset )
		? `var(--wp--preset--spacing--${ value.slice( preset.length ) })`
		: value;
}

function AddSlides( { clientId } ) {
	const { insertBlock } = useDispatch( 'core/block-editor' );
	const last = useSelect(
		( select ) =>
			select( 'core/block-editor' ).getBlocks( clientId ).at( -1 ),
		[ clientId ]
	);
	return (
		<div className="switchback-editor__add">
			<Button
				variant="secondary"
				icon="plus"
				onClick={ () =>
					insertBlock(
						createBlock( 'switchback-carousel/slide' ),
						undefined,
						clientId
					)
				}
			>
				{ __( 'New slide', 'switchback-carousel' ) }
			</Button>
			{ last && (
				<Button
					variant="tertiary"
					icon="admin-page"
					onClick={ () =>
						insertBlock( cloneBlock( last ), undefined, clientId )
					}
				>
					{ __( 'Copy last slide', 'switchback-carousel' ) }
				</Button>
			) }
		</div>
	);
}

/**
 * Linked layout: when the slide being edited changes shape or style, the
 * others follow (keeping their content).
 *
 * @param {string}  clientId Track client ID.
 * @param {boolean} enabled  Whether linked layout is on.
 */
function useLinkedLayout( clientId, enabled ) {
	const registry = useRegistry();
	const {
		replaceInnerBlocks,
		__unstableMarkNextChangeAsNotPersistent: quietly,
	} = useDispatch( 'core/block-editor' );
	const { slides, editing } = useSelect(
		( select ) => {
			const editor = select( 'core/block-editor' );
			const selected = editor.getSelectedBlockClientId();
			const path = selected
				? [ ...editor.getBlockParents( selected ), selected ]
				: [];
			const at = path.indexOf( clientId );
			return {
				slides: enabled ? editor.getBlocks( clientId ) : [],
				editing: at >= 0 ? ( path[ at + 1 ] ?? null ) : null,
			};
		},
		[ clientId, enabled ]
	);
	const seen = useRef( new Map() );

	useEffect( () => {
		if ( ! enabled ) {
			seen.current.clear();
			return;
		}
		const now = new Map(
			slides.map( ( slide ) => [
				slide.clientId,
				layoutFingerprint( slide.innerBlocks ),
			] )
		);
		const before = seen.current.get( editing );
		seen.current = now;
		if ( ! editing || ! before || before === now.get( editing ) ) {
			return;
		}
		const source = slides.find( ( slide ) => slide.clientId === editing );
		registry.batch( () =>
			slides
				.filter(
					( slide ) =>
						slide.clientId !== editing &&
						now.get( slide.clientId ) !== now.get( editing )
				)
				.forEach( ( slide ) => {
					quietly();
					replaceInnerBlocks(
						slide.clientId,
						mirrorLayout( source.innerBlocks, slide.innerBlocks ),
						false
					);
				} )
		);
	}, [ slides, editing, enabled ] ); // eslint-disable-line react-hooks/exhaustive-deps
}

export default function Edit( props ) {
	const { attributes, clientId } = props;
	const { layout, motion, slideWidth, linkedLayout, style } = attributes;

	const device = useDevice();
	const { columns, peek } = layoutFor(
		layout,
		device,
		'fade' === motion?.transition
	);

	const blockProps = useBlockProps( {
		className: 'switchback-editor is-manual',
		style: {
			'--switchback-columns': columns,
			'--switchback-peek': `${ peek }%`,
			...( gapStyle( style?.spacing?.blockGap ) && {
				'--switchback-gap': gapStyle( style.spacing.blockGap ),
			} ),
			...( slideWidth?.max && {
				'--switchback-max-width': `${ slideWidth.max }px`,
			} ),
		},
	} );

	useLinkedLayout( clientId, !! linkedLayout );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'switchback-editor__strip' },
		{
			allowedBlocks: SLIDES,
			orientation: 'horizontal',
			renderAppender: () => <AddSlides clientId={ clientId } />,
		}
	);

	return (
		<>
			<Inspector { ...props } />
			<div { ...blockProps }>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
