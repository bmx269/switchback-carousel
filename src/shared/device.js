/**
 * The device being edited: Desktop, Tablet or Mobile.
 */
import * as blockEditor from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

import { usesCoreResponsive } from './responsive';

/**
 * The editor's device preview.
 *
 * @return {string} Desktop | Tablet | Mobile.
 */
export function useDevice() {
	return useSelect(
		( select ) =>
			select( 'core/editor' )?.getDeviceType?.() ??
			select(
				'core/edit-post'
			)?.__experimentalGetPreviewDeviceType?.() ??
			'Desktop',
		[]
	);
}

const STATE_DEVICES = { '@tablet': 'Tablet', '@mobile': 'Mobile' };

// Core's responsive style state ({ viewport: 'default' | '@tablet' | '@mobile' })
// is private until the third-party API lands (Gutenberg #80388). Picked once,
// so the hook order never changes between renders.
const useCoreStyleState =
	usesCoreResponsive() && typeof blockEditor.useBlockStyleState === 'function'
		? blockEditor.useBlockStyleState
		: () => null;

/**
 * The device whose layout the inspector edits. With core responsive styles,
 * the selected viewport state wins; otherwise the device preview.
 *
 * @return {string} Desktop | Tablet | Mobile.
 */
export function useEditingDevice() {
	const preview = useDevice();
	const viewport = useCoreStyleState()?.viewport;
	return STATE_DEVICES[ viewport ] ?? preview;
}
