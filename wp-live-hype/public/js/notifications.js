/*!
 * WP Live Hype — frontend notifications.
 * Developed by ALWAYS FINAL. License: GPL-2.0-or-later.
 *
 * Vanilla JavaScript, no dependencies. All text is inserted with textContent /
 * text nodes (never innerHTML), URLs are restricted to http(s), and the
 * stylesheet is only requested right before the first notification is shown.
 */
( function ( window, document ) {
	'use strict';

	var WPLH = ( window.WPLH = window.WPLH || {} );
	if ( WPLH.loaded ) {
		return;
	}
	WPLH.loaded = true;

	var SVG_NS = 'http://www.w3.org/2000/svg';
	var TYPES = [ 'purchase', 'product_purchase', 'sale', 'popular', 'featured', 'explore', 'location', 'sale_promo' ];
	var SESSION_KEY = 'wplh_session_v1';
	var SESSION_SCHEMA = 1;
	var SESSION_IDLE_MS = 30 * 60 * 1000;
	var MOBILE_QUERY = '(max-width: 600px)';

	/* ------------------------------------------------------------------ *
	 * Utilities
	 * ------------------------------------------------------------------ */

	function now() {
		return new Date().getTime();
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		return node;
	}

	function safeUrl( value ) {
		if ( ! value || typeof value !== 'string' ) {
			return '';
		}
		try {
			var url = new window.URL( value, window.location.href );
			return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
		} catch ( e ) {
			return '';
		}
	}

	function safeColor( value, fallback ) {
		return typeof value === 'string' && /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( value ) ? value : fallback;
	}

	function toInt( value, fallback ) {
		var n = parseInt( value, 10 );
		return isNaN( n ) ? fallback : n;
	}

	function format( template, value ) {
		return String( template || '%s' ).replace( '%s', value );
	}

	/** Readable text colour (black/white) on top of a hex background. */
	function onColor( hex ) {
		var h = hex.replace( '#', '' );
		if ( h.length === 3 ) {
			h = h.charAt( 0 ) + h.charAt( 0 ) + h.charAt( 1 ) + h.charAt( 1 ) + h.charAt( 2 ) + h.charAt( 2 );
		}
		var rgb = [ 0, 2, 4 ].map( function ( i ) {
			var c = parseInt( h.substr( i, 2 ), 16 ) / 255;
			return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
		} );
		var lum = 0.2126 * rgb[ 0 ] + 0.7152 * rgb[ 1 ] + 0.0722 * rgb[ 2 ];
		// Pick whichever text colour actually has the higher WCAG contrast.
		var withWhite = 1.05 / ( lum + 0.05 );
		var withDark = ( lum + 0.05 ) / ( 0.0089 + 0.05 ); // #111827 luminance ≈ 0.0089.
		return withDark >= withWhite ? '#111827' : '#ffffff';
	}

	function storage( kind ) {
		var mem = {};
		var backend = null;
		try {
			backend = window[ kind ];
			var probe = '__wplh_probe';
			backend.setItem( probe, '1' );
			backend.removeItem( probe );
		} catch ( e ) {
			backend = null;
		}
		return {
			get: function ( key ) {
				try {
					var raw = backend ? backend.getItem( key ) : mem[ key ];
					return raw ? JSON.parse( raw ) : null;
				} catch ( e ) {
					return null;
				}
			},
			set: function ( key, value ) {
				var raw = JSON.stringify( value );
				try {
					if ( backend ) {
						backend.setItem( key, raw );
						return;
					}
				} catch ( e ) {}
				mem[ key ] = raw;
			},
		};
	}

	function svg( paths, className ) {
		var node = document.createElementNS( SVG_NS, 'svg' );
		node.setAttribute( 'viewBox', '0 0 24 24' );
		node.setAttribute( 'fill', 'none' );
		node.setAttribute( 'stroke', 'currentColor' );
		node.setAttribute( 'stroke-width', '2' );
		node.setAttribute( 'stroke-linecap', 'round' );
		node.setAttribute( 'stroke-linejoin', 'round' );
		node.setAttribute( 'aria-hidden', 'true' );
		node.setAttribute( 'focusable', 'false' );
		if ( className ) {
			node.setAttribute( 'class', className );
		}
		paths.forEach( function ( d ) {
			var path = document.createElementNS( SVG_NS, 'path' );
			path.setAttribute( 'd', d );
			node.appendChild( path );
		} );
		return node;
	}

	var ICONS = {
		close: [ 'M6 6l12 12', 'M18 6L6 18' ],
		check: [ 'M20 6L9 17l-5-5' ],
		purchase: [ 'M6 8h12l-1.2 12H7.2L6 8z', 'M9 8V7a3 3 0 0 1 6 0v1' ],
		sale: [ 'M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z', 'M7.5 7.5h.01' ],
		popular: [ 'M3 17l6-6 4 4 8-8', 'M14 7h7v7' ],
		featured: [ 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z' ],
		explore: [ 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M15.5 8.5l-2 5-5 2 2-5z' ],
		location: [ 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z', 'M12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z' ],
		sale_promo: [ 'M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z', 'M7.5 7.5h.01' ],
	};

	/* ------------------------------------------------------------------ *
	 * Time formatting (always computed at display time)
	 * ------------------------------------------------------------------ */

	var rtfCache;

	function relativeFormatter( locale ) {
		if ( rtfCache !== undefined ) {
			return rtfCache;
		}
		rtfCache = null;
		if ( window.Intl && window.Intl.RelativeTimeFormat ) {
			var candidates = [ locale, String( locale || '' ).split( '-' )[ 0 ], 'en' ];
			for ( var i = 0; i < candidates.length; i++ ) {
				if ( ! candidates[ i ] ) {
					continue;
				}
				try {
					rtfCache = new window.Intl.RelativeTimeFormat( candidates[ i ], { numeric: 'always' } );
					break;
				} catch ( e ) {}
			}
		}
		return rtfCache;
	}

	/**
	 * @param {Object} item    Notification.
	 * @param {Object} display Display settings.
	 * @param {Object} config  Global config (i18n, locale, clock skew).
	 */
	function formatTime( item, display, config ) {
		var i18n = config.i18n || {};
		if ( display.timeMode === 'recently' || ! item.timestamp ) {
			return item.time_ago || i18n.recently || '';
		}
		var ts = Date.parse( item.timestamp );
		if ( isNaN( ts ) ) {
			return item.time_ago || i18n.recently || '';
		}
		// Correct for an inaccurate visitor clock using the server's clock.
		var age = Math.max( 0, ( now() + ( config.skew || 0 ) - ts ) / 1000 );

		if ( display.timeMode === 'approximate' ) {
			if ( age < 3600 ) {
				return i18n.hour;
			}
			if ( age < 21600 ) {
				return i18n.hours;
			}
			if ( age < 86400 ) {
				return i18n.day;
			}
			if ( age < 604800 ) {
				return i18n.week;
			}
			return i18n.month;
		}

		if ( age < 60 ) {
			return i18n.justNow;
		}
		var rtf = relativeFormatter( config.locale );
		if ( ! rtf ) {
			return item.time_ago || i18n.recently || '';
		}
		if ( age < 3600 ) {
			return rtf.format( -Math.floor( age / 60 ), 'minute' );
		}
		if ( age < 86400 ) {
			return rtf.format( -Math.floor( age / 3600 ), 'hour' );
		}
		return rtf.format( -Math.floor( age / 86400 ), 'day' );
	}

	/* ------------------------------------------------------------------ *
	 * Rendering (shared by the storefront and the admin preview)
	 * ------------------------------------------------------------------ */

	function isValidItem( item ) {
		return (
			item &&
			typeof item === 'object' &&
			typeof item.id === 'string' &&
			typeof item.message === 'string' &&
			item.message.length > 0 &&
			TYPES.indexOf( item.type ) !== -1
		);
	}

	function isPurchase( item ) {
		return item.type === 'purchase' || item.type === 'product_purchase';
	}

	function buildMessage( item, display, config ) {
		var node = el( 'span', 'wplh-message' );
		var text = item.message;
		if ( text.indexOf( '{time_ago}' ) !== -1 ) {
			var time = formatTime( item, display, config );
			if ( /^en\b/i.test( config.locale || '' ) ) {
				time = time.charAt( 0 ).toLowerCase() + time.slice( 1 );
			}
			text = text.split( '{time_ago}' ).join( time );
		}
		var name = typeof item.product === 'string' ? item.product : '';
		var at = name ? text.indexOf( name ) : -1;
		if ( at === -1 ) {
			node.textContent = text;
			return node;
		}
		node.appendChild( document.createTextNode( text.slice( 0, at ) ) );
		node.appendChild( el( 'strong', 'wplh-product', name ) );
		node.appendChild( document.createTextNode( text.slice( at + name.length ) ) );
		return node;
	}

	function buildMeta( item, display, config ) {
		var i18n = config.i18n || {};
		var meta = el( 'span', 'wplh-meta' );

		if ( ( item.type === 'sale' || item.type === 'sale_promo' ) && display.price && item.price ) {
			var price = el( 'span', 'wplh-price' );
			if ( item.price.regular ) {
				var regular = el( 'del', 'wplh-regular' );
				regular.appendChild( el( 'span', 'wplh-sr', ( i18n.was || '' ) + ' ' ) );
				regular.appendChild( document.createTextNode( item.price.regular ) );
				price.appendChild( regular );
			}
			if ( item.price.sale ) {
				var sale = el( 'span', 'wplh-sale-price' );
				sale.appendChild( el( 'span', 'wplh-sr', ( i18n.now || '' ) + ' ' ) );
				sale.appendChild( document.createTextNode( item.price.sale ) );
				price.appendChild( sale );
			}
			if ( price.childNodes.length ) {
				meta.appendChild( price );
			}
			if ( item.price.discount ) {
				meta.appendChild( el( 'span', 'wplh-badge', ( i18n.save || '' ) + ' ' + item.price.discount ) );
			}
		}

		if ( isPurchase( item ) ) {
			if ( display.time ) {
				var time = el( 'span', 'wplh-time' );
				var dot = el( 'span', 'wplh-dot' );
				dot.setAttribute( 'aria-hidden', 'true' );
				time.appendChild( dot );
				time.appendChild( document.createTextNode( formatTime( item, display, config ) ) );
				meta.appendChild( time );
			}
			if ( display.verified && item.verified ) {
				var verified = el( 'span', 'wplh-verified' );
				verified.appendChild( svg( ICONS.check, 'wplh-icon' ) );
				verified.appendChild( document.createTextNode( i18n.verified || '' ) );
				meta.appendChild( verified );
			}
		}

		if ( item.type === 'location' && item.location ) {
			var place = el( 'span', 'wplh-place' );
			place.appendChild( svg( ICONS.location, 'wplh-icon' ) );
			place.appendChild( document.createTextNode( item.location ) );
			meta.appendChild( place );
		}

		if ( ( item.type === 'featured' || item.type === 'explore' ) && safeUrl( item.url ) && i18n.view ) {
			meta.appendChild( el( 'span', 'wplh-cta', i18n.view ) );
		}

		return meta.childNodes.length ? meta : null;
	}

	/**
	 * Build a notification element.
	 *
	 * @param {Object} item    Notification.
	 * @param {Object} display Display settings.
	 * @param {Object} config  Config (i18n, locale, skew).
	 * @param {Object} opts    { preview: bool }.
	 */
	function buildToast( item, display, config, opts ) {
		var i18n = config.i18n || {};
		opts = opts || {};

		var isPreview = !! ( opts.preview || item.preview );
		var toast = el( 'div', 'wplh-toast wplh-type-' + item.type + ( isPreview ? ' wplh-toast--preview' : '' ) );
		if ( isPreview ) {
			toast.appendChild( el( 'div', 'wplh-preview-strip', i18n.preview || 'PREVIEW' ) );
		}
		var url = safeUrl( item.url );
		var body = el( url ? 'a' : 'div', 'wplh-body' );
		if ( url ) {
			body.href = url;
		}

		if ( display.image ) {
			var media = el( 'span', 'wplh-media wplh-shape-' + ( display.shape || 'rounded' ) );
			media.setAttribute( 'aria-hidden', 'true' );
			var src = safeUrl( item.image );
			if ( src ) {
				var img = el( 'img', 'wplh-img' );
				img.alt = '';
				img.decoding = 'async';
				img.loading = 'lazy';
				img.width = toInt( item.image_w, 56 ) || 56;
				img.height = toInt( item.image_h, 56 ) || 56;
				img.src = src;
				media.appendChild( img );
			} else {
				media.className += ' wplh-media-icon';
				media.appendChild( svg( ICONS[ isPurchase( item ) ? 'purchase' : item.type ] || ICONS.purchase, 'wplh-icon' ) );
			}
			body.appendChild( media );
		}

		var content = el( 'span', 'wplh-content' );
		if ( display.label && item.label ) {
			content.appendChild( el( 'span', 'wplh-label', item.label ) );
		}
		content.appendChild( buildMessage( item, display, config ) );
		var meta = buildMeta( item, display, config );
		if ( meta ) {
			content.appendChild( meta );
		}
		if ( display.attribution ) {
			content.appendChild( el( 'span', 'wplh-attribution', format( i18n.poweredBy, display.attribution ) ) );
		}
		body.appendChild( content );
		toast.appendChild( body );

		if ( display.close ) {
			var close = el( 'button', 'wplh-close' );
			close.type = 'button';
			close.setAttribute( 'aria-label', i18n.close || 'Close' );
			close.appendChild( svg( ICONS.close ) );
			toast.appendChild( close );
		}

		return toast;
	}

	/**
	 * Create the fixed container (also used as the polite live region).
	 */
	function createRoot( display, config, opts ) {
		opts = opts || {};
		var classes = [
			'wplh-root',
			'wplh-pos-' + ( display.position || 'bottom-left' ),
			'wplh-m-' + ( display.mobile || 'bottom-center' ),
			'wplh-anim-' + ( display.animation || 'slide' ),
			'wplh-theme-' + ( display.theme || 'light' ),
			'wplh-shadow-' + ( display.shadow || 'soft' ),
		];
		if ( display.font === 'system' ) {
			classes.push( 'wplh-font-system' );
		}
		if ( opts.preview ) {
			classes.push( 'wplh-preview-mode' );
		}
		if ( opts.mobile ) {
			classes.push( 'wplh-sim-mobile' );
		}
		var root = el( 'div', classes.join( ' ' ) );
		applyVars( root, display );

		if ( ! opts.preview ) {
			if ( display.announce ) {
				root.setAttribute( 'role', 'status' );
				root.setAttribute( 'aria-live', 'polite' );
			} else {
				root.setAttribute( 'aria-live', 'off' );
			}
			root.setAttribute( 'aria-label', ( config.i18n && config.i18n.region ) || '' );
		}
		return root;
	}

	function applyVars( root, display ) {
		var accent = safeColor( display.accent, '#16a34a' );
		var style = root.style;
		style.setProperty( '--wplh-accent', accent );
		style.setProperty( '--wplh-on-accent', onColor( accent ) );
		if ( display.theme === 'custom' ) {
			style.setProperty( '--wplh-bg', safeColor( display.bg, '#ffffff' ) );
			style.setProperty( '--wplh-text', safeColor( display.text, '#111827' ) );
		}
		style.setProperty( '--wplh-radius', toInt( display.radius, 14 ) + 'px' );
		style.setProperty( '--wplh-width', toInt( display.width, 340 ) + 'px' );
		style.setProperty( '--wplh-ox', toInt( display.offsetX, 20 ) + 'px' );
		style.setProperty( '--wplh-oy', toInt( display.offsetY, 20 ) + 'px' );
		style.setProperty( '--wplh-z', String( toInt( display.zIndex, 99990 ) ) );
	}

	/**
	 * Render a static, visible notification into a container (admin preview).
	 */
	WPLH.renderStatic = function ( container, item, display, config, opts ) {
		opts = opts || {};
		while ( container.firstChild ) {
			container.removeChild( container.firstChild );
		}
		if ( ! isValidItem( item ) ) {
			return null;
		}
		var root = createRoot( display, config, { preview: true, mobile: !! opts.mobile } );
		var toast = buildToast( item, display, config, { preview: true } );
		toast.className += ' wplh-in';
		root.appendChild( toast );
		container.appendChild( root );
		return root;
	};
	WPLH.formatTime = formatTime;

	/* ------------------------------------------------------------------ *
	 * Storefront runtime
	 * ------------------------------------------------------------------ */

	var cfg = window.wplhConfig;
	if ( ! cfg || typeof cfg !== 'object' || ! cfg.display || cfg.adminPreview ) {
		return;
	}
	if ( ! window.Promise || ! window.fetch || ! document.body ) {
		return;
	}

	var display = cfg.display;
	var freq = cfg.freq || {};
	var local = storage( 'localStorage' );
	var sess = storage( 'sessionStorage' );
	var FIRST_MS = Math.max( 0, toInt( freq.first, 10 ) ) * 1000;
	var DURATION_MS = Math.max( 3, toInt( freq.duration, 8 ) ) * 1000;
	var INTERVAL_MS = Math.max( 5, toInt( freq.interval, 20 ) ) * 1000;
	var INTERVAL_MAX_MS = Math.max( INTERVAL_MS, toInt( freq.intervalMax, 90 ) * 1000 );
	var COOLDOWN_MS = Math.max( 1, toInt( cfg.cooldown, 30 ) ) * 60 * 1000;
	var FP_KEY = 'wplh_fp_v1';
	var sound = cfg.sound || {};
	var audioUnlocked = !! ( window.navigator.userActivation && window.navigator.userActivation.hasBeenActive );
	var audioEl = null;
	var MAX_SESSION = Math.max( 0, toInt( freq.maxSession, 5 ) );
	var MAX_PAGE = Math.max( 1, toInt( freq.maxPage, 2 ) );
	var GAP_MS = 3000;

	var state = {
		queue: [],
		root: null,
		current: null,
		shownOnPage: 0,
		stopped: false,
		paused: false,
		timer: null,
		hideTimer: null,
		remaining: 0,
		startedAt: 0,
		cssPromise: null,
	};

	function loadSession() {
		var s = local.get( SESSION_KEY );
		var t = now();
		// Keyed to a fixed schema version (not the cache version) so a mix of
		// freshly rendered and page-cached HTML can never reset the limits.
		if ( ! s || typeof s !== 'object' || s.v !== SESSION_SCHEMA || ! s.touch || t - s.touch > SESSION_IDLE_MS ) {
			s = { v: SESSION_SCHEMA, touch: t, shown: 0, last: 0, gap: 0, lastProduct: 0, seen: [], muted: false, seed: Math.floor( Math.random() * 1000 ) };
		}
		s.seed = toInt( s.seed, 0 ) % 1000;
		s.gap = toInt( s.gap, 0 );
		s.shown = toInt( s.shown, 0 );
		s.last = toInt( s.last, 0 );
		s.seen = Array.isArray( s.seen ) ? s.seen : [];
		return s;
	}

	function saveSession( s ) {
		s.touch = now();
		if ( s.seen.length > 100 ) {
			s.seen = s.seen.slice( -100 );
		}
		local.set( SESSION_KEY, s );
	}

	/** Next gap: random within the configured bounds, so timing never feels mechanical. */
	function nextGap() {
		if ( ! freq.random || INTERVAL_MAX_MS <= INTERVAL_MS ) {
			return INTERVAL_MS;
		}
		return Math.round( INTERVAL_MS + Math.random() * ( INTERVAL_MAX_MS - INTERVAL_MS ) );
	}

	function waitFor( s ) {
		return s.last ? s.last + ( s.gap || INTERVAL_MS ) - now() : 0;
	}

	/* ---- Anti-repetition: recently shown fingerprints, across sessions ---- */

	function recentFingerprints() {
		var map = local.get( FP_KEY );
		var t = now();
		var out = {};
		if ( map && typeof map === 'object' ) {
			Object.keys( map ).forEach( function ( id ) {
				if ( t - toInt( map[ id ], 0 ) < COOLDOWN_MS ) {
					out[ id ] = toInt( map[ id ], 0 );
				}
			} );
		}
		return out;
	}

	function rememberFingerprint( id ) {
		var map = recentFingerprints();
		map[ id ] = now();
		local.set( FP_KEY, map );
	}

	/* ---- Sound (optional, autoplay-policy safe) ---- */

	function unlockAudio() {
		audioUnlocked = true;
		[ 'pointerdown', 'keydown', 'touchstart' ].forEach( function ( type ) {
			document.removeEventListener( type, unlockAudio, true );
		} );
	}

	function playSound() {
		var enabled = isMobile() ? sound.mobile : sound.desktop;
		if ( ! enabled || ! sound.url || ! audioUnlocked ) {
			return; // Browsers block audio before a user gesture; visuals continue regardless.
		}
		try {
			if ( ! audioEl ) {
				audioEl = new window.Audio( sound.url );
				audioEl.preload = 'auto';
			}
			audioEl.volume = Math.max( 0, Math.min( 1, toInt( sound.volume, 35 ) / 100 ) );
			audioEl.currentTime = 0;
			var playing = audioEl.play();
			if ( playing && typeof playing[ 'catch' ] === 'function' ) {
				playing[ 'catch' ]( function () {} );
			}
		} catch ( e ) {}
	}

	if ( sound.url && ( sound.desktop || sound.mobile ) && ! audioUnlocked ) {
		[ 'pointerdown', 'keydown', 'touchstart' ].forEach( function ( type ) {
			document.addEventListener( type, unlockAudio, true );
		} );
	}

	function isMobile() {
		return !! ( window.matchMedia && window.matchMedia( MOBILE_QUERY ).matches );
	}

	function limitReached( s ) {
		if ( state.stopped || s.muted ) {
			return true;
		}
		if ( MAX_SESSION > 0 && s.shown >= MAX_SESSION ) {
			return true;
		}
		return state.shownOnPage >= MAX_PAGE;
	}

	function whenVisible( fn ) {
		if ( document.visibilityState !== 'hidden' ) {
			fn();
			return;
		}
		var handler = function () {
			if ( document.visibilityState !== 'hidden' ) {
				document.removeEventListener( 'visibilitychange', handler );
				state.timer = window.setTimeout( fn, 1500 );
			}
		};
		document.addEventListener( 'visibilitychange', handler );
	}

	function schedule( fn, delay ) {
		window.clearTimeout( state.timer );
		state.timer = window.setTimeout( function () {
			whenVisible( fn );
		}, Math.max( 0, delay ) );
	}

	/* ---- Data ---- */

	function fetchQueue() {
		var ctx = cfg.ctx || {};
		var params = 'ctx=' + encodeURIComponent( ctx.t || 'other' ) + '&pid=' + toInt( ctx.p, 0 ) + '&tid=' + toInt( ctx.c, 0 ) + '&seed=' + loadSession().seed;
		var cacheKey = 'wplh_q_' + cfg.version + '_' + params;
		var ttl = toInt( cfg.clientCache, 0 ) * 1000;

		if ( ttl > 0 ) {
			var cached = sess.get( cacheKey );
			if ( cached && Array.isArray( cached.items ) && now() - toInt( cached.t, 0 ) < ttl ) {
				cfg.skew = toInt( cached.skew, 0 );
				return window.Promise.resolve( cached.items );
			}
		}

		var url = cfg.rest + ( cfg.rest.indexOf( '?' ) === -1 ? '?' : '&' ) + params;
		var headers = { Accept: 'application/json' };
		if ( cfg.nonce ) {
			headers[ 'X-WP-Nonce' ] = cfg.nonce;
		}

		return window
			.fetch( url, { method: 'GET', credentials: cfg.nonce ? 'same-origin' : 'omit', headers: headers } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( data ) {
				var items = data && Array.isArray( data.items ) ? data.items.filter( isValidItem ) : [];
				var generated = data && data.generated ? Date.parse( data.generated ) : NaN;
				cfg.skew = isNaN( generated ) ? 0 : generated - now();
				if ( ttl > 0 ) {
					sess.set( cacheKey, { t: now(), skew: cfg.skew, items: items } );
				}
				return items;
			} )
			[ 'catch' ]( function () {
				return [];
			} );
	}

	function ensureCss() {
		if ( state.cssPromise ) {
			return state.cssPromise;
		}
		state.cssPromise = new window.Promise( function ( resolve, reject ) {
			if ( document.getElementById( 'wplh-notifications-css' ) || document.querySelector( 'link[data-wplh-css]' ) ) {
				resolve();
				return;
			}
			var link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = cfg.css;
			link.setAttribute( 'data-wplh-css', '' );
			var timeout = window.setTimeout( reject, 8000 );
			link.onload = function () {
				window.clearTimeout( timeout );
				resolve();
			};
			link.onerror = function () {
				window.clearTimeout( timeout );
				reject();
			};
			document.head.appendChild( link );
		} );
		return state.cssPromise;
	}

	function preloadImage( item ) {
		return new window.Promise( function ( resolve ) {
			var src = display.image ? safeUrl( item.image ) : '';
			if ( ! src ) {
				resolve();
				return;
			}
			var done = false;
			var finish = function () {
				if ( ! done ) {
					done = true;
					resolve();
				}
			};
			var img = new window.Image();
			img.onload = finish;
			img.onerror = function () {
				item.image = '';
				finish();
			};
			window.setTimeout( finish, 2500 );
			img.src = src;
		} );
	}

	/* ---- Analytics (anonymous, batched) ---- */

	var events = [];
	var flushTimer = null;

	function flush() {
		window.clearTimeout( flushTimer );
		if ( ! events.length || ! cfg.events ) {
			return;
		}
		var payload = JSON.stringify( { events: events.splice( 0, 25 ) } );
		var sent = false;
		try {
			if ( window.navigator.sendBeacon && window.Blob ) {
				sent = window.navigator.sendBeacon( cfg.events, new window.Blob( [ payload ], { type: 'text/plain;charset=UTF-8' } ) );
			}
		} catch ( e ) {
			sent = false;
		}
		if ( ! sent ) {
			try {
				window
					.fetch( cfg.events, {
						method: 'POST',
						body: payload,
						keepalive: true,
						credentials: 'omit',
						headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
					} )
					[ 'catch' ]( function () {} );
			} catch ( e ) {}
		}
	}

	function track( name, item ) {
		if ( ! cfg.analytics || item.preview ) {
			return;
		}
		events.push( { e: name, t: item.type, p: toInt( item.product_id, 0 ) } );
		if ( name === 'click' || events.length >= 10 ) {
			flush();
			return;
		}
		window.clearTimeout( flushTimer );
		flushTimer = window.setTimeout( flush, 10000 );
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'hidden' ) {
			flush();
		}
	} );
	window.addEventListener( 'pagehide', flush );

	/* ---- Display loop ---- */

	function ensureRoot() {
		if ( ! state.root ) {
			state.root = createRoot( display, cfg, {} );
			document.body.appendChild( state.root );
		}
		return state.root;
	}

	function nextItem( s ) {
		// Preference: unseen, different type and different product from the last one;
		// then different product only; then anything unseen.
		var best = -1;
		var productOnly = -1;
		var any = -1;
		for ( var i = 0; i < state.queue.length; i++ ) {
			var item = state.queue[ i ];
			if ( s.seen.indexOf( item.id ) !== -1 ) {
				continue;
			}
			var newProduct = item.type === 'product_purchase' || toInt( item.product_id, 0 ) !== toInt( s.lastProduct, -1 );
			var newType = item.type !== s.lastType || item.type === 'product_purchase' || item.type === 'purchase';
			if ( newProduct && newType ) {
				best = i;
				break;
			}
			if ( newProduct && productOnly === -1 ) {
				productOnly = i;
			}
			if ( any === -1 ) {
				any = i;
			}
		}
		var pick = best !== -1 ? best : ( productOnly !== -1 ? productOnly : any );
		return pick === -1 ? null : state.queue.splice( pick, 1 )[ 0 ];
	}

	function startHideTimer( ms ) {
		state.remaining = ms;
		state.startedAt = now();
		window.clearTimeout( state.hideTimer );
		state.hideTimer = window.setTimeout( function () {
			hide( 'timeout' );
		}, ms );
	}

	function pause() {
		if ( ! state.current || state.paused ) {
			return;
		}
		state.paused = true;
		window.clearTimeout( state.hideTimer );
		state.remaining -= now() - state.startedAt;
	}

	function resume() {
		if ( ! state.current || ! state.paused ) {
			return;
		}
		state.paused = false;
		startHideTimer( Math.max( state.remaining, 2000 ) );
	}

	function bind( toast, item ) {
		var close = toast.querySelector( '.wplh-close' );
		if ( close ) {
			close.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				hide( 'dismiss' );
			} );
		}
		var link = toast.querySelector( 'a.wplh-body' );
		if ( link ) {
			link.addEventListener( 'click', function () {
				track( 'click', item );
			} );
		}
		if ( freq.hover ) {
			toast.addEventListener( 'mouseenter', pause );
			toast.addEventListener( 'mouseleave', resume );
		}
		// Keyboard and screen-reader users get unlimited time while focused (WCAG 2.2.1).
		toast.addEventListener( 'focusin', pause );
		toast.addEventListener( 'focusout', function ( event ) {
			if ( ! toast.contains( event.relatedTarget ) ) {
				resume();
			}
		} );
		toast.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' || event.key === 'Esc' ) {
				hide( 'dismiss' );
			}
		} );
	}

	function show( item ) {
		preloadImage( item ).then( function () {
			if ( document.visibilityState === 'hidden' ) {
				state.queue.unshift( item );
				whenVisible( next );
				return;
			}
			var s = loadSession();
			if ( limitReached( s ) ) {
				return;
			}

			var root = ensureRoot();
			var toast = buildToast( item, display, cfg, {} );
			bind( toast, item );
			root.appendChild( toast );
			void toast.offsetWidth; // Commit the initial state so the transition runs.
			toast.className += ' wplh-in';
			state.current = { el: toast, item: item };

			s.shown += 1;
			s.last = now();
			s.gap = nextGap();
			rememberFingerprint( item.id );
			s.lastProduct = toInt( item.product_id, 0 );
			s.lastType = item.type;
			s.seen.push( item.id );
			saveSession( s );
			state.shownOnPage += 1;

			track( 'view', item );
			playSound();
			startHideTimer( DURATION_MS );
		} );
	}

	function hide( reason ) {
		var current = state.current;
		if ( ! current ) {
			return;
		}
		state.current = null;
		state.paused = false;
		window.clearTimeout( state.hideTimer );

		var toast = current.el;
		var focusInside = toast.contains( document.activeElement );
		toast.className = toast.className.replace( ' wplh-in', '' ) + ' wplh-out';
		var removed = false;
		var remove = function () {
			if ( ! removed ) {
				removed = true;
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
			}
		};
		toast.addEventListener( 'transitionend', remove );
		window.setTimeout( remove, 700 );
		if ( focusInside && document.body && typeof document.body.focus === 'function' ) {
			// Do not leave keyboard focus on a removed element.
			document.body.setAttribute( 'tabindex', '-1' );
			document.body.focus( { preventScroll: true } );
			document.body.removeAttribute( 'tabindex' );
		}

		if ( reason === 'dismiss' ) {
			track( 'dismiss', current.item );
			if ( freq.dismiss === 'session' ) {
				var s = loadSession();
				s.muted = true;
				saveSession( s );
				state.stopped = true;
				return;
			}
			if ( freq.dismiss === 'page' ) {
				state.stopped = true;
				return;
			}
		}

		var session = loadSession();
		if ( ! limitReached( session ) && state.queue.length ) {
			schedule( next, Math.max( GAP_MS, waitFor( session ) ) );
		}
	}

	function next() {
		var s = loadSession();
		if ( limitReached( s ) || state.current ) {
			return;
		}
		// Respect the minimum interval across pages and tabs.
		var wait = waitFor( s );
		if ( wait > 250 ) {
			schedule( next, wait );
			return;
		}
		var item = nextItem( s );
		if ( ! item ) {
			return;
		}
		ensureCss().then(
			function () {
				show( item );
			},
			function () {
				state.stopped = true;
			}
		);
	}

	function load() {
		fetchQueue().then( function ( items ) {
			var s = loadSession();
			var recent = recentFingerprints();
			state.queue = items.filter( function ( item ) {
				return s.seen.indexOf( item.id ) === -1 && ! recent[ item.id ];
			} );
			if ( ! state.queue.length || limitReached( s ) ) {
				return;
			}
			ensureRoot(); // Live region exists before the first announcement.
			next();
		} );
	}

	function runPreview( items ) {
		var queue = items.filter( isValidItem );
		var showPreview = function () {
			var item = queue.shift();
			if ( ! item ) {
				return;
			}
			item.preview = true;
			ensureCss().then( function () {
				var root = ensureRoot();
				var toast = buildToast( item, display, cfg, { preview: true } );
				var close = toast.querySelector( '.wplh-close' );
				root.appendChild( toast );
				void toast.offsetWidth;
				toast.className += ' wplh-in';
				var done = function () {
					toast.className = toast.className.replace( ' wplh-in', '' ) + ' wplh-out';
					window.setTimeout( function () {
						if ( toast.parentNode ) {
							toast.parentNode.removeChild( toast );
						}
						window.setTimeout( showPreview, 600 );
					}, 450 );
				};
				var timer = window.setTimeout( done, DURATION_MS );
				if ( close ) {
					close.addEventListener( 'click', function () {
						window.clearTimeout( timer );
						done();
					} );
				}
			} );
		};
		window.setTimeout( showPreview, 1000 );
	}

	function init() {
		if ( Array.isArray( cfg.preview ) ) {
			runPreview( cfg.preview );
			return;
		}
		if ( display.mobile === 'hidden' && isMobile() ) {
			return;
		}
		var s = loadSession();
		if ( limitReached( s ) ) {
			return;
		}
		var sinceLast = waitFor( s );
		schedule( load, Math.max( FIRST_MS, sinceLast ) );
	}

	init();
} )( window, document );
