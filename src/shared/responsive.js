/**
 * Per-device layout, resolved the same way as track/render.php:
 * tablet falls back to desktop; mobile falls back to one column.
 */

const DESKTOP = { columns: 3, step: 1, peek: 0 };
const MOBILE = { columns: 1, step: 1, peek: 0 };

/**
 * @param {Object} settings Device settings (may be partial or empty).
 * @param {Object} fallback Complete settings to fill gaps from.
 * @return {{columns:number, step:number, peek:number}} Complete settings.
 */
function complete( settings = {}, fallback ) {
	return {
		columns: settings.columns ?? fallback.columns,
		step: settings.step ?? fallback.step,
		peek: settings.peek ?? fallback.peek,
	};
}

/**
 * Effective layout for an editor device preview.
 *
 * @param {Object}  layout Track `layout` attribute.
 * @param {string}  device Desktop | Tablet | Mobile.
 * @param {boolean} fade   Fading tracks always show one slide.
 * @return {{columns:number, step:number, peek:number}} Layout.
 */
export function layoutFor( layout = {}, device = 'Desktop', fade = false ) {
	if ( fade ) {
		return { columns: 1, step: 1, peek: 0 };
	}
	const desktop = complete( layout.desktop, DESKTOP );
	if ( 'Tablet' === device ) {
		return complete( layout.tablet, desktop );
	}
	if ( 'Mobile' === device ) {
		return complete( layout.mobile, MOBILE );
	}
	return desktop;
}

/**
 * The device key a preview device edits.
 *
 * @param {string} device Desktop | Tablet | Mobile.
 * @return {string} desktop | tablet | mobile.
 */
export const deviceKey = ( device ) => ( device || 'Desktop' ).toLowerCase();
