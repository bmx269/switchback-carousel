/**
 * "Add slide" in the block toolbar of the carousel, its track and each slide.
 * The appender at the end of the track scrolls out of view once a carousel
 * has more slides than fit, so this keeps adding a slide one click away.
 */
import { __ } from '@wordpress/i18n';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarButton } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';

const TRACK = 'switchback-carousel/track';
const SLIDE = 'switchback-carousel/slide';

/**
 * Where a new slide goes from a block: after the slide itself, or at the end
 * of the track.
 *
 * @param {Object} editor   core/block-editor selectors.
 * @param {string} clientId Carousel, track or slide client ID.
 * @return {{track:string, index:number|undefined}|null} Target, if any.
 */
function target( editor, clientId ) {
	const name = editor.getBlockName( clientId );
	if ( SLIDE === name ) {
		return {
			track: editor.getBlockRootClientId( clientId ),
			index: editor.getBlockIndex( clientId ) + 1,
		};
	}
	if ( TRACK === name ) {
		return { track: clientId, index: undefined };
	}
	const track = editor
		.getBlocks( clientId )
		.find( ( block ) => TRACK === block.name );
	return track ? { track: track.clientId, index: undefined } : null;
}

/**
 * Toolbar button that inserts a new slide and selects it.
 *
 * @param {Object} props          Props.
 * @param {string} props.clientId Carousel, track or slide client ID.
 */
export default function AddSlideButton( { clientId } ) {
	const registry = useRegistry();
	const { insertBlock } = useDispatch( 'core/block-editor' );
	const canAdd = useSelect(
		( select ) => {
			const editor = select( 'core/block-editor' );
			const found = target( editor, clientId );
			return !! found && editor.canInsertBlockType( SLIDE, found.track );
		},
		[ clientId ]
	);

	if ( ! canAdd ) {
		return null;
	}

	return (
		<BlockControls group="other">
			<ToolbarButton
				icon="plus"
				onClick={ () => {
					const { track, index } = target(
						registry.select( 'core/block-editor' ),
						clientId
					);
					insertBlock( createBlock( SLIDE ), index, track );
				} }
			>
				{ __( 'Add slide', 'switchback-carousel' ) }
			</ToolbarButton>
		</BlockControls>
	);
}
