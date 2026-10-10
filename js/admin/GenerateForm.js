import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { sprintf, _n, __ } from '@wordpress/i18n';

import { API } from './App';
import Progress from './Progress';

const MAX_COUNT = 500;

function NumberField( { label, value, min, max, onChange, help } ) {
	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			type="number"
			label={ label }
			help={ help }
			min={ min }
			max={ max }
			value={ value }
			onChange={ ( v ) => onChange( v === '' ? '' : Number( v ) ) }
		/>
	);
}

function Select( props ) {
	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			{ ...props }
		/>
	);
}

export default function GenerateForm( { config, notify, onDone } ) {
	const [ options, setOptions ] = useState( config.options );
	const [ running, setRunning ] = useState( false );
	const [ progress, setProgress ] = useState( { done: 0, total: 0 } );
	const cancelled = useRef( false );

	const set = ( key ) => ( value ) =>
		setOptions( ( current ) => ( { ...current, [ key ]: value } ) );

	const type =
		config.postTypes.find( ( t ) => t.value === options.post_type ) ||
		config.postTypes[ 0 ];

	const categoryOptions = [
		{ value: 0, label: __( 'Default', 'persian-fake-contents' ) },
		{
			value: config.random,
			label: __( 'Random', 'persian-fake-contents' ),
		},
		// Specific categories only exist for posts; products pick a random product category.
		...( type.product ? [] : config.categories ),
	];

	const authorOptions = [
		{ value: 0, label: __( 'Random', 'persian-fake-contents' ) },
		...config.authors,
	];

	const generate = async () => {
		const total = Math.min(
			MAX_COUNT,
			Math.max( 1, Number( options.count ) || 1 )
		);
		let done = 0;
		const warnings = [];

		cancelled.current = false;
		setRunning( true );
		setProgress( { done, total } );

		try {
			while ( done < total && ! cancelled.current ) {
				const count = Math.min( config.batch, total - done );
				const result = await apiFetch( {
					path: `${ API }/generate`,
					method: 'POST',
					data: {
						options: { ...options, count: total },
						count,
						save: done === 0,
					},
				} );
				done += count;
				warnings.push( ...result.warnings );
				setProgress( { done, total } );
			}

			notify(
				'success',
				sprintf(
					/* translators: %d: number of created items */
					_n(
						'%d fake item created.',
						'%d fake items created.',
						done,
						'persian-fake-contents'
					),
					done
				)
			);
			if ( warnings.length ) {
				notify(
					'warning',
					sprintf(
						/* translators: 1: number of problems, 2: first problem message */
						__(
							'%1$d problem(s), e.g.: %2$s',
							'persian-fake-contents'
						),
						warnings.length,
						warnings[ 0 ]
					)
				);
			}
		} catch ( e ) {
			notify( 'error', e.message );
		} finally {
			setRunning( false );
			onDone();
		}
	};

	return (
		<Card className="gfpc-card">
			<CardHeader>
				<h2>{ __( 'Generate', 'persian-fake-contents' ) }</h2>
			</CardHeader>
			<CardBody>
				<div className="gfpc-grid">
					<NumberField
						label={ __( 'How many', 'persian-fake-contents' ) }
						min={ 1 }
						max={ MAX_COUNT }
						value={ options.count }
						onChange={ set( 'count' ) }
					/>
					<Select
						label={ __( 'Post type', 'persian-fake-contents' ) }
						value={ options.post_type }
						options={ config.postTypes }
						onChange={ set( 'post_type' ) }
					/>
					<Select
						label={ __( 'Status', 'persian-fake-contents' ) }
						value={ options.status }
						options={ config.statuses }
						onChange={ set( 'status' ) }
					/>
					<Select
						label={ __( 'Author', 'persian-fake-contents' ) }
						value={ options.author }
						options={ authorOptions }
						onChange={ ( v ) => set( 'author' )( Number( v ) ) }
					/>
					{ type.categories && (
						<Select
							label={
								type.product
									? __(
											'Product category',
											'persian-fake-contents'
										)
									: __( 'Category', 'persian-fake-contents' )
							}
							value={ options.category }
							options={ categoryOptions }
							onChange={ ( v ) =>
								set( 'category' )( Number( v ) )
							}
						/>
					) }
					{ type.thumbnail && (
						<Select
							label={ __(
								'Featured image',
								'persian-fake-contents'
							) }
							value={ options.image }
							options={ config.imageSources }
							onChange={ set( 'image' ) }
						/>
					) }
				</div>

				<h3>{ __( 'Content', 'persian-fake-contents' ) }</h3>
				<div className="gfpc-grid">
					<NumberField
						label={ __(
							'Paragraphs from',
							'persian-fake-contents'
						) }
						min={ 1 }
						max={ 20 }
						value={ options.paragraphs_min }
						onChange={ set( 'paragraphs_min' ) }
					/>
					<NumberField
						label={ __( 'Paragraphs to', 'persian-fake-contents' ) }
						min={ 1 }
						max={ 20 }
						value={ options.paragraphs_max }
						onChange={ set( 'paragraphs_max' ) }
					/>
					{ ! type.product && (
						<Select
							label={ __( 'Format', 'persian-fake-contents' ) }
							value={ options.format }
							options={ [
								{
									value: 'blocks',
									label: __(
										'Blocks (headings, lists, quotes)',
										'persian-fake-contents'
									),
								},
								{
									value: 'classic',
									label: __(
										'Classic HTML',
										'persian-fake-contents'
									),
								},
							] }
							onChange={ set( 'format' ) }
						/>
					) }
					{ type.tags && (
						<NumberField
							label={ __(
								'Tags per post (up to)',
								'persian-fake-contents'
							) }
							min={ 0 }
							max={ 10 }
							value={ options.tags }
							onChange={ set( 'tags' ) }
						/>
					) }
					{ type.comments && (
						<NumberField
							label={
								type.product
									? __(
											'Reviews per product (up to)',
											'persian-fake-contents'
										)
									: __(
											'Comments per post (up to)',
											'persian-fake-contents'
										)
							}
							min={ 0 }
							max={ 20 }
							value={ options.comments }
							onChange={ set( 'comments' ) }
						/>
					) }
					<NumberField
						label={ __(
							'Spread dates over last N days',
							'persian-fake-contents'
						) }
						help={ __( '0 = now', 'persian-fake-contents' ) }
						min={ 0 }
						value={ options.days }
						onChange={ set( 'days' ) }
					/>
				</div>

				<div className="gfpc-actions">
					{ running ? (
						<>
							<Progress
								done={ progress.done }
								total={ progress.total }
								label={ __(
									'Generating',
									'persian-fake-contents'
								) }
							/>
							<Button
								variant="secondary"
								onClick={ () => ( cancelled.current = true ) }
							>
								{ __( 'Stop', 'persian-fake-contents' ) }
							</Button>
						</>
					) : (
						<Button variant="primary" onClick={ generate }>
							{ __( 'Generate', 'persian-fake-contents' ) }
						</Button>
					) }
				</div>
			</CardBody>
		</Card>
	);
}
