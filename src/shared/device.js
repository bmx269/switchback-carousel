/**
 * The editor's device preview: Desktop, Tablet or Mobile.
 */
import { useSelect } from '@wordpress/data';

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
