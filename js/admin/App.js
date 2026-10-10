import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import GenerateForm from './GenerateForm';
import ItemsList from './ItemsList';

export const API = '/persian-fake/v1';

export default function App() {
	const [ config, setConfig ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ notices, setNotices ] = useState( [] );
	// Bumped after generating or deleting so the list reloads.
	const [ listVersion, setListVersion ] = useState( 0 );

	useEffect( () => {
		apiFetch( { path: `${ API }/config` } )
			.then( setConfig )
			.catch( ( e ) => setError( e.message ) );
	}, [] );

	const notify = useCallback( ( status, message ) => {
		setNotices( ( current ) => [
			...current,
			{ id: Date.now() + Math.random(), status, message },
		] );
	}, [] );

	const refresh = useCallback( () => setListVersion( ( v ) => v + 1 ), [] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! config ) {
		return <Spinner />;
	}

	return (
		<div className="gfpc">
			{ notices.map( ( notice ) => (
				<Notice
					key={ notice.id }
					status={ notice.status }
					onRemove={ () =>
						setNotices( ( current ) =>
							current.filter( ( n ) => n.id !== notice.id )
						)
					}
				>
					{ notice.message }
				</Notice>
			) ) }

			<GenerateForm
				config={ config }
				notify={ notify }
				onDone={ refresh }
			/>
			<ItemsList
				version={ listVersion }
				notify={ notify }
				onDone={ refresh }
			/>

			<p className="gfpc-cli-hint">
				{ __(
					'Need thousands of items? Use WP-CLI:',
					'persian-fake-contents'
				) }{ ' ' }
				<code>wp persian-fake generate --count=1000</code>
			</p>
		</div>
	);
}
