/**
 * Track editor.
 *
 * Manual: slides sit side by side in a scrollable strip.
 * Query: the first slide is the design. It's edited in place with one of the
 * query's posts as block context; the other posts show as previews of the
 * same design. Click a preview to edit the design with that post.
 */
import { __ } from '@wordpress/i18n';
/* eslint-disable @wordpress/no-unsafe-wp-apis -- block previews have no stable API yet; core's Query Loop uses this hook. */
import {
	BlockContextProvider,
	useBlockProps,
	useInnerBlocksProps,
	__experimentalUseBlockPreview as useBlockPreview,
} from '@wordpress/block-editor';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import { Button, Notice, Spinner } from '@wordpress/components';
import { cloneBlock, createBlock } from '@wordpress/blocks';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { memo, useEffect, useMemo, useRef, useState } from '@wordpress/element';

import Inspector from './inspector';
import { usePreview } from './use-preview';
import { useDevice } from '../shared/device';
import { layoutFor } from '../shared/responsive';
import { layoutFingerprint, mirrorLayout } from '../shared/layout-sync';

const SLIDES = [ 'switchback-carousel/slide' ];

/** Starting design for query mode. */
const starterDesign = () =>
	createBlock( 'switchback-carousel/slide', {}, [
		createBlock( 'core/post-featured-image', {
			isLink: true,
			aspectRatio: '3/2',
		} ),
		createBlock( 'core/post-title', { isLink: true, level: 3 } ),
		createBlock( 'core/post-excerpt', { excerptLength: 18 } ),
	] );

/**
 * Whether a slide is still the untouched placeholder a new carousel starts
 * with (one empty paragraph), so it can be swapped for a post design.
 *
 * @param {Object|undefined} slide Slide block.
 * @return {boolean} Placeholder or not.
 */
function isPlaceholder( slide ) {
	const inner = slide?.innerBlocks ?? [];
	return (
		inner.length === 1 &&
		'core/paragraph' === inner[ 0 ].name &&
		! String( inner[ 0 ].attributes.content ?? '' ).trim()
	);
}

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

const PostPreview = memo( function PostPreviewCard( { blocks, onEdit } ) {
	const props = useBlockPreview( {
		blocks,
		props: {
			className: 'switchback-editor__cell switchback-editor__preview',
		},
	} );
	return (
		<div
			{ ...props }
			role="button"
			tabIndex={ 0 }
			aria-label={ __(
				'Edit the slide design with this post',
				'switchback-carousel'
			) }
			onClick={ onEdit }
			onKeyDown={ ( event ) =>
				[ 'Enter', ' ' ].includes( event.key ) && onEdit()
			}
		/>
	);
} );

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
	const {
		mode,
		source,
		query,
		layout,
		motion,
		slideWidth,
		linkedLayout,
		style,
	} = attributes;
	const isQuery = 'query' === mode;

	const device = useDevice();
	const { columns, peek } = layoutFor(
		layout,
		device,
		'fade' === motion?.transition
	);

	const blockProps = useBlockProps( {
		className: `switchback-editor is-${ mode }`,
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

	useLinkedLayout( clientId, !! linkedLayout && ! isQuery );

	const { design, empty, editedPost } = useSelect(
		( select ) => {
			const blocks = select( 'core/block-editor' ).getBlocks( clientId );
			return {
				design: blocks[ 0 ],
				empty: ! blocks.length,
				editedPost: select( 'core/editor' )?.getCurrentPostId?.() ?? 0,
			};
		},
		[ clientId ]
	);

	// Query mode needs a design. A fresh carousel's placeholder slide is
	// swapped for a starter design; real slides are kept (the first one
	// becomes the design, the rest come back when switching to hand-built).
	const { replaceInnerBlocks, replaceBlock } =
		useDispatch( 'core/block-editor' );
	useEffect( () => {
		if ( ! isQuery ) {
			return;
		}
		if ( empty ) {
			replaceInnerBlocks( clientId, [ starterDesign() ], false );
		} else if ( isPlaceholder( design ) ) {
			replaceBlock( design.clientId, starterDesign() );
		}
	}, [ isQuery, empty ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const { posts, loading } = usePreview( isQuery, source, query, editedPost );
	const [ chosen, choose ] = useState( 0 );
	const active = Math.min( chosen, Math.max( 0, posts.length - 1 ) );
	const designBlocks = useMemo(
		() => ( design ? [ design ] : [] ),
		[ design ]
	);

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: isQuery
				? 'switchback-editor__cell switchback-editor__design'
				: 'switchback-editor__strip',
		},
		{
			allowedBlocks: SLIDES,
			orientation: 'horizontal',
			renderAppender: isQuery
				? false
				: () => <AddSlides clientId={ clientId } />,
		}
	);

	return (
		<>
			<Inspector { ...props } />
			<div { ...blockProps }>
				{ ! isQuery && <div { ...innerBlocksProps } /> }
				{ isQuery && (
					<>
						{ loading && ! posts.length && (
							<div className="switchback-editor__status">
								<Spinner />
							</div>
						) }
						{ ! loading && ! posts.length && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Nothing matches this query yet. The slide below is the design each post will use.',
									'switchback-carousel'
								) }
							</Notice>
						) }
						<div className="switchback-editor__strip">
							{ ( posts.length ? posts : [ null ] ).map(
								( post, index ) =>
									index === active ? (
										<BlockContextProvider
											key="design"
											value={
												post
													? {
															postId: post.id,
															postType: post.type,
														}
													: {}
											}
										>
											<div { ...innerBlocksProps } />
										</BlockContextProvider>
									) : (
										<BlockContextProvider
											key={ post.id }
											value={ {
												postId: post.id,
												postType: post.type,
											} }
										>
											<PostPreview
												blocks={ designBlocks }
												onEdit={ () => choose( index ) }
											/>
										</BlockContextProvider>
									)
							) }
						</div>
					</>
				) }
			</div>
		</>
	);
}
