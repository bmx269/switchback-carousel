/**
 * Switchback Carousel front end: mounts Splide on every `.switchback-track` and connects
 * the controls inside the same carousel (`.switchback`). Controls of a nested
 * carousel belong to that carousel.
 */
import Splide from '@splidejs/splide';
import { AutoScroll } from '@splidejs/splide-extension-auto-scroll';

const STRINGS = {
	carousel: 'carousel',
	slide: 'slide',
	slideLabel: '%1$s of %2$s',
	pageX: 'Go to page %s',
	slideX: 'Go to slide %s',
	prev: 'Previous slide',
	next: 'Next slide',
	play: 'Play',
	pause: 'Pause',
	progress: 'Carousel position',
	...( window.switchbackStrings || {} ),
};

/**
 * Splide i18n uses %s placeholders only; map ours onto it.
 */
const SPLIDE_I18N = {
	prev: STRINGS.prev,
	next: STRINGS.next,
	first: STRINGS.slideX.replace( '%s', '1' ),
	last: STRINGS.slideX,
	slideX: STRINGS.slideX,
	pageX: STRINGS.pageX,
	play: STRINGS.play,
	pause: STRINGS.pause,
	carousel: STRINGS.carousel,
	slide: STRINGS.slide,
	slideLabel: STRINGS.slideLabel
		.replace( '%1$s', '%s' )
		.replace( '%2$s', '%s' ),
};

/**
 * Replace %s placeholders in order.
 *
 * @param {string}           template Text with placeholders.
 * @param {...string|number} values   Replacements.
 * @return {string} Text.
 */
const fill = ( template, ...values ) =>
	values.reduce(
		( text, value ) => text.replace( /%(\d\$)?s/, String( value ) ),
		template
	);

/**
 * Elements matching `selector` that belong to `carousel` and not to a
 * carousel nested inside it.
 *
 * @param {Element} carousel Carousel root.
 * @param {string}  selector Selector.
 * @return {Element[]} Elements.
 */
function belongingTo( carousel, selector ) {
	return Array.from( carousel.querySelectorAll( selector ) ).filter(
		( element ) => element.closest( '.switchback' ) === carousel
	);
}

class Carousel {
	constructor( track ) {
		this.track = track;
		this.root = track.closest( '.switchback' ) || track.parentElement;
		this.config = JSON.parse( track.dataset.switchback || '{}' );
		this.teardown = [];

		// Our slide blocks carry no engine classes; Splide finds them by this one.
		const list = track.querySelector( '.splide__list' );
		Array.from( list.children ).forEach( ( slide ) =>
			slide.classList.add( 'splide__slide' )
		);

		// Leave the no-JS scrolling row before Splide measures anything. While
		// the track is still a snapping scroller, inserting loop clones and
		// moving the list makes the browser scroll it natively, and that offset
		// survives the switch to overflow: hidden. Splide positions slides with
		// transforms alone, so a leftover scrollLeft shows part of the
		// neighbouring slide (with focus: center, about a fifth of a slide).
		track.classList.add( 'is-ready' );

		const options = { ...this.config.splide, i18n: SPLIDE_I18N };
		this.splide = new Splide( track, options );
		this.fitMinimumWidth();
		this.splide.mount( options.autoScroll ? { AutoScroll } : {} );
		this.pinScroll();
		// The track itself takes focus, so arrow keys work even when no slide
		// contains a link or button.
		if ( ! track.hasAttribute( 'tabindex' ) ) {
			track.tabIndex = 0;
		}

		this.connectArrows();
		this.connectPages();
		this.connectProgress();
		this.connectCounter();
		this.connectPlayPause();
		this.connectOverlay();
		this.connectGrab();
	}

	on( target, event, handler, options ) {
		target.addEventListener( event, handler, options );
		this.teardown.push( () =>
			target.removeEventListener( event, handler, options )
		);
	}

	/**
	 * Keep the viewport's native scroll at zero. A browser still scrolls an
	 * overflow: hidden box to reveal focused content, e.g. tabbing to a link in
	 * an off-screen slide, which would shift every slide out of place.
	 */
	pinScroll() {
		const viewport = this.splide.Components.Elements.track;
		const reset = () => {
			viewport.scrollLeft = 0;
			viewport.scrollTop = 0;
		};
		reset();
		this.on( viewport, 'scroll', reset, { passive: true } );
	}

	/** Fewer columns when slides would get narrower than the minimum width. */
	fitMinimumWidth() {
		const minimum = this.config.minWidth;
		if ( ! minimum ) {
			return;
		}
		const configured = this.config.splide;
		const apply = () => {
			const width = this.track.clientWidth;
			const gap =
				parseFloat(
					window
						.getComputedStyle( this.track )
						.getPropertyValue( '--switchback-gap' )
				) || 0;
			const fits = Math.max(
				1,
				Math.floor( ( width + gap ) / ( minimum + gap ) )
			);
			const wanted = this.splide.options.perPage;
			const capped = Math.min( configured.perPage, fits );
			if ( wanted !== capped ) {
				this.splide.options = {
					perPage: capped,
					perMove: Math.min( configured.perMove, capped ),
				};
			}
		};
		this.splide.on( 'mounted resized', apply );
	}

	connectArrows() {
		const prev = belongingTo( this.root, '.switchback-controls__prev' );
		const next = belongingTo( this.root, '.switchback-controls__next' );
		prev.forEach( ( button ) =>
			this.on( button, 'click', () => this.splide.go( '<' ) )
		);
		next.forEach( ( button ) =>
			this.on( button, 'click', () => this.splide.go( '>' ) )
		);

		const sync = () => {
			const { Controller } = this.splide.Components;
			const endless =
				this.splide.is( 'loop' ) || this.splide.options.rewind;
			const atStart = ! endless && Controller.getIndex() <= 0;
			const atEnd =
				! endless && Controller.getIndex() >= Controller.getEnd();
			prev.forEach( ( button ) => ( button.disabled = atStart ) );
			next.forEach( ( button ) => ( button.disabled = atEnd ) );
			belongingTo( this.root, '.switchback-controls.is-arrows' ).forEach(
				( group ) =>
					group.classList.toggle( 'is-inactive', atStart && atEnd )
			);
		};
		this.splide.on( 'mounted move refresh updated resized', sync );
		sync();
	}

	pageCount() {
		const { Controller } = this.splide.Components;
		return Controller.toPage( Controller.getEnd() ) + 1;
	}

	connectPages() {
		const lists = belongingTo( this.root, '.switchback-controls__pages' );
		if ( ! lists.length ) {
			return;
		}
		const build = () => {
			const { Controller } = this.splide.Components;
			const pages = this.pageCount();
			const perMove = this.splide.options.perMove || 1;
			lists.forEach( ( list ) => {
				list.replaceChildren(
					...Array.from( { length: pages }, ( _, page ) => {
						const item = document.createElement( 'li' );
						const button = document.createElement( 'button' );
						button.type = 'button';
						button.className = 'switchback-controls__page';
						button.setAttribute(
							'aria-label',
							fill(
								perMove > 1 ? STRINGS.pageX : STRINGS.slideX,
								page + 1
							)
						);
						button.addEventListener( 'click', () =>
							this.splide.go( Controller.toIndex( page ) )
						);
						item.append( button );
						return item;
					} )
				);
				list.closest( '.switchback-controls' ).classList.toggle(
					'is-inactive',
					pages < 2
				);
			} );
			mark();
		};
		const mark = () => {
			const { Controller } = this.splide.Components;
			const current = Controller.toPage( Controller.getIndex() );
			lists.forEach( ( list ) =>
				Array.from( list.querySelectorAll( 'button' ) ).forEach(
					( button, page ) => {
						const active = page === current;
						button.classList.toggle( 'is-active', active );
						button.toggleAttribute( 'aria-current', active );
					}
				)
			);
		};
		this.splide.on( 'mounted refresh updated resized', build );
		this.splide.on( 'move', mark );
		build();
	}

	connectProgress() {
		const bars = belongingTo( this.root, '.switchback-controls__progress' );
		if ( ! bars.length ) {
			return;
		}
		const update = () => {
			const { Controller } = this.splide.Components;
			const end = Math.max( 1, Controller.getEnd() );
			const visible = Math.min(
				1,
				this.splide.options.perPage / this.splide.length
			);
			const position = Math.min( 1, Controller.getIndex() / end );
			bars.forEach( ( bar ) => {
				const thumb = bar.firstElementChild;
				thumb.style.width = `${ Math.max( visible, 0.08 ) * 100 }%`;
				thumb.style.insetInlineStart = `${ position * ( 1 - Math.max( visible, 0.08 ) ) * 100 }%`;
				bar.setAttribute(
					'aria-valuenow',
					String( Math.round( position * 100 ) )
				);
				bar.closest( '.switchback-controls' ).classList.toggle(
					'is-inactive',
					visible >= 1
				);
			} );
		};

		bars.forEach( ( bar ) => {
			bar.setAttribute( 'role', 'slider' );
			bar.setAttribute( 'aria-label', STRINGS.progress );
			bar.setAttribute( 'aria-valuemin', '0' );
			bar.setAttribute( 'aria-valuemax', '100' );
			bar.tabIndex = 0;

			const seek = ( clientX ) => {
				const rect = bar.getBoundingClientRect();
				let ratio = ( clientX - rect.left ) / rect.width;
				if ( this.splide.options.direction === 'rtl' ) {
					ratio = 1 - ratio;
				}
				const end = this.splide.Components.Controller.getEnd();
				this.splide.go(
					Math.round( Math.min( 1, Math.max( 0, ratio ) ) * end )
				);
			};
			let pointer = null;
			this.on( bar, 'pointerdown', ( event ) => {
				pointer = event.pointerId;
				bar.setPointerCapture( pointer );
				seek( event.clientX );
			} );
			this.on(
				bar,
				'pointermove',
				( event ) =>
					pointer === event.pointerId && seek( event.clientX )
			);
			this.on( bar, 'pointerup', () => ( pointer = null ) );
			this.on( bar, 'keydown', ( event ) => {
				const forward = {
					ArrowRight: '>',
					ArrowUp: '>',
					ArrowLeft: '<',
					ArrowDown: '<',
				}[ event.key ];
				if ( forward ) {
					event.preventDefault();
					this.splide.go( forward );
				}
			} );
		} );

		this.splide.on( 'mounted move refresh updated resized', update );
		update();
	}

	connectCounter() {
		const counters = belongingTo(
			this.root,
			'.switchback-controls__counter'
		);
		if ( ! counters.length ) {
			return;
		}
		const update = () => {
			const { Controller } = this.splide.Components;
			counters.forEach( ( counter ) => {
				counter.querySelector(
					'.switchback-controls__current'
				).textContent = String(
					Controller.toPage( Controller.getIndex() ) + 1
				);
				counter.querySelector(
					'.switchback-controls__total'
				).textContent = String( this.pageCount() );
			} );
		};
		this.splide.on( 'mounted move refresh updated resized', update );
		update();
	}

	connectPlayPause() {
		const buttons = belongingTo(
			this.root,
			'.switchback-controls__playpause'
		);
		const player =
			this.splide.Components.AutoScroll ||
			( this.splide.options.autoplay
				? this.splide.Components.Autoplay
				: null );
		if ( ! buttons.length || ! player ) {
			return;
		}
		const label = () =>
			buttons.forEach( ( button ) => {
				const paused = player.isPaused();
				button.textContent = paused ? STRINGS.play : STRINGS.pause;
				button.setAttribute( 'aria-pressed', String( paused ) );
			} );
		buttons.forEach( ( button ) => {
			button.hidden = false;
			this.on( button, 'click', () => {
				if ( player.isPaused() ) {
					player.play();
				} else {
					player.pause();
				}
				label();
			} );
		} );
		this.splide.on(
			'autoplay:play autoplay:pause autoScroll:play autoScroll:pause',
			label
		);
		label();
	}

	/** Overlay arrows centre on the track, even with content above it. */
	connectOverlay() {
		if (
			! this.root.classList.contains( 'has-overlay-controls' ) ||
			! window.ResizeObserver
		) {
			return;
		}
		const place = () => {
			const top = this.track.offsetTop + this.track.offsetHeight / 2;
			this.root.style.setProperty(
				'--switchback-track-middle',
				`${ top }px`
			);
		};
		const observer = new window.ResizeObserver( place );
		observer.observe( this.track );
		this.teardown.push( () => observer.disconnect() );
		place();
	}

	connectGrab() {
		if ( ! this.track.classList.contains( 'has-grab-cursor' ) ) {
			return;
		}
		this.splide.on( 'drag', () =>
			this.track.classList.add( 'is-grabbing' )
		);
		this.splide.on( 'dragged', () =>
			this.track.classList.remove( 'is-grabbing' )
		);
	}

	destroy() {
		this.teardown.forEach( ( undo ) => undo() );
		this.splide.destroy();
		this.track.classList.remove( 'is-ready' );
	}
}

const mounted = new WeakMap();

function mount( scope = document ) {
	scope
		.querySelectorAll( '.switchback-track[data-switchback]' )
		.forEach( ( track ) => {
			if (
				mounted.has( track ) ||
				! track.querySelector( '.splide__list' )?.children.length
			) {
				return;
			}
			try {
				mounted.set( track, new Carousel( track ) );
			} catch ( error ) {
				// A broken carousel stays a scrollable list instead of breaking the page.
				// eslint-disable-next-line no-console
				console.error( 'Switchback Carousel:', error );
			}
		} );
}

function unmount( node ) {
	[ node, ...node.querySelectorAll( '.switchback-track' ) ].forEach(
		( track ) => {
			mounted.get( track )?.destroy();
			mounted.delete( track );
		}
	);
}

window.switchback = {
	mount,
	unmount,
	get: ( track ) => mounted.get( track )?.splide,
};

function start() {
	mount();
	new window.MutationObserver( ( changes ) =>
		changes.forEach( ( change ) => {
			change.addedNodes.forEach(
				( node ) =>
					node.nodeType === 1 && mount( node.parentNode || document )
			);
			change.removedNodes.forEach(
				( node ) => node.nodeType === 1 && unmount( node )
			);
		} )
	).observe( document.body, { childList: true, subtree: true } );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
