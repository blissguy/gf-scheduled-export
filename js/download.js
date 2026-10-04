/**
 * On the Scheduled Entry Export feed list, the "Download" row action of a
 * manual export opens a date picker instead of downloading straight away.
 * Without this script (or <dialog> support) the link still works and
 * downloads every entry.
 *
 * The dialog markup is printed by maybe_render_download_dialog().
 */
( function () {
	var dialog = document.getElementById( 'gfse-download-dialog' );

	if ( ! dialog || typeof dialog.showModal !== 'function' ) {
		return;
	}

	var form         = dialog.querySelector( 'form' );
	var title        = document.getElementById( 'gfse-download-title' );
	var defaultTitle = title.textContent;
	var start        = form.elements.start;
	var end          = form.elements.end;

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '.gfse-download' );

		if ( ! link ) {
			return;
		}

		event.preventDefault();

		form.elements.feed_id.value = link.getAttribute( 'data-feed-id' );
		title.textContent           = link.getAttribute( 'data-feed-name' ) || defaultTitle;

		dialog.showModal();
	} );

	// Keep From on or before To.
	start.addEventListener( 'change', function () {
		end.min = start.value;
	} );

	end.addEventListener( 'change', function () {
		start.max = end.value;
	} );

	dialog.querySelector( '[data-gfse-cancel]' ).addEventListener( 'click', function () {
		dialog.close();
	} );

	// The response is a file, so the page stays put: close once it starts.
	form.addEventListener( 'submit', function () {
		window.setTimeout( function () {
			dialog.close();
		}, 0 );
	} );
} )();
