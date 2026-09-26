/**
 * Control icons, generated from Phosphor Icons (MIT) into includes/icons.json.
 */
import library from '../../includes/icons.json';

export const ICONS = library.icons;
export const WEIGHTS = [ 'thin', 'light', 'regular', 'bold', 'fill' ];

/**
 * Inner SVG markup for an icon and weight.
 *
 * @param {string} slug   Icon slug.
 * @param {string} weight Weight.
 * @return {{viewBox:string, markup:string}|null} Icon data.
 */
export function iconMarkup( slug, weight = 'regular' ) {
	const icon =
		ICONS.find( ( item ) => item.slug === slug ) ??
		ICONS.find( ( item ) => item.slug === 'caret-right' );
	return icon
		? {
				viewBox: icon.viewBox,
				markup: icon.weights[ weight ] ?? icon.weights.regular,
			}
		: null;
}
