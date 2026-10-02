/**
 * Browser tests for WP Live Hype (Playwright, Chromium).
 *
 *     BASE_URL=http://localhost:8899 npm run test:e2e
 *
 * Expects a store prepared by tests/setup-wordpress.sh (admin / admin,
 * fast timing). Set CHROMIUM_PATH to use a preinstalled Chromium.
 */
const { chromium } = require( 'playwright' );
const fs = require( 'fs' );

const BASE = ( process.env.BASE_URL || 'http://localhost:8899' ).replace( /\/$/, '' );
const results = [];
let failed = 0;

function check( ok, name, detail ) {
	results.push( ( ok ? '  ok   ' : '  FAIL ' ) + name + ( ! ok && detail ? ' — ' + detail : '' ) );
	if ( ! ok ) {
		failed++;
	}
}

async function toast( page, timeout ) {
	await page.waitForSelector( '.wplh-toast.wplh-in', { timeout: timeout || 40000 } );
	return page.$eval( '.wplh-toast.wplh-in', ( el ) => ( {
		cls: el.className,
		text: el.innerText.replace( /\n/g, ' | ' ),
		action: el.querySelector( '.wplh-action' ) ? el.querySelector( '.wplh-action' ).textContent : '',
	} ) );
}

( async () => {
	const browser = await chromium.launch( process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {} );
	const errors = [];
	const watch = ( page ) => page.on( 'pageerror', ( e ) => errors.push( e.message ) );

	// 1. Home page: real "Just bought!" notice leads (Hybrid default).
	let context = await browser.newContext( { viewport: { width: 1280, height: 800 } } );
	let page = await context.newPage();
	watch( page );
	await page.goto( BASE + '/' );
	let t = await toast( page );
	check( /JUST BOUGHT!/i.test( t.text ), 'home: real purchase notice leads', t.text );

	// 2. Product page → product call to action → add to cart (deliberate click).
	await page.goto( BASE + '/product/tb-500/' );
	await page.mouse.wheel( 0, 400 );
	let found = null;
	for ( let i = 0; i < 6 && ! found; i++ ) {
		t = await toast( page );
		if ( /product_cta/.test( t.cls ) ) {
			found = t;
		} else {
			await page.waitForSelector( '.wplh-toast.wplh-in', { state: 'detached', timeout: 20000 } ).catch( () => {} );
		}
	}
	check( !! found && /Add to cart/.test( found.action ), 'product page: call to action with "Add to cart"', found && found.text );
	const cookiesBefore = await context.cookies();
	check( ! cookiesBefore.some( ( c ) => c.name === 'woocommerce_items_in_cart' ), 'nothing added to the cart without a click' );
	if ( found ) {
		await Promise.all( [ page.waitForLoadState( 'load' ), page.click( '.wplh-toast.wplh-in .wplh-action' ) ] );
	}
	const cookies = await context.cookies();
	check( cookies.some( ( c ) => c.name === 'woocommerce_items_in_cart' ), 'click on "Add to cart" adds the product' );
	check( cookies.some( ( c ) => c.name === 'wplh_attr' && /^[a-f0-9]{16}\.[ab-]\.[md]\.\d{10}$/.test( c.value ) ), 'attribution cookie has the anonymous format' );

	// 3. Cart builder: genuine free-shipping progress ($299 − $85 = $214).
	await page.goto( BASE + '/shop/' );
	let promo = null;
	for ( let i = 0; i < 6 && ! promo; i++ ) {
		t = await toast( page, 45000 );
		if ( /type-promotion/.test( t.cls ) ) {
			promo = t;
		} else {
			await page.waitForSelector( '.wplh-toast.wplh-in', { state: 'detached', timeout: 20000 } ).catch( () => {} );
		}
	}
	check( !! promo && /214\.00/.test( promo.text ), 'free-shipping progress uses the real rule and cart', promo && promo.text );

	// 4. Checkout stays quiet but is counted (tracking-only).
	await page.goto( BASE + '/checkout/' );
	await page.waitForTimeout( 6000 );
	check( 0 === ( await page.$$( '.wplh-toast' ) ).length, 'checkout: nothing is shown' );
	check( await page.evaluate( () => !! ( window.wplhConfig && window.wplhConfig.trackOnly ) ), 'checkout: tracking-only mode' );
	await context.close();

	// 5. Mobile: touch-size button, never over the add-to-cart button.
	context = await browser.newContext( { viewport: { width: 390, height: 760 }, isMobile: true, hasTouch: true } );
	page = await context.newPage();
	watch( page );
	await page.goto( BASE + '/product/bpc-157/' );
	await page.evaluate( () => window.scrollTo( 0, 200 ) );
	t = await toast( page );
	const sizes = await page.evaluate( () => {
		const a = document.querySelector( '.wplh-toast.wplh-in .wplh-action' );
		const root = document.querySelector( '.wplh-root' ).getBoundingClientRect();
		const overlap = [ ...document.querySelectorAll( '.single_add_to_cart_button, .wp-block-woocommerce-product-button' ) ].some( ( n ) => {
			const r = n.getBoundingClientRect();
			return r.width && root.left < r.right && root.right > r.left && root.top < r.bottom && root.bottom > r.top;
		} );
		return { h: a ? a.getBoundingClientRect().height : 0, overlap };
	} );
	check( ! sizes.h || sizes.h >= 44, 'mobile: buttons are at least 44px tall', String( sizes.h ) );
	check( ! sizes.overlap, 'mobile: never covers add to cart' );
	await context.close();

	// 6. Right-to-left sites mirror the card.
	context = await browser.newContext();
	page = await context.newPage();
	watch( page );
	await page.addInitScript( () => document.addEventListener( 'DOMContentLoaded', () => document.documentElement.setAttribute( 'dir', 'rtl' ) ) );
	await page.goto( BASE + '/' );
	await toast( page );
	const rtl = await page.evaluate( () => {
		const root = document.querySelector( '.wplh-root' );
		const close = root.querySelector( '.wplh-close' ).getBoundingClientRect();
		const card = root.querySelector( '.wplh-toast' ).getBoundingClientRect();
		return { cls: root.className.includes( 'wplh-rtl' ), closeOnLeft: close.left - card.left < card.right - close.right };
	} );
	check( rtl.cls && rtl.closeOnLeft, 'RTL: layout mirrored' );
	await context.close();

	// 7. Two dismissals silence the session.
	context = await browser.newContext();
	page = await context.newPage();
	watch( page );
	await page.goto( BASE + '/' );
	await toast( page );
	await page.click( '.wplh-toast.wplh-in .wplh-close' );
	await toast( page );
	await page.click( '.wplh-toast.wplh-in .wplh-close' );
	await page.goto( BASE + '/shop/' );
	await page.waitForTimeout( 8000 );
	check( 0 === ( await page.$$( '.wplh-toast' ) ).length, 'two dismissals silence the session' );
	await context.close();

	// 8. Admin: every tab renders, no PHP notices, accessibility (axe).
	context = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
	page = await context.newPage();
	watch( page );
	await page.goto( BASE + '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForURL( '**/wp-admin/**', { timeout: 90000, waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );
	const axe = fs.readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );
	const tabs = [ 'dashboard', 'general', 'engine', 'conversion', 'abtest', 'weighting', 'country', 'notifications', 'display', 'sound', 'frequency', 'products', 'data', 'analytics', 'advanced', 'branding' ];
	const notices = [];
	const violations = [];
	for ( const tab of tabs ) {
		await page.goto( BASE + '/wp-admin/admin.php?page=wp-live-hype&tab=' + tab, { waitUntil: 'domcontentloaded', timeout: 90000 } );
		const text = await page.evaluate( () => document.body.innerText );
		if ( /(Warning|Notice|Deprecated|Fatal error):/.test( text ) ) {
			notices.push( tab );
		}
		await page.addScriptTag( { content: axe } );
		const found_ = await page.evaluate( async () =>
			( await window.axe.run( { include: [ '.wplh-wrap' ], exclude: [ '.select2-container' ] }, { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] } ) ).violations.map( ( v ) => v.id )
		);
		if ( found_.length ) {
			violations.push( tab + ':' + found_.join( ',' ) );
		}
	}
	check( ! notices.length, 'admin: no PHP notices on any tab', notices.join( ', ' ) );
	check( ! violations.length, 'admin: no accessibility violations (WooCommerce select boxes excluded)', violations.join( ' ' ) );
	await context.close();

	check( ! errors.filter( ( e ) => ! /domContentLoaded/.test( e ) ).length, 'no JavaScript errors', errors.join( ' | ' ) );
	await browser.close();

	console.log( 'WP Live Hype browser tests\n' + results.join( '\n' ) + '\n\n' + ( results.length - failed ) + ' passed, ' + failed + ' failed' );
	process.exit( failed ? 1 : 0 );
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
