/*!
 * ALWAYS FINAL Social Proof — frontend notifications.
 * Developed by ALWAYS FINAL. License: GPL-2.0-or-later.
 *
 * Vanilla JavaScript, no dependencies. All text is inserted with textContent /
 * text nodes (never innerHTML), URLs are restricted to http(s), and the
 * stylesheet is only requested right before the first notification is shown.
 */
( function ( window, document ) {
	'use strict';

	var AFSP = ( window.AFSP = window.AFSP || {} );
	if ( AFSP.loaded ) {
		return;
	}
	AFSP.loaded = true;

	var SVG_NS = 'http://www.w3.org/2000/svg';
	var TYPES = [ 'purchase', 'product_purchase', 'sale', 'popular' ];
	var SESSION_KEY = 'afsp_session_v1';
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
		return lum > 0.35 ? '#111827' : '#ffffff';
	}

	function storage( kind ) {
		var mem = {};
		var backend = null;
		try {
			backend = window[ kind ];
			var probe = '__afsp_probe';
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
		var node = el( 'span', 'afsp-message' );
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
		node.appendChild( el( 'strong', 'afsp-product', name ) );
		node.appendChild( document.createTextNode( text.slice( at + name.length ) ) );
		return node;
	}

	function buildMeta( item, display, config ) {
		var i18n = config.i18n || {};
		var meta = el( 'span', 'afsp-meta' );

		if ( item.type === 'sale' && display.price && item.price ) {
			var price = el( 'span', 'afsp-price' );
			if ( item.price.regular ) {
				var regular = el( 'del', 'afsp-regular' );
				regular.appendChild( el( 'span', 'afsp-sr', ( i18n.was || '' ) + ' ' ) );
				regular.appendChild( document.createTextNode( item.price.regular ) );
				price.appendChild( regular );
			}
			if ( item.price.sale ) {
				var sale = el( 'span', 'afsp-sale-price' );
				sale.appendChild( el( 'span', 'afsp-sr', ( i18n.now || '' ) + ' ' ) );
				sale.appendChild( document.createTextNode( item.price.sale ) );
				price.appendChild( sale );
			}
			if ( price.childNodes.length ) {
				meta.appendChild( price );
			}
			if ( item.price.discount ) {
				meta.appendChild( el( 'span', 'afsp-badge', ( i18n.save || '' ) + ' ' + item.price.discount ) );
			}
		}

		if ( isPurchase( item ) ) {
			if ( display.time ) {
				var time = el( 'span', 'afsp-time' );
				var dot = el( 'span', 'afsp-dot' );
				dot.setAttribute( 'aria-hidden', 'true' );
				time.appendChild( dot );
				time.appendChild( document.createTextNode( formatTime( item, display, config ) ) );
				meta.appendChild( time );
			}
			if ( display.verified && item.verified ) {
				var verified = el( 'span', 'afsp-verified' );
				verified.appendChild( svg( ICONS.check, 'afsp-icon' ) );
				verified.appendChild( document.createTextNode( i18n.verified || '' ) );
				meta.appendChild( verified );
			}
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
		var toast = el( 'div', 'afsp-toast afsp-type-' + item.type + ( isPreview ? ' afsp-toast--preview' : '' ) );
		if ( isPreview ) {
			toast.appendChild( el( 'div', 'afsp-preview-strip', i18n.preview || 'PREVIEW' ) );
		}
		var url = safeUrl( item.url );
		var body = el( url ? 'a' : 'div', 'afsp-body' );
		if ( url ) {
			body.href = url;
		}

		if ( display.image ) {
			var media = el( 'span', 'afsp-media afsp-shape-' + ( display.shape || 'rounded' ) );
			media.setAttribute( 'aria-hidden', 'true' );
			var src = safeUrl( item.image );
			if ( src ) {
				var img = el( 'img', 'afsp-img' );
				img.alt = '';
				img.decoding = 'async';
				img.loading = 'lazy';
				img.width = toInt( item.image_w, 56 ) || 56;
				img.height = toInt( item.image_h, 56 ) || 56;
				img.src = src;
				media.appendChild( img );
			} else {
				media.className += ' afsp-media-icon';
				media.appendChild( svg( ICONS[ isPurchase( item ) ? 'purchase' : item.type ] || ICONS.purchase, 'afsp-icon' ) );
			}
			body.appendChild( media );
		}

		var content = el( 'span', 'afsp-content' );
		if ( display.label && item.label ) {
			content.appendChild( el( 'span', 'afsp-label', item.label ) );
		}
		content.appendChild( buildMessage( item, display, config ) );
		var meta = buildMeta( item, display, config );
		if ( meta ) {
			content.appendChild( meta );
		}
		if ( display.attribution ) {
			content.appendChild( el( 'span', 'afsp-attribution', format( i18n.poweredBy, display.attribution ) ) );
		}
		body.appendChild( content );
		toast.appendChild( body );

		if ( display.close ) {
			var close = el( 'button', 'afsp-close' );
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
			'afsp-root',
			'afsp-pos-' + ( display.position || 'bottom-left' ),
			'afsp-m-' + ( display.mobile || 'bottom-center' ),
			'afsp-anim-' + ( display.animation || 'slide' ),
			'afsp-theme-' + ( display.theme || 'light' ),
			'afsp-shadow-' + ( display.shadow || 'soft' ),
		];
		if ( display.font === 'system' ) {
			classes.push( 'afsp-font-system' );
		}
		if ( opts.preview ) {
			classes.push( 'afsp-preview-mode' );
		}
		if ( opts.mobile ) {
			classes.push( 'afsp-sim-mobile' );
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
		style.setProperty( '--afsp-accent', accent );
		style.setProperty( '--afsp-on-accent', onColor( accent ) );
		if ( display.theme === 'custom' ) {
			style.setProperty( '--afsp-bg', safeColor( display.bg, '#ffffff' ) );
			style.setProperty( '--afsp-text', safeColor( display.text, '#111827' ) );
		}
		style.setProperty( '--afsp-radius', toInt( display.radius, 14 ) + 'px' );
		style.setProperty( '--afsp-width', toInt( display.width, 340 ) + 'px' );
		style.setProperty( '--afsp-ox', toInt( display.offsetX, 20 ) + 'px' );
		style.setProperty( '--afsp-oy', toInt( display.offsetY, 20 ) + 'px' );
		style.setProperty( '--afsp-z', String( toInt( display.zIndex, 99990 ) ) );
	}

	/**
	 * Render a static, visible notification into a container (admin preview).
	 */
	AFSP.renderStatic = function ( container, item, display, config, opts ) {
		opts = opts || {};
		while ( container.firstChild ) {
			container.removeChild( container.firstChild );
		}
		if ( ! isValidItem( item ) ) {
			return null;
		}
		var root = createRoot( display, config, { preview: true, mobile: !! opts.mobile } );
		var toast = buildToast( item, display, config, { preview: true } );
		toast.className += ' afsp-in';
		root.appendChild( toast );
		container.appendChild( root );
		return root;
	};
	AFSP.formatTime = formatTime;

	/* ------------------------------------------------------------------ *
	 * Storefront runtime
	 * ------------------------------------------------------------------ */

	var cfg = window.afspConfig;
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
	var INTERVAL_MS = Math.max( 5, toInt( freq.interval, 30 ) ) * 1000;
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
			s = { v: SESSION_SCHEMA, touch: t, shown: 0, last: 0, lastProduct: 0, seen: [], muted: false };
		}
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
		var params = 'ctx=' + encodeURIComponent( ctx.t || 'other' ) + '&pid=' + toInt( ctx.p, 0 ) + '&tid=' + toInt( ctx.c, 0 );
		var cacheKey = 'afsp_q_' + cfg.version + '_' + params;
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
			if ( document.getElementById( 'afsp-notifications-css' ) || document.querySelector( 'link[data-afsp-css]' ) ) {
				resolve();
				return;
			}
			var link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = cfg.css;
			link.setAttribute( 'data-afsp-css', '' );
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
		var fallbackIndex = -1;
		for ( var i = 0; i < state.queue.length; i++ ) {
			var item = state.queue[ i ];
			if ( s.seen.indexOf( item.id ) !== -1 ) {
				continue;
			}
			if ( item.type === 'product_purchase' || toInt( item.product_id, 0 ) !== toInt( s.lastProduct, -1 ) ) {
				return state.queue.splice( i, 1 )[ 0 ];
			}
			if ( fallbackIndex === -1 ) {
				fallbackIndex = i;
			}
		}
		return fallbackIndex === -1 ? null : state.queue.splice( fallbackIndex, 1 )[ 0 ];
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
		var close = toast.querySelector( '.afsp-close' );
		if ( close ) {
			close.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				hide( 'dismiss' );
			} );
		}
		var link = toast.querySelector( 'a.afsp-body' );
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
			toast.className += ' afsp-in';
			state.current = { el: toast, item: item };

			s.shown += 1;
			s.last = now();
			s.lastProduct = toInt( item.product_id, 0 );
			s.seen.push( item.id );
			saveSession( s );
			state.shownOnPage += 1;

			track( 'view', item );
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
		toast.className = toast.className.replace( ' afsp-in', '' ) + ' afsp-out';
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
			schedule( next, Math.max( GAP_MS, session.last + INTERVAL_MS - now() ) );
		}
	}

	function next() {
		var s = loadSession();
		if ( limitReached( s ) || state.current ) {
			return;
		}
		// Respect the minimum interval across pages and tabs.
		var wait = s.last ? s.last + INTERVAL_MS - now() : 0;
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
			state.queue = items.filter( function ( item ) {
				return s.seen.indexOf( item.id ) === -1;
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
				var close = toast.querySelector( '.afsp-close' );
				root.appendChild( toast );
				void toast.offsetWidth;
				toast.className += ' afsp-in';
				var done = function () {
					toast.className = toast.className.replace( ' afsp-in', '' ) + ' afsp-out';
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
		var sinceLast = s.last ? s.last + INTERVAL_MS - now() : 0;
		schedule( load, Math.max( FIRST_MS, sinceLast ) );
	}

	init();
} )( window, document );
