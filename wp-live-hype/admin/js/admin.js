/*!
 * WP Live Hype — admin interface.
 * Developed by ALWAYS FINAL. License: GPL-2.0-or-later.
 */
/* global jQuery */
( function ( $ ) {
	'use strict';

	var data = window.wplhAdmin || {};
	var base = window.wplhConfig || {};
	var i18n = data.i18n || {};
	var PREFIX = 'wplh_settings[';

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
		document.querySelectorAll( '.wplh-preset' ).forEach( function ( wrap ) {
			var select = wrap.querySelector( '.wplh-preset__select' );
			var custom = wrap.querySelector( '.wplh-preset__custom' );
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
		document.querySelectorAll( '.wplh-range input[type="range"]' ).forEach( function ( range ) {
			var output = range.parentNode.querySelector( 'output' );
			range.addEventListener( 'input', function () {
				if ( output ) {
					output.textContent = range.value + ( output.getAttribute( 'data-suffix' ) || '' );
				}
			} );
		} );
		document.querySelectorAll( '.wplh-color input[type="color"]' ).forEach( function ( input ) {
			var code = input.parentNode.querySelector( 'code' );
			input.addEventListener( 'input', function () {
				if ( code ) {
					code.textContent = input.value;
				}
			} );
		} );
		var reset = document.querySelector( '.wplh-reset-analytics' );
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
		var warning = textarea.parentNode.querySelector( '.wplh-template-warning' );
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
		document.querySelectorAll( '.wplh-token' ).forEach( function ( button ) {
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
		document.querySelectorAll( '.wplh-template' ).forEach( function ( textarea ) {
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
		if ( ! window.WPLH || ! window.WPLH.renderStatic ) {
			return;
		}
		var stage = panel.querySelector( '.wplh-preview__stage' );
		var slot = panel.querySelector( '.wplh-preview__slot' );
		var empty = panel.querySelector( '.wplh-preview__empty' );
		var device = panel.getAttribute( 'data-device' ) || 'desktop';
		var type = panel.getAttribute( 'data-type' ) || 'purchase';
		var display = displaySettings( panel.getAttribute( 'data-live' ) === '1' );
		var item = previewItem( type );

		stage.setAttribute( 'data-device', device );
		empty.hidden = true;

		if ( device === 'mobile' && display.mobile === 'hidden' ) {
			window.WPLH.renderStatic( slot, null, display, base, {} );
			empty.textContent = i18n.previewHidden || '';
			empty.hidden = false;
			return;
		}
		if ( ! item ) {
			window.WPLH.renderStatic( slot, null, display, base, {} );
			empty.textContent = i18n.previewMissing || '';
			empty.hidden = false;
			return;
		}

		var root = window.WPLH.renderStatic( slot, item, display, base, { mobile: device === 'mobile' } );
		var toast = root ? root.querySelector( '.wplh-toast' ) : null;
		if ( toast ) {
			// Replay the entrance animation.
			toast.classList.remove( 'wplh-in' );
			void toast.offsetWidth;
			window.requestAnimationFrame( function () {
				toast.classList.add( 'wplh-in' );
			} );
		}
	}

	function initPreviews() {
		var panels = document.querySelectorAll( '.wplh-preview' );
		panels.forEach( function ( panel ) {
			panel.setAttribute( 'data-device', 'desktop' );
			if ( ! panel.getAttribute( 'data-type' ) ) {
				panel.setAttribute( 'data-type', 'featured' );
			}
			panel.querySelectorAll( '.wplh-seg' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var attr = button.hasAttribute( 'data-device' ) ? 'data-device' : 'data-type';
					panel.setAttribute( attr, button.getAttribute( attr ) );
					button.parentNode.querySelectorAll( '.wplh-seg' ).forEach( function ( sibling ) {
						var active = sibling === button;
						sibling.classList.toggle( 'is-active', active );
						sibling.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					} );
					renderPanel( panel );
				} );
			} );
			// Previews are illustrations: never navigate away from settings.
			panel.addEventListener( 'click', function ( event ) {
				if ( event.target.closest && event.target.closest( '.wplh-root' ) ) {
					event.preventDefault();
				}
			} );
			renderPanel( panel );
		} );

		var liveForm = document.querySelector( '.wplh-tab-display .wplh-form' );
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
		var out = document.getElementById( 'wplh-eligible-value' );
		if ( ! out ) {
			return;
		}
		var countries = data.countries || {};
		if ( fieldValue( 'country_filter' ) ) {
			var code = fieldValue( 'target_country' );
			var template = data.mode === 'synthetic' ? '%s' : i18n.eligibleOnly || '%s';
			out.textContent = code && countries[ code ] ? template.replace( '%s', countries[ code ] ) : i18n.eligibleNone;
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
		var wrap = document.getElementById( 'wplh-country-preview' );
		if ( ! wrap ) {
			return;
		}
		var select = $( '#wplh-preview-country' );
		var text = document.getElementById( 'wplh-country-preview-text' );
		var slot = document.getElementById( 'wplh-country-preview-toast' );

		var load = function () {
			wrap.classList.add( 'is-loading' );
			$.post( data.ajaxUrl, {
				action: 'wplh_preview',
				nonce: data.nonce,
				country: select.val() || '',
				level: fieldValue( 'location_level' ) || '',
			} )
				.done( function ( response ) {
					var preferred = response && response.success && response.data.mode === 'synthetic' ? 'location' : 'purchase';
					if ( ! response || ! response.success || ! response.data.items || ! response.data.items[ preferred ] ) {
						text.textContent = i18n.previewFailed;
						return;
					}
					var item = response.data.items[ preferred ];
					text.textContent = '“' + item.message.replace( /\{time_ago\}/g, item.time_ago || '' ) + '”';
					if ( window.WPLH && window.WPLH.renderStatic ) {
						var root = window.WPLH.renderStatic( slot, $.extend( {}, item, { preview: true } ), displaySettings( false ), base, {} );
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
	 * Weighting: live share bars
	 * ---------------------------------------------------------------- */

	function refreshShares( table ) {
		var rows = table.querySelectorAll( 'tbody tr' );
		var weights = [];
		var total = 0;
		rows.forEach( function ( row ) {
			var checked = row.querySelector( 'input[type="radio"]:checked' );
			var bar = row.querySelector( '[data-share-bar]' );
			var weight = checked && bar && ! bar.hasAttribute( 'data-excluded' ) ? parseInt( checked.getAttribute( 'data-mult' ), 10 ) || 0 : 0;
			weights.push( weight );
			total += weight;
		} );
		rows.forEach( function ( row, index ) {
			var fill = row.querySelector( '[data-share-bar] span' );
			var pct = row.querySelector( '[data-share-pct]' );
			var share = total > 0 ? ( weights[ index ] / total ) * 100 : 0;
			if ( fill ) {
				fill.style.width = share.toFixed( 1 ) + '%';
			}
			if ( pct ) {
				pct.textContent = share > 0 ? ( share < 1 ? '<1' : Math.round( share ) ) + '%' : '—';
			}
		} );
	}

	function initWeights() {
		document.querySelectorAll( '[data-weight-table]' ).forEach( function ( table ) {
			table.addEventListener( 'change', function () {
				refreshShares( table );
			} );
			refreshShares( table );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Sound previews
	 * ---------------------------------------------------------------- */

	function initSounds() {
		var player = null;
		document.querySelectorAll( '.wplh-play' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var url = ( data.sounds || {} )[ button.getAttribute( 'data-sound' ) ];
				if ( ! url ) {
					return;
				}
				var volume = fieldValue( 'sound_volume' );
				if ( player ) {
					player.pause();
				}
				player = new window.Audio( url );
				player.volume = Math.max( 0, Math.min( 1, ( parseInt( volume, 10 ) || 0 ) / 100 ) );
				var playing = player.play();
				if ( playing && playing.catch ) {
					playing.catch( function () {} );
				}
			} );
		} );
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
		initWeights();
		initSounds();

		// Native and select2/selectWoo (jQuery) change events.
		$( document ).on( 'change input', '.wplh-form :input', function () {
			refreshConditions();
			updateEligible();
		} );
	} );
} )( jQuery );
