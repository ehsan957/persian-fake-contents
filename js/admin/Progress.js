import { sprintf, __ } from '@wordpress/i18n';

export default function Progress( { done, total, label } ) {
	const percent = total ? Math.round( ( done / total ) * 100 ) : 0;

	return (
		<div className="gfpc-progress">
			<div
				className="gfpc-progress__bar"
				role="progressbar"
				aria-valuemin={ 0 }
				aria-valuemax={ total }
				aria-valuenow={ done }
			>
				<span style={ { width: `${ percent }%` } } />
			</div>
			<span className="gfpc-progress__label">
				{ sprintf(
					/* translators: 1: label, 2: done count, 3: total count */
					__( '%1$s %2$d / %3$d', 'persian-fake-contents' ),
					label,
					done,
					total
				) }
			</span>
		</div>
	);
}
