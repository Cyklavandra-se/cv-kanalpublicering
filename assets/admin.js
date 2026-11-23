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
				document.dispatchEvent( new Event( 'cvkp:changed' ) ); // Sparas i webbläsaren direkt.
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

/*
 * Osparade ändringar sparas i webbläsaren (localStorage) medan man skriver, så att
 * inget försvinner om sidan laddas om eller man råkar lämna den. När man kommer
 * tillbaka återställs ändringarna automatiskt, med en ruta som säger det.
 * Kopian tas bort först när sidan laddats om efter sparning och det som skrevs
 * verkligen finns i det sparade utskicket. Misslyckades sparningen återställs allt.
 */
( function () {
	'use strict';

	var cfg = window.cvkpAdmin;
	var form = document.getElementById( 'post' );
	if ( ! cfg || ! cfg.postId || ! form ) {
		return;
	}

	var VERSION = 1;
	var MAX_AGE = 14 * 24 * 3600 * 1000; // Äldre kopior än två veckor används inte.
	// Ett nytt utskick får ett nytt id varje gång sidan öppnas, så det sparas under "nytt".
	var newKey = 'cvkp-utskick-' + cfg.userId + '-nytt';
	var key = cfg.isNew ? newKey : 'cvkp-utskick-' + cfg.userId + '-' + cfg.postId;
	var list = document.getElementById( 'cvkp-trip-list' );
	var EXTRA_IDS = [ 'title', 'cvkp-claude-intro', 'cvkp-claude-links' ];
	var lastSaved = '';
	var restoring = false;
	var dirty = false; // Kopian skapas först när något faktiskt har ändrats.

	function storage() {
		try {
			return window.localStorage;
		} catch ( e ) {
			return null;
		}
	}

	function read( k ) {
		var s = storage();
		if ( ! s ) {
			return null;
		}
		try {
			var data = JSON.parse( s.getItem( k || key ) || 'null' );
			return data && VERSION === data.v ? data : null;
		} catch ( e ) {
			return null;
		}
	}

	function write( data ) {
		var s = storage();
		if ( ! s ) {
			return;
		}
		try {
			s.setItem( key, JSON.stringify( data ) );
		} catch ( e ) {
			// Fullt eller avstängt – sidan fungerar ändå, bara utan kopian.
		}
	}

	function remove() {
		var s = storage();
		if ( s ) {
			try {
				s.removeItem( key );
			} catch ( e ) {}
		}
	}

	/** Fälten i utskicket utanför reselistan, med nyckel namn|ordning. */
	function fields() {
		var out = [];
		var seen = {};
		form.querySelectorAll( '[name^="cvkp["]' ).forEach( function ( el ) {
			if ( list && list.contains( el ) ) {
				return;
			}
			if ( 'hidden' === el.type || 'submit' === el.type || 'button' === el.type ) {
				return;
			}
			var n = seen[ el.name ] = ( seen[ el.name ] || 0 ) + 1;
			out.push( { key: el.name + '|' + n, el: el } );
		} );
		EXTRA_IDS.forEach( function ( id ) {
			var el = document.getElementById( id );
			if ( el ) {
				out.push( { key: '#' + id, el: el } );
			}
		} );
		return out;
	}

	function editors() {
		return ( window.tinymce ? window.tinymce.editors || [] : [] ).filter( function ( ed ) {
			return ed.id && 0 === ed.id.indexOf( 'cvkp_' );
		} );
	}

	/** Reselistan som HTML, med det som skrivits inbakat. */
	function tripHtml() {
		if ( ! list ) {
			return '';
		}
		list.querySelectorAll( 'input, textarea' ).forEach( function ( el ) {
			if ( 'TEXTAREA' === el.tagName ) {
				el.textContent = el.value;
			} else if ( 'checkbox' === el.type || 'radio' === el.type ) {
				if ( el.checked ) {
					el.setAttribute( 'checked', 'checked' );
				} else {
					el.removeAttribute( 'checked' );
				}
			} else {
				el.setAttribute( 'value', el.value );
			}
		} );
		return list.innerHTML;
	}

	function snapshot() {
		var data = { v: VERSION, postId: cfg.postId, fields: {}, editors: {}, trips: tripHtml() };
		fields().forEach( function ( f ) {
			data.fields[ f.key ] = ( 'checkbox' === f.el.type || 'radio' === f.el.type ) ? f.el.checked : f.el.value;
		} );
		editors().forEach( function ( ed ) {
			if ( ed.initialized ) {
				data.editors[ ed.id ] = ed.getContent();
			}
		} );
		return data;
	}

	function save() {
		if ( restoring || ! dirty ) {
			return;
		}
		var data = snapshot();
		var json = JSON.stringify( [ data.fields, data.editors, data.trips ] );
		if ( json === lastSaved ) {
			return;
		}
		lastSaved = json;
		data.savedAt = Date.now();
		write( data );
	}

	function setEditor( ed, html ) {
		if ( ed.initialized ) {
			ed.setContent( html );
		} else {
			ed.on( 'init', function () {
				ed.setContent( html );
			} );
		}
	}

	function restore( data ) {
		restoring = true;
		if ( list && data.trips ) {
			list.innerHTML = data.trips;
		}
		fields().forEach( function ( f ) {
			if ( ! Object.prototype.hasOwnProperty.call( data.fields, f.key ) ) {
				return;
			}
			if ( 'checkbox' === f.el.type || 'radio' === f.el.type ) {
				f.el.checked = !! data.fields[ f.key ];
			} else {
				f.el.value = data.fields[ f.key ];
			}
			f.el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
		Object.keys( data.editors || {} ).forEach( function ( id ) {
			var area = document.getElementById( id );
			if ( area ) {
				area.value = data.editors[ id ];
			}
			var ed = window.tinymce && window.tinymce.get( id );
			if ( ed ) {
				setEditor( ed, data.editors[ id ] );
			} else if ( window.tinymce ) {
				window.tinymce.on( 'AddEditor', function ( e ) {
					if ( e.editor.id === id ) {
						setEditor( e.editor, data.editors[ id ] );
					}
				} );
			}
		} );
		var title = document.getElementById( 'title' );
		if ( title && title.value ) {
			var prompt = document.getElementById( 'title-prompt-text' );
			if ( prompt ) {
				prompt.classList.add( 'screen-reader-text' );
			}
		}
		restoring = false;
	}

	function notice( savedAt, failed ) {
		var box = document.createElement( 'div' );
		box.className = 'notice ' + ( failed ? 'notice-error' : 'notice-info' ) + ' cvkp-restored';
		var time = new Date( savedAt ).toLocaleString( 'sv-SE', { dateStyle: 'short', timeStyle: 'short' } );
		box.innerHTML = failed
			? '<p><strong>Utskicket sparades inte – det ni skrev är återställt från webbläsaren.</strong> Klicka på Spara igen. Säger det samma sak, kopiera texterna och hör av er till Pär. <button type="button" class="button-link cvkp-discard">Släng ändringarna</button></p>'
			: '<p><strong>Osparade ändringar från ' + time + ' är återställda.</strong> De har sparats i den här webbläsaren. Klicka på Spara för att spara dem i utskicket. <button type="button" class="button-link cvkp-discard">Släng ändringarna</button></p>';
		var anchor = document.querySelector( '.wp-header-end' );
		( anchor ? anchor.parentNode : form.parentNode ).insertBefore( box, anchor ? anchor.nextSibling : form );
		box.querySelector( '.cvkp-discard' ).addEventListener( 'click', function () {
			if ( window.confirm( 'Slänga de osparade ändringarna och visa utskicket som det är sparat?' ) ) {
				remove();
				window.removeEventListener( 'beforeunload', save );
				window.location.reload();
			}
		} );
	}

	/** Fält som alltid sparas som de skrevs – finns de inte i det sparade utskicket gick sparningen fel. */
	var CHECK = [ '#title', 'cvkp[subject]|1', 'cvkp[preheader]|1', 'cvkp[heading]|1', 'cvkp[fb_text]|1', 'cvkp[ig_text]|1' ];
	function lostOnSave( data ) {
		var now = snapshot();
		var lost = CHECK.some( function ( k ) {
			var before = String( data.fields[ k ] || '' ).trim();
			return '' !== before && '' === String( now.fields[ k ] || '' ).trim();
		} );
		var tripsBefore = ( data.trips.match( /class="cvkp-trip"/g ) || [] ).length;
		var tripsNow = ( now.trips.match( /class="cvkp-trip"/g ) || [] ).length;
		return lost || tripsNow < tripsBefore;
	}

	// Återställ om det finns en kopia som är nyare än det sparade utskicket och skiljer sig från det.
	var draft = read();
	if ( ! cfg.isNew ) {
		// Ett nytt utskick som just sparats för första gången har sin kopia under "nytt".
		var fresh = read( newKey );
		if ( fresh && fresh.submitted && fresh.postId === cfg.postId ) {
			draft = fresh;
			var s0 = storage();
			if ( s0 ) {
				try {
					s0.removeItem( newKey );
				} catch ( e ) {}
			}
		}
	} else if ( draft && draft.submitted ) {
		// Gammal kopia från ett nytt utskick som redan har sparats och kontrollerats.
		remove();
		draft = null;
	}
	if ( draft && draft.submitted && Date.now() - draft.savedAt < MAX_AGE ) {
		// Sidan har just sparats. Ta bort kopian om allt kom med, annars återställ.
		if ( lostOnSave( draft ) ) {
			delete draft.submitted;
			restore( draft );
			notice( draft.savedAt, true );
			dirty = true;
			write( draft );
			lastSaved = JSON.stringify( [ draft.fields, draft.editors, draft.trips ] );
		} else {
			remove();
		}
	} else if ( draft && draft.savedAt && Date.now() - draft.savedAt < MAX_AGE && ( cfg.isNew || draft.savedAt > cfg.modified * 1000 ) ) {
		restore( draft );
		notice( draft.savedAt );
		dirty = true; // Det återställda är fortfarande osparat.
		lastSaved = JSON.stringify( [ draft.fields, draft.editors, draft.trips ] );
	} else if ( draft ) {
		remove(); // Utskicket är sparat efter kopian – kopian behövs inte.
	}

	// Spara medan man skriver, när reselistan ändras och med jämna mellanrum
	// (fångar också det som Claude-rutan och TinyMCE fyller i).
	var timer = null;
	function changed() {
		if ( restoring ) {
			return;
		}
		dirty = true;
		clearTimeout( timer );
		timer = setTimeout( save, 400 );
	}
	form.addEventListener( 'input', changed );
	form.addEventListener( 'change', changed );
	document.addEventListener( 'cvkp:changed', changed ); // Claude-rutan och reselistan.
	if ( list ) {
		new MutationObserver( changed ).observe( list, { childList: true } );
	}
	// Bara det man själv skriver i editorerna (inte när de laddas).
	function watch( ed ) {
		if ( ed.id && 0 === ed.id.indexOf( 'cvkp_' ) ) {
			ed.on( 'input keyup change undo redo', changed );
		}
	}
	if ( window.tinymce ) {
		window.tinymce.on( 'AddEditor', function ( e ) {
			watch( e.editor );
		} );
		editors().forEach( watch );
	}
	var interval = setInterval( save, 3000 );
	window.addEventListener( 'beforeunload', save );

	// Vid sparning behålls kopian, märkt som skickad. När sidan laddats om kontrolleras att
	// allt kom med (se ovan). Förhandsgranskningen (ny flik) räknas inte.
	form.addEventListener( 'submit', function ( event ) {
		var by = event.submitter;
		if ( by && '_blank' === by.getAttribute( 'formtarget' ) ) {
			return;
		}
		save();
		var data = read();
		if ( data ) {
			data.postId = cfg.postId; // En återställd kopia kan ha ett äldre id.
			data.submitted = true;
			data.savedAt = Date.now();
			write( data );
		}
		window.removeEventListener( 'beforeunload', save );
		clearInterval( interval );
		clearTimeout( timer );
		restoring = true; // Stoppa fler sparningar under tiden sidan skickas.
	} );
}() );
