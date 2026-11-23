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

/* Claude-rutan: hämtar resorna från länkarna och fyller i alla tomma fält. */
( function () {
	'use strict';

	var button = document.getElementById( 'cvkp-claude-run' );
	if ( ! button || ! window.cvkpAdmin ) {
		return;
	}
	var box = document.getElementById( 'cvkp-claude' );
	var spinner = box.querySelector( '.spinner' );
	var status = box.querySelector( '.cvkp-claude-status' );

	/** Fälten som kan fyllas i: fältnamn => element-id (editorer har id cvkp_<namn>). */
	var FIELDS = {
		subject: 'cvkp-subject',
		preheader: 'cvkp-preheader',
		eyebrow: 'cvkp-eyebrow',
		heading: 'cvkp-heading',
		trips_heading: 'cvkp-trips_heading',
		blog_excerpt: 'cvkp-blog_excerpt',
		fb_text: 'cvkp-fb_text',
		ig_text: 'cvkp-ig_text',
		intro: 'cvkp_intro',
		trips_intro: 'cvkp_trips_intro'
	};
	var EDITORS = [ 'intro', 'trips_intro' ];

	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	/** Ren text till stycken för editorn. */
	function toParagraphs( text ) {
		return text.trim().split( /\n\s*\n/ ).map( function ( p ) {
			return '<p>' + escapeHtml( p.trim() ).replace( /\n/g, '<br>' ) + '</p>';
		} ).join( '' );
	}

	function editor( id ) {
		var ed = window.tinymce && window.tinymce.get( id );
		return ed && ! ed.isHidden() ? ed : null;
	}

	function getValue( field ) {
		var id = FIELDS[ field ];
		var ed = editor( id );
		if ( ed ) {
			return ed.getContent( { format: 'text' } ).trim();
		}
		var el = document.getElementById( id );
		return el ? el.value.trim() : '';
	}

	function setValue( field, text ) {
		var id = FIELDS[ field ];
		var el = document.getElementById( id );
		if ( -1 !== EDITORS.indexOf( field ) ) {
			var html = toParagraphs( text );
			var ed = editor( id );
			if ( ed ) {
				ed.setContent( html );
				var frame = ed.getContainer();
				if ( frame ) {
					frame.classList.add( 'cvkp-filled' );
				}
			}
			if ( el ) {
				el.value = html;
			}
			return;
		}
		if ( el ) {
			el.value = text;
			el.classList.add( 'cvkp-filled' );
			el.dispatchEvent( new Event( 'input' ) ); // Uppdaterar räknaren för Instagram.
		}
	}

	/** Markeringen försvinner när man ändrar i fältet. */
	document.addEventListener( 'input', function ( event ) {
		if ( event.isTrusted && event.target.classList ) {
			event.target.classList.remove( 'cvkp-filled' );
		}
	} );

	button.addEventListener( 'click', function () {
		var links = document.getElementById( 'cvkp-claude-links' ).value.trim();
		var intro = document.getElementById( 'cvkp-claude-intro' ).value;
		if ( ! links ) {
			document.getElementById( 'cvkp-claude-links' ).focus();
			return;
		}

		button.disabled = true;
		spinner.classList.add( 'is-active' );
		status.textContent = 'Hämtar resorna och ber Claude skriva texterna … (brukar ta 10–30 sekunder)';
		status.classList.remove( 'cvkp-warn' );

		var body = new FormData();
		body.append( 'action', 'cvkp_claude_fill' );
		body.append( 'nonce', window.cvkpAdmin.nonce );
		body.append( 'intro', intro );
		body.append( 'links', links );
		[ 'heading', 'subject' ].forEach( function ( field ) {
			body.append( 'context[' + field + ']', getValue( field ) );
		} );
		[ 'code', 'code_note', 'ig_signer' ].forEach( function ( field ) {
			var el = document.getElementById( 'cvkp-' + field );
			body.append( 'context[' + field + ']', el ? el.value : '' );
		} );

		fetch( window.cvkpAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result.success ) {
					throw new Error( result.data || 'Okänt fel' );
				}
				var data = result.data;
				var filled = 0;
				var kept = 0;

				// Ingressen: en egen ingress i rutan ersätter (efter fråga) det som står i Inledning.
				if ( data.intro ) {
					if ( ! getValue( 'intro' ) ) {
						setValue( 'intro', data.intro );
						filled++;
					} else if ( 'egen' === data.by.split( '+' )[ 0 ] && window.confirm( 'Ersätta den inledning som redan står i utskicket med ingressen från rutan?' ) ) {
						setValue( 'intro', data.intro );
						filled++;
					} else {
						kept++;
					}
				}

				// Övriga texter: bara tomma fält.
				Object.keys( data.texts || {} ).forEach( function ( field ) {
					if ( ! FIELDS[ field ] ) {
						return;
					}
					if ( getValue( field ) ) {
						kept++;
						return;
					}
					setValue( field, data.texts[ field ] );
					filled++;
				} );

				var list = document.getElementById( 'cvkp-trip-list' );
				var wrapper = document.createElement( 'div' );
				wrapper.innerHTML = data.rows;
				var count = 0;
				while ( wrapper.firstElementChild ) {
					list.appendChild( wrapper.firstElementChild );
					count++;
				}

				var done = count + ( 1 === count ? ' resa tillagd' : ' resor tillagda' ) + ', ' + filled + ' fält ifyllda (markerade med grönt)';
				if ( kept ) {
					done += ', ' + kept + ' fält hade redan text och ändrades inte';
				}
				done += '. Läs igenom allt och klicka Spara och skapa utkast.';
				status.textContent = done + ( data.warnings.length ? ' Obs: ' + data.warnings.join( '. ' ) + '.' : '' );
				status.classList.toggle( 'cvkp-warn', data.warnings.length > 0 );
			} )
			.catch( function ( error ) {
				status.textContent = 'Det gick inte: ' + error.message;
				status.classList.add( 'cvkp-warn' );
			} )
			.finally( function () {
				button.disabled = false;
				spinner.classList.remove( 'is-active' );
			} );
	} );
}() );
