/**
 * Sexual health PDP configurator.
 * Handles 1D (dosage only) and 2D (dosage × tablets) variable products.
 * Reads config from data-* attributes on #pdp-cfg.
 * @version 1.0.4
 */
( function () {
	'use strict';

	var cfg = document.getElementById( 'pdp-cfg' );
	if ( ! cfg ) return;

	var matrixRaw = cfg.getAttribute( 'data-variation-matrix' );
	if ( ! matrixRaw ) return;

	var matrix          = JSON.parse( matrixRaw );
	var primaryAttr     = cfg.getAttribute( 'data-primary-attr' )    || '';
	var secondaryAttr   = cfg.getAttribute( 'data-secondary-attr' )  || '';
	var fixedAttrs      = JSON.parse( cfg.getAttribute( 'data-fixed-attrs' )      || '{}' );
	var primaryLabels   = JSON.parse( cfg.getAttribute( 'data-primary-labels' )   || '{}' );
	var secondaryLabels = JSON.parse( cfg.getAttribute( 'data-secondary-labels' ) || '{}' );
	var productId       = cfg.getAttribute( 'data-product-id' ) || '';
	var monthlyBilling  = cfg.getAttribute( 'data-monthly-billing' ) === '1';

	var primaryKeys   = Object.keys( matrix );
	var hasSecondary  = !! secondaryAttr;
	var secondaryKeys = [];

	// Fixed tablet count baked into fixedAttrs (e.g. Tadalafil: always 90 tablets).
	// Used to show a per-tablet subline on 1D (dosage-only) products.
	var fixedTabletKey = Object.keys( fixedAttrs ).filter( function ( k ) {
		return /tablets/i.test( k );
	} )[ 0 ];
	var fixedTabletCount = fixedTabletKey ? extractNum( fixedAttrs[ fixedTabletKey ] ) : 0;

	if ( hasSecondary && primaryKeys.length ) {
		secondaryKeys = Object.keys( matrix[ primaryKeys[0] ] );
	}

	if ( ! primaryKeys.length ) return;

	var state = {
		primary:   primaryKeys[0],
		secondary: secondaryKeys.length ? secondaryKeys[0] : null,
	};

	// ── Helpers ───────────────────────────────────────────────────────────────

	function fmt( price ) {
		var s = price.toFixed( 2 );
		return '$' + ( s.slice( -3 ) === '.00' ? s.slice( 0, -3 ) : s );
	}

	function extractNum( slug ) {
		var m = String( slug ).match( /^(\d+)/ );
		return m ? parseInt( m[1], 10 ) : 0;
	}

	function getEntry() {
		if ( hasSecondary && state.secondary ) {
			var row = matrix[ state.primary ];
			return row ? row[ state.secondary ] : null;
		}
		return matrix[ state.primary ] || null;
	}

	// ── Render ────────────────────────────────────────────────────────────────

	function renderButtons() {
		document.querySelectorAll( '.sh-pdp__primary-btn' ).forEach( function ( btn ) {
			btn.classList.toggle( 'pdp-cfg__supply--active', btn.dataset.primary === state.primary );
		} );
		document.querySelectorAll( '.sh-pdp__secondary-btn' ).forEach( function ( btn ) {
			btn.classList.toggle( 'pdp-cfg__supply--active', btn.dataset.secondary === state.secondary );
		} );
	}

	function renderSummary() {
		var el = document.getElementById( 'sh-summary' );
		if ( ! el ) return;
		var entry = getEntry();
		if ( ! entry ) { el.innerHTML = ''; return; }

		// 1D multi-month plan (e.g. TRT 3-month): show per-month as the headline price.
		// Gated on data-monthly-billing so only the subscription-plan product (TRT) hits
		// this path — other 1D products (e.g. Tadalafil, keyed by dosage) must not.
		if ( ! hasSecondary && monthlyBilling ) {
			var months = extractNum( state.primary );
			if ( months > 1 ) {
				var perMonth = entry.price / months;
				var monthRows = '';
				for ( var m = 1; m <= months; m++ ) {
					monthRows +=
						'<div class="pdp-cfg__summary-line">' +
							'<span>Month ' + m + '</span>' +
							'<span>' + fmt( perMonth ) + '</span>' +
						'</div>';
				}
				el.innerHTML =
					'<span class="pdp-cfg__summary-label">Checkout Details</span>' +
					'<div class="pdp-cfg__summary-month-price">' + fmt( perMonth ) + '<span class="pdp-cfg__summary-month-unit">/month</span></div>' +
					monthRows +
					'<div class="pdp-cfg__summary-total">' +
						'<span>Total billed today</span>' +
						'<strong class="pdp-cfg__summary-total-price">' + fmt( entry.price ) + '</strong>' +
					'</div>' +
					'<p class="pdp-cfg__summary-charged-note">Full amount charged at once — not split into monthly payments.</p>';
				return;
			}
		}

		var primaryLabel   = primaryLabels[ state.primary ]   || state.primary;
		var secondaryLabel = state.secondary ? ( secondaryLabels[ state.secondary ] || state.secondary ) : '';
		var label = primaryLabel + ( secondaryLabel ? ' &middot; ' + secondaryLabel : '' );

		var subLine = '';
		if ( hasSecondary && state.secondary ) {
			var selTablets = extractNum( state.secondary );
			if ( selTablets > 0 ) {
				var perTablet = ( entry.price / selTablets ).toFixed( 2 );
				subLine = '$' + perTablet + '/tablet';

				// Savings vs scaling up the shortest supply to the same number of tablets
				var baseKey = secondaryKeys[0];
				if ( baseKey !== state.secondary && matrix[ state.primary ] && matrix[ state.primary ][ baseKey ] ) {
					var baseEntry    = matrix[ state.primary ][ baseKey ];
					var baseTablets  = extractNum( baseKey );
					if ( baseTablets > 0 ) {
						var savings = Math.round( ( baseEntry.price / baseTablets ) * selTablets - entry.price );
						if ( savings > 0 ) {
							subLine += ' &nbsp;&middot;&nbsp; <strong class="pdp-cfg__summary-savings">Save $' + savings + '</strong>';
						}
					}
				}
			}
		} else if ( ! hasSecondary && fixedTabletCount > 0 ) {
			// e.g. Tadalafil: dosage-only product with a fixed tablet fill — show per-tablet price.
			subLine = fmt( entry.price / fixedTabletCount ) + '/tablet';
		}

		el.innerHTML =
			'<span class="pdp-cfg__summary-label">' + label + '</span>' +
			'<div class="pdp-cfg__summary-total">' +
				( subLine ? '<span class="pdp-cfg__summary-sub">' + subLine + '</span>' : '' ) +
				'<strong class="pdp-cfg__summary-total-price">' + fmt( entry.price ) + '</strong>' +
			'</div>';
	}

	function render() {
		renderButtons();
		renderSummary();
	}

	// ── Events ────────────────────────────────────────────────────────────────

	document.querySelectorAll( '.sh-pdp__primary-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			state.primary = btn.dataset.primary;
			render();
		} );
	} );

	document.querySelectorAll( '.sh-pdp__secondary-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			state.secondary = btn.dataset.secondary;
			render();
		} );
	} );

	var cta = document.getElementById( 'pdp-cta' );
	if ( cta ) {
		cta.addEventListener( 'click', function () {
			var entry = getEntry();
			if ( ! entry || ! entry.id ) return;

			var params = 'add-to-cart=' + encodeURIComponent( productId ) +
			             '&variation_id=' + encodeURIComponent( String( entry.id ) ) +
			             '&' + encodeURIComponent( primaryAttr ) + '=' + encodeURIComponent( state.primary );

			if ( hasSecondary && state.secondary ) {
				params += '&' + encodeURIComponent( secondaryAttr ) + '=' + encodeURIComponent( state.secondary );
			}

			Object.keys( fixedAttrs ).forEach( function ( k ) {
				params += '&' + encodeURIComponent( k ) + '=' + encodeURIComponent( fixedAttrs[ k ] );
			} );

			window.location.href = '/?' + params;
		} );
	}

	// ── Back-button cache reset ───────────────────────────────────────────────

	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted ) {
			state.primary   = primaryKeys[0];
			state.secondary = secondaryKeys.length ? secondaryKeys[0] : null;
			render();
		}
	} );

	render();

	// FAQ accordion
	( function () {
		var btns = Array.prototype.slice.call( document.querySelectorAll( '.myo-faq__btn' ) );
		btns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var isExpanded = this.getAttribute( 'aria-expanded' ) === 'true';
				var panel      = document.getElementById( this.getAttribute( 'aria-controls' ) );
				if ( ! panel ) return;
				btns.forEach( function ( other ) {
					if ( other === btn ) return;
					other.setAttribute( 'aria-expanded', 'false' );
					var op = document.getElementById( other.getAttribute( 'aria-controls' ) );
					if ( op ) op.classList.remove( 'is-open' );
				} );
				this.setAttribute( 'aria-expanded', String( ! isExpanded ) );
				panel.classList.toggle( 'is-open', ! isExpanded );
			} );
		} );
	}() );

}() );
