document.addEventListener( 'DOMContentLoaded', function () {

	/* -----------------------------------------------------------------------
	   On-page debug logger — errors and warnings appear as red text below
	   the CTA so you don't need DevTools open to spot problems.
	----------------------------------------------------------------------- */
	function pdpLog( msg ) { console.log( '[Myogenix PDP] ' + msg ); }
	function pdpWarn( msg ) {
		console.warn( '[Myogenix PDP] ' + msg );
		pdpAppend( msg, 'warn' );
	}
	function pdpError( msg ) {
		console.error( '[Myogenix PDP] ' + msg );
		pdpAppend( msg, 'error' );
	}
	function pdpAppend( msg, level ) {
		var log = document.getElementById( 'pdp-debug-log' );
		if ( ! log ) return;
		var line = document.createElement( 'p' );
		line.className = 'pdp-debug-line pdp-debug-line--' + level;
		line.textContent = ( level === 'error' ? '\u26a0 ' : '\u2139 ' ) + msg;
		log.appendChild( line );
	}

	/* -----------------------------------------------------------------------
	   Accordion helper (shared by FAQ + Common Questions sections)
	----------------------------------------------------------------------- */
	function initAccordion( selector ) {
		var items = Array.prototype.slice.call( document.querySelectorAll( selector ) );
		items.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var isExpanded = this.getAttribute( 'aria-expanded' ) === 'true';
				var answer     = document.getElementById( this.getAttribute( 'aria-controls' ) );
				if ( ! answer ) return;

				items.forEach( function ( other ) {
					if ( other === btn ) return;
					other.setAttribute( 'aria-expanded', 'false' );
					var otherAns = document.getElementById( other.getAttribute( 'aria-controls' ) );
					if ( otherAns ) otherAns.classList.remove( 'is-open' );
				} );

				this.setAttribute( 'aria-expanded', String( ! isExpanded ) );
				answer.classList.toggle( 'is-open', ! isExpanded );
			} );
		} );
	}

	initAccordion( '.myogenix-pdp__faq-question' );
	initAccordion( '.myogenix-pdp__cq-question' );

	/* -----------------------------------------------------------------------
	   Product configurator
	----------------------------------------------------------------------- */
	var cfg = document.getElementById( 'pdp-cfg' );
	if ( ! cfg ) return;

	var doses        = JSON.parse( cfg.getAttribute( 'data-doses' )         || '[]' );
	var priceMatrix  = JSON.parse( cfg.getAttribute( 'data-price-matrix' )  || '{}' );
	var variationMap = JSON.parse( cfg.getAttribute( 'data-variation-map' ) || '{}' );

	/*
	 * WC attribute slug → term slug mapping (confirmed via WP-CLI 2026-04-21)
	 *   attribute_pa_dosage               → value from doses array (e.g. "10mg")
	 *   attribute_pa_wm-bottle            → "1-bottle" | "2-bottle" | "3-bottle"
	 *   attribute_pa_wm-subscription-plan → "1-month"  | "3-month"
	 */
	var BOTTLE_MAP = { 1: '1-bottle', 2: '2-bottle', 3: '3-bottle' };
	var PLAN_MAP   = { 1: '1-month',  2: '1-month',  3: '3-month'  };
	var RENEW_MAP  = { 1: 'monthly',  2: 'monthly',  3: 'every 3 months' };

	var state = {
		months: 1,
		dose:   doses[ 0 ] || ''
	};

	/* Look up the real WC price for the current dose + supply selection */
	function getPrice( dose, months ) {
		var bottle = BOTTLE_MAP[ months ] || '1-bottle';
		var dosePrices = priceMatrix[ dose ];
		if ( dosePrices && dosePrices[ bottle ] !== undefined ) {
			return dosePrices[ bottle ];
		}
		return 0;
	}

	function weeklyMg( dose ) {
		var mg = parseFloat( dose );
		return isNaN( mg ) ? '\u2014' : ( mg / 4 ).toFixed( 2 );
	}

	/* --- Supply button price labels (update whenever dose changes) --------- */
	function renderSupplyPrices() {
		Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
			var m     = parseInt( btn.getAttribute( 'data-months' ), 10 );
			var price = getPrice( state.dose, m );
			var el    = btn.querySelector( '.pdp-cfg__supply-price' );
			if ( el ) {
				el.textContent = price
					? '$' + price.toFixed( 2 ) + ( m === 3 ? '/3mo' : '/mo' )
					: '\u2014';
			}
		} );
	}

	/* --- Dose selector --------------------------------------------------- */
	function renderDose() {
		var wrap = document.getElementById( 'pdp-dose' );
		if ( ! wrap || ! doses.length ) return;

		var opts = doses.map( function ( d ) {
			return '<option value="' + d + '"' + ( d === state.dose ? ' selected' : '' ) + '>' + d + '</option>';
		} ).join( '' );

		var doseNote = state.months > 1
			? 'All ' + state.months + ' bottles ship at this dose. Adjust your dose at renewal.'
			: '';

		wrap.innerHTML =
			'<div class="pdp-cfg__dose-card">' +
				'<div class="pdp-cfg__dose-select-wrap">' +
					'<select class="pdp-cfg__dose-select" id="pdp-dose-select">' + opts + '</select>' +
					'<span class="pdp-cfg__dose-chevron">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
					'</span>' +
				'</div>' +
				'<p class="pdp-cfg__dose-detail">' +
					state.dose + '/month = <strong>' + weeklyMg( state.dose ) + ' mg/week</strong> &middot; 4 injections per month' +
				'</p>' +
				( doseNote ? '<p class="pdp-cfg__dose-note">' + doseNote + '</p>' : '' ) +
			'</div>';

		document.getElementById( 'pdp-dose-select' ).addEventListener( 'change', function () {
			state.dose = this.value;
			renderDose();
			renderSupplyPrices();
			renderSummary();
		} );
	}

	/* --- Order summary --------------------------------------------------- */
	function renderSummary() {
		var wrap = document.getElementById( 'pdp-summary' );
		if ( ! wrap ) return;

		var price     = getPrice( state.dose, state.months );
		var planLabel = state.months === 3 ? '3-month subscription' : 'Monthly subscription';
		var priceStr  = price ? '$' + price.toFixed( 2 ) : '\u2014';

		wrap.innerHTML =
			'<p class="pdp-cfg__summary-label">Order Summary</p>' +
			'<div class="pdp-cfg__summary-line"><span>' + state.months + '-month supply &middot; ' + state.dose + '</span><span>' + priceStr + '</span></div>' +
			'<div class="pdp-cfg__summary-line"><span>Plan</span><span>' + planLabel + '</span></div>' +
			'<div class="pdp-cfg__summary-total">' +
				'<span>Total today</span>' +
				'<span class="pdp-cfg__summary-total-price">' + priceStr + '</span>' +
			'</div>' +
			( price
				? '<div class="pdp-cfg__summary-note">Auto-renews ' + RENEW_MAP[ state.months ] + ' at <strong>' + priceStr + '</strong>. Cancel anytime before renewal.</div>'
				: '<div class="pdp-cfg__summary-note pdp-debug-line--warn">No price found for this combination. Check WP Admin &rarr; Products &rarr; Variations.</div>'
			);
	}

	function render() {
		renderDose();
		renderSupplyPrices();
		renderSummary();
	}

	/* --- Supply button bindings ------------------------------------------ */
	Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			state.months = parseInt( this.getAttribute( 'data-months' ), 10 );
			Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( b ) {
				b.classList.remove( 'pdp-cfg__supply--active' );
			} );
			this.classList.add( 'pdp-cfg__supply--active' );
			render();
		} );
	} );

	/* -----------------------------------------------------------------------
	   CTA — GET-based add-to-cart (no POST = no "Confirm Form Resubmission").
	   Looks up variation_id from WC's embedded JSON so WC Subscriptions can
	   resolve the correct variation reliably without attribute-only guessing.
	----------------------------------------------------------------------- */
	var ctaBtn = document.getElementById( 'pdp-cta' );
	if ( ctaBtn ) {
		ctaBtn.addEventListener( 'click', function () {
			var pid    = cfg.getAttribute( 'data-product-id' );
			var bottle = BOTTLE_MAP[ state.months ] || '1-bottle';
			var plan   = PLAN_MAP[ state.months ]   || '1-month';

			/* Clear previous error and debug lines */
			var prevErr = document.getElementById( 'pdp-cta-error' );
			if ( prevErr ) prevErr.remove();
			var log = document.getElementById( 'pdp-debug-log' );
			if ( log ) log.innerHTML = '';

			if ( ! pid ) {
				pdpError( 'Could not determine product ID. Please refresh and try again.' );
				return;
			}

			/*
			 * Resolve variation_id from our PHP-built variation map.
			 * WC's data-product_variations may be false (AJAX mode) so we
			 * embed our own map keyed by dose → bottle → plan.
			 */
			var variationId = 0;
			if (
				variationMap[ state.dose ] &&
				variationMap[ state.dose ][ bottle ] &&
				variationMap[ state.dose ][ bottle ][ plan ]
			) {
				variationId = variationMap[ state.dose ][ bottle ][ plan ];
			}

			if ( ! variationId ) {
				/* Fallback: try WC's embedded form JSON if our map missed it */
				var wcForm = document.querySelector( '.variations_form' );
				if ( wcForm ) {
					var raw = wcForm.getAttribute( 'data-product_variations' );
					if ( raw && raw !== 'false' ) {
						try {
							var variations = JSON.parse( raw );
							for ( var i = 0; i < variations.length; i++ ) {
								var v = variations[ i ];
								var a = v.attributes;
								var doseOk   = ! a[ 'attribute_pa_dosage' ]               || a[ 'attribute_pa_dosage' ]               === state.dose;
								var bottleOk = ! a[ 'attribute_pa_wm-bottle' ]            || a[ 'attribute_pa_wm-bottle' ]            === bottle;
								var planOk   = ! a[ 'attribute_pa_wm-subscription-plan' ] || a[ 'attribute_pa_wm-subscription-plan' ] === plan;
								if ( doseOk && bottleOk && planOk ) {
									variationId = v.variation_id;
									break;
								}
							}
						} catch ( e ) {
							pdpError( 'Could not parse WC variation data: ' + e.message );
						}
					}
				}
			}

			pdpLog( 'Adding to cart \u2192 dosage: ' + state.dose + ' | bottle: ' + bottle + ' | plan: ' + plan + ' | variation_id: ' + ( variationId || 'NOT FOUND' ) );

			if ( ! variationId ) {
				pdpError(
					'No matching variation found for: ' + state.dose + ', ' + bottle + ', ' + plan + '. ' +
					'Verify this combination exists and is published in WP Admin \u2192 Products \u2192 Variations.'
				);
				return;
			}

			var url = window.location.pathname + '?add-to-cart=' + pid + '&quantity=1';
			url += '&variation_id='                              + variationId;
			url += '&attribute_pa_dosage='                       + encodeURIComponent( state.dose );
			url += '&attribute_pa_wm-bottle='                    + encodeURIComponent( bottle );
			url += '&attribute_pa_wm-subscription-plan='         + encodeURIComponent( plan );

			window.location.href = url;
		} );
	}

	/* Log WC form attributes on load (DevTools only — not on-page) */
	var wcFormDbg = document.querySelector( '.variations_form' );
	if ( wcFormDbg ) {
		var dbgSelects = wcFormDbg.querySelectorAll( 'select[name^="attribute_"]' );
		if ( dbgSelects.length ) {
			pdpLog( 'WC variation form found. Attributes:' );
			Array.prototype.slice.call( dbgSelects ).forEach( function ( sel ) {
				var opts = Array.prototype.slice.call( sel.options ).map( function ( o ) { return o.value || '(any)'; } );
				pdpLog( '  ' + sel.name + ' \u2192 [' + opts.join( ', ' ) + ']' );
			} );
		}
	}

	/* Initial render */
	render();

} );
