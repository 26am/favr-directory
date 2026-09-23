/**
 * Favr Directory — front-end enhancements. Everything here is progressive: the directory
 * works fully without JavaScript (plain GET forms and links).
 */
( function () {
	'use strict';

	var cfg = window.favrDirectoryPublic || { i18n: {} };
	var i18n = cfg.i18n || {};
	var DAYS = [ 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' ];
	var LAYOUT_KEY = 'favrDirectoryLayout';

	/* ------------------------------------------------------ Time & hours */

	/**
	 * Current weekday + minutes in the SITE timezone (pages may be cached, so the server's
	 * "open now" can be stale; recompute in the browser).
	 */
	function siteNow( tz ) {
		var now = new Date();
		var offset = /^([+-])(\d{2}):(\d{2})$/.exec( tz || '' );
		if ( offset ) {
			var minutes = ( parseInt( offset[ 2 ], 10 ) * 60 + parseInt( offset[ 3 ], 10 ) ) * ( '-' === offset[ 1 ] ? -1 : 1 );
			var shifted = new Date( now.getTime() + minutes * 60000 );
			return { day: shifted.getUTCDay(), minutes: shifted.getUTCHours() * 60 + shifted.getUTCMinutes() };
		}
		try {
			var parts = new Intl.DateTimeFormat( 'en-US', { timeZone: tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' } ).formatToParts( now );
			var map = {};
			parts.forEach( function ( p ) {
				map[ p.type ] = p.value;
			} );
			return {
				day: DAYS.indexOf( map.weekday.toLowerCase().slice( 0, 3 ) ),
				minutes: ( parseInt( map.hour, 10 ) % 24 ) * 60 + parseInt( map.minute, 10 )
			};
		} catch ( e ) {
			return { day: now.getDay(), minutes: now.getHours() * 60 + now.getMinutes() };
		}
	}

	function toMinutes( time ) {
		var p = String( time || '' ).split( ':' );
		return parseInt( p[ 0 ], 10 ) * 60 + parseInt( p[ 1 ] || 0, 10 );
	}

	/** Mirror of FavrDirectory\Support\Hours::isOpenAt(). */
	function isOpen( hours, now ) {
		var today = hours[ DAYS[ now.day ] ];
		var prev = hours[ DAYS[ ( now.day + 6 ) % 7 ] ];
		if ( today ) {
			if ( '24h' === today.status ) {
				return true;
			}
			if ( 'open' === today.status ) {
				var o = toMinutes( today.open );
				var c = toMinutes( today.close );
				if ( c > o ? ( now.minutes >= o && now.minutes < c ) : now.minutes >= o ) {
					return true;
				}
			}
		}
		if ( prev && 'open' === prev.status ) {
			var po = toMinutes( prev.open );
			var pc = toMinutes( prev.close );
			if ( pc <= po && now.minutes < pc ) {
				return true;
			}
		}
		return false;
	}

	function refreshStatuses( root ) {
		root.querySelectorAll( '[data-favr-hours]' ).forEach( function ( el ) {
			var hours;
			try {
				hours = JSON.parse( el.getAttribute( 'data-favr-hours' ) );
			} catch ( e ) {
				return;
			}
			if ( ! hours || Array.isArray( hours ) ) {
				return;
			}
			var holder = el.closest( '[data-favr-tz]' );
			var open = isOpen( hours, siteNow( holder ? holder.getAttribute( 'data-favr-tz' ) : '' ) );
			var label = open ? el.getAttribute( 'data-open' ) : el.getAttribute( 'data-closed' );
			el.classList.toggle( 'is-open', open );
			el.classList.toggle( 'is-closed', ! open );
			if ( label ) {
				el.textContent = label;
			}
		} );

		root.querySelectorAll( '.favr-hours__table' ).forEach( function ( table ) {
			var holder = table.closest( '[data-favr-tz]' );
			var day = DAYS[ siteNow( holder ? holder.getAttribute( 'data-favr-tz' ) : '' ).day ];
			table.querySelectorAll( 'tr[data-day]' ).forEach( function ( row ) {
				row.classList.toggle( 'is-today', row.getAttribute( 'data-day' ) === day );
			} );
		} );
	}

	/* ------------------------------------------------------- Directory */

	function applyLayout( dir, layout ) {
		if ( 'grid' !== layout && 'list' !== layout ) {
			return;
		}
		dir.classList.remove( 'favr-dir--grid', 'favr-dir--list' );
		dir.classList.add( 'favr-dir--' + layout );
		dir.querySelectorAll( '[data-favr-layout]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-pressed', btn.getAttribute( 'data-favr-layout' ) === layout ? 'true' : 'false' );
		} );
	}

	function directoryIndex( dir ) {
		return Array.prototype.indexOf.call( document.querySelectorAll( '[data-favr-dir]' ), dir );
	}

	// One in-flight request per directory, so several directories can reload at once (popstate).
	var controllers = new WeakMap();

	function load( dir, url, push ) {
		var index = directoryIndex( dir );
		var results = dir.querySelector( '[data-favr-results]' );
		if ( results ) {
			results.setAttribute( 'aria-busy', 'true' );
		}
		var previous = controllers.get( dir );
		if ( previous ) {
			previous.abort();
		}
		var controller = window.AbortController ? new AbortController() : null;
		if ( controller ) {
			controllers.set( dir, controller );
		}

		return fetch( url, { credentials: 'same-origin', signal: controller ? controller.signal : undefined, headers: { 'X-Requested-With': 'favr-directory' } } )
			.then( function ( res ) {
				if ( ! res.ok ) {
					throw new Error( 'HTTP ' + res.status );
				}
				return res.text();
			} )
			.then( function ( html ) {
				var doc = new DOMParser().parseFromString( html, 'text/html' );
				var fresh = doc.querySelectorAll( '[data-favr-dir]' )[ index ];
				if ( ! fresh ) {
					window.location.href = url;
					return;
				}
				// Keep the visitor's typing in the search box untouched.
				var active = document.activeElement;
				var keepSearch = active && active.hasAttribute( 'data-favr-search' ) && dir.contains( active );
				var freshResults = fresh.querySelector( '[data-favr-results]' );
				var freshLetters = fresh.querySelector( '.favr-letters' );
				var letters = dir.querySelector( '.favr-letters' );

				if ( results && freshResults ) {
					results.replaceWith( freshResults );
				}
				if ( letters && freshLetters ) {
					letters.replaceWith( freshLetters );
				}
				if ( ! keepSearch ) {
					var form = dir.querySelector( '[data-favr-form]' );
					var freshForm = fresh.querySelector( '[data-favr-form]' );
					if ( form && freshForm ) {
						form.replaceWith( freshForm );
					}
				}
				applyLayout( dir, preferredLayout( dir ) );
				refreshStatuses( dir );
				if ( push ) {
					window.history.pushState( { favrDirectory: true }, '', url );
				}
			} )
			.catch( function ( err ) {
				if ( err && 'AbortError' === err.name ) {
					return;
				}
				window.location.href = url;
			} )
			.finally( function () {
				var r = dir.querySelector( '[data-favr-results]' );
				if ( r ) {
					r.setAttribute( 'aria-busy', 'false' );
				}
			} );
	}

	function formUrl( form ) {
		var url = new URL( form.getAttribute( 'action' ) || window.location.href, window.location.href );
		new FormData( form ).forEach( function ( value, key ) {
			if ( '' !== String( value ).trim() ) {
				url.searchParams.set( key, value );
			} else {
				url.searchParams.delete( key );
			}
		} );
		return url.toString();
	}

	function storedLayout() {
		try {
			return window.localStorage.getItem( LAYOUT_KEY );
		} catch ( e ) {
			return null;
		}
	}

	/** The visitor's saved choice applies only to directories that offer the toggle. */
	function preferredLayout( dir ) {
		var saved = dir.querySelector( '[data-favr-layout]' ) ? storedLayout() : null;
		return saved || dir.getAttribute( 'data-layout' );
	}

	function initDirectory( dir ) {
		applyLayout( dir, preferredLayout( dir ) );

		dir.addEventListener( 'click', function ( e ) {
			var toggle = e.target.closest( '[data-favr-layout]' );
			if ( toggle ) {
				var layout = toggle.getAttribute( 'data-favr-layout' );
				applyLayout( dir, layout );
				try {
					window.localStorage.setItem( LAYOUT_KEY, layout );
				} catch ( err ) {}
				return;
			}

			var link = e.target.closest( 'a[data-favr-link], .favr-dir__pagination a' );
			if ( link && ! e.metaKey && ! e.ctrlKey && ! e.shiftKey && 0 === e.button ) {
				e.preventDefault();
				load( dir, link.href, true ).then( function () {
					if ( link.closest( '.favr-dir__pagination' ) ) {
						dir.scrollIntoView( { behavior: 'smooth', block: 'start' } );
					}
				} );
			}
		} );

		dir.addEventListener( 'submit', function ( e ) {
			var form = e.target.closest( '[data-favr-form]' );
			if ( form ) {
				e.preventDefault();
				load( dir, formUrl( form ), true );
			}
		} );

		dir.addEventListener( 'change', function ( e ) {
			if ( e.target.matches( '[data-favr-autosubmit]' ) ) {
				load( dir, formUrl( e.target.form ), true );
			}
		} );

		var timer;
		dir.addEventListener( 'input', function ( e ) {
			if ( ! e.target.matches( '[data-favr-search]' ) ) {
				return;
			}
			clearTimeout( timer );
			var form = e.target.form;
			timer = setTimeout( function () {
				var value = e.target.value.trim();
				if ( 0 === value.length || value.length >= 2 ) {
					load( dir, formUrl( form ), true );
				}
			}, 350 );
		} );
	}

	window.addEventListener( 'popstate', function () {
		document.querySelectorAll( '[data-favr-dir]' ).forEach( function ( dir ) {
			load( dir, window.location.href, false );
		} );
	} );

	/* --------------------------------------------------------- Lightbox */

	function initLightbox( gallery ) {
		var items = Array.prototype.slice.call( gallery.querySelectorAll( '[data-favr-lightbox]' ) );
		if ( ! items.length || ! window.HTMLDialogElement ) {
			return;
		}
		var dialog = document.createElement( 'dialog' );
		dialog.className = 'favr-lightbox';
		dialog.setAttribute( 'aria-label', i18n.photos || 'Photos' );
		dialog.innerHTML =
			'<figure class="favr-lightbox__figure"><img alt=""><figcaption></figcaption></figure>' +
			'<button type="button" class="favr-lightbox__btn favr-lightbox__close" aria-label="' + ( i18n.close || 'Close' ) + '"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>' +
			( items.length > 1
				? '<button type="button" class="favr-lightbox__btn favr-lightbox__prev" aria-label="' + ( i18n.prev || 'Previous' ) + '"><svg class="favr-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg></button>' +
				  '<button type="button" class="favr-lightbox__btn favr-lightbox__next" aria-label="' + ( i18n.next || 'Next' ) + '"><svg class="favr-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg></button>'
				: '' );
		document.body.appendChild( dialog );

		var img = dialog.querySelector( 'img' );
		var caption = dialog.querySelector( 'figcaption' );
		var current = 0;

		function show( index ) {
			current = ( index + items.length ) % items.length;
			var item = items[ current ];
			var thumb = item.querySelector( 'img' );
			img.src = item.getAttribute( 'href' );
			img.alt = thumb ? thumb.alt : '';
			caption.textContent = item.getAttribute( 'data-caption' ) || '';
		}

		items.forEach( function ( item, index ) {
			item.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				show( index );
				dialog.showModal();
			} );
		} );

		dialog.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.favr-lightbox__close' ) || e.target === dialog || e.target.classList.contains( 'favr-lightbox__figure' ) ) {
				dialog.close();
			} else if ( e.target.closest( '.favr-lightbox__prev' ) ) {
				show( current - 1 );
			} else if ( e.target.closest( '.favr-lightbox__next' ) ) {
				show( current + 1 );
			}
		} );

		dialog.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowLeft' === e.key ) {
				show( current - 1 );
			} else if ( 'ArrowRight' === e.key ) {
				show( current + 1 );
			}
		} );

		// Swipe on touch screens.
		var startX = null;
		dialog.addEventListener( 'touchstart', function ( e ) {
			startX = e.touches[ 0 ].clientX;
		}, { passive: true } );
		dialog.addEventListener( 'touchend', function ( e ) {
			if ( null === startX ) {
				return;
			}
			var dx = e.changedTouches[ 0 ].clientX - startX;
			if ( Math.abs( dx ) > 50 ) {
				show( current + ( dx < 0 ? 1 : -1 ) );
			}
			startX = null;
		} );
	}

	/* ----------------------------------------------------- Copy promo code */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-favr-copy]' );
		if ( ! btn || ! navigator.clipboard ) {
			return;
		}
		var code = btn.getAttribute( 'data-favr-copy' );
		navigator.clipboard.writeText( code ).then( function () {
			btn.classList.add( 'is-copied' );
			btn.textContent = i18n.copied || 'Copied!';
			setTimeout( function () {
				btn.classList.remove( 'is-copied' );
				btn.textContent = code;
			}, 1500 );
		} );
	} );

	/* ------------------------------------------------------------- Boot */

	function boot() {
		document.querySelectorAll( '[data-favr-dir]' ).forEach( initDirectory );
		document.querySelectorAll( '[data-favr-gallery]' ).forEach( initLightbox );
		refreshStatuses( document );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
