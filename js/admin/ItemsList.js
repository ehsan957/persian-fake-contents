import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { sprintf, _n, __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { API } from './App';
import Progress from './Progress';

const PER_PAGE = 20;

export default function ItemsList( { version, notify, onDone } ) {
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ purge, setPurge ] = useState( null );

	useEffect( () => {
		apiFetch( {
			path: addQueryArgs( `${ API }/items`, {
				page,
				per_page: PER_PAGE,
			} ),
		} )
			.then( setData )
			.catch( ( e ) => notify( 'error', e.message ) );
	}, [ page, version, notify ] );

	// Go back to page 1 whenever the list changes.
	useEffect( () => setPage( 1 ), [ version ] );

	const deleteAll = async () => {
		if (
			// eslint-disable-next-line no-alert
			! window.confirm(
				__(
					'Delete all fake posts, their featured images, comments and fake tags? This cannot be undone.',
					'persian-fake-contents'
				)
			)
		) {
			return;
		}

		const total = data.total;
		let deleted = 0;
		setPurge( { done: 0, total } );

		try {
			let remaining = 1;
			while ( remaining > 0 ) {
				const result = await apiFetch( {
					path: `${ API }/items`,
					method: 'DELETE',
				} );
				deleted += result.deleted;
				remaining = result.remaining;
				setPurge( { done: Math.min( deleted, total ), total } );
			}
			notify(
				'success',
				sprintf(
					/* translators: %d: number of deleted items */
					_n(
						'%d fake item deleted.',
						'%d fake items deleted.',
						deleted,
						'persian-fake-contents'
					),
					deleted
				)
			);
		} catch ( e ) {
			notify( 'error', e.message );
		} finally {
			setPurge( null );
			onDone();
		}
	};

	if ( ! data ) {
		return <Spinner />;
	}

	const pages = Math.max( 1, Math.ceil( data.total / PER_PAGE ) );

	return (
		<Card className="gfpc-card">
			<CardHeader>
				<h2>
					{ sprintf(
						/* translators: %d: number of fake items */
						__( 'Fake content (%d)', 'persian-fake-contents' ),
						data.total
					) }
				</h2>
				{ data.total > 0 && ! purge && (
					<Button
						variant="secondary"
						isDestructive
						onClick={ deleteAll }
					>
						{ __( 'Delete all', 'persian-fake-contents' ) }
					</Button>
				) }
			</CardHeader>
			<CardBody>
				{ purge && (
					<Progress
						done={ purge.done }
						total={ purge.total }
						label={ __( 'Deleting', 'persian-fake-contents' ) }
					/>
				) }

				{ data.total === 0 ? (
					<p>
						{ __(
							'Nothing generated yet.',
							'persian-fake-contents'
						) }
					</p>
				) : (
					<>
						<table className="widefat striped">
							<thead>
								<tr>
									<th>
										{ __(
											'Title',
											'persian-fake-contents'
										) }
									</th>
									<th>
										{ __(
											'Type',
											'persian-fake-contents'
										) }
									</th>
									<th>
										{ __(
											'Status',
											'persian-fake-contents'
										) }
									</th>
									<th>
										{ __(
											'Date',
											'persian-fake-contents'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ data.items.map( ( item ) => (
									<tr key={ item.id }>
										<td className="gfpc-title">
											<a
												href={
													item.editLink ||
													item.viewLink
												}
											>
												{ item.title }
											</a>
										</td>
										<td>{ item.type }</td>
										<td>{ item.status }</td>
										<td>{ item.date }</td>
									</tr>
								) ) }
							</tbody>
						</table>

						{ pages > 1 && (
							<div className="gfpc-pagination">
								<Button
									variant="tertiary"
									disabled={ page <= 1 }
									onClick={ () => setPage( page - 1 ) }
								>
									{ __(
										'Previous',
										'persian-fake-contents'
									) }
								</Button>
								<span>
									{ sprintf(
										/* translators: 1: current page, 2: total pages */
										__(
											'Page %1$d of %2$d',
											'persian-fake-contents'
										),
										page,
										pages
									) }
								</span>
								<Button
									variant="tertiary"
									disabled={ page >= pages }
									onClick={ () => setPage( page + 1 ) }
								>
									{ __( 'Next', 'persian-fake-contents' ) }
								</Button>
							</div>
						) }
					</>
				) }
			</CardBody>
		</Card>
	);
}
