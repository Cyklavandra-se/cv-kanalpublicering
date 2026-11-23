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
