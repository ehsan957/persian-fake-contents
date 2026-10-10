/**
 * "Persian Lorem" block: pick how much text you want, then it replaces itself
 * with ordinary paragraph (and heading) blocks.
 */
import apiFetch from '@wordpress/api-fetch';
import { createBlock, registerBlockType } from '@wordpress/blocks';
import {
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	Notice,
	Placeholder,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { isRTL, __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import metadata from '../../blocks/lorem/block.json';

function Edit( { attributes, setAttributes, clientId } ) {
	const { paragraphs, headings } = attributes;
	const { replaceBlocks } = useDispatch( blockEditorStore );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const insert = async () => {
		setLoading( true );
		setError( null );
		try {
			const result = await apiFetch( {
				path: addQueryArgs( '/persian-fake/v1/text', {
					paragraphs,
					headings,
				} ),
			} );
			const blocks = result.blocks.map( ( block ) =>
				block.type === 'heading'
					? createBlock( 'core/heading', {
							content: block.text,
							level: 2,
						} )
					: createBlock( 'core/paragraph', {
							content: block.text,
							// Keep Persian text right-to-left on LTR sites.
							...( isRTL() ? {} : { direction: 'rtl' } ),
						} )
			);
			replaceBlocks( clientId, blocks );
		} catch ( e ) {
			setError( e.message );
			setLoading( false );
		}
	};

	return (
		<div { ...useBlockProps() }>
			<Placeholder
				icon="editor-paragraph"
				label={ __( 'Persian Lorem', 'persian-fake-contents' ) }
				instructions={ __(
					'Insert random Persian paragraphs. They become normal paragraph blocks you can edit.',
					'persian-fake-contents'
				) }
			>
				<div
					className="gfpc-lorem-controls"
					style={ { width: '100%' } }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Paragraphs', 'persian-fake-contents' ) }
						min={ 1 }
						max={ 20 }
						value={ paragraphs }
						onChange={ ( value ) =>
							setAttributes( { paragraphs: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Add headings', 'persian-fake-contents' ) }
						checked={ headings }
						onChange={ ( value ) =>
							setAttributes( { headings: value } )
						}
					/>
					<Button
						variant="primary"
						isBusy={ loading }
						disabled={ loading }
						onClick={ insert }
						style={ { marginTop: 12 } }
					>
						{ __( 'Insert text', 'persian-fake-contents' ) }
					</Button>
				</div>
			</Placeholder>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// The block replaces itself before saving; if left in place it stores nothing.
	save: () => null,
} );
