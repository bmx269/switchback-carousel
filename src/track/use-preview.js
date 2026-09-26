/**
 * Posts for the query-mode preview, from POST /switchback-carousel/v1/preview, which
 * runs the same PHP as the front end.
 */
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';

const answers = new Map();

/**
 * @param {boolean} active  Whether the track is in query mode.
 * @param {string}  source  Source name.
 * @param {Object}  query   Query attribute.
 * @param {number}  context ID of the post being edited.
 * @return {{posts: Array<{id:number,type:string}>, loading: boolean}} Preview state.
 */
export function usePreview( active, source, query, context ) {
	const [ state, setState ] = useState( { posts: [], loading: false } );
	const key = JSON.stringify( [ source, query, context ] );

	useEffect( () => {
		if ( ! active ) {
			return undefined;
		}
		if ( answers.has( key ) ) {
			setState( { posts: answers.get( key ), loading: false } );
			return undefined;
		}
		let stale = false;
		setState( ( previous ) => ( { ...previous, loading: true } ) );
		const timer = setTimeout( () => {
			apiFetch( {
				path: '/switchback-carousel/v1/preview',
				method: 'POST',
				data: { source, query, context },
			} )
				.then( ( { posts = [] } ) => {
					answers.set( key, posts );
					if ( ! stale ) {
						setState( { posts, loading: false } );
					}
				} )
				.catch(
					() => ! stale && setState( { posts: [], loading: false } )
				);
		}, 300 );
		return () => {
			stale = true;
			clearTimeout( timer );
		};
	}, [ active, key ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return state;
}
