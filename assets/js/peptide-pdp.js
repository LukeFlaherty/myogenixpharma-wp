/**
 * Peptide PDP configurator — supply-quantity selector.
 * Single-attribute peptide products (no dose escalation).
 * Reads config from data-* attributes on #pdp-cfg.
 * @version 1.2.0
 */
( function () {
	'use strict';

	var cfg = document.getElementById( 'pdp-cfg' );
	if ( ! cfg ) return;

	// Only activates on peptide PDPs (identified by data-supply-map).
	var supplyMapRaw = cfg.getAttribute( 'data-supply-map' );
	if ( ! supplyMapRaw ) return;

	var supplyMap  = JSON.parse( supplyMapRaw );
	var supplyAttr = cfg.getAttribute( 'data-supply-attr' ) || '';
	var productId  = cfg.getAttribute( 'data-product-id' )  || '';
	var supplyKeys = Object.keys( supplyMap );

	if ( ! supplyKeys.length ) return;

	var state = { supply: supplyKeys[ 0 ] };

	// ── Helpers ───────────────────────────────────────────────────────────────

	function fmt( price ) {
		var s = price.toFixed( 2 );
		return '$' + ( s.slice( -3 ) === '.00' ? s.slice( 0, -3 ) : s );
	}

	// ── Render ────────────────────────────────────────────────────────────────

	function renderButtons() {
		document.querySelectorAll( '.pdp-cfg__supply[data-supply]' ).forEach( function ( btn ) {
			btn.classList.toggle( 'pdp-cfg__supply--active', btn.getAttribute( 'data-supply' ) === state.supply );
		} );
	}

	function renderSummary() {
		var el = document.getElementById( 'peptide-summary' );
		if ( ! el ) return;
		var entry = supplyMap[ state.supply ];
		if ( ! entry ) { el.innerHTML = ''; return; }

		var qty      = entry.qty || 1;
		var unitNoun = state.supply.indexOf( 'vial' ) !== -1 ? 'vial' : 'bottle';
		var perUnit  = '$' + Math.round( entry.price / qty ) + '/' + unitNoun;

		el.innerHTML =
			'<span class="pdp-cfg__summary-label">' + entry.label + '</span>' +
			'<div class="pdp-cfg__summary-total">' +
				'<span>' + ( qty > 1 ? perUnit : 'One-time charge' ) + '</span>' +
				'<strong class="pdp-cfg__summary-total-price">' + fmt( entry.price ) + '</strong>' +
			'</div>';
	}

	function render() {
		renderButtons();
		renderSummary();
	}

	// ── Events ────────────────────────────────────────────────────────────────

	document.querySelectorAll( '.pdp-cfg__supply[data-supply]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			state.supply = btn.getAttribute( 'data-supply' );
			render();
		} );
	} );

	var cta = document.getElementById( 'pdp-cta' );
	if ( cta ) {
		cta.addEventListener( 'click', function () {
			var entry = supplyMap[ state.supply ];
			if ( ! entry || ! entry.id ) return;

			var url = '/?add-to-cart=' + encodeURIComponent( productId ) +
			          '&variation_id='  + encodeURIComponent( String( entry.id ) ) +
			          '&'               + encodeURIComponent( supplyAttr ) +
			          '='               + encodeURIComponent( state.supply );

			window.location.href = url;
		} );
	}

	// ── Back-button cache reset ───────────────────────────────────────────────

	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted ) {
			state.supply = supplyKeys[ 0 ];
			render();
		}
	} );

	render();

}() );
