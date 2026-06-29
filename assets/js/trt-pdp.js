/**
 * TRT State Gating — testosterone PDP only.
 * Reads allowed states from data-trt-allowed-states on #pdp-cfg.
 * Auto-populates via IP geolocation (ipapi.co), falls back to manual selection.
 * Disables the CTA until a valid state is confirmed.
 * @version 1.0.0
 */
( function () {
	'use strict';

	var cfg = document.getElementById( 'pdp-cfg' );
	if ( ! cfg ) return;

	var stateSelect = document.getElementById( 'trt-state-select' );
	if ( ! stateSelect ) return; // not the TRT PDP

	var allowedStates = JSON.parse( cfg.getAttribute( 'data-trt-allowed-states' ) || '[]' );
	var cta           = document.getElementById( 'pdp-cta' );
	var stateError    = document.getElementById( 'trt-state-error' );
	var stateStatus   = document.getElementById( 'trt-state-status' );

	// ── Gate helpers ──────────────────────────────────────────────────────────

	function setCtaEnabled( enabled ) {
		if ( ! cta ) return;
		cta.disabled = ! enabled;
		cta.classList.toggle( 'pdp-cfg__cta--disabled', ! enabled );
	}

	function validate() {
		var val = stateSelect.value;
		if ( ! val ) {
			setCtaEnabled( false );
			if ( stateError ) stateError.style.display = 'none';
			return;
		}
		var allowed = allowedStates.indexOf( val ) !== -1;
		setCtaEnabled( allowed );
		if ( stateError ) stateError.style.display = allowed ? 'none' : '';
	}

	// Disable CTA immediately — user must confirm state first.
	setCtaEnabled( false );
	stateSelect.addEventListener( 'change', validate );

	// ── IP geolocation (best-effort, no permission prompt) ────────────────────

	function setStatus( msg, cls ) {
		if ( ! stateStatus ) return;
		stateStatus.textContent = msg;
		stateStatus.className   = 'trt-state__status' + ( cls ? ' trt-state__status--' + cls : '' );
	}

	setStatus( 'Detecting your state…', 'loading' );

	var controller = window.AbortController ? new AbortController() : null;
	var timeoutId  = null;

	if ( controller ) {
		timeoutId = setTimeout( function () { controller.abort(); }, 5000 );
	}

	fetch( 'https://ipapi.co/json/', controller ? { signal: controller.signal } : {} )
		.then( function ( r ) {
			if ( timeoutId ) clearTimeout( timeoutId );
			return r.json();
		} )
		.then( function ( data ) {
			var code = typeof data.region_code === 'string' ? data.region_code.toUpperCase() : '';
			var opt  = code ? stateSelect.querySelector( 'option[value="' + code + '"]' ) : null;
			if ( opt ) {
				stateSelect.value = code;
				setStatus( 'Location detected — change if needed.', 'detected' );
				validate();
			} else {
				setStatus( '', '' );
			}
		} )
		.catch( function () {
			if ( timeoutId ) clearTimeout( timeoutId );
			setStatus( '', '' );
		} );

}() );
