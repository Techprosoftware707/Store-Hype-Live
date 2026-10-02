/*!
 * WP Live Hype — frontend notifications and conversion assistant.
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
	var TYPES = [ 'purchase', 'product_purchase', 'sale', 'popular', 'featured', 'explore', 'location', 'sale_promo', 'product_cta', 'recommend', 'cart', 'checkout', 'promotion', 'promotion_done', 'nudge' ];
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
		product_cta: [ 'M6 8h12l-1.2 12H7.2L6 8z', 'M9 8V7a3 3 0 0 1 6 0v1' ],
		recommend: [ 'M12 3l1.9 4.6L19 9l-4.1 3.3L16 18l-4-2.6L8 18l1.1-5.7L5 9l5.1-1.4z' ],
		cart: [ 'M3 4h2l2.4 11h10.2L20 8H6.2', 'M9 20h.01', 'M17 20h.01' ],
		checkout: [ 'M5 11h14v9H5z', 'M8 11V8a4 4 0 0 1 8 0v3' ],
		promotion: [ 'M3 7h11v9H3z', 'M14 10h4l3 3v3h-7', 'M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', 'M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z' ],
		promotion_done: [ 'M3 7h11v9H3z', 'M14 10h4l3 3v3h-7', 'M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', 'M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z' ],
		nudge: [ 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M15.5 8.5l-2 5-5 2 2-5z' ],
		arrow: [ 'M5 12h14', 'M13 6l6 6-6 6' ],
	};

	/* ------------------------------------------------------------------ *
	 * Calls to action (shared by the storefront and the admin preview)
	 * ------------------------------------------------------------------ */

	var PRODUCT_CTA_KEYS = [ 'view', 'explore', 'offer' ];

	function stripHash( url ) {
		return String( url || '' ).split( '#' )[ 0 ];
	}

	/**
	 * The call to action for a notification: which button, where it goes.
	 * Navigation only ever happens when the visitor clicks it.
	 *
	 * @param {Object} item   Notification.
	 * @param {Object} config Config (conv, ctx).
	 * @param {string} variant A/B variant ('a', 'b' or '-').
	 * @return {Object|null} { key, text, href, action }.
	 */
	function ctaFor( item, config, variant ) {
		var conv = config.conv || {};
		var texts = conv.cta;
		if ( ! texts ) {
			return null;
		}
		var store = conv.store || {};
		var goal = conv.goal || 'cart';
		var key = '';
		var href = '';
		var action = 'link';
		switch ( item.type ) {
			case 'product_cta':
				if ( item.addUrl ) {
					key = 'add';
					href = item.addUrl;
				} else {
					key = 'options';
					action = 'options';
				}
				break;
			case 'explore':
				key = 'explore';
				href = item.url;
				break;
			case 'sale':
			case 'sale_promo':
				key = 'offer';
				href = item.url;
				break;
			case 'location':
			case 'nudge':
				key = 'shop';
				href = item.url || store.shop;
				break;
			case 'cart':
				key = 'cart';
				href = store.cart;
				break;
			case 'checkout':
				key = 'checkout';
				href = store.checkout;
				break;
			case 'promotion':
				key = 'continue';
				href = store.shop;
				break;
			case 'promotion_done':
				key = goal === 'checkout' || goal === 'purchase' ? 'checkout' : 'cart';
				href = store[ key ];
				break;
			default:
				key = 'view';
				href = item.url;
		}
		var text = texts[ key ] || '';
		var ab = conv.ab;
		if ( variant === 'b' && ab && ab.variable === 'cta' && typeof ab.b === 'string' && ab.b && PRODUCT_CTA_KEYS.indexOf( key ) !== -1 ) {
			text = ab.b;
		}
		if ( ! text ) {
			return null;
		}
		if ( action === 'link' ) {
			href = safeUrl( href );
			if ( ! href ) {
				return null;
			}
			// Already on that page ("Continue shopping" on the shop page): just close.
			if ( ! config.adminPreview && stripHash( href ) === stripHash( window.location.href ) ) {
				action = 'close';
			}
		}
		return { key: key, text: text, href: href, action: action };
	}

	/** Variant B copy for product-level synthetic events (A/B "copy" test). */
	function abMessage( item, config, variant ) {
		var ab = ( config.conv || {} ).ab;
		if ( variant !== 'b' || ! ab || ab.variable !== 'copy' || ! Array.isArray( ab.b ) || ! ab.b.length ) {
			return item.message;
		}
		if ( ! item.synthetic || ( item.type !== 'featured' && item.type !== 'explore' ) || ! item.product ) {
			return item.message;
		}
		var line = ab.b[ Math.abs( toInt( item.product_id, 0 ) ) % ab.b.length ];
		return String( line ).split( '{product}' ).join( item.product );
	}

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
		var text = abMessage( item, config, config.variant );
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

	function buildMeta( item, display, config, hasAction ) {
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

		if ( ! hasAction && ( item.type === 'featured' || item.type === 'explore' ) && safeUrl( item.url ) && i18n.view ) {
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
	 * @param {Object} opts    { preview: bool, cta: Object|null }.
	 */
	function buildToast( item, display, config, opts ) {
		var i18n = config.i18n || {};
		opts = opts || {};
		var cta = opts.cta || null;

		var isPreview = !! ( opts.preview || item.preview );
		var toast = el( 'div', 'wplh-toast wplh-type-' + item.type + ( isPreview ? ' wplh-toast--preview' : '' ) + ( cta ? ' wplh-has-action' : '' ) + ( item.quiet ? ' wplh-toast--quiet' : '' ) );
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
				media.appendChild( svg( ICONS[ isPurchase( item ) ? 'purchase' : item.type ] || ICONS.featured, 'wplh-icon' ) );
			}
			body.appendChild( media );
		}

		var content = el( 'span', 'wplh-content' );
		if ( display.label && item.label ) {
			content.appendChild( el( 'span', 'wplh-label', item.label ) );
		}
		content.appendChild( buildMessage( item, display, config ) );
		var meta = buildMeta( item, display, config, !! cta );
		if ( meta ) {
			content.appendChild( meta );
		}
		if ( display.attribution ) {
			content.appendChild( el( 'span', 'wplh-attribution', format( i18n.poweredBy, display.attribution ) ) );
		}
		body.appendChild( content );
		toast.appendChild( body );

		if ( cta ) {
			// A separate row: links are never nested inside the card link.
			var actions = el( 'div', 'wplh-actions' );
			var style = ( config.conv && config.conv.ctaStyle ) === 'link' ? 'link' : 'button';
			var button = el( cta.action === 'link' ? 'a' : 'button', 'wplh-action wplh-action--' + style + ' wplh-action-' + cta.key );
			if ( cta.action === 'link' ) {
				button.href = cta.href;
			} else {
				button.type = 'button';
			}
			button.appendChild( el( 'span', '', cta.text ) );
			button.appendChild( svg( ICONS.arrow, 'wplh-icon' ) );
			actions.appendChild( button );
			toast.appendChild( actions );
		}

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
		if ( display.image ) {
			classes.push( 'wplh-has-media' );
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
		var toast = buildToast( item, display, config, { preview: true, cta: ctaFor( item, config, opts.variant || '-' ) } );
		toast.className += ' wplh-in';
		root.appendChild( toast );
		container.appendChild( root );
		return root;
	};
	WPLH.formatTime = formatTime;
	WPLH.ctaFor = ctaFor;

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
	var conv = cfg.conv || {};
	var convTypes = conv.types || {};
	var store = conv.store || {};
	var ctx = cfg.ctx || {};
	var PAGE = ctx.t || 'other';
	var PAGE_PRODUCT = toInt( ctx.p, 0 );
	var ORDER_RECEIVED = !! ctx.o;
	var local = storage( 'localStorage' );
	var sess = storage( 'sessionStorage' );
	var sound = cfg.sound || {};

	/* ---- A/B assignment (anonymous, stored only in this browser) ---- */

	var AB_KEY = 'wplh_ab_v1';
	var variant = '-';
	( function assignVariant() {
		var ab = conv.ab;
		if ( ! ab || ! ab.id ) {
			return;
		}
		var stored = local.get( AB_KEY );
		if ( ! stored || stored.id !== ab.id || ( stored.v !== 'a' && stored.v !== 'b' ) ) {
			stored = { id: ab.id, v: Math.random() < 0.5 ? 'a' : 'b' };
			local.set( AB_KEY, stored );
		}
		variant = stored.v;
		if ( variant !== 'b' ) {
			return;
		}
		switch ( ab.variable ) {
			case 'position':
				display.position = String( ab.b );
				break;
			case 'animation':
				display.animation = String( ab.b );
				break;
			case 'frequency':
				if ( ab.b && typeof ab.b === 'object' ) {
					freq.first = ab.b.first;
					freq.interval = ab.b.interval;
					freq.intervalMax = ab.b.intervalMax;
					freq.maxPage = ab.b.maxPage;
					freq.maxSession = ab.b.maxSession;
				}
				break;
			case 'sound':
				sound.desktop = !! ab.b;
				break;
			case 'image':
				display.image = !! ab.b;
				break;
		}
	} )();
	cfg.variant = variant;

	var FIRST_MS = Math.max( 0, toInt( freq.first, 10 ) ) * 1000;
	var DURATION_MS = Math.max( 3, toInt( freq.duration, 8 ) ) * 1000;
	var INTERVAL_MS = Math.max( 5, toInt( freq.interval, 20 ) ) * 1000;
	var INTERVAL_MAX_MS = Math.max( INTERVAL_MS, toInt( freq.intervalMax, 90 ) * 1000 );
	var COOLDOWN_MS = Math.max( 1, toInt( cfg.cooldown, 30 ) ) * 60 * 1000;
	var FP_KEY = 'wplh_fp_v1';
	var audioUnlocked = !! ( window.navigator.userActivation && window.navigator.userActivation.hasBeenActive );
	var audioEl = null;
	var MAX_SESSION = Math.max( 0, toInt( freq.maxSession, 5 ) );
	var MAX_PAGE = Math.max( 1, toInt( freq.maxPage, 2 ) );
	var GAP_MS = 3000;
	var IDLE_MS = 45000; // No interaction for this long = inactive visitor.
	var ATC_QUIET_MS = 20000; // Quiet period right after an add to cart.
	var PRODUCT_DWELL_MS = 8000; // On product pages, wait for real interest first.
	var ATTR_COOKIE = 'wplh_attr';
	var ATTR_MINUTES = Math.max( 0, toInt( conv.attribution, 0 ) );
	var SUPPRESS_DISMISSALS = Math.max( 1, toInt( ( conv.suppress || {} ).dismissals, 2 ) );
	var SUPPRESS_REPEAT = Math.max( 2, toInt( ( conv.suppress || {} ).repeat, 3 ) );
	var GOAL = conv.goal || 'cart';

	// Controls a notification must never cover (add to cart, cart, checkout, payment).
	var CRITICAL =
		'.single_add_to_cart_button,form.cart .quantity,.add_to_cart_button,.checkout-button,.wc-proceed-to-checkout,#place_order,' +
		'.woocommerce-cart-form button,.woocommerce-cart-form .qty,.payment_methods,.wc_payment_methods,' +
		'.wc-block-components-checkout-place-order-button,.wc-block-cart__submit-container,.wc-block-components-quantity-selector,' +
		'.wc-block-checkout__payment-method,.wc-block-mini-cart__footer,.wp-block-woocommerce-product-button,' +
		'[class*="sticky-add-to-cart"],[class*="sticky_add_to_cart"],[class*="sticky-atc"]';

	var state = {
		priority: [],
		queue: [],
		streamLeft: Infinity,
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
		pageStart: now(),
		lastActivity: now(),
		maxScroll: 0,
		waitingActivity: false,
		finalShown: false,
		context: null,
		cart: null,
	};

	/* ---- Visitor session (anonymous, this browser only) ---- */

	function loadSession() {
		var s = local.get( SESSION_KEY );
		var t = now();
		// Keyed to a fixed schema version (not the cache version) so a mix of
		// freshly rendered and page-cached HTML can never reset the limits.
		if ( ! s || typeof s !== 'object' || s.v !== SESSION_SCHEMA || ! s.touch || t - s.touch > SESSION_IDLE_MS ) {
			s = { v: SESSION_SCHEMA, touch: t, shown: 0, last: 0, gap: 0, lastProduct: 0, seen: [], muted: false, seed: Math.floor( Math.random() * 1000 ), fresh: true };
		}
		s.seed = toInt( s.seed, 0 ) % 1000;
		s.gap = toInt( s.gap, 0 );
		s.shown = toInt( s.shown, 0 );
		s.last = toInt( s.last, 0 );
		s.seen = Array.isArray( s.seen ) ? s.seen : [];
		// Engagement signals.
		s.pv = toInt( s.pv, 0 );
		s.views = s.views && typeof s.views === 'object' ? s.views : {};
		s.cats = toInt( s.cats, 0 );
		s.clicks = toInt( s.clicks, 0 );
		s.ctas = toInt( s.ctas, 0 );
		s.dism = toInt( s.dism, 0 );
		s.atc = toInt( s.atc, 0 );
		s.quiet = toInt( s.quiet, 0 );
		s.pendingAtc = toInt( s.pendingAtc, 0 );
		s.flags = s.flags && typeof s.flags === 'object' ? s.flags : {};
		s.rel = Array.isArray( s.rel ) ? s.rel.filter( isValidItem ).slice( 0, 3 ) : [];
		return s;
	}

	function saveSession( s ) {
		s.touch = now();
		if ( s.seen.length > 100 ) {
			s.seen = s.seen.slice( -100 );
		}
		var keys = Object.keys( s.views );
		if ( keys.length > 30 ) {
			keys.slice( 0, keys.length - 30 ).forEach( function ( k ) {
				delete s.views[ k ];
			} );
		}
		delete s.fresh;
		local.set( SESSION_KEY, s );
	}

	/** Funnel stage of this visitor right now. */
	function stage( s ) {
		if ( PAGE === 'checkout' ) {
			return 'checkout';
		}
		if ( hasCart() ) {
			return 'cart';
		}
		if ( PAGE === 'product' || Object.keys( s.views ).length >= 2 ) {
			return 'explorer';
		}
		if ( s.pv >= 2 || s.clicks > 0 || state.maxScroll >= 50 || now() - state.pageStart >= 30000 ) {
			return 'engaged';
		}
		return 'new';
	}

	/* ---- Cart signals ---- */

	function cookie( name ) {
		var parts = ( '; ' + document.cookie ).split( '; ' + name + '=' );
		return parts.length > 1 ? decodeURIComponent( parts.pop().split( ';' ).shift() ) : '';
	}

	function hasCart() {
		return cookie( 'woocommerce_items_in_cart' ) === '1';
	}

	/* ---- Attribution (first-party, short-lived, anonymous) ---- */

	function randomHex( length ) {
		var out = '';
		var bytes = null;
		try {
			bytes = window.crypto.getRandomValues( new window.Uint8Array( length / 2 ) );
		} catch ( e ) {
			bytes = null;
		}
		for ( var i = 0; i < length / 2; i++ ) {
			var b = bytes ? bytes[ i ] : Math.floor( Math.random() * 256 );
			out += ( b < 16 ? '0' : '' ) + b.toString( 16 );
		}
		return out;
	}

	function readAttribution() {
		var m = /^([a-f0-9]{16})\.([ab-])\.([md])\.(\d{10})$/.exec( cookie( ATTR_COOKIE ) );
		if ( ! m || ! ATTR_MINUTES ) {
			return null;
		}
		var age = now() / 1000 - toInt( m[ 4 ], 0 );
		return age >= -300 && age <= ATTR_MINUTES * 60 ? { id: m[ 1 ] } : null;
	}

	/** Remember that this visitor interacted with a notification (clicked it). */
	function touchAttribution() {
		if ( ! ATTR_MINUTES || ! cfg.analytics ) {
			return;
		}
		var current = readAttribution();
		var value = ( current ? current.id : randomHex( 16 ) ) + '.' + variant + '.' + ( isMobile() ? 'm' : 'd' ) + '.' + Math.floor( now() / 1000 );
		document.cookie =
			ATTR_COOKIE + '=' + value + '; path=/; max-age=' + ATTR_MINUTES * 60 + '; SameSite=Lax' + ( window.location.protocol === 'https:' ? '; Secure' : '' );
	}

	/* ---- Timing ---- */

	/** Next gap: random within the configured bounds, so timing never feels mechanical. */
	function nextGap() {
		if ( ! freq.random || INTERVAL_MAX_MS <= INTERVAL_MS ) {
			return INTERVAL_MS;
		}
		return Math.round( INTERVAL_MS + Math.random() * ( INTERVAL_MAX_MS - INTERVAL_MS ) );
	}

	function waitFor( s ) {
		var due = s.last ? s.last + ( s.gap || INTERVAL_MS ) : 0;
		return Math.max( due, s.quiet ) - now();
	}

	function idle() {
		return now() - state.lastActivity >= IDLE_MS;
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

	function isMobile() {
		return !! ( window.matchMedia && window.matchMedia( MOBILE_QUERY ).matches );
	}

	function pageLimit( s ) {
		// Repeatedly viewing the same product: keep that page calm.
		if ( PAGE === 'product' && toInt( s.views[ PAGE_PRODUCT ], 0 ) >= SUPPRESS_REPEAT ) {
			return 1;
		}
		return MAX_PAGE;
	}

	function limitReached( s ) {
		if ( state.stopped || s.muted || PAGE === 'checkout' || ORDER_RECEIVED ) {
			return true;
		}
		if ( MAX_SESSION > 0 && s.shown >= MAX_SESSION ) {
			return true;
		}
		return state.shownOnPage >= pageLimit( s );
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

	/* ---- Analytics (anonymous, batched) ---- */

	var events = [];
	var funnelEvents = [];
	var flushTimer = null;

	function flush() {
		window.clearTimeout( flushTimer );
		if ( ( ! events.length && ! funnelEvents.length ) || ! cfg.events ) {
			return;
		}
		var payload = JSON.stringify( { events: events.splice( 0, 25 ), funnel: funnelEvents.splice( 0, 25 ) } );
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
		if ( events.length || funnelEvents.length ) {
			flush();
		}
	}

	function queueFlush( now_ ) {
		if ( now_ || events.length + funnelEvents.length >= 10 ) {
			flush();
			return;
		}
		window.clearTimeout( flushTimer );
		flushTimer = window.setTimeout( flush, 10000 );
	}

	function track( name, item ) {
		if ( ! cfg.analytics || item.preview ) {
			return;
		}
		events.push( { e: name, t: item.type === 'promotion_done' ? 'promotion' : item.type, p: toInt( item.product_id, 0 ) } );
		queueFlush( name === 'click' || name === 'cta' );
	}

	/** Anonymous funnel counter: event name, A/B variant letter, mobile/desktop. */
	function funnel( name, urgent ) {
		if ( ! cfg.analytics ) {
			return;
		}
		funnelEvents.push( { e: name, v: variant, d: isMobile() ? 'm' : 'd' } );
		queueFlush( urgent );
	}

	/** Count an event plus its attributed twin when the visitor recently interacted. */
	function funnelStep( name, urgent ) {
		funnel( name, urgent );
		if ( readAttribution() ) {
			funnel( name + '_attr', urgent );
		}
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'hidden' ) {
			flush();
		}
	} );
	window.addEventListener( 'pagehide', flush );

	/* ---- Engagement signals ---- */

	var scrollQueued = false;

	function onActivity() {
		state.lastActivity = now();
		if ( state.waitingActivity ) {
			state.waitingActivity = false;
			schedule( next, 2000 );
		}
	}

	function measureScroll() {
		scrollQueued = false;
		var doc = document.documentElement;
		var max = Math.max( 1, ( doc.scrollHeight || 0 ) - window.innerHeight );
		state.maxScroll = Math.max( state.maxScroll, Math.round( ( window.pageYOffset / max ) * 100 ) );
		if ( state.current ) {
			avoidCollision();
		}
	}

	window.addEventListener(
		'scroll',
		function () {
			onActivity();
			if ( ! scrollQueued ) {
				scrollQueued = true;
				( window.requestAnimationFrame || window.setTimeout )( measureScroll );
			}
		},
		{ passive: true }
	);
	[ 'pointerdown', 'keydown', 'touchstart', 'mousemove' ].forEach( function ( type ) {
		document.addEventListener( type, onActivity, { passive: true, capture: true } );
	} );

	function productInterest() {
		return state.maxScroll >= 25 || now() - state.pageStart >= PRODUCT_DWELL_MS;
	}

	/* ---- Add to cart detection (only ever the visitor's own action) ---- */

	function onAddToCart() {
		var s = loadSession();
		s.atc += 1;
		s.quiet = Math.max( s.quiet, now() + ATC_QUIET_MS );
		s.pendingAtc = 0;
		saveSession( s );
		funnelStep( 'atc', true );
		sess.set( 'wplh_cart', null );
		state.cart = null;
		if ( state.current && state.current.item.type === 'product_cta' ) {
			hide( 'atc' );
		}
		// Drop promotions of the product that was just added; re-plan for the cart stage.
		state.priority = state.priority.filter( function ( item ) {
			return item.type !== 'product_cta';
		} );
		if ( ! cfg.trackOnly ) {
			schedule( replan, ATC_QUIET_MS );
		}
	}

	function watchAddToCart() {
		if ( window.jQuery ) {
			try {
				window.jQuery( document.body ).on( 'added_to_cart', onAddToCart );
			} catch ( e ) {}
		}
		document.body.addEventListener( 'wc-blocks_added_to_cart', onAddToCart );
		// Classic (non-AJAX) single-product form: confirmed on the next page load.
		document.addEventListener(
			'submit',
			function ( event ) {
				var form = event.target;
				if ( form && form.matches && form.matches( 'form.cart' ) ) {
					var s = loadSession();
					s.pendingAtc = now();
					saveSession( s );
				}
			},
			true
		);
	}

	/* ---- Data ---- */

	function fetchActivity() {
		var params = 'ctx=' + encodeURIComponent( PAGE ) + '&pid=' + PAGE_PRODUCT + '&tid=' + toInt( ctx.c, 0 ) + '&seed=' + loadSession().seed;
		var cacheKey = 'wplh_q_' + cfg.version + '_' + params;
		var ttl = toInt( cfg.clientCache, 0 ) * 1000;
		var empty = { items: [], context: null };

		if ( ttl > 0 ) {
			var cached = sess.get( cacheKey );
			if ( cached && Array.isArray( cached.items ) && now() - toInt( cached.t, 0 ) < ttl ) {
				cfg.skew = toInt( cached.skew, 0 );
				return window.Promise.resolve( { items: cached.items, context: cached.context || null } );
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
				var context = data && data.context && typeof data.context === 'object' ? data.context : null;
				var generated = data && data.generated ? Date.parse( data.generated ) : NaN;
				cfg.skew = isNaN( generated ) ? 0 : generated - now();
				if ( ttl > 0 ) {
					sess.set( cacheKey, { t: now(), skew: cfg.skew, items: items, context: context } );
				}
				return { items: items, context: context };
			} )
			[ 'catch' ]( function () {
				return empty;
			} );
	}

	/**
	 * The visitor's own cart, read by their browser from the WooCommerce Store
	 * API. Only totals and product IDs are used; nothing is sent anywhere.
	 */
	function fetchCart() {
		var wanted = convTypes.cart || convTypes.freeShipping || convTypes.checkout || convTypes.recommend;
		if ( ! hasCart() || ! wanted || ! store.storeApi ) {
			return window.Promise.resolve( null );
		}
		var hash = cookie( 'woocommerce_cart_hash' );
		var cached = sess.get( 'wplh_cart' );
		if ( cached && cached.h === hash && now() - toInt( cached.t, 0 ) < 60000 && cached.c ) {
			return window.Promise.resolve( cached.c );
		}
		return window
			.fetch( store.storeApi, { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( data ) {
				var totals = data && data.totals ? data.totals : null;
				if ( ! totals ) {
					return null;
				}
				var freeRate = false;
				( Array.isArray( data.shipping_rates ) ? data.shipping_rates : [] ).forEach( function ( pkg ) {
					( Array.isArray( pkg.shipping_rates ) ? pkg.shipping_rates : [] ).forEach( function ( rate ) {
						if ( rate && rate.method_id === 'free_shipping' ) {
							freeRate = true;
						}
					} );
				} );
				var cart = {
					count: toInt( data.items_count, 0 ),
					ids: ( Array.isArray( data.items ) ? data.items : [] ).map( function ( line ) {
						return toInt( line.id, 0 );
					} ),
					totals: {
						items: toInt( totals.total_items, 0 ),
						itemsTax: toInt( totals.total_items_tax, 0 ),
						discount: toInt( totals.total_discount, 0 ),
						discountTax: toInt( totals.total_discount_tax, 0 ),
						currency: String( totals.currency_code || '' ),
						minor: toInt( totals.currency_minor_unit, 2 ),
						dec: String( totals.currency_decimal_separator || '.' ),
						thou: String( totals.currency_thousand_separator || '' ),
						prefix: String( totals.currency_prefix || '' ),
						suffix: String( totals.currency_suffix || '' ),
					},
					needsShipping: data.needs_shipping !== false,
					country: data.shipping_address && data.shipping_address.country ? String( data.shipping_address.country ) : '',
					freeRate: freeRate,
				};
				sess.set( 'wplh_cart', { h: hash, t: now(), c: cart } );
				return cart;
			} )
			[ 'catch' ]( function () {
				return null;
			} );
	}

	function money( minorAmount, t ) {
		var digits = Math.max( 0, Math.min( 4, t.minor ) );
		var parts = ( minorAmount / Math.pow( 10, digits ) ).toFixed( digits ).split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, t.thou );
		return t.prefix + parts.join( t.dec ) + t.suffix;
	}

	/** Render a client-side template; a line is used only when all its tokens have values. */
	function renderTemplate( type, values ) {
		var lines = ( conv.templates || {} )[ type ] || [];
		for ( var i = 0; i < lines.length; i++ ) {
			var ok = true;
			var text = String( lines[ i ] ).replace( /\{([a-z_]+)\}/g, function ( match, token ) {
				var value = values[ token ];
				if ( value === undefined || value === null || value === '' ) {
					ok = false;
					return match;
				}
				return String( value );
			} );
			if ( ok && text ) {
				return text;
			}
		}
		return '';
	}

	function clientItem( type, values, url, key ) {
		var message = renderTemplate( type, values );
		if ( ! message ) {
			return null;
		}
		return {
			id: 'c_' + type + '_' + key,
			type: type,
			message: message,
			label: ( conv.labels || {} )[ type ] || '',
			url: url || '',
			product: '',
			product_id: 0,
			synthetic: true,
			relevant: true,
		};
	}

	/** Cart-stage messages built from the visitor's genuine cart values. */
	function cartItems( cart ) {
		var out = [];
		if ( ! cart || ! cart.count ) {
			return out;
		}
		var t = cart.totals;
		var inclTax = !! store.inclTax;
		var items = t.items + ( inclTax ? t.itemsTax : 0 );
		var discount = t.discount + ( inclTax ? t.discountTax : 0 );
		var values = { cart_count: String( cart.count ), cart_total: money( items, t ) };
		var fs = store.freeShipping;

		// Free shipping: the store's real rule, same arithmetic as WooCommerce.
		var sameCurrency = ! store.currency || ! t.currency || store.currency === t.currency;
		var sameCountry = fs && ( ! cart.country || cart.country === fs.country );
		if ( convTypes.freeShipping && fs && fs.min > 0 && sameCurrency && sameCountry && cart.needsShipping ) {
			var threshold = Math.round( fs.min * Math.pow( 10, t.minor ) );
			var qualifying = items - ( fs.ignore_discounts ? 0 : discount );
			var remaining = threshold - qualifying;
			values.threshold = money( threshold, t );
			if ( remaining > 0 && ! cart.freeRate ) {
				values.amount_remaining = money( remaining, t );
				out.push( clientItem( 'promotion', values, store.shop, String( remaining ) ) );
			} else {
				var s = loadSession();
				if ( ! s.flags.fsDone ) {
					out.push( clientItem( 'promotion_done', values, GOAL === 'checkout' || GOAL === 'purchase' ? store.checkout : store.cart, 'done' ) );
				}
			}
		}
		if ( PAGE !== 'cart' ) {
			if ( convTypes.checkout && ( GOAL === 'checkout' || GOAL === 'purchase' ) ) {
				out.push( clientItem( 'checkout', values, store.checkout, cart.count + '_' + items ) );
			} else if ( convTypes.cart ) {
				out.push( clientItem( 'cart', values, store.cart, cart.count + '_' + items ) );
			}
		}
		return out.filter( Boolean );
	}

	/* ---- Conversion decision engine ---- */

	/**
	 * Decide what this visitor sees on this page, in order: context-relevant
	 * messages first (by stage and the store's primary goal), then the
	 * regular rotation.
	 */
	function plan( data, cart ) {
		var s = loadSession();
		var st = stage( s );
		var context = data.context || {};
		var inCart = cart ? cart.ids : [];
		var related = ( Array.isArray( context.related ) ? context.related : [] ).filter( isValidItem );
		var priority = [];
		var productCta = null;
		var recent = recentFingerprints();
		state.streamLeft = Infinity;
		state.context = context;

		if ( related.length ) {
			// Remember recommendations so later pages can still suggest them.
			s.rel = related.slice( 0, 3 );
			saveSession( s );
		} else if ( PAGE !== 'product' ) {
			related = s.rel;
		}
		related = related.filter( function ( item ) {
			return inCart.indexOf( toInt( item.product_id, 0 ) ) === -1 && toInt( item.product_id, 0 ) !== PAGE_PRODUCT;
		} );
		related.forEach( function ( item ) {
			item.relevant = true;
		} );

		if (
			PAGE === 'product' &&
			convTypes.productCta &&
			isValidItem( context.product ) &&
			inCart.indexOf( PAGE_PRODUCT ) === -1 &&
			toInt( s.views[ PAGE_PRODUCT ], 0 ) < SUPPRESS_REPEAT
		) {
			productCta = context.product;
			productCta.url = ''; // Already on this product: the button is the action.
			productCta.relevant = true;
			productCta.needsInterest = true;
		}

		var cartMsgs = st === 'cart' ? cartItems( cart ) : [];

		if ( PAGE === 'cart' ) {
			// Cart page: free-shipping progress only, never anything that distracts.
			state.priority = unseen(
				cartMsgs.filter( function ( item ) {
					return item.type === 'promotion' || item.type === 'promotion_done';
				} ),
				s,
				recent
			);
			state.queue = [];
			state.streamLeft = 0;
			return;
		}

		if ( st === 'cart' ) {
			priority = cartMsgs.concat( convTypes.recommend ? related.slice( 0, 2 ) : [] );
			if ( productCta ) {
				priority.splice( 1, 0, productCta );
			}
			state.streamLeft = 1; // Cart builders: keep the regular rotation in the background.
		} else if ( GOAL === 'views' ) {
			priority = ( convTypes.recommend ? related : [] ).concat( productCta ? [ productCta ] : [] );
		} else {
			priority = ( productCta ? [ productCta ] : [] ).concat( convTypes.recommend ? related : [] );
		}
		var relatedIds = related.map( function ( item ) {
			return toInt( item.product_id, 0 );
		} );
		state.priority = unseen( priority, s, recent );
		state.queue = unseen( data.items, s, recent ).filter( function ( item ) {
			var pid = toInt( item.product_id, 0 );
			if ( pid && ( pid === PAGE_PRODUCT || relatedIds.indexOf( pid ) !== -1 ) ) {
				item.relevant = true;
			}
			return inCart.indexOf( pid ) === -1 || ! pid;
		} );
		if ( st === 'new' && state.priority.length ) {
			// Lead with one store-level message, then the relevant ones.
			var lead = state.queue.shift();
			if ( lead ) {
				state.priority.unshift( lead );
			}
		}
	}

	function unseen( list, s, recent ) {
		return list.filter( function ( item ) {
			return isValidItem( item ) && s.seen.indexOf( item.id ) === -1 && ! recent[ item.id ];
		} );
	}

	function nextStreamItem( s ) {
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
		var pick = best !== -1 ? best : productOnly !== -1 ? productOnly : any;
		return pick === -1 ? null : state.queue.splice( pick, 1 )[ 0 ];
	}

	function nextItem( s ) {
		while ( state.priority.length ) {
			var item = state.priority.shift();
			if ( s.seen.indexOf( item.id ) === -1 ) {
				return item;
			}
		}
		if ( state.streamLeft <= 0 ) {
			return null;
		}
		var stream = nextStreamItem( s );
		if ( stream ) {
			state.streamLeft -= 1;
		}
		return stream;
	}

	function nudgeItem() {
		var item = clientItem( 'nudge', {}, store.shop, 'idle' );
		if ( item ) {
			item.quiet = true;
			item.relevant = false;
		}
		return item;
	}

	/* ---- Placement: never cover purchase controls ---- */

	function intersects( a, b, margin ) {
		return a.left < b.right + margin && a.right > b.left - margin && a.top < b.bottom + margin && a.bottom > b.top - margin;
	}

	function collides() {
		if ( ! state.root ) {
			return false;
		}
		var box = state.root.getBoundingClientRect();
		if ( ! box.height ) {
			return false;
		}
		var nodes = document.querySelectorAll( CRITICAL );
		for ( var i = 0; i < nodes.length && i < 60; i++ ) {
			if ( state.root.contains( nodes[ i ] ) ) {
				continue;
			}
			var r = nodes[ i ].getBoundingClientRect();
			if ( r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < window.innerHeight && intersects( box, r, 8 ) ) {
				return true;
			}
		}
		return false;
	}

	/** Flip to the opposite edge when a purchase control is underneath; give way if both collide. */
	function findPlacement() {
		var root = state.root;
		root.className = root.className.replace( ' wplh-flip', '' );
		if ( ! collides() ) {
			return true;
		}
		root.className += ' wplh-flip';
		if ( ! collides() ) {
			return true;
		}
		root.className = root.className.replace( ' wplh-flip', '' );
		return false;
	}

	function avoidCollision() {
		if ( state.current && collides() && ! findPlacement() ) {
			hide( 'collision' );
		}
	}

	function typing() {
		var active = document.activeElement;
		return !! ( active && /^(INPUT|TEXTAREA|SELECT)$/.test( active.tagName ) && active.type !== 'button' && active.type !== 'submit' );
	}

	/* ---- Display loop ---- */

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

	function ensureRoot() {
		if ( ! state.root ) {
			state.root = createRoot( display, cfg, {} );
			document.body.appendChild( state.root );
		}
		return state.root;
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

	function interacted( kind, item ) {
		var s = loadSession();
		if ( kind === 'cta' ) {
			s.ctas += 1;
		} else {
			s.clicks += 1;
		}
		// The visitor is acting on a suggestion: give them more room afterwards.
		s.quiet = Math.max( s.quiet, now() + 2 * INTERVAL_MS );
		saveSession( s );
		track( kind, item );
		funnel( kind, true );
		touchAttribution();
	}

	function chooseOptions() {
		var form = document.querySelector( 'form.cart' );
		if ( ! form ) {
			return;
		}
		form.scrollIntoView( { behavior: window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth', block: 'center' } );
		var field = form.querySelector( 'select, input:not([type="hidden"]), button' );
		if ( field && typeof field.focus === 'function' ) {
			field.focus( { preventScroll: true } );
		}
	}

	function bind( toast, item, cta ) {
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
				interacted( 'click', item );
			} );
		}
		var action = toast.querySelector( '.wplh-action' );
		if ( action && cta ) {
			action.addEventListener( 'click', function ( event ) {
				interacted( 'cta', item );
				if ( cta.key === 'add' ) {
					// Confirmed as an add to cart on the next page, once the cart cookie exists.
					var s = loadSession();
					s.pendingAtc = now();
					saveSession( s );
				}
				if ( cta.action === 'options' ) {
					event.preventDefault();
					hide( 'cta' );
					chooseOptions();
				} else if ( cta.action === 'close' ) {
					event.preventDefault();
					hide( 'cta' );
				}
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

	function requeue( item ) {
		state.priority.unshift( item );
	}

	function show( item ) {
		preloadImage( item ).then( function () {
			if ( document.visibilityState === 'hidden' ) {
				requeue( item );
				whenVisible( next );
				return;
			}
			var s = loadSession();
			if ( limitReached( s ) || state.current ) {
				return;
			}

			var root = ensureRoot();
			var cta = ctaFor( item, cfg, variant );
			if ( cta && cta.action === 'options' && ! document.querySelector( 'form.cart' ) ) {
				cta = null;
			}
			var toast = buildToast( item, display, cfg, { cta: cta } );
			bind( toast, item, cta );
			root.appendChild( toast );
			if ( ! findPlacement() ) {
				// A purchase control is underneath on both edges: wait for the visitor to scroll.
				root.removeChild( toast );
				requeue( item );
				schedule( next, 5000 );
				return;
			}
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
			if ( item.type === 'promotion_done' ) {
				s.flags.fsDone = 1;
			}
			saveSession( s );
			state.shownOnPage += 1;

			track( 'view', item );
			funnel( 'impression' );
			if ( item.relevant ) {
				funnel( 'relevant' );
			}
			if ( ! item.quiet ) {
				playSound();
			}
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
			funnel( 'dismiss' );
			var ds = loadSession();
			ds.dism += 1;
			// Repeated dismissals: the visitor has said enough — stay quiet for the session.
			if ( freq.dismiss === 'session' || ds.dism >= SUPPRESS_DISMISSALS ) {
				ds.muted = true;
				saveSession( ds );
				state.stopped = true;
				return;
			}
			saveSession( ds );
			if ( freq.dismiss === 'page' ) {
				state.stopped = true;
				return;
			}
		}
		if ( current.item.quiet ) {
			state.stopped = true; // The final low-intensity message: nothing follows it.
			return;
		}

		var session = loadSession();
		if ( ! limitReached( session ) ) {
			schedule( next, Math.max( GAP_MS, waitFor( session ) ) );
		}
	}

	function next() {
		var s = loadSession();
		if ( limitReached( s ) || state.current ) {
			return;
		}
		// Respect the minimum interval (and quiet periods) across pages and tabs.
		var wait = waitFor( s );
		if ( wait > 250 ) {
			schedule( next, wait );
			return;
		}
		if ( typing() ) {
			schedule( next, 4000 ); // Never interrupt someone filling in a field.
			return;
		}
		if ( idle() ) {
			// Inactive visitor: one final, low-intensity message, then stop.
			var nudge = convTypes.nudge && ! s.flags.nudged && ! state.finalShown && PAGE !== 'cart' ? nudgeItem() : null;
			if ( nudge && s.seen.indexOf( nudge.id ) === -1 ) {
				state.finalShown = true;
				s.flags.nudged = 1;
				saveSession( s );
				ensureCss().then( function () {
					show( nudge );
				} );
				return;
			}
			state.waitingActivity = true;
			return;
		}
		var item = nextItem( s );
		if ( ! item ) {
			return;
		}
		if ( item.needsInterest && ! productInterest() ) {
			requeue( item );
			schedule( next, 1500 );
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

	function replan() {
		state.cart = null;
		fetchCart().then( function ( cart ) {
			state.cart = cart;
			// The product just added is no longer promoted; recommendations remain.
			plan( { items: state.queue, context: { related: ( state.context || {} ).related || [] } }, cart );
			next();
		} );
	}

	function load() {
		window.Promise.all( [ fetchActivity(), fetchCart() ] ).then( function ( results ) {
			var s = loadSession();
			state.cart = results[ 1 ];
			plan( results[ 0 ], state.cart );
			if ( ( ! state.priority.length && ! state.queue.length ) || limitReached( s ) ) {
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
				var toast = buildToast( item, display, cfg, { preview: true, cta: ctaFor( item, cfg, variant ) } );
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

	/** Page-level funnel steps and engagement signals (also in tracking-only mode). */
	function observePage() {
		var s = loadSession();
		if ( s.fresh ) {
			funnel( 'session' );
		}
		s.pv += 1;
		if ( PAGE === 'product' && PAGE_PRODUCT ) {
			if ( ! s.views[ PAGE_PRODUCT ] ) {
				funnelStep( 'product_view' );
			}
			s.views[ PAGE_PRODUCT ] = toInt( s.views[ PAGE_PRODUCT ], 0 ) + 1;
		}
		if ( PAGE === 'category' || PAGE === 'tag' ) {
			s.cats += 1;
		}
		if ( PAGE === 'cart' && ! s.flags.cart ) {
			s.flags.cart = 1;
			funnelStep( 'cart' );
		}
		if ( PAGE === 'checkout' && ! ORDER_RECEIVED && ! s.flags.checkout ) {
			s.flags.checkout = 1;
			funnelStep( 'checkout' );
		}
		if ( ORDER_RECEIVED ) {
			s.flags = {}; // A new purchase journey starts after an order.
		}
		saveSession( s );
		// A classic add-to-cart form or our "Add to cart" button was used on the previous page.
		if ( s.pendingAtc && now() - s.pendingAtc < 120000 && hasCart() ) {
			onAddToCart();
		} else if ( s.pendingAtc && now() - s.pendingAtc >= 120000 ) {
			s.pendingAtc = 0;
			saveSession( s );
		}
		watchAddToCart();
	}

	function init() {
		if ( Array.isArray( cfg.preview ) ) {
			runPreview( cfg.preview );
			return;
		}
		observePage();
		if ( cfg.trackOnly || PAGE === 'checkout' || ORDER_RECEIVED ) {
			return; // Checkout stays quiet: nothing is ever shown there.
		}
		if ( display.mobile === 'hidden' && isMobile() ) {
			return;
		}
		if ( sound.url && ( sound.desktop || sound.mobile ) && ! audioUnlocked ) {
			[ 'pointerdown', 'keydown', 'touchstart' ].forEach( function ( type ) {
				document.addEventListener( type, unlockAudio, true );
			} );
		}
		var s = loadSession();
		if ( limitReached( s ) ) {
			return;
		}
		schedule( load, Math.max( FIRST_MS, waitFor( s ) ) );
	}

	init();
} )( window, document );
