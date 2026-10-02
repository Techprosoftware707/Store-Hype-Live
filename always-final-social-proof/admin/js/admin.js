/*!
 * ALWAYS FINAL Social Proof — admin interface.
 * Developed by ALWAYS FINAL. License: GPL-2.0-or-later.
 */
/* global jQuery, afspAdmin */
( function ( $ ) {
	'use strict';

	var data = window.afspAdmin || {};
	var base = window.afspConfig || {};
	var i18n = data.i18n || {};
	var PREFIX = 'afsp_settings[';

	function field( key ) {
		return document.querySelector( '[name="' + PREFIX + key + ']"]' );
	}

	function fieldValue( key ) {
		var radios = document.querySelectorAll( 'input[type="radio"][name="' + PREFIX + key + ']"]' );
		if ( radios.length ) {
			for ( var i = 0; i < radios.length; i++ ) {
				if ( radios[ i ].checked ) {
					return radios[ i ].value;
				}
			}
			return '';
		}
		var el = field( key );
		if ( ! el ) {
			return null;
		}
		if ( el.type === 'checkbox' ) {
			return el.checked;
		}
		return el.value;
	}

	/* ---------------------------------------------------------------- *
	 * Conditional sections
	 * ---------------------------------------------------------------- */

	function refreshConditions() {
		document.querySelectorAll( '[data-show-when]' ).forEach( function ( node ) {
			var value = fieldValue( node.getAttribute( 'data-show-when' ) );
			if ( value !== null ) {
				node.hidden = ! value;
			}
		} );
		document.querySelectorAll( '[data-hide-when]' ).forEach( function ( node ) {
			var value = fieldValue( node.getAttribute( 'data-hide-when' ) );
			if ( value !== null ) {
				node.hidden = !! value;
			}
		} );
		document.querySelectorAll( '[data-show-when-value]' ).forEach( function ( node ) {
			var parts = node.getAttribute( 'data-show-when-value' ).split( '=' );
			var value = fieldValue( parts[ 0 ] );
			if ( value !== null ) {
				node.hidden = String( value ) !== parts[ 1 ];
			}
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Preset selects (progressive enhancement over number inputs)
	 * ---------------------------------------------------------------- */

	function initPresets() {
		document.querySelectorAll( '.afsp-preset' ).forEach( function ( wrap ) {
			var select = wrap.querySelector( '.afsp-preset__select' );
			var custom = wrap.querySelector( '.afsp-preset__custom' );
			var input = custom ? custom.querySelector( 'input' ) : null;
			if ( ! select || ! input ) {
				return;
			}
			select.hidden = false;
			var sync = function () {
				var isCustom = select.value === 'custom';
				custom.hidden = ! isCustom;
				if ( ! isCustom ) {
					input.value = select.value;
				}
			};
			select.addEventListener( 'change', function () {
				sync();
				if ( select.value === 'custom' ) {
					input.focus();
				}
			} );
			sync();
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Small field enhancements
	 * ---------------------------------------------------------------- */

	function initInputs() {
		document.querySelectorAll( '.afsp-range input[type="range"]' ).forEach( function ( range ) {
			var output = range.parentNode.querySelector( 'output' );
			range.addEventListener( 'input', function () {
				if ( output ) {
					output.textContent = range.value + 'px';
				}
			} );
		} );
		document.querySelectorAll( '.afsp-color input[type="color"]' ).forEach( function ( input ) {
			var code = input.parentNode.querySelector( 'code' );
			input.addEventListener( 'input', function () {
				if ( code ) {
					code.textContent = input.value;
				}
			} );
		} );
		var reset = document.querySelector( '.afsp-reset-analytics' );
		if ( reset ) {
			reset.addEventListener( 'submit', function ( event ) {
				if ( ! window.confirm( i18n.confirmReset ) ) {
					event.preventDefault();
				}
			} );
		}
	}

	/* ---------------------------------------------------------------- *
	 * Template tokens
	 * ---------------------------------------------------------------- */

	function validateTemplate( textarea ) {
		var allowed = ( textarea.getAttribute( 'data-tokens' ) || '' ).split( ',' );
		var warning = textarea.parentNode.querySelector( '.afsp-template-warning' );
		if ( ! warning ) {
			return;
		}
		var problems = [];
		textarea.value.split( /\r?\n/ ).forEach( function ( line ) {
			if ( ! line.trim() ) {
				return;
			}
			if ( line.indexOf( '{product}' ) === -1 ) {
				problems.push( '"' + line.trim() + '" — {product}' );
			}
			var match;
			var re = /\{([a-z_]+)\}/g;
			while ( ( match = re.exec( line ) ) ) {
				if ( allowed.indexOf( match[ 1 ] ) === -1 ) {
					problems.push( '{' + match[ 1 ] + '}' );
				}
			}
		} );
		warning.hidden = ! problems.length;
		warning.textContent = problems.length ? '⚠ ' + problems.join( ' · ' ) : '';
	}

	function initTokens() {
		document.querySelectorAll( '.afsp-token' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var target = document.getElementById( button.getAttribute( 'data-target' ) );
				if ( ! target ) {
					return;
				}
				var token = button.getAttribute( 'data-token' );
				var start = target.selectionStart || 0;
				var end = target.selectionEnd || 0;
				target.value = target.value.slice( 0, start ) + token + target.value.slice( end );
				target.focus();
				target.selectionStart = target.selectionEnd = start + token.length;
				validateTemplate( target );
			} );
		} );
		document.querySelectorAll( '.afsp-template' ).forEach( function ( textarea ) {
			textarea.addEventListener( 'input', function () {
				validateTemplate( textarea );
			} );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Live previews
	 * ---------------------------------------------------------------- */

	var FIELD_MAP = {
		position_desktop: 'position',
		position_mobile: 'mobile',
		animation: 'animation',
		theme: 'theme',
		accent_color: 'accent',
		bg_color: 'bg',
		text_color: 'text',
		radius: 'radius',
		shadow: 'shadow',
		width: 'width',
		offset_x: 'offsetX',
		offset_y: 'offsetY',
		font: 'font',
		show_image: 'image',
		image_shape: 'shape',
		show_label: 'label',
		show_time: 'time',
		time_display: 'timeMode',
		show_verified: 'verified',
		show_close: 'close',
		show_sale_price: 'price',
	};

	function displaySettings( live ) {
		var display = $.extend( {}, base.display || {} );
		if ( live ) {
			Object.keys( FIELD_MAP ).forEach( function ( key ) {
				var value = fieldValue( key );
				if ( value !== null ) {
					display[ FIELD_MAP[ key ] ] = value;
				}
			} );
		}
		return display;
	}

	function previewItem( type ) {
		var items = data.preview || {};
		var item = items[ type ];
		return item ? $.extend( true, {}, item, { preview: true } ) : null;
	}

	function renderPanel( panel ) {
		if ( ! window.AFSP || ! window.AFSP.renderStatic ) {
			return;
		}
		var stage = panel.querySelector( '.afsp-preview__stage' );
		var slot = panel.querySelector( '.afsp-preview__slot' );
		var empty = panel.querySelector( '.afsp-preview__empty' );
		var device = panel.getAttribute( 'data-device' ) || 'desktop';
		var type = panel.getAttribute( 'data-type' ) || 'purchase';
		var display = displaySettings( panel.getAttribute( 'data-live' ) === '1' );
		var item = previewItem( type );

		stage.setAttribute( 'data-device', device );
		empty.hidden = true;

		if ( device === 'mobile' && display.mobile === 'hidden' ) {
			window.AFSP.renderStatic( slot, null, display, base, {} );
			empty.textContent = i18n.previewHidden || '';
			empty.hidden = false;
			return;
		}
		if ( ! item ) {
			window.AFSP.renderStatic( slot, null, display, base, {} );
			empty.textContent = i18n.previewMissing || '';
			empty.hidden = false;
			return;
		}

		var root = window.AFSP.renderStatic( slot, item, display, base, { mobile: device === 'mobile' } );
		var toast = root ? root.querySelector( '.afsp-toast' ) : null;
		if ( toast ) {
			// Replay the entrance animation.
			toast.classList.remove( 'afsp-in' );
			void toast.offsetWidth;
			window.requestAnimationFrame( function () {
				toast.classList.add( 'afsp-in' );
			} );
		}
	}

	function initPreviews() {
		var panels = document.querySelectorAll( '.afsp-preview' );
		panels.forEach( function ( panel ) {
			panel.setAttribute( 'data-device', 'desktop' );
			panel.setAttribute( 'data-type', 'purchase' );
			panel.querySelectorAll( '.afsp-seg' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var attr = button.hasAttribute( 'data-device' ) ? 'data-device' : 'data-type';
					panel.setAttribute( attr, button.getAttribute( attr ) );
					button.parentNode.querySelectorAll( '.afsp-seg' ).forEach( function ( sibling ) {
						var active = sibling === button;
						sibling.classList.toggle( 'is-active', active );
						sibling.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					} );
					renderPanel( panel );
				} );
			} );
			// Previews are illustrations: never navigate away from settings.
			panel.addEventListener( 'click', function ( event ) {
				if ( event.target.closest && event.target.closest( '.afsp-root' ) ) {
					event.preventDefault();
				}
			} );
			renderPanel( panel );
		} );

		var liveForm = document.querySelector( '.afsp-tab-display .afsp-form' );
		if ( liveForm ) {
			var timer = null;
			var rerender = function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( function () {
					panels.forEach( function ( panel ) {
						if ( panel.getAttribute( 'data-live' ) === '1' ) {
							renderPanel( panel );
						}
					} );
				}, 120 );
			};
			liveForm.addEventListener( 'input', rerender );
			liveForm.addEventListener( 'change', rerender );
		}
	}

	/* ---------------------------------------------------------------- *
	 * Country tab
	 * ---------------------------------------------------------------- */

	function updateEligible() {
		var out = document.getElementById( 'afsp-eligible-value' );
		if ( ! out ) {
			return;
		}
		var countries = data.countries || {};
		if ( fieldValue( 'country_filter' ) ) {
			var code = fieldValue( 'target_country' );
			out.textContent = code && countries[ code ] ? ( i18n.eligibleOnly || '%s' ).replace( '%s', countries[ code ] ) : i18n.eligibleNone;
			return;
		}
		var scope = fieldValue( 'unfiltered_scope' );
		if ( scope === 'all' ) {
			out.textContent = i18n.eligibleAll;
		} else {
			var label = document.querySelector( 'input[name="' + PREFIX + 'unfiltered_scope]"]:checked' );
			out.textContent = label ? label.parentNode.querySelector( 'strong' ).textContent : '';
		}
	}

	function initCountryPreview() {
		var wrap = document.getElementById( 'afsp-country-preview' );
		if ( ! wrap ) {
			return;
		}
		var select = $( '#afsp-preview-country' );
		var text = document.getElementById( 'afsp-country-preview-text' );
		var slot = document.getElementById( 'afsp-country-preview-toast' );

		var load = function () {
			wrap.classList.add( 'is-loading' );
			$.post( data.ajaxUrl, {
				action: 'afsp_preview',
				nonce: data.nonce,
				country: select.val() || '',
				level: fieldValue( 'location_level' ) || '',
			} )
				.done( function ( response ) {
					if ( ! response || ! response.success || ! response.data.items || ! response.data.items.purchase ) {
						text.textContent = i18n.previewFailed;
						return;
					}
					var item = response.data.items.purchase;
					text.textContent = '“' + item.message.replace( /\{time_ago\}/g, item.time_ago || '' ) + '”';
					if ( window.AFSP && window.AFSP.renderStatic ) {
						var root = window.AFSP.renderStatic( slot, $.extend( {}, item, { preview: true } ), displaySettings( false ), base, {} );
						if ( root ) {
							root.style.position = 'relative';
							root.style.left = root.style.right = root.style.top = root.style.bottom = 'auto';
						}
					}
				} )
				.fail( function () {
					text.textContent = i18n.previewFailed;
				} )
				.always( function () {
					wrap.classList.remove( 'is-loading' );
				} );
		};

		select.on( 'change', load );
		$( '[name="' + PREFIX + 'location_level]"]' ).on( 'change', load );
		load();
	}

	/* ---------------------------------------------------------------- *
	 * Boot
	 * ---------------------------------------------------------------- */

	$( function () {
		initPresets();
		initInputs();
		initTokens();
		refreshConditions();
		updateEligible();
		initPreviews();
		initCountryPreview();

		// Native and select2/selectWoo (jQuery) change events.
		$( document ).on( 'change input', '.afsp-form :input', function () {
			refreshConditions();
			updateEligible();
		} );
	} );
} )( jQuery );
