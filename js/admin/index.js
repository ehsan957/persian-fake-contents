/**
 * Tools > Persian Fake Contents admin app.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import App from './App';
import './style.scss';

domReady( () => {
	const container = document.getElementById( 'gfpc-app' );
	if ( container ) {
		createRoot( container ).render( <App /> );
	}
} );
