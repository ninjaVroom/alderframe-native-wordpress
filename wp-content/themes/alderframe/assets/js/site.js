/* Alderframe front-end behaviour. Progressive enhancement: every element is usable without this file. */
const reduce = matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
const $$ = ( s, r = document ) => [ ...r.querySelectorAll( s ) ];

/* ---------------------------------------------------------------- header: hide on scroll down, show on up */
const header = document.querySelector( '[data-af-header]' );
let lastY = scrollY;
addEventListener( 'scroll', () => {
	if ( ! header || document.documentElement.classList.contains( 'af-menu-open' ) ) return;
	const y = scrollY;
	header.classList.toggle( 'is-scrolled', y > 8 );
	header.classList.toggle( 'is-hidden', y > 400 && y > lastY && ! header.contains( document.activeElement ) );
	lastY = y;
}, { passive: true } );

/* ---------------------------------------------------------------- mobile menu */
const toggle = document.querySelector( '[data-af-menu]' );
const nav = document.getElementById( 'af-nav' );
const setMenu = ( open ) => {
	toggle.setAttribute( 'aria-expanded', String( open ) );
	toggle.querySelector( '.af-menu-toggle__label' ).textContent = open ? 'Close' : 'Menu';
	nav.classList.toggle( 'is-open', open );
	document.documentElement.classList.toggle( 'af-menu-open', open );
	if ( open ) nav.querySelector( 'a' )?.focus();
};
toggle?.addEventListener( 'click', () => setMenu( toggle.getAttribute( 'aria-expanded' ) !== 'true' ) );
addEventListener( 'keydown', ( e ) => {
	if ( e.key === 'Escape' && toggle?.getAttribute( 'aria-expanded' ) === 'true' ) {
		setMenu( false );
		toggle.focus();
	}
} );
matchMedia( '(min-width: 1041px)' ).addEventListener( 'change', ( e ) => e.matches && toggle && setMenu( false ) );

/* ---------------------------------------------------------------- focus error summary / confirmation */
const focusTarget = document.querySelector( '[data-af-focus]' );
if ( focusTarget ) {
	const go = () => setTimeout( () => focusTarget.focus(), 60 );
	document.readyState === 'complete' ? go() : addEventListener( 'load', go );
}

/* ---------------------------------------------------------------- signature: scroll-scrubbed Blender sequence */
const model = document.querySelector( '[data-af-model-section]' );
const stage = model?.querySelector( '[data-af-seq]' );
if ( model && stage && ! reduce && matchMedia( '(min-width: 960px)' ).matches ) {
	const canvas = stage.querySelector( 'canvas' );
	const ctx = canvas.getContext( '2d' );
	const total = Number( stage.dataset.frames );
	const base = stage.dataset.afSeq;
	const frames = new Array( total );
	const steps = $$( '.af-model__step', model );
	const sticky = model.querySelector( '.af-model__sticky' );
	const bar = document.createElement( 'div' );
	bar.className = 'af-model__progress';
	bar.innerHTML = '<span></span>';
	sticky.append( bar );
	let current = -1;
	let loaded = false;

	const draw = ( i ) => {
		// Nearest loaded frame, so scrubbing never shows a blank canvas.
		let k = i;
		for ( let d = 0; d < total && ! frames[ k ]?.complete; d++ ) k = ( i + ( d % 2 ? d : -d ) + total ) % total;
		const img = frames[ k ];
		if ( ! img?.complete || ! img.naturalWidth ) return;
		const w = canvas.clientWidth * devicePixelRatio;
		const h = canvas.clientHeight * devicePixelRatio;
		if ( canvas.width !== w || canvas.height !== h ) Object.assign( canvas, { width: w, height: h } );
		const s = Math.max( w / img.naturalWidth, h / img.naturalHeight );
		ctx.drawImage( img, ( w - img.naturalWidth * s ) / 2, ( h - img.naturalHeight * s ) / 2, img.naturalWidth * s, img.naturalHeight * s );
		model.classList.add( 'is-live' );
	};

	const load = () => {
		if ( loaded ) return;
		loaded = true;
		// Keyframes first (every 8th), then fill: the scrub is usable almost immediately.
		const order = [ ...Array( total ).keys() ].sort( ( a, b ) => ( a % 8 ) - ( b % 8 ) || a - b );
		order.forEach( ( i ) => {
			const img = new Image();
			img.decoding = 'async';
			img.src = `${ base }f_${ String( i + 1 ).padStart( 3, '0' ) }.webp`;
			img.onload = () => i === current && draw( i );
			frames[ i ] = img;
		} );
	};

	const update = () => {
		const r = model.getBoundingClientRect();
		const p = Math.min( 1, Math.max( 0, -r.top / ( r.height - innerHeight ) ) );
		const i = Math.min( total - 1, Math.round( p * ( total - 1 ) ) );
		bar.style.setProperty( '--p', p.toFixed( 4 ) );
		const active = Math.min( steps.length - 1, Math.floor( p * steps.length * 0.999 ) );
		steps.forEach( ( s, n ) => s.classList.toggle( 'is-active', n === active ) );
		if ( i !== current ) {
			current = i;
			draw( i );
		}
	};

	new IntersectionObserver( ( [ en ] ) => en.isIntersecting && load(), { rootMargin: '150% 0px' } ).observe( model );
	let ticking = false;
	addEventListener( 'scroll', () => {
		if ( ticking ) return;
		ticking = true;
		requestAnimationFrame( () => {
			ticking = false;
			update();
		} );
	}, { passive: true } );
	addEventListener( 'resize', () => draw( current ) );
	update();
}

/* ---------------------------------------------------------------- consent preferences (Consent Mode v2 defaults: denied) */
const consentKey = 'af-consent-v1';
const readConsent = () => {
	try {
		return JSON.parse( localStorage.getItem( consentKey ) ) || { analytics: false };
	} catch {
		return { analytics: false };
	}
};
$$( '[data-af-consent]' ).forEach( ( btn ) => btn.addEventListener( 'click', () => {
	let dlg = document.querySelector( '.af-consent' );
	if ( ! dlg ) {
		dlg = document.createElement( 'dialog' );
		dlg.className = 'af-consent';
		dlg.setAttribute( 'aria-labelledby', 'af-consent-title' );
		dlg.innerHTML = `<form method="dialog">
			<h2 id="af-consent-title">Consent preferences</h2>
			<label><input type="checkbox" checked disabled> <span>Strictly necessary: session and form security. Always active.</span></label>
			<label><input type="checkbox" name="analytics"> <span>Analytics (GA4): anonymous visit statistics, off until you allow it.</span></label>
			<p>Marketing cookies are not used on Alderframe.</p>
			<div class="af-consent__actions"><button class="af-btn af-btn--moss" value="save">Save preferences</button><button class="af-btn af-btn--light" value="cancel">Cancel</button></div>
		</form>`;
		document.body.append( dlg );
		dlg.addEventListener( 'close', () => {
			if ( dlg.returnValue !== 'save' ) return;
			const analytics = dlg.querySelector( '[name=analytics]' ).checked;
			try {
				localStorage.setItem( consentKey, JSON.stringify( { analytics } ) );
			} catch {}
			window.gtag?.( 'consent', 'update', { analytics_storage: analytics ? 'granted' : 'denied' } );
		} );
	}
	dlg.querySelector( '[name=analytics]' ).checked = readConsent().analytics;
	dlg.showModal();
} ) );
