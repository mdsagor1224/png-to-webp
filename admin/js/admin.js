/**
 * PNG to WebP Converter — Admin JavaScript
 * Vanilla JS. No jQuery dependency. Uses core wp.media / wp.Uploader where needed.
 */
( function () {
	'use strict';

	if ( typeof window.ptwAdmin === 'undefined' ) {
		return;
	}

	var cfg = window.ptwAdmin;

	/**
	 * Perform an AJAX POST request against admin-ajax.php using fetch().
	 *
	 * @param {string} action AJAX action name.
	 * @param {Object} data   Extra POST fields.
	 * @return {Promise<Object>} Parsed JSON response.
	 */
	function ptwRequest( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function formatBytes( bytes ) {
		if ( ! bytes ) {
			return '0 B';
		}
		var units = [ 'B', 'KB', 'MB', 'GB' ];
		var i = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
		i = Math.min( i, units.length - 1 );
		return ( bytes / Math.pow( 1024, i ) ).toFixed( 1 ) + ' ' + units[ i ];
	}

	/* -----------------------------------------------------------------
	 * Media Library row actions (Convert to WebP / Regenerate WebP)
	 * --------------------------------------------------------------- */
	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( target.classList && ( target.classList.contains( 'ptw-convert' ) || target.classList.contains( 'ptw-regenerate' ) ) ) {
			event.preventDefault();

			var isRegenerate = target.classList.contains( 'ptw-regenerate' );

			if ( isRegenerate && ! window.confirm( cfg.i18n.confirmRegenerate ) ) {
				return;
			}

			var attachmentId = target.getAttribute( 'data-attachment-id' );
			var originalText = target.textContent;
			target.textContent = cfg.i18n.converting;

			ptwRequest( 'ptw_convert_single', {
				attachment_id: attachmentId,
				regenerate: isRegenerate ? 1 : 0,
			} ).then( function ( response ) {
				if ( response.success ) {
					target.textContent = cfg.i18n.converted;
				} else {
					target.textContent = cfg.i18n.failed;
					window.alert( response.data && response.data.message ? response.data.message : cfg.i18n.failed );
					target.textContent = originalText;
				}
			} ).catch( function () {
				target.textContent = originalText;
				window.alert( cfg.i18n.failed );
			} );
		}
	} );

	/* -----------------------------------------------------------------
	 * Bulk conversion page
	 * --------------------------------------------------------------- */
	var progressWrap   = document.getElementById( 'ptw-progress-wrap' );
	var progressFill   = document.getElementById( 'ptw-progress-bar-fill' );
	var progressBar    = document.getElementById( 'ptw-progress-bar' );
	var progressLabel  = document.getElementById( 'ptw-progress-label' );
	var countConverted = document.getElementById( 'ptw-count-converted' );
	var countSkipped   = document.getElementById( 'ptw-count-skipped' );
	var countFailed    = document.getElementById( 'ptw-count-failed' );
	var sizeOriginal   = document.getElementById( 'ptw-size-original' );
	var sizeWebp       = document.getElementById( 'ptw-size-webp' );
	var sizeSaved      = document.getElementById( 'ptw-size-saved' );
	var resultsBody    = document.getElementById( 'ptw-results-body' );

	if ( ! progressWrap ) {
		return; // Not on the bulk conversion page.
	}

	var selectedIds = [];

	function updateProgress( done, total ) {
		var percent = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		progressFill.style.width = percent + '%';
		progressBar.setAttribute( 'aria-valuenow', String( percent ) );
		progressLabel.textContent = percent + '% (' + done + '/' + total + ')';
	}

	function addResultRow( item ) {
		var row = document.createElement( 'tr' );

		var statusClass = 'ptw-status-' + ( item.status || 'failed' );
		var statusLabel = item.status === 'converted' ? cfg.i18n.converted
			: item.status === 'skipped' ? item.message
			: item.status === 'failed' ? cfg.i18n.failed
			: item.status;

		row.innerHTML =
			'<td></td><td></td><td></td><td></td><td></td>';

		var cells = row.querySelectorAll( 'td' );
		cells[ 0 ].textContent = item.original_name || ( 'ID #' + item.attachment_id );
		cells[ 1 ].textContent = item.original_size ? formatBytes( item.original_size ) : '—';
		cells[ 2 ].textContent = item.webp_size ? formatBytes( item.webp_size ) : '—';
		cells[ 3 ].textContent = item.savings_percent ? item.savings_percent + '%' : '—';

		var statusSpan = document.createElement( 'span' );
		statusSpan.className = 'ptw-status ' + statusClass;
		statusSpan.textContent = statusLabel;
		cells[ 4 ].appendChild( statusSpan );

		resultsBody.appendChild( row );
	}

	/**
	 * Process a queue of attachment IDs sequentially (one AJAX call at a time)
	 * so the browser never freezes, regardless of library size.
	 *
	 * @param {Array<number>} ids Attachment IDs to convert.
	 */
	function processQueue( ids ) {
		progressWrap.hidden = false;
		resultsBody.innerHTML = '';

		var total     = ids.length;
		var done      = 0;
		var converted = 0;
		var skipped   = 0;
		var failed    = 0;
		var totalOriginal = 0;
		var totalWebp      = 0;

		updateProgress( 0, total );

		function next( index ) {
			if ( index >= total ) {
				return;
			}

			ptwRequest( 'ptw_convert_batch_item', { attachment_id: ids[ index ] } ).then( function ( response ) {
				var item = response.success ? response.data : { status: 'failed', message: cfg.i18n.failed, attachment_id: ids[ index ] };

				if ( 'converted' === item.status ) {
					converted++;
					totalOriginal += item.original_size || 0;
					totalWebp += item.webp_size || 0;
				} else if ( 'skipped' === item.status ) {
					skipped++;
				} else {
					failed++;
				}

				done++;

				countConverted.textContent = String( converted );
				countSkipped.textContent = String( skipped );
				countFailed.textContent = String( failed );
				sizeOriginal.textContent = formatBytes( totalOriginal );
				sizeWebp.textContent = formatBytes( totalWebp );
				sizeSaved.textContent = formatBytes( Math.max( 0, totalOriginal - totalWebp ) );

				addResultRow( item );
				updateProgress( done, total );

				next( index + 1 );
			} ).catch( function () {
				failed++;
				done++;
				countFailed.textContent = String( failed );
				updateProgress( done, total );
				next( index + 1 );
			} );
		}

		next( 0 );
	}

	/* --- Select Images (wp.media library picker) --- */
	var selectBtn = document.getElementById( 'ptw-select-images' );
	var startSelectedBtn = document.getElementById( 'ptw-start-selected' );

	if ( selectBtn && window.wp && wp.media ) {
		selectBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			var frame = wp.media( {
				title: cfg.i18n.dropHere,
				button: { text: cfg.i18n.startConversion },
				multiple: true,
				library: { type: [ 'image/png', 'image/jpeg' ] },
			} );

			frame.on( 'select', function () {
				var selection = frame.state().get( 'selection' );
				selectedIds = selection.map( function ( attachment ) {
					return attachment.id;
				} );
				startSelectedBtn.disabled = selectedIds.length === 0;
				startSelectedBtn.textContent = startSelectedBtn.textContent.replace( /\s\(\d+\)$/, '' ) + ' (' + selectedIds.length + ')';
			} );

			frame.open();
		} );
	}

	if ( startSelectedBtn ) {
		startSelectedBtn.addEventListener( 'click', function () {
			if ( selectedIds.length ) {
				processQueue( selectedIds );
			}
		} );
	}

	/* --- Convert All Eligible Images --- */
	var convertAllBtn = document.getElementById( 'ptw-convert-all' );
	if ( convertAllBtn ) {
		convertAllBtn.addEventListener( 'click', function () {
			convertAllBtn.disabled = true;
			ptwRequest( 'ptw_get_convertible_ids', {} ).then( function ( response ) {
				convertAllBtn.disabled = false;
				var ids = response.success && response.data.ids ? response.data.ids : [];
				if ( ! ids.length ) {
					window.alert( cfg.i18n.noImagesFound );
					return;
				}
				processQueue( ids );
			} );
		} );
	}

	/* --- Drag & Drop zone: uses core wp.Uploader for secure uploads --- */
	var dropzone = document.getElementById( 'ptw-dropzone' );

	if ( dropzone ) {
		dropzone.addEventListener( 'click', function () {
			if ( selectBtn ) {
				selectBtn.click();
			}
		} );

		dropzone.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || ' ' === event.key ) {
				event.preventDefault();
				if ( selectBtn ) {
					selectBtn.click();
				}
			}
		} );

		[ 'dragenter', 'dragover' ].forEach( function ( evtName ) {
			dropzone.addEventListener( evtName, function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				dropzone.classList.add( 'ptw-dragover' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( evtName ) {
			dropzone.addEventListener( evtName, function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				dropzone.classList.remove( 'ptw-dragover' );
			} );
		} );

		dropzone.addEventListener( 'drop', function ( event ) {
			var files = event.dataTransfer ? event.dataTransfer.files : null;

			if ( ! files || ! files.length || ! window.wp || ! wp.Uploader ) {
				return;
			}

			// Upload dropped files through WordPress's own secure uploader,
			// which enforces standard Media Library permissions and MIME checks.
			var uploadedIds = [];
			var uploader = new wp.Uploader( {
				browser: null,
				dropzone: dropzone,
				success: function ( attachment ) {
					uploadedIds.push( attachment.get( 'id' ) );
				},
				complete: function () {
					if ( uploadedIds.length ) {
						processQueue( uploadedIds );
					}
				},
			} );

			// wp.Uploader listens on the dropzone element itself once instantiated,
			// so the current drop event is picked up automatically by its internal binder.
			void uploader;
		} );
	}
} )();
