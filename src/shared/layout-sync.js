/**
 * Linked layouts: rebuild one slide to follow another's structure and styles
 * while it keeps its own content.
 *
 * Blocks are paired by name and position among blocks of that name, level by
 * level. Paired blocks take the source's attributes except content attributes;
 * source blocks without a partner are copied in; target blocks without a
 * partner are removed. Block types we don't know keep all their attributes and
 * only follow the source's ordering.
 */
import { cloneBlock, createBlock } from '@wordpress/blocks';

/** Content attributes preserved per slide. */
export const CONTENT_ATTRIBUTES = {
	'core/paragraph': [ 'content' ],
	'core/heading': [ 'content' ],
	'core/list-item': [ 'content' ],
	'core/quote': [ 'citation' ],
	'core/pullquote': [ 'value', 'citation' ],
	'core/image': [
		'url',
		'id',
		'alt',
		'caption',
		'title',
		'href',
		'linkTarget',
		'rel',
	],
	'core/button': [ 'text', 'url', 'title', 'rel', 'linkTarget' ],
	'core/cover': [ 'url', 'id', 'alt', 'focalPoint' ],
	'core/media-text': [ 'mediaUrl', 'mediaId', 'mediaAlt', 'href' ],
	'core/video': [ 'src', 'id', 'poster', 'caption' ],
	'core/embed': [ 'url' ],
	'core/icon': [ 'icon' ],
	'outermost/icon-block': [ 'icon', 'iconName', 'linkUrl' ],
};

/** Layout-only blocks: every attribute syncs. */
export const STRUCTURAL_TYPES = [
	'core/group',
	'core/columns',
	'core/column',
	'core/buttons',
	'core/spacer',
	'core/separator',
	'core/list',
];

function mergeAttributes( source, target ) {
	const content = CONTENT_ATTRIBUTES[ source.name ];
	if ( STRUCTURAL_TYPES.includes( source.name ) ) {
		return { ...source.attributes };
	}
	if ( ! content ) {
		return { ...target.attributes };
	}
	const merged = { ...source.attributes };
	for ( const key of content ) {
		if ( key in target.attributes ) {
			merged[ key ] = target.attributes[ key ];
		} else {
			delete merged[ key ];
		}
	}
	return merged;
}

/**
 * Rebuild `targets` to follow `sources`.
 *
 * @param {Array} sources Source inner blocks.
 * @param {Array} targets Target inner blocks.
 * @return {Array} New inner blocks for the target.
 */
export function mirrorLayout( sources, targets ) {
	const pools = {};
	for ( const block of targets ) {
		( pools[ block.name ] ??= [] ).push( block );
	}
	const used = {};

	return sources.map( ( source ) => {
		const index = used[ source.name ] ?? 0;
		used[ source.name ] = index + 1;
		const match = pools[ source.name ]?.[ index ];

		if ( ! match ) {
			return cloneBlock( source );
		}
		return createBlock(
			source.name,
			mergeAttributes( source, match ),
			mirrorLayout( source.innerBlocks, match.innerBlocks )
		);
	} );
}

/**
 * Signature of a block tree's structure and non-content attributes; equal
 * signatures mean syncing would change nothing.
 *
 * @param {Array} blocks Blocks.
 * @return {string} Signature.
 */
export function layoutFingerprint( blocks ) {
	return JSON.stringify(
		blocks.map( ( block ) => {
			const content = CONTENT_ATTRIBUTES[ block.name ] ?? [];
			const attrs =
				STRUCTURAL_TYPES.includes( block.name ) ||
				CONTENT_ATTRIBUTES[ block.name ]
					? Object.fromEntries(
							Object.entries( block.attributes ).filter(
								( [ key ] ) => ! content.includes( key )
							)
						)
					: {};
			return [
				block.name,
				attrs,
				layoutFingerprint( block.innerBlocks ),
			];
		} )
	);
}
