/**
 * Query settings for tracks that get slides from posts.
 *
 * A source lists the controls it uses; without a list the general set shows.
 * `relationshipField` only ever shows when a source asks for it.
 */
import { __ } from '@wordpress/i18n';
/* eslint-disable @wordpress/no-unsafe-wp-apis -- no stable ToolsPanel or NumberControl yet; core's Query Loop uses these. */
import {
	FormTokenField,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';

import FieldRules, { fieldOptions, useFields } from './field-rules';

const GENERAL = [
	'postType',
	'limit',
	'skip',
	'sort',
	'terms',
	'authors',
	'keyword',
	'parent',
	'stickies',
	'excludeCurrentPost',
	'fields',
];

const SORTS = [
	[ 'date', __( 'Publish date', 'switchback-carousel' ) ],
	[ 'modified', __( 'Last updated', 'switchback-carousel' ) ],
	[ 'title', __( 'Title', 'switchback-carousel' ) ],
	[ 'menu_order', __( 'Page order', 'switchback-carousel' ) ],
	[ 'random', __( 'Random', 'switchback-carousel' ) ],
	[ 'comments', __( 'Most comments', 'switchback-carousel' ) ],
	[ 'field', __( 'A field (text or date)', 'switchback-carousel' ) ],
	[ 'field_number', __( 'A field (number)', 'switchback-carousel' ) ],
].map( ( [ value, label ] ) => ( { value, label } ) );

/**
 * Term picker for one taxonomy, storing term IDs.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.taxonomy Taxonomy record.
 * @param {number[]} props.ids      Selected term IDs.
 * @param {Function} props.onChange Receives term IDs.
 */
function TermPicker( { taxonomy, ids, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const searchLater = useDebounce( setSearch, 250 );
	const idList = ids.join( ',' );
	const { found, picked } = useSelect(
		( select ) => {
			const records = ( args ) =>
				select( coreStore ).getEntityRecords(
					'taxonomy',
					taxonomy.slug,
					{ _fields: 'id,name', ...args }
				) ?? [];
			return {
				found: records( { search, per_page: 20 } ),
				picked: idList
					? records( { include: idList, per_page: 100 } )
					: [],
			};
		},
		[ taxonomy.slug, search, idList ]
	);
	const idByName = new Map(
		[ ...found, ...picked ].map( ( term ) => [ term.name, term.id ] )
	);

	return (
		<FormTokenField
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ taxonomy.name }
			value={ picked.map( ( term ) => term.name ) }
			suggestions={ found.map( ( term ) => term.name ) }
			onInputChange={ searchLater }
			onChange={ ( names ) =>
				onChange(
					names
						.map( ( name ) => idByName.get( name ) )
						.filter( Boolean )
				)
			}
		/>
	);
}

function AuthorPicker( { ids, onChange } ) {
	const people = useSelect(
		( select ) =>
			select( coreStore ).getUsers( {
				who: 'authors',
				per_page: 100,
				_fields: 'id,name',
			} ) ?? [],
		[]
	);
	const idByName = new Map(
		people.map( ( person ) => [ person.name, person.id ] )
	);
	return (
		<FormTokenField
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ __( 'Written by', 'switchback-carousel' ) }
			value={ people
				.filter( ( person ) => ids.includes( person.id ) )
				.map( ( person ) => person.name ) }
			suggestions={ people.map( ( person ) => person.name ) }
			onChange={ ( names ) =>
				onChange(
					names
						.map( ( name ) => idByName.get( name ) )
						.filter( Boolean )
				)
			}
		/>
	);
}

/**
 * The parent select's value for the stored parent setting.
 *
 * @param {string} parent '', 'current' or an ID.
 * @return {string} '' | current | id.
 */
function parentChoice( parent ) {
	if ( 'current' === parent ) {
		return 'current';
	}
	return parent ? 'id' : '';
}

export default function QueryPanel( { attributes, setAttributes, controls } ) {
	const query = attributes.query;
	const uses = ( control ) =>
		controls ? controls.includes( control ) : GENERAL.includes( control );
	const update = ( changes ) =>
		setAttributes( { query: { ...query, ...changes } } );
	const sort = query.sort ?? {};

	const { types, taxonomies, hierarchical, editedType } = useSelect(
		( select ) => {
			const core = select( coreStore );
			const viewable = (
				core.getPostTypes( { per_page: -1 } ) ?? []
			).filter(
				( type ) =>
					type.viewable &&
					! [ 'attachment', 'wp_block', 'wp_navigation' ].includes(
						type.slug
					)
			);
			return {
				types: viewable,
				taxonomies: (
					core.getTaxonomies( {
						type: query.postType,
						per_page: -1,
						context: 'view',
					} ) ?? []
				).filter(
					( taxonomy ) => taxonomy.visibility?.publicly_queryable
				),
				hierarchical: !! viewable.find(
					( type ) => type.slug === query.postType
				)?.hierarchical,
				editedType: select( 'core/editor' )?.getCurrentPostType?.(),
			};
		},
		[ query.postType ]
	);
	const sortFields = useFields( query.postType );
	const ownFields = useFields( editedType );

	const clearFilters = () =>
		update( {
			terms: {},
			authors: [],
			keyword: '',
			parent: '',
			excludeCurrentPost: false,
			fields: { match: 'all', rules: [] },
		} );

	return (
		<PanelBody title={ __( 'Which posts', 'switchback-carousel' ) }>
			{ uses( 'postType' ) && (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Post type', 'switchback-carousel' ) }
					value={ query.postType }
					options={
						types.length
							? types.map( ( type ) => ( {
									value: type.slug,
									label:
										type.labels?.singular_name ?? type.name,
								} ) )
							: [
									{
										value: query.postType,
										label: query.postType,
									},
								]
					}
					onChange={ ( postType ) =>
						update( {
							postType,
							terms: {},
							fields: { match: 'all', rules: [] },
							sort: { ...sort, field: '' },
						} )
					}
				/>
			) }

			{ uses( 'relationshipField' ) && (
				<>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Relationship field',
							'switchback-carousel'
						) }
						help={ __(
							'A field on the page being viewed that stores the chosen posts.',
							'switchback-carousel'
						) }
						value={
							ownFields.some(
								( field ) =>
									field.key === query.relationshipField
							)
								? query.relationshipField
								: ''
						}
						options={ fieldOptions(
							ownFields,
							__(
								'Type the field key below',
								'switchback-carousel'
							)
						) }
						onChange={ ( relationshipField ) =>
							update( { relationshipField } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Field key', 'switchback-carousel' ) }
						value={ query.relationshipField }
						onChange={ ( relationshipField ) =>
							update( { relationshipField } )
						}
					/>
				</>
			) }

			{ uses( 'sort' ) && (
				<>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Sort by', 'switchback-carousel' ) }
						value={ sort.by }
						options={ SORTS }
						onChange={ ( by ) =>
							update( { sort: { ...sort, by } } )
						}
					/>
					{ String( sort.by ).startsWith( 'field' ) && (
						<>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Sort field',
									'switchback-carousel'
								) }
								value={
									sortFields.some(
										( field ) => field.key === sort.field
									)
										? sort.field
										: ''
								}
								options={ fieldOptions(
									sortFields,
									__(
										'Type the field key below',
										'switchback-carousel'
									)
								) }
								onChange={ ( field ) =>
									update( { sort: { ...sort, field } } )
								}
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Field key',
									'switchback-carousel'
								) }
								value={ sort.field }
								onChange={ ( field ) =>
									update( { sort: { ...sort, field } } )
								}
							/>
						</>
					) }
					{ 'random' !== sort.by && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Order', 'switchback-carousel' ) }
							value={ sort.direction }
							options={ [
								{
									value: 'desc',
									label: __(
										'Highest / newest first',
										'switchback-carousel'
									),
								},
								{
									value: 'asc',
									label: __(
										'Lowest / oldest first',
										'switchback-carousel'
									),
								},
							] }
							onChange={ ( direction ) =>
								update( { sort: { ...sort, direction } } )
							}
						/>
					) }
				</>
			) }

			{ uses( 'limit' ) && (
				<RangeControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Number of slides', 'switchback-carousel' ) }
					value={ query.limit }
					min={ 1 }
					max={ 50 }
					onChange={ ( limit ) => update( { limit: limit ?? 6 } ) }
				/>
			) }

			{ uses( 'skip' ) && (
				<NumberControl
					__next40pxDefaultSize
					label={ __( 'Skip the first', 'switchback-carousel' ) }
					value={ query.skip }
					min={ 0 }
					onChange={ ( skip ) =>
						update( { skip: Number( skip ) || 0 } )
					}
				/>
			) }

			{ uses( 'stickies' ) && 'post' === query.postType && (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Sticky posts', 'switchback-carousel' ) }
					value={ query.stickies }
					options={ [
						{
							value: 'include',
							label: __(
								'Treat like other posts',
								'switchback-carousel'
							),
						},
						{
							value: 'exclude',
							label: __( 'Leave out', 'switchback-carousel' ),
						},
						{
							value: 'only',
							label: __(
								'Show only sticky posts',
								'switchback-carousel'
							),
						},
					] }
					onChange={ ( stickies ) => update( { stickies } ) }
				/>
			) }

			<ToolsPanel
				label={ __( 'Narrow it down', 'switchback-carousel' ) }
				resetAll={ clearFilters }
			>
				{ uses( 'fields' ) && (
					<ToolsPanelItem
						label={ __( 'Field values', 'switchback-carousel' ) }
						hasValue={ () => !! query.fields?.rules?.length }
						onDeselect={ () =>
							update( { fields: { match: 'all', rules: [] } } )
						}
						isShownByDefault
					>
						<FieldRules
							postType={ query.postType }
							value={ query.fields }
							onChange={ ( fields ) => update( { fields } ) }
						/>
					</ToolsPanelItem>
				) }

				{ uses( 'terms' ) &&
					taxonomies.map( ( taxonomy ) => (
						<ToolsPanelItem
							key={ taxonomy.slug }
							label={ taxonomy.name }
							hasValue={ () =>
								!! query.terms?.[ taxonomy.slug ]?.length
							}
							onDeselect={ () =>
								update( {
									terms: {
										...query.terms,
										[ taxonomy.slug ]: [],
									},
								} )
							}
						>
							<TermPicker
								taxonomy={ taxonomy }
								ids={ query.terms?.[ taxonomy.slug ] ?? [] }
								onChange={ ( ids ) =>
									update( {
										terms: {
											...query.terms,
											[ taxonomy.slug ]: ids,
										},
									} )
								}
							/>
						</ToolsPanelItem>
					) ) }

				{ uses( 'authors' ) && (
					<ToolsPanelItem
						label={ __( 'Authors', 'switchback-carousel' ) }
						hasValue={ () => !! query.authors?.length }
						onDeselect={ () => update( { authors: [] } ) }
					>
						<AuthorPicker
							ids={ query.authors ?? [] }
							onChange={ ( authors ) => update( { authors } ) }
						/>
					</ToolsPanelItem>
				) }

				{ uses( 'keyword' ) && (
					<ToolsPanelItem
						label={ __( 'Keyword', 'switchback-carousel' ) }
						hasValue={ () => !! query.keyword }
						onDeselect={ () => update( { keyword: '' } ) }
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Containing the words',
								'switchback-carousel'
							) }
							value={ query.keyword }
							onChange={ ( keyword ) => update( { keyword } ) }
						/>
					</ToolsPanelItem>
				) }

				{ uses( 'parent' ) && hierarchical && (
					<ToolsPanelItem
						label={ __( 'Parent', 'switchback-carousel' ) }
						hasValue={ () => !! query.parent }
						onDeselect={ () => update( { parent: '' } ) }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Parent', 'switchback-carousel' ) }
							value={ parentChoice( query.parent ) }
							options={ [
								{
									value: '',
									label: __( 'Any', 'switchback-carousel' ),
								},
								{
									value: 'current',
									label: __(
										'Sub-pages of the page being viewed',
										'switchback-carousel'
									),
								},
								{
									value: 'id',
									label: __(
										'Sub-pages of a specific page',
										'switchback-carousel'
									),
								},
							] }
							onChange={ ( choice ) =>
								update( {
									parent:
										'id' === choice
											? query.parent || '0'
											: choice,
								} )
							}
						/>
						{ 'id' === parentChoice( query.parent ) && (
							<NumberControl
								__next40pxDefaultSize
								label={ __( 'Page ID', 'switchback-carousel' ) }
								value={ query.parent }
								min={ 0 }
								onChange={ ( parent ) =>
									update( { parent: String( parent ?? '' ) } )
								}
							/>
						) }
					</ToolsPanelItem>
				) }

				{ uses( 'excludeCurrentPost' ) && (
					<ToolsPanelItem
						label={ __(
							'Leave out the current post',
							'switchback-carousel'
						) }
						hasValue={ () => !! query.excludeCurrentPost }
						onDeselect={ () =>
							update( { excludeCurrentPost: false } )
						}
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Don’t show the post being viewed',
								'switchback-carousel'
							) }
							checked={ !! query.excludeCurrentPost }
							onChange={ ( excludeCurrentPost ) =>
								update( { excludeCurrentPost } )
							}
						/>
					</ToolsPanelItem>
				) }
			</ToolsPanel>
		</PanelBody>
	);
}
