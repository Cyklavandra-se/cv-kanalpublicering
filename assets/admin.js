/* Reselistan i utskick: lägg till, flytta och ta bort resor. */
( function () {
	'use strict';

	var list = document.getElementById( 'cvkp-trip-list' );
	var select = document.getElementById( 'cvkp-trip-select' );
	var addButton = document.getElementById( 'cvkp-add-trip' );
	if ( ! list || ! select || ! addButton || ! window.cvkpAdmin ) {
		return;
	}
	var spinner = addButton.parentNode.querySelector( '.spinner' );

	addButton.addEventListener( 'click', function () {
		var id = select.value;
		if ( ! id ) {
			select.focus();
			return;
		}
		if ( list.querySelector( '.cvkp-trip[data-id="' + id + '"]' ) && ! window.confirm( 'Resan finns redan i utskicket. Lägga till den igen?' ) ) {
			return;
		}

		addButton.disabled = true;
		if ( spinner ) {
			spinner.classList.add( 'is-active' );
		}

		var body = new FormData();
		body.append( 'action', 'cvkp_trip_row' );
		body.append( 'nonce', window.cvkpAdmin.nonce );
		body.append( 'id', id );

		fetch( window.cvkpAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result.success ) {
					throw new Error( result.data || 'Okänt fel' );
				}
				var wrapper = document.createElement( 'div' );
				wrapper.innerHTML = result.data.html.trim();
				var row = wrapper.firstElementChild;
				list.appendChild( row );
				select.value = '';
				row.querySelector( '.cvkp-trip-text' ).focus();
			} )
			.catch( function ( error ) {
				window.alert( 'Kunde inte lägga till resan: ' + error.message );
			} )
			.finally( function () {
				addButton.disabled = false;
				if ( spinner ) {
					spinner.classList.remove( 'is-active' );
				}
			} );
	} );

	list.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button' );
		if ( ! button ) {
			return;
		}
		var row = button.closest( '.cvkp-trip' );
		if ( button.classList.contains( 'cvkp-up' ) && row.previousElementSibling ) {
			list.insertBefore( row, row.previousElementSibling );
		} else if ( button.classList.contains( 'cvkp-down' ) && row.nextElementSibling ) {
			list.insertBefore( row.nextElementSibling, row );
		} else if ( button.classList.contains( 'cvkp-remove' ) ) {
			var title = row.querySelector( '.cvkp-trip-title' ).textContent;
			if ( window.confirm( 'Ta bort "' + title + '" från utskicket?' ) ) {
				row.remove();
			}
		}
	} );
}() );

/* Facebook och Instagram: räknare, kopiera text och bekräftelse före schemaläggning. */
( function () {
	'use strict';

	var caption = document.getElementById( 'cvkp-ig_text' );
	var counter = document.querySelector( '.cvkp-ig-count' );
	if ( caption && counter ) {
		var max = parseInt( counter.getAttribute( 'data-max' ), 10 ) || 2200;
		var update = function () {
			var length = Array.from( caption.value ).length;
			var tags = ( caption.value.match( /#[\p{L}\p{N}_]+/gu ) || [] ).length;
			counter.textContent = length + ' / ' + max + ' tecken · ' + tags + ' hashtaggar (husstil 3–5)';
			counter.classList.toggle( 'cvkp-warn', length > max || tags < 3 || tags > 5 );
		};
		caption.addEventListener( 'input', update );
		update();
	}

	document.addEventListener( 'click', function ( event ) {
		var copy = event.target.closest( '.cvkp-copy' );
		if ( copy ) {
			var field = document.getElementById( copy.getAttribute( 'data-target' ) );
			if ( field && navigator.clipboard ) {
				navigator.clipboard.writeText( field.value ).then( function () {
					copy.textContent = 'Kopierat!';
					setTimeout( function () {
						copy.textContent = 'Kopiera text';
					}, 2000 );
				} );
			}
			return;
		}
		var confirmButton = event.target.closest( '.cvkp-confirm' );
		if ( confirmButton && ! window.confirm( confirmButton.getAttribute( 'data-confirm' ) ) ) {
			event.preventDefault();
		}
	} );
}() );
