/**
 * Field rules: filter posts by registered meta or SCF/ACF field values.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

export const OPERATORS = [
	[ 'is', __( 'is', 'switchback-carousel' ) ],
	[ 'is_not', __( 'is not', 'switchback-carousel' ) ],
	[ 'contains', __( 'contains', 'switchback-carousel' ) ],
	[ 'not_contains', __( 'doesn’t contain', 'switchback-carousel' ) ],
	[ 'gt', __( 'is more than', 'switchback-carousel' ) ],
	[ 'gte', __( 'is at least', 'switchback-carousel' ) ],
	[ 'lt', __( 'is less than', 'switchback-carousel' ) ],
	[ 'lte', __( 'is at most', 'switchback-carousel' ) ],
	[ 'in', __( 'is one of', 'switchback-carousel' ) ],
	[ 'not_in', __( 'is none of', 'switchback-carousel' ) ],
	[ 'between', __( 'is between', 'switchback-carousel' ) ],
	[ 'not_between', __( 'is outside', 'switchback-carousel' ) ],
	[ 'exists', __( 'is filled in', 'switchback-carousel' ) ],
	[ 'not_exists', __( 'is empty', 'switchback-carousel' ) ],
];

const CASTS = [
	[ 'text', __( 'Text', 'switchback-carousel' ) ],
	[ 'number', __( 'Whole number', 'switchback-carousel' ) ],
	[ 'decimal', __( 'Decimal number', 'switchback-carousel' ) ],
	[ 'date', __( 'Date', 'switchback-carousel' ) ],
	[ 'datetime', __( 'Date and time', 'switchback-carousel' ) ],
	[ 'time', __( 'Time', 'switchback-carousel' ) ],
];

const asOptions = ( pairs ) =>
	pairs.map( ( [ value, label ] ) => ( { value, label } ) );
const LISTS = [ 'in', 'not_in' ];
const RANGES = [ 'between', 'not_between' ];
const NO_VALUE = [ 'exists', 'not_exists' ];

const fieldCache = new Map();

/**
 * Fields a post type can be filtered or sorted by.
 *
 * @param {string} postType Post type.
 * @return {Array<{key:string,label:string,origin:string,cast:string}>} Fields.
 */
export function useFields( postType ) {
	const [ fields, setFields ] = useState(
		() => fieldCache.get( postType ) ?? []
	);
	useEffect( () => {
		if ( ! postType ) {
			setFields( [] );
			return undefined;
		}
		if ( fieldCache.has( postType ) ) {
			setFields( fieldCache.get( postType ) );
			return undefined;
		}
		let stale = false;
		apiFetch( {
			path: `/switchback-carousel/v1/fields?postType=${ encodeURIComponent( postType ) }`,
		} )
			.then( ( response ) => {
				fieldCache.set( postType, response.fields ?? [] );
				if ( ! stale ) {
					setFields( response.fields ?? [] );
				}
			} )
			.catch( () => ! stale && setFields( [] ) );
		return () => {
			stale = true;
		};
	}, [ postType ] );
	return fields;
}

/**
 * Select options for fields, plus "other" for typing a key.
 *
 * @param {Array}  fields     Fields.
 * @param {string} emptyLabel Label for the empty option.
 * @return {Array} Options.
 */
export function fieldOptions( fields, emptyLabel ) {
	return [
		{ value: '', label: emptyLabel },
		...fields.map( ( field ) => ( {
			value: field.key,
			label:
				field.label === field.key
					? field.key
					: `${ field.label } — ${ field.key }`,
		} ) ),
	];
}

function valueHelp( rule, field ) {
	if ( LISTS.includes( rule.operator ) ) {
		return __( 'Separate values with commas.', 'switchback-carousel' );
	}
	if ( RANGES.includes( rule.operator ) ) {
		return __(
			'Two values separated by a comma, e.g. 10, 20.',
			'switchback-carousel'
		);
	}
	if (
		[ 'date', 'datetime' ].includes( rule.cast ) ||
		'date' === field?.cast
	) {
		return __(
			'Use {today}, {today+7} or {today-30}. SCF/ACF date pickers store dates as {today_ymd}.',
			'switchback-carousel'
		);
	}
	return undefined;
}

export default function FieldRules( { postType, value, onChange } ) {
	const fields = useFields( postType );
	const rules = value?.rules ?? [];
	const match = value?.match ?? 'all';
	const save = ( nextRules, nextMatch = match ) =>
		onChange( { match: nextMatch, rules: nextRules } );
	const edit = ( index, changes ) =>
		save(
			rules.map( ( rule, i ) =>
				i === index ? { ...rule, ...changes } : rule
			)
		);

	return (
		<div className="switchback-editor__rules">
			{ rules.length > 1 && (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Show posts matching', 'switchback-carousel' ) }
					value={ match }
					options={ [
						{
							value: 'all',
							label: __( 'every rule', 'switchback-carousel' ),
						},
						{
							value: 'any',
							label: __(
								'at least one rule',
								'switchback-carousel'
							),
						},
					] }
					onChange={ ( next ) => save( rules, next ) }
				/>
			) }

			{ rules.map( ( rule, index ) => {
				const known = fields.find(
					( field ) => field.key === rule.field
				);
				return (
					<fieldset className="switchback-editor__rule" key={ index }>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Field', 'switchback-carousel' ) }
							value={ known ? rule.field : '' }
							options={ fieldOptions(
								fields,
								__(
									'Other (type the key)',
									'switchback-carousel'
								)
							) }
							onChange={ ( key ) => {
								const field = fields.find(
									( item ) => item.key === key
								);
								edit( index, {
									field: key,
									...( field && { cast: field.cast } ),
								} );
							} }
						/>
						{ ! known && (
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Field key',
									'switchback-carousel'
								) }
								value={ rule.field }
								onChange={ ( key ) =>
									edit( index, { field: key } )
								}
							/>
						) }
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Condition', 'switchback-carousel' ) }
							value={ rule.operator }
							options={ asOptions( OPERATORS ) }
							onChange={ ( operator ) =>
								edit( index, { operator } )
							}
						/>
						{ ! NO_VALUE.includes( rule.operator ) && (
							<>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Value',
										'switchback-carousel'
									) }
									help={ valueHelp( rule, known ) }
									value={ rule.value }
									onChange={ ( next ) =>
										edit( index, { value: next } )
									}
								/>
								<SelectControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Compare as',
										'switchback-carousel'
									) }
									value={ rule.cast }
									options={ asOptions( CASTS ) }
									onChange={ ( cast ) =>
										edit( index, { cast } )
									}
								/>
							</>
						) }
						<Button
							variant="link"
							isDestructive
							onClick={ () =>
								save( rules.filter( ( _, i ) => i !== index ) )
							}
						>
							{ __( 'Remove rule', 'switchback-carousel' ) }
						</Button>
					</fieldset>
				);
			} ) }

			<Button
				variant="secondary"
				size="compact"
				disabled={ rules.length >= 10 }
				onClick={ () =>
					save( [
						...rules,
						{ field: '', operator: 'is', value: '', cast: 'text' },
					] )
				}
			>
				{ rules.length
					? __( 'Add another rule', 'switchback-carousel' )
					: __( 'Add a field rule', 'switchback-carousel' ) }
			</Button>
		</div>
	);
}
