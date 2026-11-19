/**
 * Per-device layout: read, resolve and write it in one place. Mirrors
 * includes/responsive.php; see docs/responsive-styles.md.
 *
 * Two storage shapes are read, `style` winning per field:
 * - legacy: layout.{desktop,tablet,mobile}.{columns,step,peek}
 * - core responsive style states, named like core's Gallery block:
 *   style["@tablet"|"@mobile"].{columns,step,peek}. Desktop is the base value
 *   and stays in layout.desktop.
 *
 * Tablet and phone writes go to the shape usesCoreResponsive() picks, and
 * clear the same field from the other shape so it can't shadow the new value.
 */

export const VIEWPORT_STATES = { tablet: '@tablet', mobile: '@mobile' };
export const DEVICES = [ 'desktop', 'tablet', 'mobile' ];
const FIELDS = [ 'columns', 'step', 'peek' ];

const DESKTOP = { columns: 3, step: 1, peek: 0 };
const MOBILE = { columns: 1, step: 1, peek: 0 };

/**
 * Whether the editor stores layout in core responsive style states. Set by
 * PHP from uses_core_responsive().
 *
 * @return {boolean} Core storage on.
 */
export const usesCoreResponsive = () =>
	!! globalThis.switchbackResponsive?.core;

/**
 * @param {Object} settings Stored settings for one device.
 * @return {Object} Only the per-device fields that are set.
 */
function fields( settings ) {
	const clean = {};
	FIELDS.forEach( ( field ) => {
		if ( typeof settings?.[ field ] === 'number' ) {
			clean[ field ] = settings[ field ];
		}
	} );
	return clean;
}

/**
 * Stored per-device settings from either shape. Gaps stay gaps.
 *
 * @param {Object} layout Legacy `layout` value.
 * @param {Object} style  The block's `style` attribute.
 * @return {{desktop:Object, tablet:Object, mobile:Object}} Stored settings.
 */
export function storedLayouts( layout = {}, style = {} ) {
	return Object.fromEntries(
		DEVICES.map( ( key ) => [
			key,
			{
				...fields( layout?.[ key ] ),
				...fields( style?.[ VIEWPORT_STATES[ key ] ] ),
			},
		] )
	);
}

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
 * Effective layout for a device, resolved the same way as the PHP render:
 * tablet falls back to desktop; mobile falls back to one column.
 *
 * @param {Object}  layout Legacy `layout` attribute.
 * @param {string}  device Desktop | Tablet | Mobile.
 * @param {boolean} fade   Fading tracks always show one slide.
 * @param {Object}  style  The block's `style` attribute.
 * @return {{columns:number, step:number, peek:number}} Layout.
 */
export function layoutFor(
	layout = {},
	device = 'Desktop',
	fade = false,
	style = {}
) {
	if ( fade ) {
		return { columns: 1, step: 1, peek: 0 };
	}
	const stored = storedLayouts( layout, style );
	const desktop = complete( stored.desktop, DESKTOP );
	if ( 'Tablet' === device ) {
		return complete( stored.tablet, desktop );
	}
	if ( 'Mobile' === device ) {
		return complete( stored.mobile, MOBILE );
	}
	return desktop;
}

/**
 * Attribute changes that set some fields for one device.
 *
 * @param {Object}  current        Current `{ layout, style }`.
 * @param {Object}  current.layout Legacy `layout` value.
 * @param {Object}  current.style  The block's `style` attribute.
 * @param {string}  key            desktop | tablet | mobile.
 * @param {Object}  changes        Fields to set; undefined clears a field.
 * @param {boolean} [core]         Write core style states (default: the flag).
 * @return {{layout:Object, style:Object|undefined}} New `layout` and `style`.
 */
export function writeDeviceLayout(
	{ layout = {}, style = {} },
	key,
	changes,
	core = usesCoreResponsive()
) {
	const merged = {
		...storedLayouts( layout, style )[ key ],
		...changes,
	};
	Object.keys( merged ).forEach(
		( field ) => merged[ field ] === undefined && delete merged[ field ]
	);

	// Desktop is the base value and always lives in `layout`.
	const toStyle = core && 'desktop' !== key;
	const nextLayout = { ...layout };
	if ( ! toStyle && Object.keys( merged ).length ) {
		nextLayout[ key ] = merged;
	} else {
		delete nextLayout[ key ];
	}

	const state = VIEWPORT_STATES[ key ];
	if ( ! state ) {
		return { layout: nextLayout, style };
	}
	// Our fields share the viewport state with core's own styles; keep those.
	const bucket = { ...( style?.[ state ] || {} ) };
	FIELDS.forEach( ( field ) => delete bucket[ field ] );
	Object.assign( bucket, toStyle ? merged : {} );
	const nextStyle = { ...( style || {} ) };
	if ( Object.keys( bucket ).length ) {
		nextStyle[ state ] = bucket;
	} else {
		delete nextStyle[ state ];
	}
	return {
		layout: nextLayout,
		style: Object.keys( nextStyle ).length ? nextStyle : undefined,
	};
}

/**
 * The device key a preview device edits.
 *
 * @param {string} device Desktop | Tablet | Mobile.
 * @return {string} desktop | tablet | mobile.
 */
export const deviceKey = ( device ) => ( device || 'Desktop' ).toLowerCase();
