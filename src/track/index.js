/**
 * Carousel track.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	icon: (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M3 7h5v10H3V7Zm6.5 0h5v10h-5V7ZM16 7h5v10h-5V7Z" />
		</svg>
	),
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
