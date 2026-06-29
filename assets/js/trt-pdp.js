/**
 * TRT State Gating — testosterone PDP only.
 * Custom dropdown replaces native <select> for consistent cross-device styling.
 * Auto-populates via IP geolocation (ipapi.co), falls back to manual selection.
 * Disables the CTA until a valid state is confirmed.
 * @version 1.1.0
 */
( function () {
	'use strict';

	var cfg = document.getElementById( 'pdp-cfg' );
	if ( ! cfg ) return;

	var picker = document.getElementById( 'trt-state-picker' );
	if ( ! picker ) return; // not the TRT PDP

	var trigger     = document.getElementById( 'trt-state-trigger' );
	var display     = document.getElementById( 'trt-state-display' );
	var optionsList = document.getElementById( 'trt-state-options' );
	var hiddenInput = document.getElementById( 'trt-state-value' );
	var options     = optionsList ? Array.from( optionsList.querySelectorAll( '.trt-state__option' ) ) : [];

	var allowedStates = JSON.parse( cfg.getAttribute( 'data-trt-allowed-states' ) || '[]' );
	var cta           = document.getElementById( 'pdp-cta' );
	var stateError    = document.getElementById( 'trt-state-error' );
	var stateStatus   = document.getElementById( 'trt-state-status' );

	var isOpen      = false;
	var highlighted = -1;
	var selectedVal = '';

	// ── Gate helpers ──────────────────────────────────────────────────────────

	function setCtaEnabled( enabled ) {
		if ( ! cta ) return;
		cta.disabled = ! enabled;
		cta.classList.toggle( 'pdp-cfg__cta--disabled', ! enabled );
	}

	function validate() {
		if ( ! selectedVal ) {
			setCtaEnabled( false );
			if ( stateError ) stateError.style.display = 'none';
			return;
		}
		var allowed = allowedStates.indexOf( selectedVal ) !== -1;
		setCtaEnabled( allowed );
		if ( stateError ) stateError.style.display = allowed ? 'none' : '';
	}

	setCtaEnabled( false ); // disabled until state confirmed

	// ── Custom dropdown ────────────────────────────────────────────────────────

	function openDropdown() {
		isOpen = true;
		trigger.setAttribute( 'aria-expanded', 'true' );
		trigger.classList.add( 'trt-state__trigger--open' );
		optionsList.classList.add( 'trt-state__options--open' );
		if ( highlighted >= 0 && options[ highlighted ] ) {
			options[ highlighted ].scrollIntoView( { block: 'nearest' } );
		}
	}

	function closeDropdown() {
		isOpen = false;
		trigger.setAttribute( 'aria-expanded', 'false' );
		trigger.classList.remove( 'trt-state__trigger--open' );
		optionsList.classList.remove( 'trt-state__options--open' );
		highlighted = -1;
		options.forEach( function ( o ) { o.classList.remove( 'trt-state__option--highlighted' ); } );
	}

	function setHighlight( idx ) {
		if ( highlighted >= 0 && options[ highlighted ] ) {
			options[ highlighted ].classList.remove( 'trt-state__option--highlighted' );
		}
		highlighted = idx;
		if ( idx >= 0 && options[ idx ] ) {
			options[ idx ].classList.add( 'trt-state__option--highlighted' );
			options[ idx ].scrollIntoView( { block: 'nearest' } );
		}
	}

	function selectOption( opt ) {
		var val  = opt.dataset.value;
		var text = opt.textContent.trim();
		selectedVal = val;
		if ( hiddenInput ) hiddenInput.value = val;
		if ( display ) {
			display.textContent = text;
			display.classList.remove( 'trt-state__trigger-text--placeholder' );
		}
		options.forEach( function ( o ) {
			var sel = o === opt;
			o.classList.toggle( 'trt-state__option--selected', sel );
			o.setAttribute( 'aria-selected', String( sel ) );
		} );
		closeDropdown();
		validate();
	}

	function autoSelect( code ) {
		for ( var i = 0; i < options.length; i++ ) {
			if ( options[ i ].dataset.value === code ) {
				highlighted = i;
				selectOption( options[ i ] );
				return;
			}
		}
	}

	trigger.addEventListener( 'click', function () {
		if ( isOpen ) { closeDropdown(); } else { openDropdown(); }
	} );

	trigger.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			if ( isOpen ) { closeDropdown(); } else { openDropdown(); }
		} else if ( e.key === 'Escape' ) {
			closeDropdown();
			trigger.focus();
		} else if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			if ( ! isOpen ) openDropdown();
			setHighlight( Math.min( highlighted + 1, options.length - 1 ) );
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			setHighlight( Math.max( highlighted - 1, 0 ) );
		} else if ( e.key === 'Tab' ) {
			closeDropdown();
		} else if ( e.key.length === 1 ) {
			// Type-ahead: find next option starting with the typed character
			var char  = e.key.toLowerCase();
			var start = ( highlighted + 1 ) % options.length;
			for ( var i = 0; i < options.length; i++ ) {
				var idx = ( start + i ) % options.length;
				if ( options[ idx ].textContent.trim().toLowerCase().charAt( 0 ) === char ) {
					if ( ! isOpen ) openDropdown();
					setHighlight( idx );
					break;
				}
			}
		}
	} );

	options.forEach( function ( opt, i ) {
		opt.addEventListener( 'mouseenter', function () { setHighlight( i ); } );
		opt.addEventListener( 'click', function () {
			selectOption( opt );
			trigger.focus();
		} );
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( isOpen && ! picker.contains( e.target ) ) {
			closeDropdown();
		}
	} );

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
			if ( code ) {
				autoSelect( code );
				if ( selectedVal ) {
					setStatus( 'Location detected — change if needed.', 'detected' );
				} else {
					setStatus( '', '' );
				}
			} else {
				setStatus( '', '' );
			}
		} )
		.catch( function () {
			if ( timeoutId ) clearTimeout( timeoutId );
			setStatus( '', '' );
		} );

}() );
