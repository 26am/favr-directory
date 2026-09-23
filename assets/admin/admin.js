/* Favr Directory — admin extras. The field UI itself (tabs, media, hours, repeaters, conditions)
   comes from favr-core (assets/core/fields.js); this adds the listing health meter, "go to field"
   shortcuts, the featured star on the list screen and colour pickers. */
( function ( $ ) {
	'use strict';

	var cfg = window.favrDirectory || {};
	var i18n = cfg.i18n || {};

	/* ------------------------------------------------------ Go to field */

	function goTo( field, tab ) {
		var panel = document.getElementById( 'favr-panel' );
		var coreTargets = {
			title: '#title',
			description: '#content',
			category: '#favr_business_catdiv',
			cover: '#postimagediv'
		};
		if ( coreTargets[ field ] ) {
			var $target = $( coreTargets[ field ] );
			if ( $target.length ) {
				$( 'html, body' ).animate( { scrollTop: $target.offset().top - 60 }, 200 );
				$target.find( 'input, textarea, a' ).addBack( 'input, textarea' ).first().trigger( 'focus' );
			}
			return;
		}
		if ( panel && window.favrCore ) {
			window.favrCore.activateTab( panel, tab );
		}
		var $field = $( '.favr-field[data-field="' + field + '"]' );
		if ( panel ) {
			$( 'html, body' ).animate( { scrollTop: $( panel ).offset().top - 40 }, 200 );
		}
		$field.addClass( 'is-highlighted' );
		setTimeout( function () {
			$field.removeClass( 'is-highlighted' );
		}, 1600 );
		$field.find( 'input:not([type=hidden]), textarea, select, button' ).first().trigger( 'focus' );
	}

	/* ------------------------------------------------------ Health meter */

	function valueFilled( fieldId ) {
		var $wrap = $( '.favr-field[data-field="' + fieldId + '"]' );
		if ( ! $wrap.length ) {
			return false;
		}
		if ( $wrap.hasClass( 'favr-field--hours' ) ) {
			return ! $wrap.find( '.favr-hours' ).hasClass( 'is-empty' );
		}
		if ( $wrap.hasClass( 'favr-field--checkboxes' ) ) {
			return $wrap.find( 'input:checked' ).length > 0;
		}
		if ( $wrap.hasClass( 'favr-field--toggle' ) ) {
			return $wrap.find( 'input[type=checkbox]' ).is( ':checked' );
		}
		var filled = false;
		$wrap.find( 'input:not([type=checkbox]), textarea, select' ).each( function () {
			if ( $.trim( $( this ).val() || '' ) !== '' ) {
				filled = true;
			}
		} );
		return filled;
	}

	function descriptionFilled() {
		var editor = window.tinymce && window.tinymce.get( 'content' );
		// Until TinyMCE has initialized, the textarea is the source of truth.
		var ready = editor && editor.initialized && ! editor.isHidden();
		var text = ready ? editor.getContent( { format: 'text' } ) : $( '#content' ).val();
		return $.trim( text || '' ) !== '';
	}

	function updateHealth() {
		var $box = $( '.favr-health' );
		if ( ! $box.length || ! cfg.weights ) {
			return;
		}
		var total = 0;
		var earned = 0;
		$.each( cfg.weights, function ( key, weight ) {
			var filled;
			switch ( key ) {
				case 'title':
					filled = $.trim( $( '#title' ).val() ) !== '';
					break;
				case 'description':
					filled = descriptionFilled();
					break;
				case 'category':
					filled = $( '#favr_business_catchecklist input:checked' ).length > 0;
					break;
				case 'cover':
					filled = parseInt( $( '#_thumbnail_id' ).val(), 10 ) > 0;
					break;
				default:
					filled = valueFilled( key );
			}
			total += weight;
			earned += filled ? weight : 0;
		} );
		var score = total ? Math.round( ( earned / total ) * 100 ) : 0;
		$box.attr( { 'data-score': score, 'data-tone': score >= 90 ? 'good' : ( score >= 60 ? 'ok' : 'low' ) } );
		$box.find( '.favr-health__ring' ).css( '--favr-score', score );
		$box.find( '.favr-health__value' ).text( score + '%' );
		$box.find( '.favr-health__headline' ).text( score >= 90 ? i18n.great : ( score >= 60 ? i18n.almost : i18n.needsMore ) );

		// Tabs with content get a dot.
		$( '.favr-pane' ).each( function () {
			var id = this.id.replace( 'favr-pane-', '' );
			var has = false;
			$( this ).find( '.favr-field[data-field]' ).each( function () {
				var fieldId = $( this ).data( 'field' );
				if ( ! $( this ).hasClass( 'favr-field--toggle' ) && valueFilled( fieldId ) ) {
					has = true;
					return false;
				}
			} );
			$( '.favr-tab[data-tab="' + id + '"]' ).toggleClass( 'has-content', has );
		} );
	}

	/* ---------------------------------------------------- List: featured */

	function initFeaturedStars() {
		$( document ).on( 'click', '.favr-star', function () {
			var $star = $( this );
			$star.prop( 'disabled', true );
			$.post( cfg.ajaxUrl, { action: 'favr_toggle_featured', nonce: cfg.nonce, id: $star.data( 'id' ) } )
				.done( function ( res ) {
					if ( res && res.success ) {
						var on = !! res.data.featured;
						$star.toggleClass( 'is-on', on ).attr( 'aria-pressed', on ? 'true' : 'false' );
						$star.find( '.dashicons' ).toggleClass( 'dashicons-star-filled', on ).toggleClass( 'dashicons-star-empty', ! on );
					}
				} )
				.always( function () {
					$star.prop( 'disabled', false );
				} );
		} );
	}

	/* ------------------------------------------------------------- Boot */

	$( function () {
		initFeaturedStars();

		$( document ).on( 'click', '.favr-goto', function () {
			goTo( $( this ).data( 'field' ), $( this ).data( 'tab' ) );
		} );

		if ( $.fn.wpColorPicker ) {
			$( '.favr-color' ).wpColorPicker();
		}

		if ( $( '.favr-health' ).length ) {
			updateHealth();
			var debounce;
			$( '#post' ).on( 'input change', function () {
				clearTimeout( debounce );
				debounce = setTimeout( updateHealth, 250 );
			} );
			$( document ).on( 'tinymce-editor-init', function ( event, editor ) {
				if ( editor && 'content' === editor.id ) {
					updateHealth();
					editor.on( 'input change keyup', function () {
						clearTimeout( debounce );
						debounce = setTimeout( updateHealth, 400 );
					} );
				}
			} );
			// The featured-image box updates #_thumbnail_id via AJAX.
			$( document ).on( 'ajaxComplete', function () {
				clearTimeout( debounce );
				debounce = setTimeout( updateHealth, 300 );
			} );
		}
	} );
} )( jQuery );
